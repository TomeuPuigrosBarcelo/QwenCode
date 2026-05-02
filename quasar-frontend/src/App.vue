<template>
  <router-view />
</template>

<script setup>
import { useTenantStore } from 'stores/tenant'
import { useAuthStore } from 'stores/auth'
import { onMounted } from 'vue'

const tenantStore = useTenantStore()
const authStore = useAuthStore()

onMounted(() => {
  // Intentar cargar tenant desde caché primero para velocidad
  if (!tenantStore.loaded) {
    tenantStore.loadFromCache()
  }
  
  // Cargar usuario desde localStorage si existe token
  authStore.loadUserFromStorage()
})
</script>
