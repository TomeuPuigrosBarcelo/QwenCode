import { RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  // Rutas Públicas (Web del cliente)
  {
    path: '/',
    component: () => import('layouts/PublicLayout.vue'),
    children: [
      { 
        path: '', 
        name: 'home',
        component: () => import('pages/public/home/HomePage.vue') 
      },
      {
        path: 'property/:id',
        name: 'property-detail',
        component: () => import('pages/public/property/PropertyDetailPage.vue'),
      },
      {
        path: 'booking/:propertyId',
        name: 'booking-create',
        component: () => import('pages/public/booking/BookingCreatePage.vue'),
      },
      {
        path: 'booking-success/:id',
        name: 'booking-success',
        component: () => import('pages/public/booking/BookingSuccessPage.vue'),
      },
    ],
  },

  // Rutas Dashboard Propietario
  {
    path: '/dashboard',
    component: () => import('layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { 
        path: '', 
        name: 'dashboard',
        redirect: '/dashboard/analytics' 
      },
      {
        path: 'analytics',
        name: 'dashboard-analytics',
        component: () => import('pages/dashboard/analytics/AnalyticsPage.vue'),
      },
      {
        path: 'properties',
        name: 'dashboard-properties',
        component: () => import('pages/dashboard/properties/PropertiesListPage.vue'),
      },
      {
        path: 'properties/create',
        name: 'dashboard-property-create',
        component: () => import('pages/dashboard/properties/PropertyFormPage.vue'),
      },
      {
        path: 'properties/:id',
        name: 'dashboard-property-edit',
        component: () => import('pages/dashboard/properties/PropertyFormPage.vue'),
      },
      {
        path: 'bookings',
        name: 'dashboard-bookings',
        component: () => import('pages/dashboard/bookings/BookingsListPage.vue'),
      },
      {
        path: 'bookings/:id',
        name: 'dashboard-booking-detail',
        component: () => import('pages/dashboard/bookings/BookingDetailPage.vue'),
      },
      {
        path: 'rates',
        name: 'dashboard-rates',
        component: () => import('pages/dashboard/rates/RatesPage.vue'),
      },
      {
        path: 'settings',
        name: 'dashboard-settings',
        component: () => import('pages/dashboard/settings/SettingsPage.vue'),
      },
      {
        path: 'branding',
        name: 'dashboard-branding',
        component: () => import('pages/dashboard/settings/BrandingPage.vue'),
      },
    ],
  },

  // Rutas SuperAdmin
  {
    path: '/admin',
    component: () => import('layouts/SuperAdminLayout.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true },
    children: [
      { 
        path: '', 
        name: 'superadmin',
        redirect: '/admin/tenants' 
      },
      {
        path: 'tenants',
        name: 'superadmin-tenants',
        component: () => import('pages/superadmin/TenantsListPage.vue'),
      },
      {
        path: 'tenants/:id',
        name: 'superadmin-tenant-detail',
        component: () => import('pages/superadmin/TenantDetailPage.vue'),
      },
      {
        path: 'logs',
        name: 'superadmin-logs',
        component: () => import('pages/superadmin/SystemLogsPage.vue'),
      },
      {
        path: 'users',
        name: 'superadmin-users',
        component: () => import('pages/superadmin/UsersListPage.vue'),
      },
    ],
  },

  // Rutas de Autenticación
  {
    path: '/auth',
    children: [
      {
        path: 'login',
        name: 'auth-login',
        component: () => import('pages/auth/LoginPage.vue'),
      },
      {
        path: 'register',
        name: 'auth-register',
        component: () => import('pages/auth/RegisterPage.vue'),
      },
    ],
  },

  // Rutas de Error
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/errors/ErrorNotFound.vue'),
  },
  
  // Tenant no encontrado
  {
    path: '/tenant-not-found',
    name: 'tenant-not-found',
    component: () => import('pages/errors/TenantNotFound.vue'),
  },
]

export default routes
