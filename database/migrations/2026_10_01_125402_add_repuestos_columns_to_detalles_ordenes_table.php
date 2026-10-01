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
        Schema::table('detalles_ordenes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_repuesto_saliente')->nullable();
            $table->unsignedBigInteger('id_repuesto_entrante')->nullable();

            $table->foreign('id_repuesto_saliente')->references('id_repuesto')->on('repuestos')->onDelete('set null');
            $table->foreign('id_repuesto_entrante')->references('id_repuesto')->on('repuestos')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalles_ordenes', function (Blueprint $table) {
            $table->dropForeign(['id_repuesto_saliente']);
            $table->dropForeign(['id_repuesto_entrante']);
            $table->dropColumn(['id_repuesto_saliente', 'id_repuesto_entrante']);
        });
    }
};
