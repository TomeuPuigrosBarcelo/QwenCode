import { defineStore } from 'pinia'
import { api } from 'boot/axios'
import { date } from 'quasar'

export interface Booking {
  id: number
  tenant_id: number
  property_id: number
  user_id?: number
  guest_email: string
  guest_phone: string
  check_in: string
  check_out: string
  num_guests: number
  total_amount: number
  paid_amount: number
  deposit_amount: number
  currency: string
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed'
  payment_status: 'unpaid' | 'partial' | 'paid' | 'refunded'
  guest_details: any
  booked_at: string
  created_at: string
  updated_at: string
  property?: {
    id: number
    name: string
    address: string
  }
}

export interface BookingFilters {
  status?: string
  check_in_start?: string
  check_in_end?: string
  property_id?: number
  page?: number
  per_page?: number
}

interface BookingState {
  bookings: Booking[]
  currentBooking: Booking | null
  totalCount: number
  isLoading: boolean
  filters: BookingFilters
}

export const useBookingStore = defineStore('booking', {
  state: (): BookingState => ({
    bookings: [],
    currentBooking: null,
    totalCount: 0,
    isLoading: false,
    filters: {
      page: 1,
      per_page: 20,
    },
  }),

  getters: {
    pendingBookings: (state) => 
      state.bookings.filter(b => b.status === 'pending'),
    confirmedBookings: (state) => 
      state.bookings.filter(b => b.status === 'confirmed'),
    upcomingCheckIns: (state) => {
      const today = date.formatDate(new Date(), 'YYYY-MM-DD')
      return state.bookings.filter(b => 
        b.status === 'confirmed' && b.check_in >= today
      )
    },
  },

  actions: {
    async fetchBookings(filters: Partial<BookingFilters> = {}) {
      this.isLoading = true
      this.filters = { ...this.filters, ...filters }

      try {
        const params = new URLSearchParams()
        
        Object.entries(this.filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.append(key, String(value))
          }
        })

        const response = await api.get(`/bookings?${params.toString()}`)
        
        this.bookings = response.data.data
        this.totalCount = response.data.meta?.total || 0
        
        return response.data
      } catch (error: any) {
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async fetchBooking(id: number) {
      this.isLoading = true
      
      try {
        const response = await api.get(`/bookings/${id}`)
        this.currentBooking = response.data
        return response.data
      } catch (error: any) {
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async createBooking(bookingData: Partial<Booking>) {
      try {
        const response = await api.post('/bookings', bookingData)
        
        // Recargar lista de bookings
        await this.fetchBookings()
        
        return response.data
      } catch (error: any) {
        throw error
      }
    },

    async updateBooking(id: number, bookingData: Partial<Booking>) {
      try {
        const response = await api.put(`/bookings/${id}`, bookingData)
        
        // Actualizar en la lista local
        const index = this.bookings.findIndex(b => b.id === id)
        if (index !== -1) {
          this.bookings[index] = response.data
        }
        
        if (this.currentBooking?.id === id) {
          this.currentBooking = response.data
        }
        
        return response.data
      } catch (error: any) {
        throw error
      }
    },

    async cancelBooking(id: number, reason?: string) {
      try {
        const response = await api.post(`/bookings/${id}/cancel`, { reason })
        
        // Actualizar en la lista local
        const index = this.bookings.findIndex(b => b.id === id)
        if (index !== -1) {
          this.bookings[index] = response.data
        }
        
        if (this.currentBooking?.id === id) {
          this.currentBooking = response.data
        }
        
        return response.data
      } catch (error: any) {
        throw error
      }
    },

    async confirmBooking(id: number) {
      try {
        const response = await api.post(`/bookings/${id}/confirm`)
        
        // Actualizar en la lista local
        const index = this.bookings.findIndex(b => b.id === id)
        if (index !== -1) {
          this.bookings[index] = response.data
        }
        
        if (this.currentBooking?.id === id) {
          this.currentBooking = response.data
        }
        
        return response.data
      } catch (error: any) {
        throw error
      }
    },

    // Verificar disponibilidad antes de crear booking
    async checkAvailability(
      propertyId: number,
      checkIn: string,
      checkOut: string
    ): Promise<{ available: boolean; message?: string }> {
      try {
        const response = await api.get('/bookings/check-availability', {
          params: { property_id: propertyId, check_in: checkIn, check_out: checkOut }
        })
        
        return response.data
      } catch (error: any) {
        return {
          available: false,
          message: error.response?.data?.message || 'Error verificando disponibilidad',
        }
      }
    },

    setFilters(filters: Partial<BookingFilters>) {
      this.filters = { ...this.filters, ...filters }
    },

    clearFilters() {
      this.filters = {
        page: 1,
        per_page: 20,
      }
    },
  },
})
