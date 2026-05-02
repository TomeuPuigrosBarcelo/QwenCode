import { defineStore } from 'pinia'
import { api } from 'boot/axios'
import { Notify } from 'quasar'

export interface User {
  id: number
  email: string
  name: string
  role: 'super_admin' | 'owner' | 'staff'
  tenant_id?: number
  is_super_admin: boolean
}

interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    token: localStorage.getItem('auth_token') || null,
    isAuthenticated: !!localStorage.getItem('auth_token'),
    isLoading: false,
  }),

  getters: {
    isSuperAdmin: (state) => state.user?.is_super_admin || false,
    isOwner: (state) => state.user?.role === 'owner',
    userEmail: (state) => state.user?.email || '',
    userName: (state) => state.user?.name || '',
  },

  actions: {
    async login(email: string, password: string) {
      try {
        this.isLoading = true
        const response = await api.post('/auth/login', { email, password })
        
        const { user, token } = response.data
        
        this.token = token
        this.user = user
        this.isAuthenticated = true
        
        localStorage.setItem('auth_token', token)
        
        Notify.create({
          type: 'positive',
          message: `Bienvenido, ${user.name}!`,
        })
        
        return user
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Error al iniciar sesión',
        })
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async register(userData: {
      name: string
      email: string
      password: string
      subdomain: string
      business_name: string
    }) {
      try {
        this.isLoading = true
        const response = await api.post('/auth/register', userData)
        
        const { user, token } = response.data
        
        this.token = token
        this.user = user
        this.isAuthenticated = true
        
        localStorage.setItem('auth_token', token)
        
        Notify.create({
          type: 'positive',
          message: 'Registro exitoso. ¡Bienvenido!',
        })
        
        return user
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Error en el registro',
        })
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async logout() {
      try {
        await api.post('/auth/logout')
      } catch (error) {
        console.error('Error al cerrar sesión:', error)
      } finally {
        this.token = null
        this.user = null
        this.isAuthenticated = false
        localStorage.removeItem('auth_token')
        
        Notify.create({
          type: 'info',
          message: 'Sesión cerrada correctamente',
        })
      }
    },

    async fetchUser() {
      if (!this.token) return
      
      try {
        const response = await api.get('/auth/me')
        this.user = response.data
      } catch (error: any) {
        if (error.response?.status === 401) {
          this.logout()
        }
      }
    },

    // Función para impersonación (SuperAdmin)
    async impersonate(userId: number) {
      try {
        const response = await api.post(`/admin/users/${userId}/impersonate`)
        
        const { user, token } = response.data
        
        this.token = token
        this.user = user
        this.isAuthenticated = true
        
        localStorage.setItem('auth_token', token)
        
        Notify.create({
          type: 'warning',
          message: `Modo impersonación activado como ${user.email}`,
          timeout: 5000,
        })
        
        return user
      } catch (error: any) {
        Notify.create({
          type: 'negative',
          message: error.response?.data?.message || 'Error al impersonar usuario',
        })
        throw error
      }
    },

    // Salir de modo impersonación
    async stopImpersonation() {
      try {
        const response = await api.post('/admin/impersonation/stop')
        
        const { user, token } = response.data
        
        this.token = token
        this.user = user
        this.isAuthenticated = true
        
        localStorage.setItem('auth_token', token)
        
        Notify.create({
          type: 'info',
          message: 'Modo impersonación desactivado',
        })
      } catch (error: any) {
        // En caso de error, hacer logout completo
        this.logout()
      }
    },
  },
})
