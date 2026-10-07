<?php

namespace App\Models;

use App\Enums\CotizacionTipo;
use App\Enums\CotizacionEstado;

/**
 * Modelo Cotizacion
 * 
 * Representa la cabecera de una cotización comercial para Venta, Recambio o Reparación.
 * Maneja jerarquías padre/hija (tipo 'extra') y referencias a repuestos de cliente a reparar.
 */
class Cotizacion extends ApiModel
{
    protected $table = 'cotizaciones';
    protected $primaryKey = 'id_cotizacion';
    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion',
        'id_orden',
        'id_admin',
        'id_cliente',
        'id_tipo_orden',
        'num_cotizacion_padre',
        'num_cotizacion',
        'tipo',
        'monto_total_gravado',
        'monto_total_exento',
        'monto_total_iva',
        'monto_total',
        'estado',
        'pdf_cotizacion',
        'fecha_creacion',
        'fecha_vencimiento',
        'fecha_anulacion',
        'id_admin_anulacion',
        'motivo_anulacion',
        'num_repuestos_a_reparar',
        'services_tags_repuestos_a_reparar',
    ];

    protected $casts = [
        'tipo'                        => CotizacionTipo::class,
        'estado'                      => CotizacionEstado::class,
        'monto_total_gravado'         => 'float',
        'monto_total_exento'          => 'float',
        'monto_total_iva'             => 'float',
        'monto_total'                 => 'float',
        'fecha_creacion'              => 'datetime',
        'fecha_vencimiento'           => 'datetime',
        'fecha_anulacion'             => 'datetime',
        'num_repuestos_a_reparar'     => 'integer',
    ];

    /**
     * Mutator y Accessor para el campo nativo PostgreSQL character varying[] (services_tags_repuestos_a_reparar).
     * PostgreSQL espera el formato literal de array: {"elem1","elem2"}
     */
    public function getServicesTagsRepuestosARepararAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (empty($value) || $value === '{}') {
            return [];
        }
        // Parsea el formato PostgreSQL {"item1","item2"} o JSON si viniese como tal
        if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
            $inner = substr($value, 1, -1);
            if (empty($inner)) {
                return [];
            }
            return str_getcsv($inner, ',', '"');
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

        public function setServicesTagsRepuestosARepararAttribute($value): void
    {
        if (is_array($value)) {
            // Convierte el array PHP al formato nativo PostgreSQL: {"elem1","elem2"}
            $escaped = array_map(function ($item) {
                $itemStr = str_replace(chr(92), chr(92) . chr(92), (string) $item);
                $itemStr = str_replace(chr(34), chr(92) . chr(34), $itemStr);
                return chr(34) . $itemStr . chr(34);
            }, $value);
            $this->attributes['services_tags_repuestos_a_reparar'] = '{' . implode(',', $escaped) . '}';
        } else {
            $this->attributes['services_tags_repuestos_a_reparar'] = $value;
        }
    }

    /**
     * Evento booted del modelo:
     * Al crearse, HasGeneratedID asigna el id_cotizacion con el nuevo valor de 'App\\Models\\Cotizacion'
     * en la tabla secuencias. En ese mismo instante, se genera el num_cotizacion sincronizado: COT-XXXXX.
     */
    protected static function booted()
    {
        static::creating(function ($cotizacion) {
            if (empty($cotizacion->num_cotizacion)) {
                $cotizacion->num_cotizacion = 'COT-' . str_pad($cotizacion->id_cotizacion, 5, '0', STR_PAD_LEFT);
            }
            if (empty($cotizacion->pdf_cotizacion)) {
                $cotizacion->pdf_cotizacion = $cotizacion->num_cotizacion . '.pdf';
            }
        });
    }

    /**
     * Orden de trabajo asociada una vez que la cotización es aceptada o si nace de una reparación.
     * Consulta SQL Raw:
     * SELECT * FROM ordenes WHERE id_orden = $this->id_orden LIMIT 1;
     */
    public function orden()
    {
        return $this->belongsTo(Orden::class, 'id_orden', 'id_orden');
    }

    /**
     * Administrador que elaboró la cotización.
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = $this->id_admin LIMIT 1;
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }

    /**
     * Administrador que anuló la cotización.
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = $this->id_admin_anulacion LIMIT 1;
     */
    public function adminAnulacion()
    {
        return $this->belongsTo(Admin::class, 'id_admin_anulacion', 'id_admin');
    }

    /**
     * Cliente al que va dirigida la cotización.
     * Consulta SQL Raw:
     * SELECT * FROM clientes WHERE id_cliente = $this->id_cliente LIMIT 1;
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    /**
     * Tipo de orden base (Venta, Recambio, Reparación).
     * Consulta SQL Raw:
     * SELECT * FROM tipos_ordenes WHERE id_tipo_orden = $this->id_tipo_orden LIMIT 1;
     */
    public function tipoOrden()
    {
        return $this->belongsTo(TipoOrden::class, 'id_tipo_orden', 'id_tipo_orden');
    }

    /**
     * Líneas de inventario cotizadas (insumos o repuestos salientes/entrantes).
     * Consulta SQL Raw:
     * SELECT * FROM cotizaciones_inventario WHERE id_cotizacion = $this->id_cotizacion;
     */
    public function detallesInventario()
    {
        return $this->hasMany(CotizacionInventario::class, 'id_cotizacion', 'id_cotizacion');
    }

    /**
     * Líneas de servicios de taller cotizados.
     * Consulta SQL Raw:
     * SELECT * FROM cotizaciones_servicios_taller WHERE id_cotizacion = $this->id_cotizacion;
     */
    public function detallesServiciosTaller()
    {
        return $this->hasMany(CotizacionServicioTaller::class, 'id_cotizacion', 'id_cotizacion');
    }
}