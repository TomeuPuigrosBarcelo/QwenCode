<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ical_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->text('url_import'); // URL iCal de Airbnb/Booking
            $table->string('last_sync_hash')->nullable(); // Para detectar cambios sin reparsear todo
            $table->timestamp('last_successful_sync')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['property_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ical_syncs');
    }
};
