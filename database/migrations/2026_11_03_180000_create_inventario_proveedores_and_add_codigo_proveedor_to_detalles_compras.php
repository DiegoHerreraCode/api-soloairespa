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
        // 1. Crear tabla inventario_proveedores sin auto-increment y sin onDelete cascade ni indice unico compuesto
        Schema::create('inventario_proveedores', function (Blueprint $table) {
            $table->unsignedBigInteger('id_inventario_proveedor')->primary();
            $table->unsignedBigInteger('id_proveedor');
            $table->unsignedBigInteger('id_inventario');
            $table->string('codigo_proveedor', 100);

            $table->foreign('id_proveedor')->references('id_proveedor')->on('proveedores');
            $table->foreign('id_inventario')->references('id_inventario')->on('inventario');
        });

        // 2. Agregar codigo_proveedor en detalles_compras
        Schema::table('detalles_compras', function (Blueprint $table) {
            $table->string('codigo_proveedor', 100)->nullable()->after('id_inventario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalles_compras', function (Blueprint $table) {
            $table->dropColumn('codigo_proveedor');
        });

        Schema::dropIfExists('inventario_proveedores');
    }
};
