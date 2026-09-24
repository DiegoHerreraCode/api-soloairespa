<!DOCTYPE html>
<html>

<head>
    <title>Código de Verificación</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px;">
    <div
        style="background-color: #ffffff; max-width: 600px; margin: 0 auto; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h2 style="color: #333; text-align: center;">Recuperación de Contraseña</h2>
        <p>Hola, {{ $nombre }}</p>
        <p>Has solicitado restablecer tu contraseña. Utiliza el siguiente código de verificación:</p>
        <div style="background-color: #f0f4f8; padding: 20px; text-align: center; border-radius: 8px; margin: 25px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #007bff;">{{ $codigo }}</span>
        </div>
    </div>
</body>

</html>