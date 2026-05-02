<template>
  <q-page class="q-pa-md">
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12">
        <div class="row items-center justify-between">
          <div>
            <h4 class="q-ma-none">Transacciones de Pago</h4>
            <p class="text-grey-7 q-mt-xs q-mb-none">Gestiona todos los pagos recibidos</p>
          </div>
          <div class="row q-gutter-sm">
            <q-input
              v-model="filters.search"
              outlined
              dense
              placeholder="Buscar por ID, huésped o email"
              class="bg-white"
              style="width: 300px"
              clearable
            >
              <template v-slot:prepend>
                <q-icon name="search" />
              </template>
            </q-input>
            
            <q-select
              v-model="filters.status"
              :options="statusOptions"
              outlined
              dense
              label="Estado"
              class="bg-white"
              style="width: 150px"
              clearable
              emit-value
              map-options
            />
            
            <q-select
              v-model="filters.provider"
              :options="providerOptions"
              outlined
              dense
              label="Proveedor"
              class="bg-white"
              style="width: 150px"
              clearable
              emit-value
              map-options
            />
            
            <q-btn
              color="primary"
              icon="download"
              label="Exportar CSV"
              @click="exportToCSV"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-md-3">
        <stats-card
          title="Total Ingresado"
          :value="formatCurrency(stats.totalAmount)"
          icon="account_balance_wallet"
          color="positive"
        />
      </div>
      <div class="col-12 col-md-3">
        <stats-card
          title="Pagos Exitosos"
          :value="stats.successfulCount"
          icon="check_circle"
          color="positive"
        />
      </div>
      <div class="col-12 col-md-3">
        <stats-card
          title="Pendientes"
          :value="stats.pendingCount"
          icon="pending"
          color="warning"
        />
      </div>
      <div class="col-12 col-md-3">
        <stats-card
          title="Fallidos/Reembolsados"
          :value="stats.failedCount"
          icon="error"
          color="negative"
        />
      </div>
    </div>

    <!-- Tabla de Pagos -->
    <q-card>
      <q-table
        :rows="filteredPayments"
        :columns="columns"
        row-key="id"
        :loading="loading"
        :pagination="pagination"
        flat
        bordered
      >
        <template v-slot:body-cell-status="props">
          <q-td :props="props">
            <payment-status-badge :status="props.row.status" />
          </q-td>
        </template>

        <template v-slot:body-cell-provider="props">
          <q-td :props="props">
            <q-badge :color="getProviderColor(props.row.provider)">
              {{ getProviderLabel(props.row.provider) }}
            </q-badge>
          </q-td>
        </template>

        <template v-slot:body-cell-amount="props">
          <q-td :props="props" class="text-weight-bold">
            {{ formatCurrency(props.row.amount, props.row.currency) }}
          </q-td>
        </template>

        <template v-slot:body-cell-actions="props">
          <q-td :props="props">
            <q-btn
              flat
              dense
              round
              icon="visibility"
              color="primary"
              @click="viewPayment(props.row)"
            >
              <q-tooltip>Ver detalle</q-tooltip>
            </q-btn>
            
            <q-btn
              v-if="props.row.status === 'succeeded' && props.row.provider === 'stripe'"
              flat
              dense
              round
              icon="undo"
              color="warning"
              @click="confirmRefund(props.row)"
            >
              <q-tooltip>Reembolsar</q-tooltip>
            </q-btn>
          </q-td>
        </template>

        <template v-slot:no-data>
          <div class="full-width row flex-center text-grey q-pa-lg">
            <q-icon name="receipt_long" size="3em" class="q-mr-sm" />
            <span>No se encontraron pagos</span>
          </div>
        </template>
      </q-table>
    </q-card>

    <!-- Diálogo de Reembolso -->
    <q-dialog v-model="showRefundDialog" persistent>
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">Confirmar Reembolso</div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          ¿Estás seguro de reembolsar el pago de 
          <strong>{{ formatCurrency(selectedPayment?.amount || 0, selectedPayment?.currency) }}</strong>?
          <p class="text-caption text-grey-7 q-mt-sm">
            Esta acción se procesará a través de Stripe y puede tardar 5-10 días hábiles en reflejarse.
          </p>
          
          <q-input
            v-model="refundReason"
            label="Motivo del reembolso (opcional)"
            type="textarea"
            outlined
            autogrow
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey" v-close-popup />
          <q-btn 
            label="Confirmar Reembolso" 
            color="warning" 
            :loading="refunding"
            @click="processRefund"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Diálogo de Detalle -->
    <q-dialog v-model="showDetailDialog" maximized>
      <payment-detail 
        v-if="selectedPayment" 
        :payment="selectedPayment" 
        @close="showDetailDialog = false"
        @refund-requested="confirmRefund"
      />
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import PaymentDetail from './PaymentDetail.vue'
import PaymentStatusBadge from 'src/components/PaymentStatusBadge.vue'
import StatsCard from 'src/components/StatsCard.vue'
import { useTenantStore } from 'src/stores/tenant'
import { useAuthStore } from 'src/stores/auth'
import api from 'src/services/api'
import { formatCurrency } from 'src/composables/useCurrency'

const $q = useQuasar()
const tenantStore = useTenantStore()
const authStore = useAuthStore()

// Estado
const loading = ref(false)
const payments = ref<any[]>([])
const filters = ref({
  search: '',
  status: null,
  provider: null
})

const pagination = ref({
  page: 1,
  rowsPerPage: 15
})

const showDetailDialog = ref(false)
const showRefundDialog = ref(false)
const selectedPayment = ref<any>(null)
const refundReason = ref('')
const refunding = ref(false)

// Opciones de filtros
const statusOptions = [
  { label: 'Exitoso', value: 'succeeded' },
  { label: 'Pendiente', value: 'pending' },
  { label: 'Fallido', value: 'failed' },
  { label: 'Reembolsado', value: 'refunded' }
]

const providerOptions = [
  { label: 'Stripe', value: 'stripe' },
  { label: 'Bizum Manual', value: 'bizum_manual' },
  { label: 'Transferencia', value: 'bank_transfer' },
  { label: 'Efectivo', value: 'cash' }
]

// Columnas de la tabla
const columns = [
  { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
  { name: 'booking_id', label: 'Reserva', field: 'booking_id', align: 'left', sortable: true },
  { name: 'guest', label: 'Huésped', field: 'guest_email', align: 'left', sortable: true },
  { name: 'provider', label: 'Proveedor', field: 'provider', align: 'center', sortable: true },
  { name: 'amount', label: 'Importe', field: 'amount', align: 'right', sortable: true },
  { name: 'status', label: 'Estado', field: 'status', align: 'center', sortable: true },
  { name: 'created_at', label: 'Fecha', field: 'created_at', align: 'center', sortable: true, format: (val: string) => new Date(val).toLocaleDateString() },
  { name: 'actions', label: 'Acciones', field: 'actions', align: 'center' }
]

// Pagos filtrados
const filteredPayments = computed(() => {
  let result = payments.value

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    result = result.filter(p => 
      p.id.toString().includes(search) ||
      p.guest_email?.toLowerCase().includes(search) ||
      p.booking_id.toString().includes(search)
    )
  }

  if (filters.value.status) {
    result = result.filter(p => p.status === filters.value.status)
  }

  if (filters.value.provider) {
    result = result.filter(p => p.provider === filters.value.provider)
  }

  return result
})

// Estadísticas
const stats = computed(() => {
  const list = payments.value
  return {
    totalAmount: list.filter(p => p.status === 'succeeded').reduce((sum, p) => sum + p.amount, 0),
    successfulCount: list.filter(p => p.status === 'succeeded').length,
    pendingCount: list.filter(p => p.status === 'pending').length,
    failedCount: list.filter(p => ['failed', 'refunded'].includes(p.status)).length
  }
})

// Métodos
const getProviderColor = (provider: string) => {
  const colors: any = {
    stripe: 'deep-purple',
    bizum_manual: 'blue',
    bank_transfer: 'orange',
    cash: 'green'
  }
  return colors[provider] || 'grey'
}

const getProviderLabel = (provider: string) => {
  const labels: any = {
    stripe: 'Stripe',
    bizum_manual: 'Bizum',
    bank_transfer: 'Transferencia',
    cash: 'Efectivo'
  }
  return labels[provider] || provider
}

const loadPayments = async () => {
  loading.value = true
  try {
    const response = await api.get('/payments')
    payments.value = response.data
  } catch (error) {
    $q.notify({
      color: 'negative',
      message: 'Error al cargar pagos',
      icon: 'error'
    })
  } finally {
    loading.value = false
  }
}

const viewPayment = (payment: any) => {
  selectedPayment.value = payment
  showDetailDialog.value = true
}

const confirmRefund = (payment: any) => {
  selectedPayment.value = payment
  refundReason.value = ''
  showRefundDialog.value = true
}

const processRefund = async () => {
  if (!selectedPayment.value) return
  
  refunding.value = true
  try {
    await api.post(`/payments/${selectedPayment.value.id}/refund`, {
      reason: refundReason.value
    })
    
    $q.notify({
      color: 'positive',
      message: 'Reembolso procesado correctamente',
      icon: 'check'
    })
    
    showRefundDialog.value = false
    loadPayments()
  } catch (error: any) {
    $q.notify({
      color: 'negative',
      message: error.response?.data?.message || 'Error al procesar reembolso',
      icon: 'error'
    })
  } finally {
    refunding.value = false
  }
}

const exportToCSV = () => {
  // Implementar exportación a CSV
  $q.notify({
    color: 'info',
    message: 'Exportando CSV...',
    icon: 'download'
  })
}

onMounted(() => {
  loadPayments()
})
</script>
