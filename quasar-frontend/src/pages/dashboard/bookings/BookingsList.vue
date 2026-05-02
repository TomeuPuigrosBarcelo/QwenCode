<template>
  <q-page class="q-pa-md">
    <div class="row q-col-gutter-md items-center q-mb-md">
      <div class="col-xs-12 col-sm-6">
        <h4 class="q-my-none text-primary">Gestión de Reservas</h4>
      </div>
      <div class="col-xs-12 col-sm-6 text-right">
        <q-btn color="primary" icon="add" label="Nueva Reserva" @click="goToCreate" />
      </div>
    </div>

    <!-- Filtros -->
    <q-card class="q-mb-md">
      <q-card-section>
        <div class="row q-col-gutter-md">
          <div class="col-xs-12 col-sm-3">
            <q-input v-model="filters.search" outlined placeholder="Buscar por email o nombre" dense clearable>
              <template v-slot:prepend>
                <q-icon name="search" />
              </template>
            </q-input>
          </div>
          <div class="col-xs-12 col-sm-2">
            <q-select v-model="filters.property" :options="propertyOptions" outlined label="Inmueble" dense clearable option-label="name" option-value="id" />
          </div>
          <div class="col-xs-12 col-sm-2">
            <q-select v-model="filters.status" :options="statusOptions" outlined label="Estado" dense clearable />
          </div>
          <div class="col-xs-12 col-sm-2">
            <q-input v-model="filters.checkInFrom" type="date" outlined label="Desde" dense />
          </div>
          <div class="col-xs-12 col-sm-2">
            <q-input v-model="filters.checkInTo" type="date" outlined label="Hasta" dense />
          </div>
          <div class="col-xs-12 col-sm-1 flex items-center justify-end">
            <q-btn round dense icon="filter_list" color="primary" @click="applyFilters" />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Tabla de Reservas -->
    <q-card>
      <q-table
        :rows="bookings"
        :columns="columns"
        row-key="id"
        :loading="loading"
        :pagination="pagination"
        @request="onRequest"
        flat
        bordered
      >
        <template v-slot:body-cell-status="props">
          <q-td :props="props">
            <q-badge :color="getStatusColor(props.row.status)" :label="props.row.status" />
          </q-td>
        </template>

        <template v-slot:body-cell-payment_status="props">
          <q-td :props="props">
            <q-badge :color="getPaymentStatusColor(props.row.payment_status)" :label="props.row.payment_status" />
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props">
            <q-btn flat dense round icon="visibility" color="primary" @click="viewBooking(props.row)">
              <q-tooltip>Ver detalle</q-tooltip>
            </q-btn>
            <q-btn flat dense round icon="edit" color="secondary" @click="editBooking(props.row)">
              <q-tooltip>Editar</q-tooltip>
            </q-btn>
            <q-btn flat dense round icon="more_vert">
              <q-menu>
                <q-list>
                  <q-item clickable v-close-popup @click="downloadInvoice(props.row)">
                    <q-item-section avatar><q-icon name="receipt_long" /></q-item-section>
                    <q-item-section>Descargar Factura</q-item-section>
                  </q-item>
                  <q-item clickable v-close-popup @click="sendEmail(props.row)">
                    <q-item-section avatar><q-icon name="email" /></q-item-section>
                    <q-item-section>Enviar Email</q-item-section>
                  </q-item>
                  <q-separator />
                  <q-item clickable v-close-popup @click="cancelBooking(props.row)" class="text-negative">
                    <q-item-section avatar><q-icon name="cancel" /></q-item-section>
                    <q-item-section>Cancelar</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </q-td>
        </template>
      </q-table>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingStore } from 'src/stores/booking'
import { usePropertyStore } from 'src/stores/property'
import { date } from 'quasar'

const router = useRouter()
const bookingStore = useBookingStore()
const propertyStore = usePropertyStore()

const loading = ref(false)
const bookings = ref<any[]>([])
const properties = ref<any[]>([])

const filters = ref({
  search: '',
  property: null,
  status: null,
  checkInFrom: '',
  checkInTo: ''
})

const statusOptions = ['pending', 'confirmed', 'cancelled', 'completed']

const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
  { name: 'guest', label: 'Huésped', field: (row: any) => `${row.guest_details?.name || 'Sin nombre'} (${row.guest_email})`, align: 'left', sortable: true },
  { name: 'property', label: 'Inmueble', field: (row: any) => row.property?.name, align: 'left', sortable: true },
  { name: 'check_in', label: 'Check-in', field: 'check_in', format: (val: string) => date.formatDate(val, 'DD/MM/YYYY'), align: 'center', sortable: true },
  { name: 'check_out', label: 'Check-out', field: 'check_out', format: (val: string) => date.formatDate(val, 'DD/MM/YYYY'), align: 'center', sortable: true },
  { name: 'total', label: 'Total', field: 'total_amount', format: (val: number) => `€${val.toFixed(2)}`, align: 'right', sortable: true },
  { name: 'status', label: 'Estado', field: 'status', align: 'center' },
  { name: 'payment_status', label: 'Pago', field: 'payment_status', align: 'center' },
  { name: 'actions', label: 'Acciones', field: 'actions', align: 'center' }
]

const pagination = ref({
  page: 1,
  rowsPerPage: 10,
  rowsNumber: 0,
  sortBy: 'check_in',
  descending: true
})

const propertyOptions = computed(() => properties.value.map(p => ({ id: p.id, name: p.name })))

onMounted(async () => {
  loading.value = true
  await Promise.all([
    propertyStore.fetchProperties(),
    fetchBookings()
  ])
  properties.value = propertyStore.properties
  loading.value = false
})

const fetchBookings = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.rowsPerPage,
      ...filters.value
    }
    const response = await bookingStore.fetchBookings(params)
    bookings.value = response.data
    pagination.value.rowsNumber = response.total
  } catch (error) {
    console.error('Error fetching bookings:', error)
  } finally {
    loading.value = false
  }
}

const onRequest = (props: any) => {
  const { page, rowsPerPage, sortBy, descending } = props.pagination
  pagination.value.page = page
  pagination.value.rowsPerPage = rowsPerPage
  pagination.value.sortBy = sortBy
  pagination.value.descending = descending
  fetchBookings()
}

const applyFilters = () => {
  pagination.value.page = 1
  fetchBookings()
}

const getStatusColor = (status: string) => {
  const colors: any = {
    pending: 'orange',
    confirmed: 'green',
    cancelled: 'red',
    completed: 'blue'
  }
  return colors[status] || 'grey'
}

const getPaymentStatusColor = (status: string) => {
  const colors: any = {
    unpaid: 'red',
    partial: 'orange',
    paid: 'green',
    refunded: 'blue'
  }
  return colors[status] || 'grey'
}

const goToCreate = () => {
  router.push({ name: 'booking-create' })
}

const viewBooking = (booking: any) => {
  router.push({ name: 'booking-detail', params: { id: booking.id } })
}

const editBooking = (booking: any) => {
  router.push({ name: 'booking-edit', params: { id: booking.id } })
}

const cancelBooking = (booking: any) => {
  // Lógica de cancelación
}

const downloadInvoice = (booking: any) => {
  // Descargar factura
}

const sendEmail = (booking: any) => {
  // Enviar email
}
</script>
