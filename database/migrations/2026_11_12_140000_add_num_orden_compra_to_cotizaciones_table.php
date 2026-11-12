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
            if (!Schema::hasColumn('cotizaciones', 'num_orden_compra')) {
                $table->string('num_orden_compra')->nullable()->after('num_cotizacion');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('cotizaciones', 'num_orden_compra')) {
                $table->dropColumn('num_orden_compra');
            }
        });
    }
};

