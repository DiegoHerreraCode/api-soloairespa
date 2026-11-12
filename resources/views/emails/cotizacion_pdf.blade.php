<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización #{{ $numCotizacion }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; color: #1f2937; margin: 0;">
    <div style="background-color: #ffffff; max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;">
        <div style="border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px;">
            <h2 style="color: #1e3a8a; margin: 0; font-size: 20px;">SOLO AIRE SpA</h2>
            <p style="margin: 3px 0 0 0; font-size: 13px; color: #6b7280;">Cotización Comercial Generada</p>
        </div>

        <p style="font-size: 14px; margin-bottom: 15px;">Estimado(a) <strong>{{ $clienteNombre }}</strong>,</p>

        <p style="font-size: 14px; line-height: 1.5; margin-bottom: 15px;">
            Adjunto a este correo electrónico encontrará el documento en formato PDF con la cotización <strong>#{{ $numCotizacion }}</strong> emitida por nuestro equipo comercial.
        </p>

        <div style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 15px; margin: 20px 0;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <tr>
                    <td style="color: #1e40af; font-weight: bold; padding: 4px 0;">N° de Cotización:</td>
                    <td style="text-align: right; font-weight: bold; color: #1e40af; padding: 4px 0;">#{{ $numCotizacion }}</td>
                </tr>
                <tr>
                    <td style="color: #374151; padding: 4px 0;">Monto Total:</td>
                    <td style="text-align: right; font-weight: bold; color: #047857; font-size: 15px; padding: 4px 0;">${{ number_format($montoTotal, 2, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <p style="font-size: 13px; line-height: 1.5; color: #4b5563; margin-bottom: 20px;">
            Si tiene alguna duda o requiere modificaciones sobre esta cotización, por favor no dude en comunicarse con nosotros respondiendo directamente a este correo o mediante nuestros canales de contacto habituales.
        </p>

        <div style="border-top: 1px solid #e5e7eb; padding-top: 15px; font-size: 12px; color: #9ca3af; text-align: center;">
            <p style="margin: 0;">Solo Aire SpA • Maruri 857 Independencia, Santiago de Chile</p>
            <p style="margin: 4px 0 0 0;">Tel: +56 985394629 • Soloairespa@gmail.com</p>
        </div>
    </div>
</body>
</html>

