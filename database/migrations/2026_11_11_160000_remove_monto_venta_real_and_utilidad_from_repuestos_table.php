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
        Schema::table('repuestos', function (Blueprint $table) {
            if (Schema::hasColumn('repuestos', 'monto_venta_real')) {
                $table->dropColumn('monto_venta_real');
            }
            if (Schema::hasColumn('repuestos', 'utilidad')) {
                $table->dropColumn('utilidad');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repuestos', function (Blueprint $table) {
            $table->decimal('monto_venta_real', 12, 2)->nullable()->after('costo_reparacion_con_ganancia');
            $table->decimal('utilidad', 12, 2)->nullable()->after('monto_venta_real');
        });
    }
};

