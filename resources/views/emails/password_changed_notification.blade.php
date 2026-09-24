<!DOCTYPE html>
<html>

<head>
    <title>Contraseña Actualizada</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px;">
    <div
        style="background-color: #ffffff; max-width: 600px; margin: 0 auto; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h2 style="color: #28a745; text-align: center;">¡Contraseña Actualizada!</h2>
        <p>Hola, {{ $nombre }}</p>
        <p>Te informamos que la contraseña de tu cuenta ha sido actualizada exitosamente.</p>
        <p>Si no realizaste este cambio, por favor ponte en contacto con nuestro equipo de soporte de inmediato.</p>
        <p>Para iniciar sesión, utiliza los siguientes datos:</p>
        <p>Correo electrónico: <strong>{{ $email }}</strong></p>
        <p>Contraseña: <strong>{{ $password }}</strong></p>
        <p style="margin-top: 30px; font-size: 14px; color: #666;">Gracias por usar nuestros servicios.</p>
    </div>
</body>

</html>