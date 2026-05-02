<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Cliente (puede ser null si es guest)
            $table->string('guest_email');
            $table->string('guest_phone');
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('num_guests');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('deposit_amount', 10, 2)->default(0); // Fianza
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->json('guest_details')->nullable(); // {name, id_number, notes, special_requests}
            $table->timestamp('booked_at');
            $table->timestamps();
            
            // Índices críticos para búsquedas y anti-overbooking
            $table->index(['tenant_id', 'check_in', 'check_out']);
            $table->index(['property_id', 'check_in', 'status']);
            $table->index(['guest_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
