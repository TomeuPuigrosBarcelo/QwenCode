import { defineStore } from 'pinia'
import { api } from 'boot/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
    loading: false
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    isSuperAdmin: (state) => state.user?.is_super_admin || false,
    userRole: (state) => state.user?.role || null,
    tenantId: (state) => state.user?.tenant_id || null
  },

  actions: {
    async login(credentials) {
      this.loading = true
      
      try {
        const response = await api.post('/auth/login', credentials)
        const { token, user } = response.data
        
        this.token = token
        this.user = user
        
        localStorage.setItem('token', token)
        localStorage.setItem('user', JSON.stringify(user))
        
        // Configurar axios con el nuevo token
        api.defaults.headers.common['Authorization'] = `Bearer ${token}`
        
        return user
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async register(userData) {
      this.loading = true
      
      try {
        const response = await api.post('/auth/register', userData)
        const { token, user } = response.data
        
        this.token = token
        this.user = user
        
        localStorage.setItem('token', token)
        localStorage.setItem('user', JSON.stringify(user))
        
        return user
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await api.post('/auth/logout')
      } catch (error) {
        console.error('Error en logout:', error)
      } finally {
        this.user = null
        this.token = null
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        delete api.defaults.headers.common['Authorization']
      }
    },

    loadUserFromStorage() {
      const storedUser = localStorage.getItem('user')
      if (storedUser && this.token) {
        try {
          this.user = JSON.parse(storedUser)
          api.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
          return true
        } catch (e) {
          this.logout()
        }
      }
      return false
    },

    async impersonate(userId) {
      this.loading = true
      
      try {
        const response = await api.post(`/super-admin/impersonate/${userId}`)
        const { token, user } = response.data
        
        this.token = token
        this.user = user
        
        localStorage.setItem('token', token)
        localStorage.setItem('user', JSON.stringify(user))
        
        api.defaults.headers.common['Authorization'] = `Bearer ${token}`
        
        return user
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async stopImpersonation() {
      try {
        await api.post('/super-admin/stop-impersonate')
        this.logout()
        window.location.reload()
      } catch (error) {
        console.error('Error al detener impersonación:', error)
      }
    }
  }
})
