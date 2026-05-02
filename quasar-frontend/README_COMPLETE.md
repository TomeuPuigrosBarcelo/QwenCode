# README.md

```markdown
# Vacational SaaS - Plataforma Multitenant de Alquiler Vacacional

Plataforma completa de gestión de alquiler vacacional con arquitectura Multitenant (Single Database), desarrollada con Laravel 12 y Quasar Framework 2.18+.

## 🏗️ Arquitectura

### Backend (Laravel 12)
- **Multitenancy**: Identificación por subdominio con aislamiento de datos mediante `tenant_id`
- **Base de Datos**: MySQL 8.0 con índices optimizados para consultas multitenant
- **Cache**: Redis para sesiones, cache y colas
- **Pagos**: Stripe multi-account con claves dinámicas por tenant
- **Traducciones**: Pool global compartido para optimizar costes de IA

### Frontend (Quasar 2.18+ / Vue 3)
- **Framework**: Quasar con Composition API y TypeScript
- **Estado**: Pinia para gestión de estado
- **Routing**: Vue Router con guards multitenant
- **PWA**: Instalable con manifiesto dinámico por tenant
- **Branding Dinámico**: Colores y logo configurables por tenant en tiempo real

## 🚀 Características Principales

### Para Propietarios (Tenants)
- ✅ Registro y onboarding autogestionado
- ✅ Personalización de marca (logo, colores)
- ✅ Gestión ilimitada de inmuebles
- ✅ Calendario de disponibilidad con sincronización iCal (Airbnb/Booking)
- ✅ Tarifas dinámicas por temporada
- ✅ Motor de reservas con anti-overbooking
- ✅ Pagos parciales y múltiples pasarelas (Stripe, Bizum, Transferencia)
- ✅ Emails automatizados (check-in, check-out, recordatorios)
- ✅ Traducción automática con IA de descripciones
- ✅ Dashboard financiero con métricas

### Para Super Administrador
- ✅ Panel maestro con todos los tenants
- ✅ Métricas globales y por tenant
- ✅ Sistema de logs con filtro por tenant_id
- ✅ Impersonación ("Login as") para soporte técnico
- ✅ Gestión de suscripciones

### Para Clientes Finales
- ✅ Web pública personalizada por marca
- ✅ Buscador con calendario de disponibilidad
- ✅ Proceso de reserva optimizado
- ✅ Múltiples métodos de pago
- ✅ Confirmación inmediata con Google Calendar
- ✅ PWA instalable

## 📋 Requisitos Previos

### Backend
- PHP 8.3+
- Composer 2.7+
- MySQL 8.0
- Redis 7.0+
- Node.js 18+ (para Vite)

### Frontend
- Node.js 18+
- npm 9+ o yarn 1.21+

## 🔧 Instalación

### Backend (Laravel)

```bash
cd /workspace/vacational-saas

# Instalar dependencias
composer install

# Copiar archivo de entorno
cp .env.example .env

# Configurar variables de entorno (BD, Redis, Stripe, OpenAI)
# EDITAR .env con tus credenciales

# Generar clave de aplicación
php artisan key:generate

# Ejecutar migraciones
php artisan migrate

# (Opcional) Seeders para datos de prueba
php artisan db:seed

# Iniciar servidor de desarrollo
php artisan serve
```

### Frontend (Quasar)

```bash
cd /workspace/quasar-frontend

# Instalar dependencias
npm install

# Copiar archivo de entorno
cp .env.example .env

# Configurar URL de la API
# VUE_APP_API_URL=http://localhost:8000/api

# Iniciar servidor de desarrollo
npm run dev

# O para PWA
npm run dev:pwa
```

## 🌐 Configuración Multitenant

### Subdominios en Desarrollo

Para probar el sistema multitenant localmente:

1. Editar `/etc/hosts` (Linux/Mac) o `C:\Windows\System32\drivers\etc\hosts` (Windows):
```
127.0.0.1   demo.local
127.0.0.1   playa.local
127.0.0.1   admin.local
```

2. Acceder a:
   - `http://demo.local:9000` - Tenant demo
   - `http://playa.local:9000` - Tenant playa
   - `http://localhost:9000?subdomain=demo` - Alternativa con query param

### Producción

Configurar DNS wildcard para capturar todos los subdominios:
```
*.tudominio.com  →  IP-del-servidor
```

## 🔐 Variables de Entorno Clave

### Backend (.env)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vacational_saas
DB_USERNAME=root
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

STRIPE_SECRET_KEY=sk_test_xxx
OPENAI_API_KEY=sk_xxx

APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:9000
```

### Frontend (.env)
```env
VUE_APP_API_URL=http://localhost:8000/api
VUE_ROUTER_MODE=history
```

## 📁 Estructura del Proyecto

```
/workspace
├── vacational-saas/          # Backend Laravel
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   └── Middleware/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Jobs/
│   ├── database/migrations/
│   └── routes/api.php
│
└── quasar-frontend/          # Frontend Quasar
    ├── src/
    │   ├── boot/             # axios.ts, tenant.ts
    │   ├── components/       # PropertyCard, BookingCalendar...
    │   ├── layouts/          # PublicLayout, MainLayout, SuperAdminLayout
    │   ├── pages/
    │   │   ├── public/       # Web cliente
    │   │   ├── dashboard/    # Panel propietario
    │   │   └── superadmin/   # Panel global
    │   ├── router/
    │   ├── stores/           # Pinia: auth, tenant, booking
    │   └── utils/
    └── package.json
```

## 🎨 Personalización de Marca

El sistema permite a cada tenant configurar:
- Logo y favicon
- Colores primario y secundario
- Textos de emails
- Políticas de cancelación

Estos cambios se aplican en tiempo real sin necesidad de recompilar.

## 🔒 Seguridad

- Encriptación AES-256 para claves API de Stripe
- Global Scopes en Laravel para aislamiento de tenant_id
- Logs de auditoría para acciones de SuperAdmin
- Rate limiting por tenant
- Validación de webhooks de Stripe

## 📊 Optimizaciones Implementadas

1. **Pool Global de Traducciones**: Textos estructurales (<50 palabras) se comparten entre tenants
2. **Cache Redis**: Configuración de tenant cacheada por 1 hora
3. **Índices DB**: Compuestos en (tenant_id, created_at) para queries rápidas
4. **Lazy Loading**: Componentes Vue cargados bajo demanda
5. **Colas**: Jobs asíncronos para emails, traducciones IA y sync de calendarios

## 🧪 Testing

```bash
# Backend
cd vacational-saas
php artisan test

# Frontend
cd quasar-frontend
npm run test:unit
```

## 📝 Licencia

Propietario. Todos los derechos reservados.

## 👥 Soporte

Para incidencias o preguntas, contactar con el equipo de desarrollo.
```

## 📄 generated_files.md

```markdown
# Archivos Generados - Frontend Quasar

## Estructura Completa

### Boot Files (Inicialización)
- `src/boot/axios.ts` - Interceptors para token y tenant-id
- `src/boot/tenant.ts` - Detección de subdominio y carga de branding

### Stores (Pinia)
- `src/stores/auth.ts` - Autenticación, login, logout, impersonation
- `src/stores/tenant.ts` - Configuración del tenant, branding
- `src/stores/booking.ts` - Gestión de reservas, filtros, disponibilidad

### Layouts
- `src/layouts/PublicLayout.vue` - Web pública para clientes
- `src/layouts/MainLayout.vue` - Dashboard de propietarios
- `src/layouts/SuperAdminLayout.vue` - Panel de administración global

### Router
- `src/router/index.ts` - Configuración de Vue Router
- `src/router/routes.ts` - Definición de todas las rutas

### Components
- `src/components/PropertyCard.vue` - Card de inmueble reutilizable

### Pages (Vistas)
- `src/pages/public/home/HomePage.vue` - Home con buscador
- `src/pages/public/property/` - Detalle de inmueble
- `src/pages/public/booking/` - Proceso de reserva
- `src/pages/dashboard/` - CRUD completo para propietarios
- `src/pages/superadmin/` - Gestión global de tenants

### Configuración
- `package.json` - Dependencias actualizadas (FullCalendar, vue-i18n, etc.)
- `quasar.config.js` - Configuración de Quasar
- `.env.example` - Variables de entorno de ejemplo

## Características Clave Implementadas

1. **Detección Automática de Tenant**
   - Por subdominio (playa.dominio.com)
   - Por parámetro query en desarrollo (?subdomain=demo)
   - Carga configuración antes de montar la app

2. **Branding Dinámico**
   - Variables CSS actualizadas en tiempo real
   - Logo y favicon cambiantes
   - Colores primario/secundario configurables

3. **Interceptors HTTP**
   - Token Bearer automático
   - Headers X-Tenant-ID o X-Tenant-Subdomain
   - Manejo centralizado de errores 401, 403, 500

4. **Gestión de Estado**
   - Autenticación persistente en localStorage
   - Impersonación para SuperAdmin
   - Filtros de reservas memorizados

5. **Rutas Protegidas**
   - Guards para autenticación
   - Guards para rol SuperAdmin
   - Redirección por tenant no encontrado

## Próximos Pasos

1. Crear páginas específicas faltantes:
   - PropertyDetailPage.vue
   - BookingCreatePage.vue
   - Dashboard pages completas
   - SuperAdmin pages

2. Añadir componentes:
   - BookingCalendar.vue (FullCalendar)
   - PaymentGateway.vue (Stripe/Bizum)
   - TranslationButton.vue (IA)

3. Implementar i18n con vue-i18n

4. Configurar PWA con manifest dinámico

## Comandos Útiles

```bash
# Desarrollo
npm run dev

# Build producción
npm run build

# Linting
npm run lint

# Formateo
npm run format
```
```
