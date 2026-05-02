<template>
  <q-dialog v-model="isOpen" maximized position="right">
    <q-card class="column full-height">
      <!-- Header -->
      <q-card-section class="row items-center q-pb-none bg-grey-2">
        <div class="text-h6 col">
          <q-icon name="receipt_long" class="q-mr-sm" />
          Detalle de Pago #{{ payment?.id }}
        </div>
        
        <q-space />
        
        <q-btn flat round dense icon="close" v-close-popup>
          <q-tooltip>Cerrar</q-tooltip>
        </q-btn>
      </q-card-section>

      <q-card-section class="col q-pt-md">
        <div class="row q-col-gutter-lg">
          
          <!-- Columna Izquierda: Información Principal -->
          <div class="col-12 col-md-7">
            
            <!-- Estado y Monto -->
            <q-card class="q-mb-md">
              <q-card-section>
                <div class="row items-center justify-between">
                  <div>
                    <div class="text-caption text-grey-7">Estado del Pago</div>
                    <payment-status-badge :status="payment?.status" size="lg" />
                  </div>
                  <div class="text-right">
                    <div class="text-caption text-grey-7">Importe Total</div>
                    <div class="text-h4 text-weight-bold text-primary">
                      {{ formatCurrency(payment?.amount || 0, payment?.currency) }}
                    </div>
                  </div>
                </div>
              </q-card-section>
            </q-card>

            <!-- Información del Huésped -->
            <q-card class="q-mb-md">
              <q-card-section>
                <div class="text-subtitle2 q-mb-md">Información del Huésped</div>
                
                <q-list separator>
                  <q-item>
                    <q-item-section avatar>
                      <q-icon name="person" color="primary" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label caption>Nombre</q-item-label>
                      <q-item-label>{{ payment?.guest_details?.name || 'N/A' }}</q-item-label>
                    </q-item-section>
                  </q-item>
                  
                  <q-item>
                    <q-item-section avatar>
                      <q-icon name="email" color="primary" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label caption>Email</q-item-label>
                      <q-item-label>{{ payment?.guest_email || 'N/A' }}</q-item-label>
                    </q-item-section>
                  </q-item>
                  
                  <q-item>
                    <q-item-section avatar>
                      <q-icon name="phone" color="primary" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label caption>Teléfono</q-item-label>
                      <q-item-label>{{ payment?.guest_phone || 'N/A' }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-card-section>
            </q-card>

            <!-- Información de la Reserva -->
            <q-card class="q-mb-md">
              <q-card-section>
                <div class="text-subtitle2 q-mb-md">Reserva Asociada</div>
                
                <q-list separator>
                  <q-item clickable v-if="booking" @click="viewBooking">
                    <q-item-section avatar>
                      <q-icon name="bed" color="primary" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label caption>ID Reserva</q-item-label>
                      <q-item-label class="text-weight-bold">#{{ booking?.id }}</q-item-label>
                    </q-item-section>
                    <q-item-section side>
                      <q-btn flat dense icon="open_in_new" color="primary" />
                    </q-item-section>
                  </q-item>
                  
                  <q-item>
                    <q-item-section avatar>
                      <q-icon name="event" color="primary" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label caption>Fechas</q-item-label>
                      <q-item-label>
                        {{ formatDate(booking?.check_in) }} - {{ formatDate(booking?.check_out) }}
                      </q-item-label>
                    </q-item-section>
                  </q-item>
                  
                  <q-item>
                    <q-item-section avatar>
                      <q-icon name="home" color="primary" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label caption>Inmueble</q-item-label>
                      <q-item-label>{{ property?.name || 'Cargando...' }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-card-section>
            </q-card>

            <!-- Metadata del Proveedor -->
            <q-card class="q-mb-md" v-if="payment?.metadata">
              <q-card-section>
                <div class="text-subtitle2 q-mb-md">
                  Detalles Técnicos ({{ getProviderLabel(payment?.provider) }})
                </div>
                
                <q-list dense>
                  <q-item v-if="payment?.provider_intent_id">
                    <q-item-section>
                      <q-item-label caption>ID Transacción Proveedor</q-item-label>
                      <q-item-label class="text-mono">{{ payment.provider_intent_id }}</q-item-label>
                    </q-item-section>
                  </q-item>
                  
                  <q-item v-for="(value, key) in payment.metadata" :key="key">
                    <q-item-section>
                      <q-item-label caption>{{ formatKey(key) }}</q-item-label>
                      <q-item-label>{{ formatValue(value) }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-card-section>
            </q-card>

          </div>

          <!-- Columna Derecha: Timeline y Acciones -->
          <div class="col-12 col-md-5">
            
            <!-- Timeline de Estados -->
            <q-card class="q-mb-md">
              <q-card-section>
                <div class="text-subtitle2 q-mb-md">Línea de Tiempo</div>
                
                <q-timeline color="primary">
                  <q-timeline-entry
                    title="Pago Creado"
                    :subtitle="formatDateFull(payment?.created_at)"
                    icon="add_shopping_cart"
                  >
                    <div class="text-caption text-grey-7">
                      Se inició el proceso de pago
                    </div>
                  </q-timeline-entry>
                  
                  <q-timeline-entry
                    v-if="payment?.status === 'succeeded'"
                    title="Pago Exitoso"
                    :subtitle="formatDateFull(payment?.updated_at)"
                    icon="check_circle"
                    color="positive"
                  >
                    <div class="text-caption text-positive">
                      El pago fue procesado correctamente
                    </div>
                  </q-timeline-entry>
                  
                  <q-timeline-entry
                    v-if="payment?.status === 'pending'"
                    title="Pendiente de Confirmación"
                    subtitle="Esperando confirmación"
                    icon="pending"
                    color="warning"
                  >
                    <div class="text-caption text-warning">
                      El pago está pendiente de validación
                    </div>
                  </q-timeline-entry>
                  
                  <q-timeline-entry
                    v-if="payment?.status === 'failed'"
                    title="Pago Fallido"
                    :subtitle="formatDateFull(payment?.updated_at)"
                    icon="error"
                    color="negative"
                  >
                    <div class="text-caption text-negative">
                      El pago no pudo ser procesado
                    </div>
                  </q-timeline-entry>
                  
                  <q-timeline-entry
                    v-if="payment?.status === 'refunded'"
                    title="Reembolsado"
                    :subtitle="formatDateFull(payment?.updated_at)"
                    icon="undo"
                    color="orange"
                  >
                    <div class="text-caption">
                      El importe ha sido devuelto al huésped
                    </div>
                  </q-timeline-entry>
                </q-timeline>
              </q-card-section>
            </q-card>

            <!-- Acciones -->
            <q-card>
              <q-card-section>
                <div class="text-subtitle2 q-mb-md">Acciones</div>
                
                <div class="column q-gutter-sm">
                  <q-btn
                    v-if="payment?.status === 'succeeded' && payment?.provider === 'stripe'"
                    color="warning"
                    label="Procesar Reembolso"
                    icon="undo"
                    class="full-width"
                    @click="$emit('refund-requested', payment)"
                  />
                  
                  <q-btn
                    color="secondary"
                    label="Enviar Recibo por Email"
                    icon="send"
                    class="full-width"
                    @click="sendReceipt"
                  />
                  
                  <q-btn
                    color="grey-7"
                    label="Descargar PDF"
                    icon="picture_as_pdf"
                    class="full-width"
                    @click="downloadPDF"
                  />
                  
                  <q-separator class="q-my-sm" />
                  
                  <q-btn
                    flat
                    color="primary"
                    label="Ver en Stripe Dashboard"
                    icon="open_in_new"
                    class="full-width"
                    v-if="payment?.provider === 'stripe' && payment?.provider_intent_id"
                    @click="openStripeDashboard"
                  />
                </div>
              </q-card-section>
            </q-card>

          </div>
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useQuasar, date } from 'quasar'
import PaymentStatusBadge from 'src/components/PaymentStatusBadge.vue'
import api from 'src/services/api'
import { formatCurrency } from 'src/composables/useCurrency'

const $q = useQuasar()

const props = defineProps<{
  payment: any
}>()

const emit = defineEmits<{
  close: []
  'refund-requested': [payment: any]
}>()

const isOpen = ref(true)
const booking = ref<any>(null)
const property = ref<any>(null)
const loading = ref(false)

// Métodos de formato
const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString('es-ES')
}

const formatDateFull = (dateString: string) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleString('es-ES')
}

const formatKey = (key: string) => {
  return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const formatValue = (value: any) => {
  if (typeof value === 'boolean') return value ? 'Sí' : 'No'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

const getProviderLabel = (provider: string) => {
  const labels: any = {
    stripe: 'Stripe',
    bizum_manual: 'Bizum Manual',
    bank_transfer: 'Transferencia Bancaria',
    cash: 'Efectivo'
  }
  return labels[provider] || provider
}

// Cargar datos relacionados
const loadRelatedData = async () => {
  if (!props.payment?.booking_id) return
  
  loading.value = true
  try {
    // Cargar reserva
    const bookingResponse = await api.get(`/bookings/${props.payment.booking_id}`)
    booking.value = bookingResponse.data
    
    // Cargar propiedad
    if (booking.value?.property_id) {
      const propertyResponse = await api.get(`/properties/${booking.value.property_id}`)
      property.value = propertyResponse.data
    }
  } catch (error) {
    console.error('Error loading related data:', error)
  } finally {
    loading.value = false
  }
}

// Acciones
const viewBooking = () => {
  if (booking.value) {
    // Navegar a detalle de reserva (implementar según router)
    $q.notify({
      color: 'info',
      message: `Navegando a reserva #${booking.value.id}`,
      icon: 'info'
    })
  }
}

const sendReceipt = async () => {
  try {
    await api.post(`/payments/${props.payment.id}/send-receipt`)
    $q.notify({
      color: 'positive',
      message: 'Recibo enviado correctamente',
      icon: 'check'
    })
  } catch (error) {
    $q.notify({
      color: 'negative',
      message: 'Error al enviar recibo',
      icon: 'error'
    })
  }
}

const downloadPDF = () => {
  $q.notify({
    color: 'info',
    message: 'Generando PDF...',
    icon: 'download'
  })
  // Implementar descarga de PDF
}

const openStripeDashboard = () => {
  const env = props.payment.metadata?.test_mode ? 'test' : 'live'
  const url = `https://dashboard.stripe.com/${env}/payments/${props.payment.provider_intent_id}`
  window.open(url, '_blank')
}

onMounted(() => {
  loadRelatedData()
})
</script>

<style scoped>
.text-mono {
  font-family: 'Courier New', monospace;
  font-size: 0.85em;
}
</style>
