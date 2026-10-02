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
        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->string('estado', 20)->default('completado')->after('monto');
            $table->timestamp('fecha_anulacion')->nullable()->after('comprobante');
            $table->unsignedBigInteger('id_admin_anulacion')->nullable()->after('fecha_anulacion');
            $table->string('motivo_anulacion', 250)->nullable()->after('id_admin_anulacion');

            $table->foreign('id_admin_anulacion')->references('id_admin')->on('admins')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->dropForeign(['id_admin_anulacion']);
            $table->dropColumn(['estado', 'fecha_anulacion', 'id_admin_anulacion', 'motivo_anulacion']);
        });
    }
};
