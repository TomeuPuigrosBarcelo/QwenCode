<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade'); // Null = error global del sistema
            $table->string('level')->index(); // error, warning, info, critical
            $table->text('message');
            $table->json('context')->nullable(); // {stack_trace, request_data, user_id}
            $table->timestamps();
            
            $table->index(['tenant_id', 'level']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
