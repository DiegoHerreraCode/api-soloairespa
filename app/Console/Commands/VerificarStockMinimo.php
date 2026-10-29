<?php

namespace App\Console\Commands;

use App\Services\InventarioService;
use Illuminate\Console\Command;

class VerificarStockMinimo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventario:verificar-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica los items de inventario bajo stock minimo y notifica a los administradores';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        InventarioService::verificarYNotificarStockMinimo();
    }
}
