<?php

namespace App\Services;

use App\Models\Cotizacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PDFService
{
    /**
     * Datos maestros de la empresa emisora
     */
    public static function getDatosEmpresa(): array
    {
        return [
            'nombre'           => 'SOLO AIRE SPA',
            'rut'              => '77.618.216-8',
            'direccion'        => 'Maruri 857 Independencia, Santiago de Chile',
            'telefono'         => '+56 985394629',
            'correo'           => 'Soloairespa@gmail.com',
            'banco'            => 'BANCO SANTANDER',
            'tipo_cuenta'      => 'CUENTA CORRIENTE',
            'numero_cuenta'    => '0-000-8871366-4',
            'logo_public_path' => 'SOLO_AIRE_LOGO.png',
        ];
    }

    /**
     * Genera el archivo PDF de la cotización y lo almacena en el disco público.
     *
     * @param Cotizacion|int|string $cotizacion
     * @return string|null Ruta relativa guardada en storage/app/public o null en caso de error.
     */
    public static function generarCotizacionPDF($cotizacion): ?string
    {
        try {
            // Asegurar que el modelo cuenta con todas sus relaciones cargadas
            if (!$cotizacion instanceof Cotizacion) {
                $cotizacion = CotizacionService::getOne($cotizacion);
            } else {
                $cotizacion->loadMissing([
                    'cliente',
                    'tipoOrden',
                    'admin',
                    'detallesInventario.inventarioInsumoSaliente',
                    'detallesInventario.inventarioRepuestoSaliente',
                    'detallesInventario.inventarioRepuestoEntrante',
                    'detallesServiciosTaller.servicioTaller',
                ]);
            }

            if (!$cotizacion) {
                Log::error('PDFService::generarCotizacionPDF - Cotización no encontrada');
                return null;
            }

            $empresa = self::getDatosEmpresa();

            // Preparar logo en Base64 para máxima compatibilidad con DomPDF
            $logoBase64 = null;
            $posiblesRutasLogo = [
                storage_path('app/public/' . $empresa['logo_public_path']),
                storage_path('app/public/images/' . $empresa['logo_public_path']),
                public_path('storage/' . $empresa['logo_public_path']),
            ];

            foreach ($posiblesRutasLogo as $ruta) {
                if (file_exists($ruta)) {
                    $imageData = file_get_contents($ruta);
                    $mimeType = mime_content_type($ruta) ?: 'image/png';
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                    break;
                }
            }

            $data = [
                'cotizacion' => $cotizacion,
                'cliente'    => $cotizacion->cliente,
                'tipoOrden'  => $cotizacion->tipoOrden,
                'empresa'    => $empresa,
                'logoBase64' => $logoBase64,
            ];

            $pdf = Pdf::loadView('pdf.cotizacion', $data);
            $pdf->setPaper('letter', 'portrait');

            $filename = 'cotizacion_' . ($cotizacion->num_cotizacion ?: $cotizacion->id_cotizacion) . '_' . time() . '.pdf';
            $path = 'cotizaciones/' . $filename;

            Storage::disk('public')->put($path, $pdf->output());

            return $path;
        } catch (\Throwable $e) {
            Log::error('Error generating Cotizacion PDF: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
}