<!DOCTYPE html>
<html>
<head>
    <title>Bienvenido</title>
</head>
<body>
    <h1>Bienvenido a nuestra plataforma: {{ $nombre }}</h1>
    <p>Gracias por registrarte en nuestra plataforma. Ahora puedes disfrutar de todas las funcionalidades que ofrecemos.</p>
    <p>Para iniciar sesión, utiliza los siguientes datos:</p>
    <p>Usuario: {{ $email }}</p>
    <p>Contraseña: {{ $password }}</p>
</body>
</html>
