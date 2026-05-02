<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->nullable()->constrained()->onDelete('cascade'); // Null = regla global
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('type', ['blocked', 'maintenance', 'reserved_pending', 'reserved_confirmed', 'reserved_external']);
            $table->string('source')->default('manual'); // manual, airbnb_ical, booking_xml, web_direct
            $table->string('external_id')->nullable(); // ID en Airbnb/Booking para sync
            $table->timestamps();
            
            // Índices críticos para verificación de disponibilidad (ANTI-OVERBOOKING)
            $table->index(['property_id', 'start_date', 'end_date', 'type']);
            $table->index(['tenant_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rules');
    }
};
