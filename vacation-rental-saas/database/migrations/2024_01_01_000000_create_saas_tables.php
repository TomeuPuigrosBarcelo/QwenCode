<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. TENANTS
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain')->unique(); // ej: "marina-playa" -> marina-playa.tudominio.com
            $table->string('custom_domain')->nullable()->unique();
            $table->enum('status', ['active', 'suspended', 'trial'])->default('trial');
            $table->json('branding')->nullable(); // { "logo_url": "...", "primary_color": "#FF0000" }
            $table->string('default_locale')->default('es');
            $table->timestamp('subscribed_until')->nullable();
            $table->timestamps();
            
            $table->index(['subdomain', 'status']);
        });

        // 2. USERS
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // Null para SuperAdmin
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'owner', 'staff'])->default('owner');
            $table->boolean('is_super_admin')->default(false);
            $table->json('preferences')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index(['tenant_id', 'role']);
        });

        // 3. PROPERTIES
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference_code')->unique(); // Código interno único
            $table->string('address');
            $table->string('google_maps_place_id')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('area_house_m2', 8, 2)->nullable();
            $table->decimal('area_land_m2', 8, 2)->nullable();
            $table->integer('min_stay_default')->default(1);
            $table->time('check_in_time')->default('15:00:00');
            $table->time('check_out_time')->default('11:00:00');
            $table->json('policies_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'is_active']);
        });

        // 4. TRANSLATIONS (Centralizada + Pool Global)
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // Null si es global compartida
            $table->string('entity_type'); // 'property', 'email_template', 'amenity'
            $table->unsignedBigInteger('entity_id'); // ID de la tabla padre
            $table->string('field_key'); // 'description', 'name', 'subject'
            $table->string('locale'); // 'es', 'en', 'fr'
            $table->text('value');
            $table->boolean('is_machine_translated')->default(false);
            $table->boolean('is_global')->default(false); // TRUE si es compartida entre tenants
            $table->string('content_hash')->nullable(); // Hash del texto original para buscar duplicados globales
            
            $table->timestamps();
            
            // Índice único para evitar duplicados por entidad
            $table->unique(['entity_type', 'entity_id', 'field_key', 'locale'], 'uk_entity_locale');
            // Índice para buscar traducciones globales rápidas
            $table->index(['is_global', 'content_hash', 'locale']);
        });

        // 5. AI_TRANSLATION_JOBS
        Schema::create('ai_translation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('source_lang');
            $table->string('target_lang');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('original_content');
            $table->text('translated_content')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
        });

        // 6. PROPERTY_IMAGES
        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->integer('sort_order')->default(0);
            $table->string('alt_text_key')->nullable(); // Clave para buscar en translations
            $table->timestamps();
        });

        // 7. SEASONAL_RATES
        Schema::create('seasonal_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price_per_night', 10, 2);
            $table->integer('min_stay_override')->nullable();
            $table->timestamps();
            
            $table->index(['property_id', 'start_date', 'end_date']);
        });

        // 8. BOOKING_RULES (Bloqueos, Mantenimiento, Reservas Externas)
        Schema::create('booking_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete(); // Null = bloqueo global
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('type', ['blocked', 'maintenance', 'reserved_external']);
            $table->string('source')->default('manual'); // manual, airbnb_ical, booking_xml
            $table->string('external_id')->nullable();
            $table->timestamps();
            
            $table->index(['property_id', 'start_date', 'end_date']);
        });

        // 9. BOOKINGS
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Cliente registrado (opcional)
            $table->string('guest_email');
            $table->string('guest_phone');
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('num_guests');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->string('currency')->default('EUR');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->json('guest_details')->nullable();
            $table->timestamp('booked_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['tenant_id', 'check_in', 'check_out']);
            $table->index(['property_id', 'check_in', 'check_out']);
        });

        // 10. PAYMENTS
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // stripe, bizum_manual, bank_transfer, cash
            $table->string('provider_intent_id')->nullable(); // Payment Intent ID de Stripe
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('EUR');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['booking_id', 'status']);
        });

        // 11. TENANT_STRIPE_CONFIG
        Schema::create('tenant_stripe_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_type')->default('direct'); // direct (usando sus keys)
            $table->text('pk_encrypted');
            $table->text('sk_encrypted');
            $table->text('whsec_encrypted')->nullable();
            $table->boolean('test_mode')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });

        // 12. EMAIL_TEMPLATES (Solo estructura, contenido en translations)
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key')->unique(); // check_in, check_out, welcome...
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tenant_id', 'key']);
        });
        
        // 13. AUDIT_LOGS & SYSTEM_LOGS
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level'); // error, warning, info
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'level', 'created_at']);
        });
    }

    public function down(): void
    {
        // Orden inverso aproximado para drop
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('tenant_stripe_configs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_rules');
        Schema::dropIfExists('seasonal_rates');
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('ai_translation_jobs');
        Schema::dropIfExists('translations');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};
