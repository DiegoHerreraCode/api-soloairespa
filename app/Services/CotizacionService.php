<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Cotizacion;
use App\Models\CotizacionInventario;
use App\Models\CotizacionServicioTaller;
use App\Models\Orden;
use App\Models\Repuesto;
use App\Models\Inventario;
use App\Models\Reparacion;
use App\Models\ServicioTaller;
use App\Models\Secuencia;
use App\Enums\CotizacionTipo;
use App\Enums\CotizacionEstado;
use App\Enums\OrdenEstadoOperativo;
use Illuminate\Support\Facades\DB;
use App\Services\PDFService;
use App\Services\InventarioService;

/**
 * Service CotizacionService
 * 
 * Gestiona el ciclo de vida completo de las cotizaciones:
 * - Creación de cotizaciones para Venta, Recambio y Reparación (con nombres de repuestos únicos vía timestamp).
 * - Generación atómica del número correlativo COT-XXXXX usando la tabla secuencias.
 * - Creación de cotizaciones Hijas/Extra a partir de modificaciones en reparaciones activas.
 * - Control de bloqueo de edición en reparaciones mientras el cliente contesta.
 * - Flujo de Aceptación: conversión a orden de trabajo o aplicación de cambios sobre reparaciones existentes.
 * - Flujos de Rechazo y Anulación.
 */
class CotizacionService
{
    /**
     * Retorna todas las cotizaciones con sus relaciones principales.
     * Consulta SQL Raw:
     * SELECT * FROM cotizaciones;
     */
    public static function getAll()
    {
        return Cotizacion::with(['cliente', 'tipoOrden', 'admin', 'detallesInventario', 'detallesServiciosTaller'])->get();
    }

    /**
     * Obtiene una cotización por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM cotizaciones WHERE id_cotizacion = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Cotizacion::with([
            'cliente',
            'tipoOrden',
            'admin',
            'orden',
            'detallesInventario.inventarioInsumoSaliente',
            'detallesInventario.inventarioRepuestoSaliente',
            'detallesInventario.inventarioRepuestoEntrante',
            'detallesServiciosTaller.servicioTaller'
        ])->find($id);
    }

    /**
     * Genera un número de cotización correlativo único y seguro frente a concurrencia (ej: COT-00001).
     * Consulta SQL Raw:
     * SELECT * FROM secuencias WHERE modelo = 'CotizacionNumero' FOR UPDATE;
     * UPDATE secuencias SET ultimo_id = ultimo_id + 1 WHERE modelo = 'CotizacionNumero';
     */
    /**
     * Genera un número de cotización correlativo único basado en la fila de 'App\Models\Cotizacion'
     * en la tabla secuencias (ej: si el ultimo_id actual es 4, la siguiente cotización será COT-00005).
     *
     * Consulta SQL Raw equivalente:
     * SELECT ultimo_id FROM secuencias WHERE modelo = 'App\Models\Cotizacion';
     */
    public static function previsualizarSiguienteNumeroCotizacion(): string
    {
        $ultimoId = Secuencia::where('modelo', 'App\Models\Cotizacion')->value('ultimo_id') ?? 0;
        return 'COT-' . str_pad((int) $ultimoId + 1, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Registra una cotización en el sistema.
     * 
     * Si es de tipo Reparación, formatea cada nombre de repuesto ingresado por el usuario
     * agregándole un timestamp para garantizar su unicidad: ej: Compresor_20261006_080215
     * 
     * Consulta SQL Raw:
     * INSERT INTO cotizaciones (...) VALUES (...);
     * INSERT INTO cotizaciones_inventario (...) VALUES (...);
     * INSERT INTO cotizaciones_servicios_taller (...) VALUES (...);
     */
    public static function create(array $data)
    {
        DB::beginTransaction();

        $detallesInventario = $data['detalles_inventario'] ?? [];
        $detallesServicios = $data['detalles_servicios'] ?? [];
        unset($data['detalles_inventario'], $data['detalles_servicios']);

        // 1. num_cotizacion se genera automáticamente sincronizado
        // con la fila 'App\Models\Cotizacion' de la tabla secuencias si no viene definido.

        // 2. Establecer estados y fechas por defecto
        $data['tipo'] = $data['tipo'] ?? CotizacionTipo::INICIAL->value;
        $data['estado'] = $data['estado'] ?? CotizacionEstado::PENDIENTE->value;
        $data['fecha_creacion'] = $data['fecha_creacion'] ?? now();
        $data['fecha_vencimiento'] = $data['fecha_vencimiento'] ?? now()->addDays(15);
        // num_cotizacion se completa en Cotizacion::booted

        // 3. Formatear service_tags de repuestos a reparar si es Reparación:
        // - Si es cotización INICIAL: genera nombres únicos agregando timestamp.
        // - Si es cotización EXTRA (o el repuesto ya tiene su service_tag definitivo): conserva el tag exacto.
        $mapaNombres = []; // Mapa [nombre_original => nombre_con_timestamp]
        if (!empty($data['services_tags_repuestos_a_reparar'])) {
            $esTipoExtra = ($data['tipo'] ?? null) === CotizacionTipo::EXTRA->value || ($data['tipo'] ?? null) === CotizacionTipo::EXTRA;
            $tagsOriginales = $data['services_tags_repuestos_a_reparar'];

            if ($esTipoExtra) {
                // Conservar service_tag exacto del repuesto existente
                $data['services_tags_repuestos_a_reparar'] = array_values($tagsOriginales);
                $data['num_repuestos_a_reparar'] = count($tagsOriginales);
                foreach ($tagsOriginales as $tag) {
                    $mapaNombres[$tag] = $tag;
                }
            } else {
                $nombresUnicos = [];
                $timestamp = now()->format('Ymd_His');
                $idx = 1;
                foreach ($tagsOriginales as $nomOriginal) {
                    $nomLimpio = trim((string) $nomOriginal);
                    $nomConTimestamp = $nomLimpio . '_' . $timestamp . ($idx > 1 ? "_{$idx}" : '');
                    $nombresUnicos[] = $nomConTimestamp;
                    $mapaNombres[$nomLimpio] = $nomConTimestamp;
                    $idx++;
                }
                $data['services_tags_repuestos_a_reparar'] = $nombresUnicos;
                $data['num_repuestos_a_reparar'] = count($nombresUnicos);
            }
        }

        // 4. Crear cabecera de cotización
        // Consulta SQL Raw equivalente:
        // INSERT INTO "cotizaciones" ("num_cotizacion", "id_cliente", "id_tipo_orden", ...) VALUES (...);
        $cotizacion = Cotizacion::create($data);

        // 5. Procesar líneas de inventario (insumos / repuestos)
        $totalGravado = 0.00;
        $totalExento = 0.00;
        $totalIva = 0.00;

        foreach ($detallesInventario as $linea) {
            $id_inventario = $linea['id_inventario_insumo_saliente'] ?? $linea['id_inventario_repuesto_saliente'];
            $item = Inventario::find($id_inventario);
            $precioUnitarioBase = (float) ($linea['precio_unitario_base'] ?? ($item ? (($item->monto_compra_prom ?? 0) + ($item->monto_reparacion_prom ?? 0)) : 0.00));
            $cantidad = (int) ($linea['cantidad'] ?? 1);
            $precioUnitario = (float) ($linea['precio_unitario'] ?? 0.00);
            $porcentajeIva = (float) ($linea['porcentaje_iva'] ?? 19.00);
            $subtotal = round($cantidad * ($precioUnitario - ($linea['monto_tasacion'] ?? 0.00)), 2);
            $montoIva = round($subtotal * ($porcentajeIva / 100), 2);
            $totalLinea = $subtotal + $montoIva;

            // Mapear nombre_repuesto_a_reparar si vino el nombre original
            $nombreRep = $linea['service_tag_repuesto_a_reparar'] ?? $linea['nombre_repuesto_a_reparar'] ?? null;
            if ($nombreRep && isset($mapaNombres[$nombreRep])) {
                $nombreRep = $mapaNombres[$nombreRep];
            }

            CotizacionInventario::create([
                'id_cotizacion' => $cotizacion->id_cotizacion,
                'id_inventario_insumo_saliente' => $linea['id_inventario_insumo_saliente'] ?? null,
                'id_inventario_repuesto_saliente' => $linea['id_inventario_repuesto_saliente'] ?? null,
                'id_inventario_repuesto_entrante' => $linea['id_inventario_repuesto_entrante'] ?? null,
                'cantidad' => $cantidad,
                'precio_unitario_base' => $precioUnitarioBase,
                'precio_unitario' => $precioUnitario,
                'monto_tasacion' => isset($linea['monto_tasacion']) ? (float) $linea['monto_tasacion'] : null,
                'monto_total_linea_sin_iva' => $subtotal,
                'porcentaje_iva' => $porcentajeIva,
                'monto_iva' => $montoIva,
                'monto_total_linea_con_iva' => $totalLinea,
                'service_tag_repuesto_a_reparar' => $nombreRep,
            ]);

            if ($porcentajeIva > 0) {
                $totalGravado += $subtotal;
            } else {
                $totalExento += $subtotal;
            }
            $totalIva += $montoIva;
        }

        // 6. Procesar líneas de servicios de taller
        foreach ($detallesServicios as $lineaServ) {
            $id_servicio_taller = $lineaServ['id_servicio_taller'];
            $servicio = ServicioTaller::find($id_servicio_taller);
            $precioUnitarioBase = (float) ($lineaServ['precio_unitario_base'] ?? ($servicio ? ($servicio->costo_base ?? 0.00) : 0.00));
            $cantidad = (int) ($lineaServ['cantidad'] ?? 1);
            $precioUnitario = (float) ($lineaServ['precio_unitario'] ?? 0.00);
            $porcentajeIva = (float) ($lineaServ['porcentaje_iva'] ?? 19.00);
            $subtotal = round($cantidad * $precioUnitario, 2);
            $montoIva = round($subtotal * ($porcentajeIva / 100), 2);
            $totalLinea = $subtotal + $montoIva;

            $nombreRep = $lineaServ['service_tag_repuesto_a_reparar'] ?? $lineaServ['nombre_repuesto_a_reparar'] ?? null;
            if ($nombreRep && isset($mapaNombres[$nombreRep])) {
                $nombreRep = $mapaNombres[$nombreRep];
            }

            CotizacionServicioTaller::create([
                'id_cotizacion' => $cotizacion->id_cotizacion,
                'id_servicio_taller' => $lineaServ['id_servicio_taller'],
                'cantidad' => $cantidad,
                'precio_unitario_base' => $precioUnitarioBase,
                'precio_unitario' => $precioUnitario,
                'monto_total_linea_sin_iva' => $subtotal,
                'porcentaje_iva' => $porcentajeIva,
                'monto_iva' => $montoIva,
                'monto_total_linea_con_iva' => $totalLinea,
                'service_tag_repuesto_a_reparar' => $nombreRep,
            ]);

            if ($porcentajeIva > 0) {
                $totalGravado += $subtotal;
            } else {
                $totalExento += $subtotal;
            }
            $totalIva += $montoIva;
        }

        // 7. Consolidar totales en la cabecera si no venían prefijados
        $montoTotalNeto = round($totalGravado + $totalExento + $totalIva, 2);
        $cotizacion->update([
            'monto_total_gravado' => round($totalGravado, 2),
            'monto_total_exento' => round($totalExento, 2),
            'monto_total_iva' => round($totalIva, 2),
            'monto_total' => $montoTotalNeto,
        ]);

        DB::commit();

        // Generar automáticamente el PDF de la cotización y almacenar su ruta
        $pdfPath = PDFService::generarCotizacionPDF($cotizacion);
        if ($pdfPath) {
            $cotizacion->update(['pdf_cotizacion' => $pdfPath]);
        }

        return self::getOne($cotizacion->id_cotizacion);
    }

    /**
     * Crea una cotización hija/extra a partir de la modificación de una reparación activa en taller.
     * 
     * Regla:
     * - Si la orden padre nació de una cotización: asigna num_cotizacion_padre = num_cotizacion original.
     * - Si la orden padre nació sin cotización previa: num_cotizacion_padre = null.
     */
    public static function crearCotizacionExtraDesdeReparacion(int $idOrden, int $idRepuesto, array $payloadModificacion)
    {
        $orden = Orden::find($idOrden);
        if (!$orden) {
            return 'Orden no encontrada';
        }

        $repuesto = Repuesto::find($idRepuesto);
        if (!$repuesto) {
            return 'Repuesto no encontrado';
        }

        // 1. Verificar si ya existe una cotización extra pendiente para este repuesto en específico
        // Consulta SQL Raw equivalente:
        // SELECT EXISTS (
        //     SELECT 1 FROM cotizaciones 
        //     WHERE id_orden = :idOrden 
        //       AND tipo = 'extra' 
        //       AND estado = 'pendiente' 
        //       AND :serviceTag = ANY(services_tags_repuestos_a_reparar)
        // );
        if (self::tieneCotizacionExtraPendiente($idOrden, $repuesto->service_tag)) {
            return "Ya existe una cotización extra pendiente de aprobación por el cliente para este repuesto ({$repuesto->service_tag})";
        }

        // Buscar cotización inicial padre si existió
        // Consulta SQL Raw:
        // SELECT * FROM cotizaciones WHERE id_orden = :idOrden AND tipo = 'inicial' LIMIT 1;
        $cotizacionPadre = Cotizacion::where('id_orden', $idOrden)
            ->where('tipo', CotizacionTipo::INICIAL->value)
            ->first();

        $numCotizacionPadre = $cotizacionPadre ? $cotizacionPadre->num_cotizacion : null;

        // Mapear insumos recibidos desde la UI para CotizacionInventario
        $insumosCotizacion = [];
        foreach ($payloadModificacion['insumos'] ?? [] as $ins) {
            $insumosCotizacion[] = [
                'id_inventario_insumo_saliente' => $ins['id_inventario_insumo_saliente'] ?? $ins['id_inventario'] ?? null,
                'cantidad' => $ins['cantidad'] ?? 1,
                'precio_unitario_base' => $ins['precio_unitario_base'] ?? $ins['costo_unitario'] ?? null,
                'precio_unitario' => $ins['precio_unitario'] ?? $ins['costo_unitario_con_ganancia'] ?? 0.00,
                'porcentaje_iva' => $ins['porcentaje_iva'] ?? 19.00,
                'service_tag_repuesto_a_reparar' => $repuesto->service_tag,
            ];
        }

        // Mapear servicios recibidos desde la UI para CotizacionServicioTaller
        $serviciosCotizacion = [];
        foreach ($payloadModificacion['servicios'] ?? [] as $srv) {
            $serviciosCotizacion[] = [
                'id_servicio_taller' => $srv['id_servicio_taller'],
                'cantidad' => $srv['cantidad'] ?? 1,
                'precio_unitario_base' => $srv['precio_unitario_base'] ?? $srv['costo_unitario'] ?? null,
                'precio_unitario' => $srv['precio_unitario'] ?? $srv['costo_unitario_con_ganancia'] ?? 0.00,
                'porcentaje_iva' => $srv['porcentaje_iva'] ?? 19.00,
                'service_tag_repuesto_a_reparar' => $repuesto->service_tag,
            ];
        }

        // Construir data para create()
        $data = [
            'id_orden' => $idOrden,
            'id_admin' => (auth()->id() ? (Admin::where('id_user', auth()->id())->value('id_admin') ?? 1) : 1),
            'id_cliente' => $orden->id_cliente,
            'id_tipo_orden' => $orden->id_tipo_orden,
            'num_cotizacion_padre' => $numCotizacionPadre,
            'tipo' => CotizacionTipo::EXTRA->value,
            'estado' => CotizacionEstado::PENDIENTE->value,
            'num_repuestos_a_reparar' => 1,
            'services_tags_repuestos_a_reparar' => [$repuesto->service_tag],
            'detalles_inventario' => $insumosCotizacion,
            'detalles_servicios' => $serviciosCotizacion,
        ];

        return self::create($data);
    }

    /**
     * Verifica si una orden tiene alguna cotización extra pendiente de contestación por el cliente.
     * Si se proporciona $serviceTag, verifica si existe una cotización extra pendiente para dicho repuesto.
     * 
     * Consulta SQL Raw equivalente (con service_tag):
     * SELECT EXISTS (
     *     SELECT 1 FROM "cotizaciones" 
     *     WHERE "id_orden" = :idOrden 
     *       AND "tipo" = 'extra' 
     *       AND "estado" = 'pendiente' 
     *       AND :serviceTag = ANY("services_tags_repuestos_a_reparar")
     * );
     *
     * Consulta SQL Raw equivalente (sin service_tag):
     * SELECT EXISTS (
     *     SELECT 1 FROM "cotizaciones" 
     *     WHERE "id_orden" = :idOrden 
     *       AND "tipo" = 'extra' 
     *       AND "estado" = 'pendiente'
     * );
     *
     * @param int $idOrden
     * @param string|null $serviceTag
     * @return bool
     */
    public static function tieneCotizacionExtraPendiente(int $idOrden, ?string $serviceTag = null): bool
    {
        // Consulta SQL Raw equivalente:
        // SELECT EXISTS (
        //     SELECT 1 FROM "cotizaciones" 
        //     WHERE "id_orden" = :idOrden 
        //       AND "tipo" = 'extra' 
        //       AND "estado" = 'pendiente'
        //       AND (:serviceTag IS NULL OR :serviceTag = ANY("services_tags_repuestos_a_reparar"))
        // );
        $query = Cotizacion::where('id_orden', $idOrden)
            ->where('tipo', CotizacionTipo::EXTRA->value)
            ->where('estado', CotizacionEstado::PENDIENTE->value);

        if (!empty($serviceTag)) {
            $query->whereRaw('? = ANY(services_tags_repuestos_a_reparar)', [$serviceTag]);
        }

        return $query->exists();
    }

    /**
     * Acepta una cotización y ejecuta la acción correspondiente según el tipo:
     * - Si es cotización INICIAL (Venta, Recambio, Reparación): crea la orden de trabajo.
     * - Si es cotización EXTRA (Reparación): aplica las modificaciones sobre la reparación activa.
     */
    public static function aceptar(int $idCotizacion, array $data = [])
    {
        $cotizacion = Cotizacion::with(['detallesInventario', 'detallesServiciosTaller', 'tipoOrden'])->find($idCotizacion);
        if (!$cotizacion) {
            return 'Cotización no encontrada';
        }

        if ($cotizacion->estado !== CotizacionEstado::PENDIENTE) {
            return 'Solo se pueden aceptar cotizaciones en estado pendiente';
        }

        DB::beginTransaction();

        $nombreTipo = $cotizacion->tipoOrden ? strtolower(trim($cotizacion->tipoOrden->nombre)) : '';

        // CASO 1: Cotización EXTRA (hija) de una Reparación en curso
        if ($cotizacion->tipo === CotizacionTipo::EXTRA) {
            $resultado = self::aplicarCotizacionExtraSobreReparacion($cotizacion, $data);
            if (is_string($resultado)) {
                DB::rollBack();
                return $resultado;
            }

            $cotizacion->update([
                'estado' => CotizacionEstado::ACEPTADA->value,
            ]);

            DB::commit();

            $idsInventarioAfectados = $cotizacion->detallesInventario
                ->pluck('id_inventario_insumo_saliente')
                ->merge($cotizacion->detallesInventario->pluck('id_inventario_repuesto_saliente'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($idsInventarioAfectados)) {
                InventarioService::verificarYNotificarStockBajo($idsInventarioAfectados);
            }

            return self::getOne($cotizacion->id_cotizacion);
        }

        // CASO 2: Cotización INICIAL -> Generar Orden de Trabajo correspondiente
        $ordenCreada = null;

        if ($nombreTipo === 'venta') {
            // Requiere mapeo de seriales físicos que salen
            $ordenCreada = self::generarOrdenVentaDesdeCotizacion($cotizacion, $data);
        } elseif ($nombreTipo === 'recambio') {
            // Requiere id_repuesto_saliente físico, serial_entrante, nombre_entrante y monto_tasacion
            $ordenCreada = self::generarOrdenRecambioDesdeCotizacion($cotizacion, $data);
        } elseif ($nombreTipo === 'reparacion') {
            // Requiere seriales y modelos de las piezas físicas del cliente
            $ordenCreada = self::generarOrdenReparacionDesdeCotizacion($cotizacion, $data);
        } else {
            DB::rollBack();
            return 'Tipo de orden no soportado para cotización';
        }

        if (is_string($ordenCreada)) {
            DB::rollBack();
            return $ordenCreada;
        }

        // Vincular cotización a la orden y marcar como aceptada
        $cotizacion->update([
            'id_orden' => $ordenCreada->id_orden,
            'estado' => CotizacionEstado::ACEPTADA->value,
        ]);

        DB::commit();

        $idsInventarioAfectados = $cotizacion->detallesInventario
            ->pluck('id_inventario_insumo_saliente')
            ->merge($cotizacion->detallesInventario->pluck('id_inventario_repuesto_saliente'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($idsInventarioAfectados)) {
            InventarioService::verificarYNotificarStockBajo($idsInventarioAfectados);
        }

        return self::getOne($cotizacion->id_cotizacion);
    }

    /**
     * Aplica los insumos y servicios de una cotización extra sobre la reparación activa.
     * Importante: NO actualiza montos económicos en la orden padre hasta que el estado sea 'finalizada'.
     */
    protected static function aplicarCotizacionExtraSobreReparacion(Cotizacion $cotizacion, array $data)
    {
        // Obtener el nombre del repuesto involucrado
        $nombres = $cotizacion->services_tags_repuestos_a_reparar ?? [];
        $nombreRep = $nombres[0] ?? null;

        // Buscar el repuesto en la orden
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "repuestos" 
        // WHERE "id_orden_entrada" = :id_orden 
        //   AND "service_tag" = :nombreRep 
        // LIMIT 1;
        $repuesto = Repuesto::where('id_orden_entrada', $cotizacion->id_orden)
            ->when($nombreRep, fn($q) => $q->where('service_tag', $nombreRep))
            ->first();

        if (!$repuesto) {
            return 'No se encontró el repuesto asociado a esta cotización en la orden';
        }

        // Buscar la reparación activa del repuesto
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "reparaciones" WHERE "id_repuesto" = :id_repuesto LIMIT 1;
        $reparacion = Reparacion::where('id_repuesto', $repuesto->id_repuesto)->first();
        if (!$reparacion) {
            return 'No se encontró la ficha de reparación para este repuesto';
        }

        // La cotización EXTRA es estrictamente acumulativa (solo agrega nuevos o incrementa existentes).
        // 1. Procesar insumos cotizados extra:
        foreach ($cotizacion->detallesInventario as $detInv) {
            $insumoExistente = \App\Models\ReparacionInsumo::where('id_reparacion', $reparacion->id_reparacion)
                ->where('id_inventario', $detInv->id_inventario_insumo_saliente)
                ->first();

            if ($insumoExistente) {
                // Incrementar cantidad existente conservando los costos comerciales cotizados
                ReparacionInsumoService::update($insumoExistente->id_reparacion_insumo, [
                    'cantidad' => (int) $insumoExistente->cantidad + (int) $detInv->cantidad,
                    'costo_unitario' => $detInv->precio_unitario_base,
                    'costo_unitario_con_ganancia' => $detInv->precio_unitario,
                    'porcentaje_iva' => $detInv->porcentaje_iva,
                ]);
            } else {
                // Registrar nuevo insumo
                ReparacionInsumoService::create([
                    'id_reparacion' => $reparacion->id_reparacion,
                    'id_inventario' => $detInv->id_inventario_insumo_saliente,
                    'cantidad' => $detInv->cantidad,
                    'costo_unitario' => $detInv->precio_unitario_base,
                    'costo_unitario_con_ganancia' => $detInv->precio_unitario,
                    'porcentaje_iva' => $detInv->porcentaje_iva,
                ]);
            }
        }

        // 2. Procesar servicios cotizados extra:
        foreach ($cotizacion->detallesServiciosTaller as $detServ) {
            $servicioExistente = \App\Models\ReparacionServicioTaller::where('id_reparacion', $reparacion->id_reparacion)
                ->where('id_servicio_taller', $detServ->id_servicio_taller)
                ->first();

            if ($servicioExistente) {
                // Incrementar cantidad existente conservando los costos comerciales cotizados
                ReparacionServicioTallerService::update($servicioExistente->id_reparacion_servicio_taller, [
                    'cantidad' => (int) $servicioExistente->cantidad + (int) $detServ->cantidad,
                    'costo_unitario' => $detServ->precio_unitario_base,
                    'costo_unitario_con_ganancia' => $detServ->precio_unitario,
                    'porcentaje_iva' => $detServ->porcentaje_iva,
                ]);
            } else {
                // Registrar nuevo servicio
                ReparacionServicioTallerService::create([
                    'id_reparacion' => $reparacion->id_reparacion,
                    'id_servicio_taller' => $detServ->id_servicio_taller,
                    'cantidad' => $detServ->cantidad,
                    'costo_unitario' => $detServ->precio_unitario_base,
                    'costo_unitario_con_ganancia' => $detServ->precio_unitario,
                    'porcentaje_iva' => $detServ->porcentaje_iva,
                    'estado' => 'pendiente_asignacion',
                ]);
            }
        }

        // Recalcular costos consolidados de la reparación
        ReparacionService::recalcularCostosReparacion($reparacion->id_reparacion);

        return true;
    }

    /**
     * Convierte una cotización de Venta en una Orden de Venta.
     * Es un ensamblador / puente: toma los precios, cantidades y productos ya fijados en la cotización, 
     * le pega los números de serie físicos que tú le indicas en el payload, 
     * y le pasa todo listo a OrdenService para que descuente bodega y cree la venta oficial.
     */
    protected static function generarOrdenVentaDesdeCotizacion(Cotizacion $cotizacion, array $data)
    {
        // $data['detalles'] debe contener el mapeo de los id_repuesto_saliente serializados
        $detallesPayload = [];
        $mapeosSeriales = $data['detalles'] ?? [];

        foreach ($cotizacion->detallesInventario as $idx => $det) {
            $mapeo = $mapeosSeriales[$idx] ?? [];

            $detallesPayload[] = [
                'id_inventario_insumo_saliente' => $det->id_inventario_insumo_saliente,
                'id_inventario_repuesto_saliente' => $det->id_inventario_repuesto_saliente,
                'id_repuesto_saliente' => $mapeo['id_repuesto_saliente'] ?? null,
                'repuestos_salientes' => $mapeo['repuestos_salientes'] ?? null,
                'cantidad' => $det->cantidad,
                'precio_unitario' => $det->precio_unitario,
                'porcentaje_iva' => $det->porcentaje_iva,
            ];
        }

        $ordenData = [
            'id_cliente' => $cotizacion->id_cliente,
            'id_tipo_orden' => $cotizacion->id_tipo_orden,
            'id_admin' => $cotizacion->id_admin,
            'monto_total_gravado' => $cotizacion->monto_total_gravado,
            'monto_total_exento' => $cotizacion->monto_total_exento,
            'monto_total_iva' => $cotizacion->monto_total_iva,
            'monto_total' => $cotizacion->monto_total,
            'monto_pendiente' => $cotizacion->monto_total,
            'detalles' => $detallesPayload,
        ];

        return OrdenService::create($ordenData);
    }

    /**
     * Convierte una cotización de Recambio en una Orden de Recambio.
     * Nota: La pieza entrante queda como 'pendiente_reparacion' sin generar fila en reparaciones.
     */
    protected static function generarOrdenRecambioDesdeCotizacion(Cotizacion $cotizacion, array $data)
    {
        // $data['detalles'] contiene id_repuesto_saliente, serial_entrante, service_tag_entrante y monto_tasacion
        $detallesPayload = [];
        $mapeosRecambio = $data['detalles'] ?? [];

        foreach ($cotizacion->detallesInventario as $idx => $det) {
            $mapeo = $mapeosRecambio[$idx] ?? [];

            $detallesPayload[] = [
                'id_inventario_repuesto_saliente' => $det->id_inventario_repuesto_saliente,
                'id_repuesto_saliente' => $mapeo['id_repuesto_saliente'] ?? null,
                'precio_unitario' => $det->precio_unitario,
                'id_inventario_repuesto_entrante' => $det->id_inventario_repuesto_entrante,
                'serial_entrante' => $mapeo['serial_entrante'] ?? 'SN-REC-' . uniqid(),
                'service_tag_entrante' => $mapeo['service_tag_entrante'] ?? $mapeo['nombre_entrante'] ?? 'Repuesto Recambio Entrante',
                'monto_tasacion' => (float) ($mapeo['monto_tasacion'] ?? $det->monto_tasacion ?? 0.00),
                'porcentaje_iva' => $det->porcentaje_iva,
                'cantidad' => 1,
            ];
        }

        $ordenData = [
            'id_cliente' => $cotizacion->id_cliente,
            'id_tipo_orden' => $cotizacion->id_tipo_orden,
            'id_admin' => $cotizacion->id_admin,
            'monto_total_gravado' => $cotizacion->monto_total_gravado,
            'monto_total_exento' => $cotizacion->monto_total_exento,
            'monto_total_iva' => $cotizacion->monto_total_iva,
            'monto_total' => $cotizacion->monto_total,
            'monto_pendiente' => $cotizacion->monto_total,
            'detalles' => $detallesPayload,
        ];

        return OrdenService::create($ordenData);
    }

    /**
     * Convierte una cotización de Reparación en una Orden de Reparación.
     */
    protected static function generarOrdenReparacionDesdeCotizacion(Cotizacion $cotizacion, array $data)
    {
        // $data['repuestos_entrantes'] contiene el serial y nombre físico de cada pieza
        $repuestosEntrantes = $data['repuestos_entrantes'] ?? [];

        $ordenData = [
            'id_cliente' => $cotizacion->id_cliente,
            'id_tipo_orden' => $cotizacion->id_tipo_orden,
            'id_admin' => $cotizacion->id_admin,
            'estado_operativo' => OrdenEstadoOperativo::EN_ESPERA->value,
            'repuestos_entrantes' => $repuestosEntrantes,
            'fecha_entrega_reparacion' => $data['fecha_entrega_reparacion'] ?? null,
        ];

        $orden = OrdenService::create($ordenData);
        if (!$orden || is_string($orden)) {
            return $orden;
        }

        // Ahora asociar los insumos y servicios cotizados a cada reparación creada
        foreach ($cotizacion->services_tags_repuestos_a_reparar ?? [] as $nomRep) {
            // Buscar el repuesto creado en la orden
            $repuesto = Repuesto::where('id_orden_entrada', $orden->id_orden)
                ->where('service_tag', $nomRep)
                ->first();

            if (!$repuesto) {
                continue;
            }

            $reparacion = Reparacion::firstOrCreate(
                ['id_repuesto' => $repuesto->id_repuesto],
                [
                    'id_orden' => $orden->id_orden,
                    'estado' => \App\Enums\ReparacionEstado::PENDIENTE->value,
                    'fecha_inicio' => null,
                    'id_admin_fecha_inicio' => null,
                    'fecha_fin' => null,
                    'id_admin_fecha_fin' => null,
                ]
            );

            // Insumos cotizados para este repuesto
            $insumosRep = $cotizacion->detallesInventario->where('service_tag_repuesto_a_reparar', $nomRep);
            foreach ($insumosRep as $ins) {
                ReparacionInsumoService::create([
                    'id_reparacion' => $reparacion->id_reparacion,
                    'id_inventario' => $ins->id_inventario_insumo_saliente,
                    'cantidad' => $ins->cantidad,
                    'costo_unitario' => $ins->precio_unitario_base,
                    'costo_unitario_con_ganancia' => $ins->precio_unitario,
                    'porcentaje_iva' => $ins->porcentaje_iva,
                ]);
            }

            // Servicios cotizados para este repuesto
            $serviciosRep = $cotizacion->detallesServiciosTaller->where('service_tag_repuesto_a_reparar', $nomRep);
            foreach ($serviciosRep as $srv) {
                ReparacionServicioTallerService::create([
                    'id_reparacion' => $reparacion->id_reparacion,
                    'id_servicio_taller' => $srv->id_servicio_taller,
                    'cantidad' => $srv->cantidad,
                    'costo_unitario' => $srv->precio_unitario_base,
                    'costo_unitario_con_ganancia' => $srv->precio_unitario,
                    'porcentaje_iva' => $srv->porcentaje_iva,
                    'estado' => 'pendiente_asignacion',
                ]);
            }
        }

        return $orden;
    }

    /**
     * Rechaza una cotización.
     */
    public static function rechazar(int $idCotizacion)
    {
        $cotizacion = Cotizacion::find($idCotizacion);
        if (!$cotizacion) {
            return 'Cotización no encontrada';
        }

        if ($cotizacion->estado !== CotizacionEstado::PENDIENTE) {
            return 'Solo se pueden rechazar cotizaciones en estado pendiente';
        }

        $cotizacion->update([
            'estado' => CotizacionEstado::RECHAZADA->value,
        ]);

        return self::getOne($cotizacion->id_cotizacion);
    }

    /**
     * Anula una cotización registrando motivo y administrador responsable.
     */
    public static function anular(int $idCotizacion, array $data)
    {
        $cotizacion = Cotizacion::find($idCotizacion);
        if (!$cotizacion) {
            return 'Cotización no encontrada';
        }

        if ($cotizacion->estado === CotizacionEstado::ANULADA) {
            return 'La cotización ya se encuentra anulada';
        }

        $cotizacion->update([
            'estado' => CotizacionEstado::ANULADA->value,
            'fecha_anulacion' => now(),
            'id_admin_anulacion' => $data['id_admin_anulacion'] ?? (auth()->id() ? (Admin::where('id_user', auth()->id())->value('id_admin') ?? 1) : 1),
            'motivo_anulacion' => $data['motivo_anulacion'] ?? 'Anulación de cotización',
        ]);

        return self::getOne($cotizacion->id_cotizacion);
    }

    /**
     * Elimina físicamente una cotización y sus líneas asociadas si no ha sido aceptada.
     */
    public static function delete(int $id)
    {
        $cotizacion = Cotizacion::find($id);
        if (!$cotizacion) {
            return null;
        }

        if ($cotizacion->estado === CotizacionEstado::ACEPTADA) {
            return 'No se puede eliminar una cotización que ya fue aceptada';
        }

        DB::beginTransaction();

        // Consulta SQL Raw equivalente:
        // DELETE FROM "cotizaciones_inventario" WHERE "id_cotizacion" = :id;
        // DELETE FROM "cotizaciones_servicios_taller" WHERE "id_cotizacion" = :id;
        // DELETE FROM "cotizaciones" WHERE "id_cotizacion" = :id;
        CotizacionInventario::where('id_cotizacion', $id)->delete();
        CotizacionServicioTaller::where('id_cotizacion', $id)->delete();
        $cotizacion->delete();

        DB::commit();

        return $cotizacion;
    }
}