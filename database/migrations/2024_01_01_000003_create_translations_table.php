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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade'); // NULL = global pool
            $table->string('entity_type')->index(); // property, email_template, policy, structural, image_alt
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('field_key'); // name, description, subject, body, content
            $table->char('locale', 2); // es, en, fr, de
            $table->text('value');
            $table->boolean('is_machine_translated')->default(false);
            $table->timestamps();
            
            // Índice único para evitar duplicados
            $table->unique(['tenant_id', 'entity_type', 'entity_id', 'field_key', 'locale'], 
                          'uk_entity_locale_key');
            
            // Índice especial para búsquedas en pool global
            $table->index(['entity_type', 'field_key', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
