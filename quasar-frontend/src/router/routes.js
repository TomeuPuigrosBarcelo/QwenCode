const routes = [
  // Rutas Públicas
  {
    path: '/',
    component: () => import('layouts/PublicLayout.vue'),
    children: [
      { path: '', name: 'home', component: () => import('pages/public/home/HomePage.vue') },
      { path: 'property/:id', name: 'property-detail', component: () => import('pages/public/property-detail/PropertyDetailPage.vue') },
      { path: 'checkout/:bookingId', name: 'checkout', component: () => import('pages/public/checkout/CheckoutPage.vue') }
    ]
  },

  // Rutas de Autenticación
  {
    path: '/auth',
    component: () => import('layouts/AuthLayout.vue'),
    children: [
      { path: 'login', name: 'login', component: () => import('pages/auth/LoginPage.vue') },
      { path: 'register', name: 'register', component: () => import('pages/auth/RegisterPage.vue') }
    ]
  },

  // Dashboard del Propietario
  {
    path: '/dashboard',
    component: () => import('layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('pages/dashboard/DashboardPage.vue') },
      
      // Propiedades
      { path: 'properties', name: 'properties-list', component: () => import('pages/dashboard/properties/PropertiesListPage.vue') },
      { path: 'properties/create', name: 'property-create', component: () => import('pages/dashboard/properties/PropertyFormPage.vue') },
      { path: 'properties/:id/edit', name: 'property-edit', component: () => import('pages/dashboard/properties/PropertyFormPage.vue') },
      
      // Reservas
      { path: 'bookings', name: 'bookings-list', component: () => import('pages/dashboard/bookings/BookingsListPage.vue') },
      { path: 'bookings/:id', name: 'booking-detail', component: () => import('pages/dashboard/bookings/BookingDetailPage.vue') },
      
      // Tarifas
      { path: 'rates', name: 'rates-calendar', component: () => import('pages/dashboard/rates/RatesCalendarPage.vue') },
      
      // Configuración
      { path: 'settings', name: 'settings', component: () => import('pages/dashboard/settings/SettingsPage.vue') },
      { path: 'settings/branding', name: 'settings-branding', component: () => import('pages/dashboard/settings/BrandingPage.vue') },
      { path: 'settings/payments', name: 'settings-payments', component: () => import('pages/dashboard/settings/PaymentsPage.vue') },
      { path: 'settings/emails', name: 'settings-emails', component: () => import('pages/dashboard/settings/EmailTemplatesPage.vue') }
    ]
  },

  // Panel SuperAdmin
  {
    path: '/super-admin',
    component: () => import('layouts/SuperAdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'super-admin-dashboard', component: () => import('pages/super-admin/DashboardPage.vue') },
      { path: 'tenants', name: 'super-admin-tenants', component: () => import('pages/super-admin/TenantsListPage.vue') },
      { path: 'tenants/:id', name: 'super-admin-tenant-detail', component: () => import('pages/super-admin/TenantDetailPage.vue') },
      { path: 'logs', name: 'super-admin-logs', component: () => import('pages/super-admin/SystemLogsPage.vue') }
    ]
  },

  // Ruta 404
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue')
  }
]

export default routes
