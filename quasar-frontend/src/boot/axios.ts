import { boot } from 'quasar/wrappers'
import axios, { AxiosError } from 'axios'
import { useAuthStore } from 'src/stores/auth'
import { useTenantStore } from 'src/stores/tenant'
import { Notify } from 'quasar'

// Crear instancia de axios
const api = axios.create({ 
  baseURL: process.env.VUE_APP_API_URL || 'http://localhost:8000/api',
  timeout: 30000,
})

// Interceptor para añadir token y tenant-id
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()
    const tenantStore = useTenantStore()
    
    // Añadir token si existe
    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }
    
    // Añadir tenant-id desde el store (detectado por subdominio)
    if (tenantStore.tenant?.id) {
      config.headers['X-Tenant-ID'] = tenantStore.tenant.id
    } else if (tenantStore.subdomain) {
      config.headers['X-Tenant-Subdomain'] = tenantStore.subdomain
    }
    
    return config
  },
  (error) => Promise.reject(error)
)

// Interceptor para manejar respuestas y errores
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    const authStore = useAuthStore()
    
    // Manejar error 401 (No autorizado)
    if (error.response?.status === 401) {
      authStore.logout()
      Notify.create({
        type: 'negative',
        message: 'Sesión expirada. Por favor, inicia sesión nuevamente.',
      })
    }
    
    // Manejar error 403 (Prohibido - posible problema de tenant)
    if (error.response?.status === 403) {
      Notify.create({
        type: 'warning',
        message: 'No tienes permisos para acceder a este recurso.',
      })
    }
    
    // Manejar error 404
    if (error.response?.status === 404) {
      Notify.create({
        type: 'info',
        message: 'Recurso no encontrado.',
      })
    }
    
    // Manejar error 500
    if (error.response?.status === 500) {
      Notify.create({
        type: 'negative',
        message: 'Error del servidor. Intente más tarde.',
      })
    }
    
    return Promise.reject(error)
  }
)

export default boot(({ app }) => {
  // Hacer la instancia de axios disponible en los componentes como this.$axios
  app.config.globalProperties.$axios = axios
  app.config.globalProperties.$api = api
})

export { api }
