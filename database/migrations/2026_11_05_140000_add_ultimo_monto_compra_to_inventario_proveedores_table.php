<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventario_proveedores', function (Blueprint $table) {
            $table->decimal('ultimo_monto_compra', 12, 2)->default(0.00)->after('codigo_proveedor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventario_proveedores', function (Blueprint $table) {
            $table->dropColumn('ultimo_monto_compra');
        });
    }
};

