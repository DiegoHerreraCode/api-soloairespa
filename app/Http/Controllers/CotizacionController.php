<?php

namespace App\Http\Controllers;

use App\Services\CotizacionService;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class CotizacionController
 *
 * Controlador RESTful encargado de gestionar las cotizaciones para Venta, Recambio y Reparación.
 * Maneja la creación, consulta, aceptación (conversión en orden), rechazo y anulación de cotizaciones.
 */
class CotizacionController extends Controller
{
    /**
     * Retorna todas las cotizaciones registradas.
     *
     * Lógica:
     * 1. Consulta la colección completa de cotizaciones mediante CotizacionService::getAll().
     * 2. Retorna respuesta estándar JSON con código 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "cotizaciones";
     *
     * @return JsonResponse Lista de cotizaciones.
     */
    public function index(): JsonResponse
    {
        $cotizaciones = CotizacionService::getAll();
        return $this->successResponse(
            $cotizaciones,
            $cotizaciones->isEmpty() ? 'No se encontraron cotizaciones' : 'Cotizaciones obtenidas correctamente'
        );
    }

    /**
     * Registra una nueva cotización en el sistema.
     *
     * Lógica:
     * 1. Valida cabecera (cliente, tipo de orden, fechas, nombres de repuestos).
     * 2. En reparaciones, agrega timestamp único a cada nombre de repuesto.
     * 3. Registra las líneas en cotizaciones_inventario y cotizaciones_servicios_taller.
     * 4. Retorna la cotización creada con código 201.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_cliente'                     => 'required|integer|exists:clientes,id_cliente',
            'id_tipo_orden'                  => 'required|integer|exists:tipos_ordenes,id_tipo_orden',
            'id_admin'                       => 'nullable|integer|exists:admins,id_admin',
            'fecha_vencimiento'              => 'nullable|date',
            'num_repuestos_a_reparar'        => 'nullable|integer|min:1',
            'services_tags_repuestos_a_reparar'    => 'nullable|array',
            'services_tags_repuestos_a_reparar.*'  => 'required_with:services_tags_repuestos_a_reparar|string|max:100',

            // Líneas de inventario (insumos, repuestos salientes o recambios)
            'detalles_inventario'                                     => 'nullable|array',
            'detalles_inventario.*.id_inventario_insumo_saliente'     => 'nullable|integer|exists:inventario,id_inventario',
            'detalles_inventario.*.id_inventario_repuesto_saliente'   => 'nullable|integer|exists:inventario,id_inventario',
            'detalles_inventario.*.id_inventario_repuesto_entrante'   => 'nullable|integer|exists:inventario,id_inventario',
            'detalles_inventario.*.cantidad'                          => 'nullable|integer|min:1',
            'detalles_inventario.*.precio_unitario'                   => 'nullable|numeric|min:0',
            'detalles_inventario.*.monto_tasacion'                    => 'nullable|numeric|min:0',
            'detalles_inventario.*.porcentaje_iva'                    => 'nullable|numeric|min:0',
            'detalles_inventario.*.service_tag_repuesto_a_reparar'         => 'nullable|string|max:100',

            // Líneas de servicios de taller (mano de obra)
            'detalles_servicios'                                      => 'nullable|array',
            'detalles_servicios.*.id_servicio_taller'                 => 'required_with:detalles_servicios|integer|exists:servicios_taller,id_servicio_taller',
            'detalles_servicios.*.cantidad'                           => 'nullable|integer|min:1',
            'detalles_servicios.*.precio_unitario'                    => 'nullable|numeric|min:0',
            'detalles_servicios.*.porcentaje_iva'                     => 'nullable|numeric|min:0',
            'detalles_servicios.*.service_tag_repuesto_a_reparar'          => 'nullable|string|max:100',
        ]);

        $data = $request->all();

        if (empty($data['id_admin'])) {
            $userId = auth()->id();
            $admin = $userId ? Admin::where('id_user', $userId)->first() : null;
            $data['id_admin'] = $admin ? $admin->id_admin : 1;
        }

        $cotizacion = CotizacionService::create($data);
        if (!$cotizacion) {
            return $this->errorResponse('Cotización no creada', 404);
        }

        return $this->successResponse($cotizacion, 'Cotización creada correctamente', 201);
    }

    /**
     * Consulta y entrega la información de una cotización con sus detalles por ID.
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $cotizacion = CotizacionService::getOne($id);
        if (!$cotizacion) {
            return $this->errorResponse('Cotización no encontrada', 404);
        }

        return $this->successResponse($cotizacion, 'Cotización obtenida correctamente');
    }

    /**
     * Acepta una cotización por parte del cliente y genera la orden o aplica cambios en taller.
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function aceptar(Request $request, $id): JsonResponse
    {
        $data = $request->all();
        $resultado = CotizacionService::aceptar($id, $data);

        if (is_string($resultado)) {
            return $this->errorResponse($resultado, 422);
        }

        return $this->successResponse($resultado, 'Cotización aceptada y procesada correctamente');
    }

    /**
     * Rechaza formalmente una cotización.
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function rechazar($id): JsonResponse
    {
        $resultado = CotizacionService::rechazar($id);

        if (is_string($resultado)) {
            return $this->errorResponse($resultado, 422);
        }

        return $this->successResponse($resultado, 'Cotización rechazada correctamente');
    }

    /**
     * Anula una cotización registrando motivo y administrador.
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function anular(Request $request, $id): JsonResponse
    {
        $request->validate([
            'motivo_anulacion' => 'required|string|max:250',
        ]);

        $data = $request->all();
        $userId = auth()->id();
        $admin = $userId ? Admin::where('id_user', $userId)->first() : null;
        $data['id_admin_anulacion'] = $admin ? $admin->id_admin : ($data['id_admin_anulacion'] ?? 1);

        $resultado = CotizacionService::anular($id, $data);

        if (is_string($resultado)) {
            return $this->errorResponse($resultado, 422);
        }

        return $this->successResponse($resultado, 'Cotización anulada correctamente');
    }

    /**
     * Elimina físicamente una cotización si aún no fue aceptada.
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $resultado = CotizacionService::delete($id);

        if (is_string($resultado)) {
            return $this->errorResponse($resultado, 422);
        }

        if (!$resultado) {
            return $this->errorResponse('Cotización no encontrada o no se pudo eliminar', 404);
        }

        return $this->successResponse(null, 'Cotización eliminada exitosamente');
    }
}