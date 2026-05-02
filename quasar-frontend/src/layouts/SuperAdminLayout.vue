<template>
  <q-layout view="hHh Lpr fFf">
    <q-header elevated class="bg-dark text-white">
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer" />

        <q-toolbar-title>
          <q-icon name="admin_panel_settings" class="q-mr-sm" />
          SuperAdmin Panel - Gestión Global
        </q-toolbar-title>

        <q-space />

        <q-btn flat round dense icon="logout" @click="logout">
          <q-tooltip>Cerrar Sesión</q-tooltip>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      bordered
      class="bg-grey-2"
    >
      <q-list>
        <q-item-label header>SuperAdmin</q-item-label>

        <q-item clickable v-ripple :to="{ name: 'super-admin-dashboard' }" exact>
          <q-item-section avatar><q-icon name="dashboard" /></q-item-section>
          <q-item-section>Dashboard Global</q-item-section>
        </q-item>

        <q-item clickable v-ripple :to="{ name: 'super-admin-tenants' }">
          <q-item-section avatar><q-icon name="business" /></q-item-section>
          <q-item-section>Tenants</q-item-section>
          <q-item-section side>
            <q-badge color="primary">{{ tenantsCount }}</q-badge>
          </q-item-section>
        </q-item>

        <q-item clickable v-ripple :to="{ name: 'super-admin-logs' }">
          <q-item-section avatar><q-icon name="bug_report" /></q-item-section>
          <q-item-section>Logs del Sistema</q-item-section>
        </q-item>

        <q-separator class="q-my-md" />

        <q-item-label header>Herramientas</q-item-label>

        <q-item clickable v-ripple>
          <q-item-section avatar><q-icon name="people" /></q-item-section>
          <q-item-section>Usuarios Globales</q-item-section>
        </q-item>

        <q-item clickable v-ripple>
          <q-item-section avatar><q-icon name="analytics" /></q-item-section>
          <q-item-section>Métricas Globales</q-item-section>
        </q-item>

        <q-item clickable v-ripple>
          <q-item-section avatar><q-icon name="settings_applications" /></q-item-section>
          <q-item-section>Configuración Plataforma</q-item-section>
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
import { useRouter } from 'vue-router'
import { useAuthStore } from 'stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const leftDrawerOpen = ref(false)
const tenantsCount = ref(42) // Esto vendría de una API

const toggleLeftDrawer = () => {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

const logout = async () => {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>
