<template>
  <q-page class="q-pa-md">
    <div class="row q-col-gutter-md items-center q-mb-md">
      <div class="col-xs-12 col-sm-6">
        <q-btn flat icon="arrow_back" label="Volver" @click="$router.back()" />
        <h4 class="q-my-none text-primary q-ml-md">{{ isEdit ? 'Editar Reserva' : 'Nueva Reserva Manual' }}</h4>
      </div>
    </div>

    <q-form @submit="onSubmit" class="q-gutter-md">
      <div class="row q-col-gutter-md">
        <!-- Columna Izquierda: Datos de la Reserva -->
        <div class="col-xs-12 col-lg-8">
          <q-card class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Selección de Inmueble y Fechas</div>
              
              <q-select
                v-model="form.property_id"
                :options="propertyOptions"
                label="Inmueble *"
                outlined
                option-label="name"
                option-value="id"
                emit-value
                map-options
                :rules="[val => !!val || 'Seleccione un inmueble']"
                @update:model-value="onPropertyChange"
              />

              <div class="row q-col-gutter-md q-mt-md">
                <div class="col-xs-12 col-sm-6">
                  <q-input
                    v-model="form.check_in"
                    type="date"
                    outlined
                    label="Check-in *"
                    :min="today"
                    :rules="[val => !!val || 'Fecha requerida']"
                  />
                </div>
                <div class="col-xs-12 col-sm-6">
                  <q-input
                    v-model="form.check_out"
                    type="date"
                    outlined
                    label="Check-out *"
                    :min="form.check_in || today"
                    :rules="[val => !!val || 'Fecha requerida', validateCheckOut]"
                  />
                </div>
              </div>

              <div v-if="availabilityError" class="q-mt-md text-negative">
                <q-icon name="warning" /> {{ availabilityError }}
              </div>
            </q-card-section>
          </q-card>

          <q-card class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Datos del Huésped</div>
              
              <q-input
                v-model="form.guest_name"
                outlined
                label="Nombre Completo *"
                :rules="[val => !!val || 'Nombre requerido']"
              />

              <q-input
                v-model="form.guest_email"
                type="email"
                outlined
                label="Email *"
                :rules="[val => !!val || 'Email requerido', val => /.+@.+\..+/.test(val) || 'Email inválido']"
              />

              <q-input
                v-model="form.guest_phone"
                type="tel"
                outlined
                label="Teléfono"
              />

              <q-input
                v-model.number="form.num_guests"
                type="number"
                outlined
                label="Número de Huéspedes *"
                :min="1"
                :rules="[val => !!val && val > 0 || 'Mínimo 1 huésped']"
              />

              <q-input
                v-model="form.notes"
                type="textarea"
                outlined
                label="Notas / Comentarios"
                rows="3"
              />
            </q-card-section>
          </q-card>

          <q-card>
            <q-card-section>
              <div class="text-h6 q-mb-md">Configuración de Pago</div>
              
              <q-select
                v-model="form.payment_method"
                :options="paymentMethods"
                outlined
                label="Método de Pago *"
                option-label="label"
                option-value="value"
                emit-value
                map-options
                :rules="[val => !!val || 'Método requerido']"
              />

              <q-input
                v-if="form.payment_method === 'bizum_manual'"
                v-model="form.bizum_phone"
                type="tel"
                outlined
                label="Teléfono Bizum"
                hint="El huésped realizará el Bizum a este número"
              />

              <q-input
                v-if="['stripe', 'bizum_manual'].includes(form.payment_method)"
                v-model.number="form.advance_percentage"
                type="number"
                outlined
                label="% a Cobrar por Adelantado"
                suffix="%"
                :min="0"
                :max="100"
                hint="Dejar en 100 para cobrar todo"
              />
            </q-card-section>
          </q-card>
        </div>

        <!-- Columna Derecha: Resumen -->
        <div class="col-xs-12 col-lg-4">
          <q-card class="sticky-top" style="top: 20px; z-index: 1000;">
            <q-card-section class="bg-secondary text-white">
              <div class="text-h6">Resumen de la Reserva</div>
            </q-card-section>
            <q-list separator>
              <q-item>
                <q-item-section>Precio por noche</q-item-section>
                <q-item-section side>{{ formatCurrency(pricePerNight) }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>Noches</q-item-section>
                <q-item-section side>{{ nights }}</q-item-section>
              </q-item>
              <q-item>
                <q-item-section>Subtotal</q-item-section>
                <q-item-section side>{{ formatCurrency(subtotal) }}</q-item-section>
              </q-item>
              <q-item v-if="form.deposit_amount > 0">
                <q-item-section>Fianza</q-item-section>
                <q-item-section side>{{ formatCurrency(form.deposit_amount) }}</q-item-section>
              </q-item>
              <q-item class="bg-grey-2">
                <q-item-section class="text-weight-bold">Total</q-item-section>
                <q-item-section side class="text-weight-bold text-primary">{{ formatCurrency(totalAmount) }}</q-item-section>
              </q-item>
              <q-item v-if="form.advance_percentage < 100">
                <q-item-section class="text-positive">A cobrar ahora ({{ form.advance_percentage }}%)</q-item-section>
                <q-item-section side class="text-positive">{{ formatCurrency(amountToPayNow) }}</q-item-section>
              </q-item>
            </q-list>

            <q-card-actions vertical align="stretch">
              <q-btn color="primary" label="Crear Reserva" type="submit" :loading="submitting" icon="check" />
              <q-btn color="grey" label="Cancelar" @click="$router.back()" />
            </q-card-actions>
          </q-card>
        </div>
      </div>
    </q-form>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar, date } from 'quasar'
import { usePropertyStore } from 'src/stores/property'
import { useBookingStore } from 'src/stores/booking'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const propertyStore = usePropertyStore()
const bookingStore = useBookingStore()

const isEdit = computed(() => !!route.params.id)
const submitting = ref(false)
const availabilityError = ref('')
const pricePerNight = ref(0)

const today = date.formatDate(new Date(), 'YYYY-MM-DD')

const form = ref({
  property_id: null as number | null,
  check_in: '',
  check_out: '',
  guest_name: '',
  guest_email: '',
  guest_phone: '',
  num_guests: 1,
  notes: '',
  payment_method: 'stripe',
  bizum_phone: '',
  advance_percentage: 100,
  deposit_amount: 0
})

const paymentMethods = [
  { label: 'Tarjeta (Stripe)', value: 'stripe' },
  { label: 'Bizum Manual', value: 'bizum_manual' },
  { label: 'Transferencia Bancaria', value: 'bank_transfer' },
  { label: 'Efectivo en Llegada', value: 'cash' }
]

const propertyOptions = computed(() => propertyStore.properties.map(p => ({ id: p.id, name: p.name })))

const nights = computed(() => {
  if (!form.value.check_in || !form.value.check_out) return 0
  const start = new Date(form.value.check_in)
  const end = new Date(form.value.check_out)
  const diffTime = Math.abs(end.getTime() - start.getTime())
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
})

const subtotal = computed(() => nights.value * pricePerNight.value)

const totalAmount = computed(() => subtotal.value + form.value.deposit_amount)

const amountToPayNow = computed(() => totalAmount.value * (form.value.advance_percentage / 100))

onMounted(async () => {
  await propertyStore.fetchProperties()
  
  if (isEdit.value) {
    await loadBooking()
  }
})

const loadBooking = async () => {
  try {
    const booking = await bookingStore.getBooking(route.params.id as string)
    form.value = {
      property_id: booking.property_id,
      check_in: booking.check_in,
      check_out: booking.check_out,
      guest_name: booking.guest_details?.name || '',
      guest_email: booking.guest_email,
      guest_phone: booking.guest_phone || '',
      num_guests: booking.num_guests,
      notes: booking.notes || '',
      payment_method: booking.payment_method || 'stripe',
      bizum_phone: booking.bizum_phone || '',
      advance_percentage: booking.advance_percentage || 100,
      deposit_amount: booking.deposit_amount || 0
    }
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Error al cargar la reserva' })
  }
}

const onPropertyChange = async (propertyId: number) => {
  const property = propertyStore.properties.find(p => p.id === propertyId)
  if (property) {
    // Obtener tarifa para las fechas seleccionadas
    if (form.value.check_in && form.value.check_out) {
      await checkAvailabilityAndPrice()
    }
  }
}

watch([() => form.value.check_in, () => form.value.check_out], () => {
  if (form.value.property_id) {
    checkAvailabilityAndPrice()
  }
})

const checkAvailabilityAndPrice = async () => {
  if (!form.value.property_id || !form.value.check_in || !form.value.check_out) return
  
  try {
    const result = await bookingStore.checkAvailability({
      property_id: form.value.property_id,
      check_in: form.value.check_in,
      check_out: form.value.check_out
    })
    
    if (result.available) {
      availabilityError.value = ''
      pricePerNight.value = result.price_per_night
    } else {
      availabilityError.value = result.reason || 'Fechas no disponibles'
      pricePerNight.value = 0
    }
  } catch (error) {
    availabilityError.value = 'Error al verificar disponibilidad'
    pricePerNight.value = 0
  }
}

const validateCheckOut = (val: string) => {
  if (!val) return true
  if (!form.value.check_in) return true
  return new Date(val) > new Date(form.value.check_in) || 'Check-out debe ser posterior al check-in'
}

const formatCurrency = (amount: number) => {
  return `€${amount.toFixed(2)}`
}

const onSubmit = async () => {
  if (availabilityError.value) {
    $q.notify({ type: 'warning', message: 'Las fechas seleccionadas no están disponibles' })
    return
  }

  submitting.value = true
  try {
    if (isEdit.value) {
      await bookingStore.updateBooking(route.params.id as string, form.value)
      $q.notify({ type: 'positive', message: 'Reserva actualizada correctamente' })
    } else {
      await bookingStore.createBooking(form.value)
      $q.notify({ type: 'positive', message: 'Reserva creada correctamente' })
    }
    router.push({ name: 'bookings-list' })
  } catch (error: any) {
    $q.notify({ 
      type: 'negative', 
      message: error.response?.data?.message || 'Error al guardar la reserva' 
    })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.sticky-top {
  position: sticky;
}
</style>
