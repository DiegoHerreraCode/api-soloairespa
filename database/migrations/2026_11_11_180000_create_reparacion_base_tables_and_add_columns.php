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
        // 1. Agregar monto_venta_reparacion_base a inventario
        Schema::table('inventario', function (Blueprint $table) {
            if (!Schema::hasColumn('inventario', 'monto_venta_reparacion_base')) {
                $table->decimal('monto_venta_reparacion_base', 12, 2)->nullable()->after('monto_venta_unitario');
            }
        });

        // 2. Agregar monto_venta_unitario a servicios_taller
        Schema::table('servicios_taller', function (Blueprint $table) {
            if (!Schema::hasColumn('servicios_taller', 'monto_venta_unitario')) {
                $table->decimal('monto_venta_unitario', 12, 2)->default(0.00)->after('porcentaje_ganancia');
            }
        });

        // 3. Crear tabla inventario_reparacion_insumos
        if (!Schema::hasTable('inventario_reparacion_insumos')) {
            Schema::create('inventario_reparacion_insumos', function (Blueprint $table) {
                $table->id('id_inventario_reparacion_insumo');
                $table->unsignedBigInteger('id_inventario'); // Compresor o válvula
                $table->unsignedBigInteger('id_insumo');     // Insumo utilizado
                $table->integer('cantidad')->default(1);

                $table->foreign('id_inventario')->references('id_inventario')->on('inventario')->onDelete('cascade');
                $table->foreign('id_insumo')->references('id_inventario')->on('inventario')->onDelete('cascade');
            });
        }

        // 4. Crear tabla inventario_reparacion_servicios
        if (!Schema::hasTable('inventario_reparacion_servicios')) {
            Schema::create('inventario_reparacion_servicios', function (Blueprint $table) {
                $table->id('id_inventario_reparacion_servicio');
                $table->unsignedBigInteger('id_inventario'); // Compresor o válvula
                $table->unsignedBigInteger('id_servicio_taller'); // Servicio de taller
                $table->integer('cantidad')->default(1);

                $table->foreign('id_inventario')->references('id_inventario')->on('inventario')->onDelete('cascade');
                $table->foreign('id_servicio_taller')->references('id_servicio_taller')->on('servicios_taller')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventario_reparacion_servicios');
        Schema::dropIfExists('inventario_reparacion_insumos');

        Schema::table('servicios_taller', function (Blueprint $table) {
            if (Schema::hasColumn('servicios_taller', 'monto_venta_unitario')) {
                $table->dropColumn('monto_venta_unitario');
            }
        });

        Schema::table('inventario', function (Blueprint $table) {
            if (Schema::hasColumn('inventario', 'monto_venta_reparacion_base')) {
                $table->dropColumn('monto_venta_reparacion_base');
            }
        });
    }
};

