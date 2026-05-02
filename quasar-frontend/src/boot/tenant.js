import { boot } from 'quasar/wrappers'
import { useTenantStore } from '../stores/tenant'

export default boot(async ({ router, store }) => {
  const tenantStore = useTenantStore(store)
  
  // Detectar subdominio o dominio personalizado
  const hostname = window.location.hostname
  const parts = hostname.split('.')
  
  let subdomain = null
  if (parts.length > 2 && !hostname.includes('localhost')) {
    // Ej: cliente.miapp.com -> subdomain = 'cliente'
    subdomain = parts[0]
  } else if (parts.length === 2 && !hostname.includes('localhost')) {
    // Dominio personalizado sin subdominio
    subdomain = '@custom'
  } else {
    // Localhost o desarrollo -> usar variable de entorno o 'demo'
    subdomain = import.meta.env.VITE_DEFAULT_SUBDOMAIN || 'demo'
  }
  
  try {
    await tenantStore.loadTenantConfig(subdomain)
  } catch (error) {
    console.error('Error cargando configuración del tenant:', error)
    // Redirigir a página de error o tenant no encontrado
  }
})
