<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$ruta = 'C:\proyectos-web\backend\api-soloairespa\storage\app\public\cotizaciones\cotizacion_COT-00079_1794520724.pdf';
echo "Ruta: $ruta\n";
echo "Filesize: " . filesize($ruta) . "\n";

$mailable = new \App\Mail\Mailer(
    'Prueba Inspect',
    'emails.cotizacion_pdf',
    ['clienteNombre' => 'Test', 'numCotizacion' => 'COT-79', 'montoTotal' => 100],
    [$ruta]
);

$mailer = app('mailer');
$email = $mailer->render($mailable); // o build

// Construir Symfony Email
$refMethod = new ReflectionMethod($mailable, 'buildView');
// Vamos a construirlo enviandolo a array o usando Symfony
$symfonyEmail = (new \Symfony\Component\Mime\Email());
foreach ($mailable->attachments() as $att) {
    echo "Attachment type: " . get_class($att) . "\n";
}

// Simulemos lo que hace Laravel al preparar el Mailable
$closure = function() use ($mailable, $ruta) {
    $mail = new \App\Mail\Mailer('Test', 'emails.cotizacion_pdf', ['clienteNombre' => 'Test', 'numCotizacion' => 'COT-79', 'montoTotal' => 100], [$ruta]);
    $sent = \Illuminate\Support\Facades\Mail::to('test@test.com')->send($mail);
};


