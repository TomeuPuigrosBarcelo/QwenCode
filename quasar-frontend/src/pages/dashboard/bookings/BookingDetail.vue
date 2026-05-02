<template>
  <q-page class="q-pa-md">
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner color="primary" size="3em" />
    </div>

    <div v-else-if="!booking" class="text-center q-pa-xl">
      <q-icon name="warning" size="3em" color="warning" />
      <h5>Reserva no encontrada</h5>
      <q-btn color="primary" label="Volver al listado" @click="$router.back()" />
    </div>

    <div v-else>
      <!-- Header -->
      <div class="row q-col-gutter-md items-center q-mb-md">
        <div class="col-xs-12 col-sm-6">
          <q-btn flat icon="arrow_back" label="Volver" @click="$router.back()" />
          <h4 class="q-my-none text-primary q-ml-md">Reserva #{{ booking.id }}</h4>
        </div>
        <div class="col-xs-12 col-sm-6 text-right">
          <q-badge :color="getStatusColor(booking.status)" :label="booking.status" class="q-mr-sm" />
          <q-badge :color="getPaymentStatusColor(booking.payment_status)" :label="booking.payment_status" />
          <q-btn-dropdown v-if="canEdit" color="secondary" label="Acciones" class="q-ml-md">
            <q-list>
              <q-item clickable v-close-popup @click="editBooking">
                <q-item-section avatar><q-icon name="edit" /></q-item-section>
                <q-item-section>Editar Reserva</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="downloadInvoice">
                <q-item-section avatar><q-icon name="receipt_long" /></q-item-section>
                <q-item-section>Descargar Factura</q-item-section>
              </q-item>
              <q-separator />
              <q-item clickable v-close-popup @click="confirmCancel" class="text-negative">
                <q-item-section avatar><q-icon name="cancel" /></q-item-section>
                <q-item-section>Cancelar Reserva</q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </div>
      </div>

      <div class="row q-col-gutter-md">
        <!-- Columna Izquierda: Detalles -->
        <div class="col-xs-12 col-lg-8">
          <!-- Información del Huésped -->
          <q-card class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Información del Huésped</div>
              <div class="row q-col-gutter-md">
                <div class="col-xs-12 col-sm-6">
                  <div class="text-caption text-grey-7">Nombre</div>
                  <div>{{ booking.guest_details?.name || 'No especificado' }}</div>
                </div>
                <div class="col-xs-12 col-sm-6">
                  <div class="text-caption text-grey-7">Email</div>
                  <div>
                    <q-icon name="email" size="xs" class="q-mr-xs" />
                    <a :href="`mailto:${booking.guest_email}`">{{ booking.guest_email }}</a>
                  </div>
                </div>
                <div class="col-xs-12 col-sm-6">
                  <div class="text-caption text-grey-7">Teléfono</div>
                  <div>
                    <q-icon name="phone" size="xs" class="q-mr-xs" />
                    {{ booking.guest_phone || 'No especificado' }}
                  </div>
                </div>
                <div class="col-xs-12 col-sm-6">
                  <div class="text-caption text-grey-7">Número de Huéspedes</div>
                  <div>{{ booking.num_guests }} personas</div>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Detalles de la Estancia -->
          <q-card class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Detalles de la Estancia</div>
              <div class="row q-col-gutter-md">
                <div class="col-xs-12 col-sm-4">
                  <div class="text-caption text-grey-7">Check-in</div>
                  <div class="text-h6">{{ formatDate(booking.check_in) }}</div>
                  <div class="text-caption">{{ booking.property?.check_in_time || '15:00' }}</div>
                </div>
                <div class="col-xs-12 col-sm-4">
                  <div class="text-caption text-grey-7">Check-out</div>
                  <div class="text-h6">{{ formatDate(booking.check_out) }}</div>
                  <div class="text-caption">{{ booking.property?.check_out_time || '11:00' }}</div>
                </div>
                <div class="col-xs-12 col-sm-4">
                  <div class="text-caption text-grey-7">Noches</div>
                  <div class="text-h6">{{ calculateNights() }} noches</div>
                </div>
              </div>
              
              <q-separator class="q-my-md" />
              
              <div class="text-caption text-grey-7 q-mb-sm">Inmueble</div>
              <div class="row items-center">
                <q-avatar v-if="booking.property?.images?.[0]?.url" size="64px" class="q-mr-md">
                  <img :src="booking.property.images[0].url" />
                </q-avatar>
                <div>
                  <div class="text-h6">{{ booking.property?.name }}</div>
                  <div class="text-caption text-grey-7">
                    <q-icon name="location_on" size="xs" /> {{ booking.property?.address }}
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Timeline de Eventos -->
          <q-card>
            <q-card-section>
              <div class="text-h6 q-mb-md">Timeline de Eventos</div>
              <q-timeline color="primary">
                <q-timeline-entry :subtitle="formatDate(booking.booked_at)" title="Reserva Creada">
                  <div>Reserva realizada por el huésped</div>
                </q-timeline-entry>
                <q-timeline-entry v-if="booking.status === 'confirmed'" title="Confirmada">
                  <div>La reserva ha sido confirmada</div>
                </q-timeline-entry>
                <q-timeline-entry v-if="booking.payments?.length" title="Pagos Realizados">
                  <div v-for="payment in booking.payments" :key="payment.id" class="text-caption">
                    {{ payment.amount }}€ - {{ payment.provider }} ({{ payment.status }})
                  </div>
                </q-timeline-entry>
              </q-timeline>
            </q-card-section>
          </q-card>
        </div>

        <!-- Columna Derecha: Pagos y Resumen -->
        <div class="col-xs-12 col-lg-4">
          <!-- Resumen Financiero -->
          <q-card class="q-mb-md">
            <q-card-section class="bg-primary text-white">
              <div class="text-h6">Resumen Financiero</div>
            </q-card-section>
            <q-list separator>
              <q-item>
                <q-item-section>Precio por noche</q-item-section>
                <q-item-section side>{{ formatCurrency(calculatePricePerNight()) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>Noches</q-item-section>
                <q-item-section side>{{ calculateNights() }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>Subtotal</q-item-section>
                <q-item-section side>{{ formatCurrency(booking.total_amount - (booking.deposit_amount || 0)) }}</q-item-section>
              </q-item>
              <q-item v-if="booking.deposit_amount">
                <q-item-section>Fianza</q-item-section>
                <q-item-section side>{{ formatCurrency(booking.deposit_amount) }}</q-item-section>
              </q-item>
              <q-item class="bg-grey-2">
                <q-item-section class="text-weight-bold">Total</q-item-section>
                <q-item-section side class="text-weight-bold text-primary">{{ formatCurrency(booking.total_amount) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>Pagado</q-item-section>
                <q-item-section side class="text-positive">{{ formatCurrency(booking.paid_amount) }}</q-item-section>
              </q-item>
              <q-item v-if="booking.paid_amount < booking.total_amount">
                <q-item-section class="text-negative">Pendiente</q-item-section>
                <q-item-section side class="text-negative">{{ formatCurrency(booking.total_amount - booking.paid_amount) }}</q-item-section>
              </q-item>
            </q-list>
            
            <q-card-actions v-if="booking.payment_status !== 'paid'" align="center">
              <q-btn color="positive" label="Registrar Pago" icon="payment" class="full-width" @click="registerPayment" />
            </q-card-actions>
          </q-card>

          <!-- Notas Internas -->
          <q-card>
            <q-card-section>
              <div class="text-h6 q-mb-md">Notas Internas</div>
              <q-input
                v-model="internalNotes"
                type="textarea"
                outlined
                rows="4"
                placeholder="Añadir notas internas..."
                @blur="saveNotes"
              />
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>

    <!-- Diálogo de Cancelación -->
    <q-dialog v-model="showCancelDialog">
      <q-card style="min-width: 350px">
        <q-card-section>
          <div class="text-h6">Cancelar Reserva</div>
        </q-card-section>
        <q-card-section>
          ¿Estás seguro de que deseas cancelar esta reserva? Esta acción puede generar un reembolso según la política de cancelación.
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="No" color="grey" v-close-popup />
          <q-btn label="Sí, Cancelar" color="negative" @click="cancelBooking" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingStore } from 'src/stores/booking'
import { date, useQuasar } from 'quasar'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const bookingStore = useBookingStore()

const loading = ref(true)
const booking = ref<any>(null)
const internalNotes = ref('')
const showCancelDialog = ref(false)

const canEdit = computed(() => {
  return booking.value && ['pending', 'confirmed'].includes(booking.value.status)
})

onMounted(async () => {
  const id = route.params.id as string
  try {
    booking.value = await bookingStore.getBooking(id)
    internalNotes.value = booking.value.internal_notes || ''
  } catch (error) {
    console.error('Error fetching booking:', error)
    $q.notify({ type: 'negative', message: 'Error al cargar la reserva' })
  } finally {
    loading.value = false
  }
})

const formatDate = (dateString: string) => {
  return date.formatDate(dateString, 'DD/MM/YYYY')
}

const formatCurrency = (amount: number) => {
  return `€${amount.toFixed(2)}`
}

const calculateNights = () => {
  if (!booking.value) return 0
  const start = new Date(booking.value.check_in)
  const end = new Date(booking.value.check_out)
  const diffTime = Math.abs(end.getTime() - start.getTime())
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
}

const calculatePricePerNight = () => {
  const nights = calculateNights()
  if (nights === 0) return 0
  return (booking.value.total_amount - (booking.value.deposit_amount || 0)) / nights
}

const getStatusColor = (status: string) => {
  const colors: any = { pending: 'orange', confirmed: 'green', cancelled: 'red', completed: 'blue' }
  return colors[status] || 'grey'
}

const getPaymentStatusColor = (status: string) => {
  const colors: any = { unpaid: 'red', partial: 'orange', paid: 'green', refunded: 'blue' }
  return colors[status] || 'grey'
}

const editBooking = () => {
  router.push({ name: 'booking-edit', params: { id: booking.value.id } })
}

const downloadInvoice = () => {
  // Implementar descarga de factura
  $q.notify({ type: 'info', message: 'Descargando factura...' })
}

const confirmCancel = () => {
  showCancelDialog.value = true
}

const cancelBooking = async () => {
  try {
    await bookingStore.cancelBooking(booking.value.id)
    $q.notify({ type: 'positive', message: 'Reserva cancelada correctamente' })
    showCancelDialog.value = false
    router.push({ name: 'bookings-list' })
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Error al cancelar la reserva' })
  }
}

const registerPayment = () => {
  // Implementar registro de pago
  $q.notify({ type: 'info', message: 'Funcionalidad en desarrollo' })
}

const saveNotes = async () => {
  try {
    await bookingStore.updateBooking(booking.value.id, { internal_notes: internalNotes.value })
    $q.notify({ type: 'positive', message: 'Notas guardadas' })
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Error al guardar notas' })
  }
}
</script>
