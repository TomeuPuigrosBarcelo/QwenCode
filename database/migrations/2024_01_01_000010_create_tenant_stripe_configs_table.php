<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_stripe_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->onDelete('cascade');
            $table->string('account_type')->default('direct'); // direct, connect_standard, connect_express
            $table->text('pk_encrypted'); // Clave pública encriptada
            $table->text('sk_encrypted'); // Clave secreta encriptada
            $table->text('whsec_encrypted')->nullable(); // Webhook secret encriptado
            $table->boolean('test_mode')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_stripe_configs');
    }
};
