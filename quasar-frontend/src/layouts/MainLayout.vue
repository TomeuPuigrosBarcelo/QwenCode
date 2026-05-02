<template>
  <q-layout view="hHh Lpr fFf">
    <q-header :style="{ backgroundColor: tenantStore.primaryColor }" elevated>
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer" />

        <q-toolbar-title>
          <div class="row items-center no-wrap">
            <img v-if="tenantStore.logoUrl" :src="tenantStore.logoUrl" alt="Logo" class="q-mr-sm logo-header" style="height: 35px;">
            <span>{{ tenantStore.tenantName }} - Dashboard</span>
          </div>
        </q-toolbar-title>

        <q-space />

        <!-- Selector de idioma -->
        <q-btn flat round dense icon="language">
          <q-menu>
            <q-list style="min-width: 100px">
              <q-item clickable v-close-popup @click="changeLocale('es')">
                <q-item-section>Español</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="changeLocale('en')">
                <q-item-section>English</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>

        <!-- Notificaciones -->
        <q-btn flat round dense icon="notifications">
          <q-badge color="red" floating>{{ notificationsCount }}</q-badge>
        </q-btn>

        <!-- Menú de usuario -->
        <q-btn flat round dense icon="account_circle">
          <q-menu>
            <q-list style="min-width: 200px">
              <q-item>
                <q-item-section avatar>
                  <q-avatar color="primary" text-color="white" icon="person" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ authStore.user?.email }}</q-item-label>
                  <q-item-label caption>{{ authStore.userRole }}</q-item-label>
                </q-item-section>
              </q-item>

              <q-separator />

              <q-item clickable v-close-popup @click="$router.push('/dashboard/settings')">
                <q-item-section avatar><q-icon name="settings" /></q-item-section>
                <q-item-section>Configuración</q-item-section>
              </q-item>

              <q-item clickable v-close-popup @click="$router.push('/dashboard/settings/branding')">
                <q-item-section avatar><q-icon name="palette" /></q-item-section>
                <q-item-section>Marca</q-item-section>
              </q-item>

              <q-separator v-if="authStore.isSuperAdmin" />

              <q-item v-if="authStore.isSuperAdmin" clickable v-close-popup @click="$router.push('/super-admin')">
                <q-item-section avatar><q-icon name="admin_panel_settings" /></q-item-section>
                <q-item-section>Panel SuperAdmin</q-item-section>
              </q-item>

              <q-separator />

              <q-item clickable v-close-popup @click="logout">
                <q-item-section avatar><q-icon name="logout" /></q-item-section>
                <q-item-section>Cerrar Sesión</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      bordered
      :style="{ backgroundColor: tenantStore.secondaryColor + '10' }"
    >
      <q-list>
        <q-item-label header>Menú Principal</q-item-label>

        <q-item clickable v-ripple :to="{ name: 'dashboard' }" exact>
          <q-item-section avatar><q-icon name="dashboard" /></q-item-section>
          <q-item-section>Dashboard</q-item-section>
        </q-item>

        <q-item clickable v-ripple :to="{ name: 'bookings-list' }">
          <q-item-section avatar><q-icon name="event" /></q-item-section>
          <q-item-section>Reservas</q-item-section>
          <q-item-section side>
            <q-badge color="red" v-if="bookingStore.pendingBookings > 0">
              {{ bookingStore.pendingBookings }}
            </q-badge>
          </q-item-section>
        </q-item>

        <q-item clickable v-ripple :to="{ name: 'properties-list' }">
          <q-item-section avatar><q-icon name="home" /></q-item-section>
          <q-item-section>Inmuebles</q-item-section>
        </q-item>

        <q-item clickable v-ripple :to="{ name: 'rates-calendar' }">
          <q-item-section avatar><q-icon name="attach_money" /></q-item-section>
          <q-item-section>Tarifas</q-item-section>
        </q-item>

        <q-separator class="q-my-md" />

        <q-item-label header>Configuración</q-item-label>

        <q-item clickable v-ripple :to="{ name: 'settings' }">
          <q-item-section avatar><q-icon name="tune" /></q-item-section>
          <q-item-section>General</q-item-section>
        </q-item>

        <q-item clickable v-ripple :to="{ name: 'settings-branding' }">
          <q-item-section avatar><q-icon name="color_lens" /></q-item-section>
          <q-item-section>Marca</q-item-section>
        </q-item>

        <q-item clickable v-ripple :to="{ name: 'settings-payments' }">
          <q-item-section avatar><q-icon name="payment" /></q-item-section>
          <q-item-section>Pagos</q-item-section>
        </q-item>

        <q-item clickable v-ripple :to="{ name: 'settings-emails' }">
          <q-item-section avatar><q-icon name="email" /></q-item-section>
          <q-item-section>Emails</q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useTenantStore } from 'stores/tenant'
import { useAuthStore } from 'stores/auth'
import { useBookingStore } from 'stores/booking'
import { useRouter } from 'vue-router'

const tenantStore = useTenantStore()
const authStore = useAuthStore()
const bookingStore = useBookingStore()
const router = useRouter()

const leftDrawerOpen = ref(false)
const notificationsCount = ref(3)

const toggleLeftDrawer = () => {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

const changeLocale = (locale) => {
  console.log('Cambiando idioma a:', locale)
}

const logout = async () => {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>

<style scoped>
.logo-header {
  max-height: 35px;
  width: auto;
}
</style>
