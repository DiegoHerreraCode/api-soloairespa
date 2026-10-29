<?php

namespace App\Console\Commands;

use App\Services\CotizacionService;
use Illuminate\Console\Command;

class ActualizarCotizacionesVencidas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cotizaciones:actualizar-vencidas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza las cotizaciones pendientes cuya fecha de vencimiento ya expiro a estado rechazada';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        CotizacionService::actualizarCotizacionesVencidas();
    }
}
