import { boot } from 'quasar/wrappers'
import { useTenantStore } from 'src/stores/tenant'

export default boot(async ({ router }) => {
  const tenantStore = useTenantStore()
  
  // Detectar tenant desde subdominio o dominio personalizado
  const hostname = window.location.hostname
  const port = window.location.port
  const protocol = window.location.protocol
  
  // Lógica de detección de subdominio
  // Ejemplos: 
  // - playa.miapp.com -> subdomain: 'playa'
  // - miapp.com -> subdomain: null (usar por defecto o redirigir)
  // - localhost:9000 -> usar para desarrollo
  
  let subdomain: string | null = null
  
  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    // En desarrollo, podemos usar un parámetro query o un valor fijo
    const urlParams = new URLSearchParams(window.location.search)
    subdomain = urlParams.get('subdomain') || 'demo'
  } else {
    // En producción, extraer subdominio del hostname
    const parts = hostname.split('.')
    
    // Si tenemos más de 2 partes (ej: playa.dominio.com), la primera es el subdominio
    // Excepción: www no cuenta como subdominio real
    if (parts.length > 2 && parts[0] !== 'www') {
      subdomain = parts[0]
    }
  }
  
  // Guardar subdominio en el store
  tenantStore.setSubdomain(subdomain)
  
  // Cargar configuración del tenant si hay subdominio
  if (subdomain) {
    try {
      await tenantStore.loadTenantConfig(subdomain)
      
      // Actualizar documento con colores y logo del tenant
      if (tenantStore.tenant?.branding) {
        const { primary, secondary, logo } = tenantStore.tenant.branding
        
        // Establecer variables CSS para colores dinámicos
        const root = document.documentElement
        if (primary) root.style.setProperty('--q-primary', primary)
        if (secondary) root.style.setProperty('--q-secondary', secondary)
        
        // Cambiar favicon dinámicamente
        if (logo) {
          const favicon = document.querySelector('link[rel="icon"]') as HTMLLinkElement
          if (favicon) favicon.href = logo
        }
      }
    } catch (error) {
      console.error('Error cargando configuración del tenant:', error)
      // Podríamos redirigir a una página de error o tenant no encontrado
    }
  }
  
  // Guard de rutas para verificar tenant
  router.beforeEach((to, from, next) => {
    // Rutas que no requieren tenant (ej: landing page global)
    const publicRoutes = ['global-home', 'global-about']
    
    if (!publicRoutes.includes(to.name as string) && !tenantStore.tenant && !subdomain) {
      // Redirigir a página de tenant no encontrado o global
      next({ name: 'tenant-not-found' })
    } else {
      next()
    }
  })
})
