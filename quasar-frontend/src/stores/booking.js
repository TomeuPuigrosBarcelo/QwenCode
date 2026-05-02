import { defineStore } from 'pinia'
import { api } from 'boot/axios'

export const useBookingStore = defineStore('booking', {
  state: () => ({
    bookings: [],
    currentBooking: null,
    loading: false,
    filters: {
      status: null,
      property_id: null,
      date_from: null,
      date_to: null
    }
  }),

  getters: {
    totalBookings: (state) => state.bookings.length,
    pendingBookings: (state) => state.bookings.filter(b => b.status === 'pending').length,
    confirmedBookings: (state) => state.bookings.filter(b => b.status === 'confirmed').length
  },

  actions: {
    async fetchBookings(params = {}) {
      this.loading = true
      
      try {
        const queryParams = { ...this.filters, ...params }
        const response = await api.get('/bookings', { params: queryParams })
        this.bookings = response.data
        return response.data
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchBooking(id) {
      this.loading = true
      
      try {
        const response = await api.get(`/bookings/${id}`)
        this.currentBooking = response.data
        return response.data
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async createBooking(bookingData) {
      this.loading = true
      
      try {
        const response = await api.post('/bookings', bookingData)
        this.bookings.unshift(response.data)
        return response.data
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async updateBooking(id, bookingData) {
      this.loading = true
      
      try {
        const response = await api.put(`/bookings/${id}`, bookingData)
        const index = this.bookings.findIndex(b => b.id === id)
        if (index !== -1) {
          this.bookings[index] = response.data
        }
        if (this.currentBooking?.id === id) {
          this.currentBooking = response.data
        }
        return response.data
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async cancelBooking(id, reason = null) {
      this.loading = true
      
      try {
        const response = await api.post(`/bookings/${id}/cancel`, { reason })
        const index = this.bookings.findIndex(b => b.id === id)
        if (index !== -1) {
          this.bookings[index] = response.data
        }
        return response.data
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async checkAvailability(propertyId, checkIn, checkOut) {
      try {
        const response = await api.get('/bookings/check-availability', {
          params: {
            property_id: propertyId,
            check_in: checkIn,
            check_out: checkOut
          }
        })
        return response.data
      } catch (error) {
        throw error
      }
    },

    setFilters(filters) {
      this.filters = { ...this.filters, ...filters }
    },

    clearFilters() {
      this.filters = {
        status: null,
        property_id: null,
        date_from: null,
        date_to: null
      }
    }
  }
})
