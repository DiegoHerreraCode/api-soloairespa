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
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'repuestos_entrantes_recambio')) {
                $table->jsonb('repuestos_entrantes_recambio')->nullable()->after('repuestos_entrantes_reparacion');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('cotizaciones', 'repuestos_entrantes_recambio')) {
                $table->dropColumn('repuestos_entrantes_recambio');
            }
        });
    }
};

