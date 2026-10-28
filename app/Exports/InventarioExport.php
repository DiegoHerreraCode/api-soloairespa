<?php
namespace App\Exports;
use App\Models\Inventario;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class InventarioExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $query = Inventario::query();

        if (!empty($this->filters['sku'])) {
            $query->where('sku', 'like', '%' . $this->filters['sku'] . '%');
        }

        if (!empty($this->filters['nombre'])) {
            $query->where('nombre', 'like', '%' . $this->filters['nombre'] . '%');
        }

        // El frontend manda 'insumo', 'compresor', etc. o 'all'
        if (!empty($this->filters['tipo']) && $this->filters['tipo'] !== 'all') {
            $query->where('tipo', $this->filters['tipo']);
        }

        // El frontend manda 'nuevo', 'usado', 'na' o 'all'
        if (!empty($this->filters['condicion']) && $this->filters['condicion'] !== 'all') {
            $condicion = $this->filters['condicion'] === 'na' ? 'NA' : $this->filters['condicion'];
            $query->where('condicion', $condicion);
        }

        return $query->orderBy('id_inventario', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'SKU',
            'Nombre',
            'Tipo',
            'Condición',
            'Stock Total',
            'Stock Propio',
            'Stock Cliente',
            'Stock Mínimo',
            'Precio Venta Unitario',
        ];
    }

    public function map($item): array
    {
        // Extraer el string del enum de forma segura
        $tipoStr = $item->tipo?->value ?? (string) $item->tipo;
        $condicionStr = $item->condicion?->value ?? (string) $item->condicion;

        return [
            (string) ($item->id_inventario),
            $item->sku ?? '',
            $item->nombre ?? '',
            strtoupper($tipoStr ?? ''),
            $condicionStr ? strtoupper($condicionStr) : 'N/A',
            (string) ($item->cantidad_total ?? 0),
            (string) ($item->cantidad_propia ?? 0),
            (string) ($item->cantidad_cliente ?? 0),
            (string) ($item->stock_minimo ?? 0),
            (string) ($item->monto_venta_unitario ?? 0),
        ];
    }
}