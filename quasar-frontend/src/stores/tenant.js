import { defineStore } from 'pinia'
import { api } from 'boot/axios'

export const useTenantStore = defineStore('tenant', {
  state: () => ({
    config: null,
    loaded: false,
    loading: false,
    error: null
  }),

  getters: {
    branding: (state) => state.config?.branding || {},
    primaryColor: (state) => state.config?.branding?.primary_color || '#1976D2',
    secondaryColor: (state) => state.config?.branding?.secondary_color || '#26A69A',
    logoUrl: (state) => state.config?.branding?.logo_url || '',
    tenantName: (state) => state.config?.name || '',
    defaultLocale: (state) => state.config?.default_locale || 'es'
  },

  actions: {
    async loadTenantConfig(identifier) {
      this.loading = true
      this.error = null
      
      try {
        const response = await api.get(`/public/tenant/${identifier}`)
        this.config = response.data
        
        // Guardar en localStorage para futuras peticiones
        localStorage.setItem('tenant_id', this.config.id)
        localStorage.setItem('tenant_config', JSON.stringify(this.config))
        
        // Aplicar colores dinámicamente
        this.applyBranding(this.config.branding)
        
        this.loaded = true
      } catch (error) {
        this.error = error
        throw error
      } finally {
        this.loading = false
      }
    },

    applyBranding(branding) {
      if (!branding) return
      
      const root = document.documentElement
      
      if (branding.primary_color) {
        root.style.setProperty('--q-primary', branding.primary_color)
      }
      
      if (branding.secondary_color) {
        root.style.setProperty('--q-secondary', branding.secondary_color)
      }
      
      // Actualizar favicon dinámicamente
      if (branding.favicon_url) {
        const link = document.querySelector("link[rel~='icon']")
        if (link) {
          link.href = branding.favicon_url
        }
      }
    },

    loadFromCache() {
      const cached = localStorage.getItem('tenant_config')
      if (cached) {
        try {
          this.config = JSON.parse(cached)
          this.applyBranding(this.config.branding)
          this.loaded = true
          return true
        } catch (e) {
          localStorage.removeItem('tenant_config')
        }
      }
      return false
    },

    clearConfig() {
      this.config = null
      this.loaded = false
      localStorage.removeItem('tenant_id')
      localStorage.removeItem('tenant_config')
    }
  }
})
