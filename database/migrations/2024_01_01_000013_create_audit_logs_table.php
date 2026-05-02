<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade'); // Null = acción global (SuperAdmin)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action'); // login_as, config_change, booking_create, property_update
            $table->json('metadata')->nullable(); // {before, after, ip, user_agent}
            $table->string('ip_address', 45);
            $table->timestamps();
            
            $table->index(['tenant_id', 'action']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
