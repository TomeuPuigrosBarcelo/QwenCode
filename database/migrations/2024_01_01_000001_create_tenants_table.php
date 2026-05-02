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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain')->unique()->index();
            $table->string('custom_domain')->nullable()->unique();
            $table->enum('status', ['active', 'suspended', 'trial', 'cancelled'])->default('trial');
            $table->json('branding')->nullable(); // {logo_url, primary_color, secondary_color, favicon_url}
            $table->char('default_locale', 2)->default('es');
            $table->timestamp('subscribed_until')->nullable();
            $table->timestamps();
            
            // Índices para búsquedas rápidas
            $table->index(['status', 'subscribed_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
