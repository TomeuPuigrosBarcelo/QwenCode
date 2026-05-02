# API de Gestión de Alquiler Vacacional Multitenant

## Descripción
API RESTful completa para una plataforma SaaS de gestión de alquiler vacacional con arquitectura Multitenant (Single Database). Construida con Laravel 12 y PHP 8.3+.

## Características Principales

### 🏢 Multitenancy
- Identificación por subdominio o dominio personalizado
- Aislamiento de datos mediante Global Scopes
- Soporte para SuperAdmin con impersonation

### 💳 Pagos Multi-Account
- Stripe dinámico por tenant (claves encriptadas)
- Webhooks enrutados por tenant_id
- Soporte para pagos parciales

### 🌍 Traducciones Centralizadas
- Tabla única de traducciones compartida
- Pool global para textos estructurales
- Traducción asíncrona con IA (OpenAI)

### 📅 Anti-Overbooking
- Locking pesimista en reservas
- Sincronización iCal (Airbnb/Booking)
- Reglas de bloqueo configurables

## Estructura del Proyecto

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── PropertyController.php
│   │   ├── BookingController.php
│   │   ├── PaymentController.php
│   │   ├── SeasonalRateController.php
│   │   ├── BookingRuleController.php
│   │   ├── TranslationController.php
│   │   ├── DashboardController.php
│   │   ├── StripeConfigController.php
│   │   ├── EmailTemplateController.php
│   │   ├── ICalSyncController.php
│   │   ├── WebhookController.php
│   │   └── SuperAdminController.php
│   ├── Middleware/
│   │   └── IdentifyTenant.php
│   └── Requests/
│       ├── RegisterTenantRequest.php
│       ├── LoginRequest.php
│       └── StoreBookingRequest.php
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   ├── Property.php
│   ├── Booking.php
│   ├── Payment.php
│   ├── SeasonalRate.php
│   ├── BookingRule.php
│   ├── Translation.php
│   ├── EmailTemplate.php
│   ├── ICalSync.php
│   ├── TenantStripeConfig.php
│   ├── AuditLog.php
│   └── SystemLog.php
├── Services/
│   ├── StripeServiceFactory.php
│   └── PricingService.php
├── Jobs/
│   └── ProcessAiTranslation.php
└── Models/Scopes/
    └── TenantScope.php
```

## Instalación

### Requisitos
- PHP 8.3+
- MySQL 8.0
- Redis
- Composer

### Pasos

1. **Clonar e instalar dependencias**
```bash
composer install
```

2. **Configurar variables de entorno**
```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vacation_rental
DB_USERNAME=root
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

OPENAI_API_KEY=sk-...
STRIPE_VERSION=2024-06-20
```

3. **Ejecutar migraciones**
```bash
php artisan migrate
```

4. **Configurar colas**
```bash
php artisan queue:table
php artisan migrate
```

5. **Crear SuperAdmin inicial**
```bash
php artisan tinker
>>> App\Models\User::create([
    'email' => 'admin@platform.com',
    'password' => Hash::make('password'),
    'is_super_admin' => true,
    'role' => 'super_admin'
]);
```

## Endpoints API

### Públicos (sin autenticación)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/v1/register` | Registro de nuevo tenant |
| POST | `/api/v1/login` | Login usuario |
| GET | `/api/v1/public/properties` | Listar propiedades (web cliente) |
| GET | `/api/v1/public/properties/{id}` | Detalle propiedad |
| POST | `/api/v1/public/bookings/availability` | Verificar disponibilidad |
| POST | `/api/v1/stripe/webhook` | Webhook Stripe |

### Protegidos (requieren auth + tenant)

#### Dashboard
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/dashboard` | Dashboard principal |
| GET | `/api/v1/dashboard/stats` | Estadísticas detalladas |

#### Propiedades
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/properties` | Listar propiedades |
| POST | `/api/v1/properties` | Crear propiedad |
| GET | `/api/v1/properties/{id}` | Detalle propiedad |
| PUT | `/api/v1/properties/{id}` | Actualizar propiedad |
| DELETE | `/api/v1/properties/{id}` | Eliminar propiedad |
| POST | `/api/v1/properties/{id}/images` | Subir imágenes |
| POST | `/api/v1/properties/{id}/translate` | Traducir con IA |

#### Tarifas
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/properties/{id}/rates` | Listar tarifas |
| POST | `/api/v1/properties/rates` | Crear tarifa |
| PUT | `/api/v1/rates/{id}` | Actualizar tarifa |
| DELETE | `/api/v1/rates/{id}` | Eliminar tarifa |
| GET | `/api/v1/properties/{id}/calendar` | Calendario precios |

#### Reservas
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/bookings` | Listar reservas |
| POST | `/api/v1/bookings` | Crear reserva |
| POST | `/api/v1/bookings/check-availability` | Verificar disponibilidad |
| POST | `/api/v1/bookings/{id}/cancel` | Cancelar reserva |

#### Pagos
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/payments/stats` | Estadísticas pagos |
| POST | `/api/v1/payments/{id}/refund` | Reembolsar pago |
| POST | `/api/v1/payments/{id}/confirm-manual` | Confirmar pago manual |

#### Configuración Stripe
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/stripe/config` | Obtener config |
| POST | `/api/v1/stripe/config` | Guardar config |
| POST | `/api/v1/stripe/config/validate` | Validar credenciales |

#### iCal Sync
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/ical-syncs` | Listar syncs |
| POST | `/api/v1/ical-syncs` | Crear sync |
| POST | `/api/v1/ical-syncs/{id}/sync-now` | Sincronizar ahora |

### SuperAdmin

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/admin/dashboard` | Dashboard global |
| GET | `/api/v1/admin/tenants` | Listar tenants |
| POST | `/api/v1/admin/tenants/{id}/impersonate` | Login as |
| POST | `/api/v1/admin/tenants/{id}/toggle-status` | Suspender/Activar |
| GET | `/api/v1/admin/logs` | Logs sistema |
| GET | `/api/v1/admin/audit-logs` | Auditoría |

## Seguridad

### Encriptación
- Claves Stripe encriptadas con `Crypt::encryptString()`
- Las claves nunca se exponen en respuestas API

### Aislamiento de Datos
- Global Scope aplica `WHERE tenant_id = X` automáticamente
- SuperAdmin puede bypass con auditoría

### Webhooks
- Firma verificada con secret por tenant
- tenant_id extraído de metadata del evento

## Colas y Jobs

### ProcessAiTranslation
Procesa traducciones asíncronas con OpenAI:
- Reutiliza traducciones globales existentes
- Reintentos automáticos en fallos
- Detecta textos estructurales (<50 palabras) para pool global

```bash
php artisan queue:work --queue=translations
```

## Comandos Útiles

```bash
# Ver logs de tenant específico
php artisan tinker
>>> App\Models\SystemLog::where('tenant_id', 1)->latest()->get()

# Forzar sincronización iCal
php artisan tinker
>>> App\Models\ICalSync::find(1)->property->syncICal()

# Limpiar traducciones huérfanas
php artisan tinker
>>> App\Models\Translation::whereNull('entity_id')->delete()
```

## Testing

```bash
# Ejecutar tests
php artisan test

# Tests con cobertura
php artisan test --coverage

# Test específico
php artisan test --filter=BookingTest
```

## Licencia
Propietario - Todos los derechos reservados
