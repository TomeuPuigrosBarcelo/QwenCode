# Vacational SaaS - Frontend Quasar

Aplicación frontend multitenant para gestión de alquiler vacacional construida con **Quasar Framework 2.18+**, **Vue 3**, **Pinia** y **Vue Router**.

## 🚀 Características Principales

### Multitenancy
- Detección automática por subdominio
- Branding dinámico (colores, logo, favicon)
- Aislamiento completo de datos por tenant
- Soporte para dominios personalizados

### Web Pública
- Buscador avanzado con calendario de disponibilidad
- Listado de propiedades con filtros por zona
- Página de detalle con galería y descripción multi-idioma
- Motor de reservas con pasarela de pago dinámica

### Dashboard del Propietario
- Calendario de reservas interactivo
- Gestión completa de inmuebles
- Configuración de tarifas por temporada
- Personalización de marca (colores, logo)
- Plantillas de email automatizadas
- Integración con Stripe multi-account

### Panel SuperAdmin
- Vista global de todos los tenants
- Métricas y logs centralizados
- Sistema de impersonación (login as)
- Gestión de usuarios globales

## 📋 Requisitos Previos

- Node.js 18+ 
- npm o yarn
- Backend Laravel 12 ejecutándose en `http://localhost:8000`

## 🛠️ Instalación

1. **Clonar el repositorio**
```bash
git clone <tu-repositorio>
cd quasar-frontend
```

2. **Instalar dependencias**
```bash
npm install
```

3. **Configurar variables de entorno**
```bash
cp .env.example .env
```

Editar `.env` con tus configuraciones:
```env
VITE_API_URL=http://localhost:8000/api
VITE_DEFAULT_SUBDOMAIN=demo
```

4. **Iniciar servidor de desarrollo**
```bash
npm run dev
```

La aplicación estará disponible en `http://localhost:9000`

## 🏗️ Estructura del Proyecto

```
quasar-frontend/
├── src/
│   ├── boot/              # Archivos de inicialización
│   │   ├── axios.js       # Configuración de Axios con interceptores
│   │   └── tenant.js      # Detección y carga de configuración del tenant
│   ├── components/        # Componentes reutilizables
│   │   ├── booking/       # Componentes de reservas
│   │   ├── payment/       # Componentes de pago
│   │   └── property/      # Componentes de propiedades
│   ├── layouts/           # Layouts principales
│   │   ├── PublicLayout.vue       # Web pública
│   │   ├── MainLayout.vue         # Dashboard propietario
│   │   └── SuperAdminLayout.vue   # Panel SuperAdmin
│   ├── pages/             # Vistas de la aplicación
│   │   ├── public/        # Páginas públicas
│   │   ├── dashboard/     # Dashboard del propietario
│   │   └── super-admin/   # Panel SuperAdmin
│   ├── router/            # Configuración de rutas
│   ├── stores/            # Stores de Pinia
│   │   ├── auth.js        # Autenticación
│   │   ├── tenant.js      # Configuración del tenant
│   │   └── booking.js     # Gestión de reservas
│   └── App.vue
├── public/                # Assets estáticos
├── quasar.config.js       # Configuración de Quasar
└── package.json
```

## 🔑 Funcionalidades Clave

### Detección de Tenant
El sistema detecta automáticamente el tenant basado en el subdominio:
- `cliente1.tuapp.com` → carga configuración de "cliente1"
- `cliente2.tuapp.com` → carga configuración de "cliente2"
- localhost → usa `VITE_DEFAULT_SUBDOMAIN`

### Branding Dinámico
Los colores, logo y favicon se aplican dinámicamente mediante variables CSS inyectadas desde el store de Pinia.

### Interceptors de Axios
Todas las peticiones incluyen automáticamente:
- Token de autenticación (si existe)
- Header `X-Tenant-ID` para identificación del tenant

### Traducciones
Soporte multi-idioma con cambio dinámico mediante la API del backend.

## 📦 Comandos Disponibles

```bash
# Desarrollo
npm run dev

# Build para producción
npm run build

# Linting
npm run lint

# Formateo de código
npm run format
```

## 🔐 Autenticación

El sistema maneja tres roles principales:
- **Propietario**: Acceso al dashboard de su tenant
- **Staff**: Acceso limitado según permisos
- **SuperAdmin**: Acceso global a todos los tenants con capacidad de impersonación

## 🌐 PWA

La aplicación es totalmente instalable como PWA con:
- Service worker para caché offline
- Manifiesto dinámico generado según el tenant
- Soporte para instalación en móviles y desktop

## 🤝 Integración con Backend

La API debe estar disponible en `VITE_API_URL` y seguir la especificación RESTful definida en el backend Laravel.

Endpoints principales:
- `/public/tenant/{identifier}` - Obtener configuración del tenant
- `/auth/login`, `/auth/register` - Autenticación
- `/properties` - CRUD de propiedades
- `/bookings` - Gestión de reservas
- `/payments` - Procesamiento de pagos

## 📄 Licencia

MIT License

---

Desarrollado con ❤️ usando Quasar Framework y Vue 3
