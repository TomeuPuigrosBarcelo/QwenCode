<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasonal_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price_per_night', 10, 2);
            $table->integer('min_stay_override')->nullable(); // Null usa el default del inmueble
            $table->timestamps();
            
            // Índices críticos para búsquedas de rango de fechas
            $table->index(['property_id', 'start_date', 'end_date']);
            $table->index(['tenant_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonal_rates');
    }
};
