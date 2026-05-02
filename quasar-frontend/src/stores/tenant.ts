import { defineStore } from 'pinia'
import { api } from 'boot/axios'

export interface Branding {
  logo?: string
  favicon?: string
  primary?: string
  secondary?: string
}

export interface Tenant {
  id: number
  name: string
  subdomain: string
  custom_domain?: string
  status: 'active' | 'suspended' | 'trial'
  branding: Branding
  default_locale: string
  subscribed_until?: string
}

interface TenantState {
  tenant: Tenant | null
  subdomain: string | null
  isLoading: boolean
  error: string | null
}

export const useTenantStore = defineStore('tenant', {
  state: (): TenantState => ({
    tenant: null,
    subdomain: null,
    isLoading: false,
    error: null,
  }),

  getters: {
    isActive: (state) => state.tenant?.status === 'active',
    isTrial: (state) => state.tenant?.status === 'trial',
    isSuspended: (state) => state.tenant?.status === 'suspended',
    primaryColor: (state) => state.tenant?.branding?.primary || '#1976D2',
    secondaryColor: (state) => state.tenant?.branding?.secondary || '#26A69A',
    logoUrl: (state) => state.tenant?.branding?.logo,
    tenantName: (state) => state.tenant?.name || '',
  },

  actions: {
    setSubdomain(subdomain: string | null) {
      this.subdomain = subdomain
    },

    async loadTenantConfig(subdomain: string) {
      if (this.tenant?.subdomain === subdomain) {
        return // Ya cargado
      }

      this.isLoading = true
      this.error = null

      try {
        const response = await api.get(`/tenants/by-subdomain/${subdomain}`)
        this.tenant = response.data
      } catch (error: any) {
        this.error = error.response?.data?.message || 'Error cargando configuración del tenant'
        this.tenant = null
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async updateBranding(branding: Partial<Branding>) {
      if (!this.tenant) return

      try {
        const response = await api.put(`/tenants/${this.tenant.id}/branding`, branding)
        this.tenant = response.data
        
        // Actualizar variables CSS en tiempo real
        const root = document.documentElement
        if (branding.primary) {
          root.style.setProperty('--q-primary', branding.primary)
        }
        if (branding.secondary) {
          root.style.setProperty('--q-secondary', branding.secondary)
        }
        if (branding.logo) {
          const favicon = document.querySelector('link[rel="icon"]') as HTMLLinkElement
          if (favicon) favicon.href = branding.logo
        }
      } catch (error: any) {
        throw error
      }
    },

    clearTenant() {
      this.tenant = null
      this.subdomain = null
      this.error = null
    },
  },
})
