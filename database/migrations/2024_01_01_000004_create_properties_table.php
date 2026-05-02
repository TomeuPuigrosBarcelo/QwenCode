<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('reference_code')->unique();
            $table->text('address');
            $table->string('google_maps_place_id')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('area_house_m2', 8, 2)->nullable();
            $table->decimal('area_land_m2', 8, 2)->nullable();
            $table->integer('min_stay_default')->default(1);
            $table->time('check_in_time')->default('15:00');
            $table->time('check_out_time')->default('11:00');
            $table->json('policies_config')->nullable(); // {cancellation: flexible|moderate|strict, deposit_amount, rules}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
