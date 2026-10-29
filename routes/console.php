<?php

// use Illuminate\Foundation\Inspiring;
// use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');

Schedule::command('cotizaciones:actualizar-vencidas')->dailyAt('00:00');

Schedule::command('inventario:verificar-stock')->weeklyOn(1, '08:00');
