<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // stripe, bizum_manual, bank_transfer, cash
            $table->string('provider_intent_id')->nullable(); // Payment Intent ID de Stripe
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('pending');
            $table->json('metadata')->nullable(); // Datos específicos del gateway, last4, etc.
            $table->timestamps();
            
            $table->index(['tenant_id', 'booking_id']);
            $table->index(['provider_intent_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
