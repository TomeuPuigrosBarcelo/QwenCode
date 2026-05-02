<template>
  <q-page class="q-pa-md">
    <!-- Header con métricas clave -->
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-md-3">
        <q-card class="bg-primary text-white">
          <q-card-section>
            <div class="text-h6">Ingresos Mes</div>
            <div class="text-h4">{{ formatCurrency(revenueMonth) }}</div>
            <q-icon name="trending_up" class="absolute-top-right q-mr-md q-mt-md" />
          </q-card-section>
        </q-card>
      </div>
      
      <div class="col-12 col-md-3">
        <q-card class="bg-secondary text-white">
          <q-card-section>
            <div class="text-h6">Reservas Confirmadas</div>
            <div class="text-h4">{{ confirmedBookingsCount }}</div>
            <q-icon name="event_available" class="absolute-top-right q-mr-md q-mt-md" />
          </q-card-section>
        </q-card>
      </div>
      
      <div class="col-12 col-md-3">
        <q-card class="bg-accent text-white">
          <q-card-section>
            <div class="text-h6">Pendientes</div>
            <div class="text-h4">{{ pendingBookingsCount }}</div>
            <q-icon name="pending_actions" class="absolute-top-right q-mr-md q-mt-md" />
          </q-card-section>
        </q-card>
      </div>
      
      <div class="col-12 col-md-3">
        <q-card class="bg-positive text-white">
          <q-card-section>
            <div class="text-h6">Ocupación Mes</div>
            <div class="text-h4">{{ occupancyRate }}%</div>
            <q-icon name="percent" class="absolute-top-right q-mr-md q-mt-md" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Calendario y Gráficos -->
    <div class="row q-col-gutter-md">
      <!-- Calendario -->
      <div class="col-12 col-lg-8">
        <q-card>
          <q-card-section>
            <div class="text-h6 q-mb-md">Calendario de Reservas</div>
            <FullCalendar 
              ref="calendarRef"
              :options="calendarOptions"
              style="height: 500px;"
            />
          </q-card-section>
        </q-card>
      </div>
      
      <!-- Gráfico de ingresos -->
      <div class="col-12 col-lg-4">
        <q-card>
          <q-card-section>
            <div class="text-h6 q-mb-md">Ingresos Últimos 6 Meses</div>
            <Bar :data="revenueChartData" :options="chartOptions" />
          </q-card-section>
        </q-card>
        
        <q-card class="q-mt-md">
          <q-card-section>
            <div class="text-h6 q-mb-md">Accesos Rápidos</div>
            <q-list>
              <q-item clickable v-ripple @click="$router.push('/dashboard/bookings')">
                <q-item-section avatar>
                  <q-icon name="book_online" color="primary" />
                </q-item-section>
                <q-item-section>Nueva Reserva</q-item-section>
              </q-item>
              
              <q-item clickable v-ripple @click="$router.push('/dashboard/properties')">
                <q-item-section avatar>
                  <q-icon name="home_work" color="secondary" />
                </q-item-section>
                <q-item-section>Gestionar Inmuebles</q-item-section>
              </q-item>
              
              <q-item clickable v-ripple @click="$router.push('/dashboard/rates')">
                <q-item-section avatar>
                  <q-icon name="price_change" color="accent" />
                </q-item-section>
                <q-item-section>Configurar Tarifas</q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Últimas reservas -->
    <q-card class="q-mt-md">
      <q-card-section>
        <div class="text-h6 q-mb-md">Últimas Reservas</div>
        <q-table
          :rows="recentBookings"
          :columns="columns"
          row-key="id"
          flat
          bordered
          :loading="loading"
          @row-click="goToBooking"
        >
          <template v-slot:body-cell-status="props">
            <q-td :props="props">
              <q-badge :color="getStatusColor(props.row.status)">
                {{ getStatusLabel(props.row.status) }}
              </q-badge>
            </q-td>
          </template>
          
          <template v-slot:body-cell-payment_status="props">
            <q-td :props="props">
              <q-badge :color="getPaymentStatusColor(props.row.payment_status)">
                {{ getPaymentStatusLabel(props.row.payment_status) }}
              </q-badge>
            </q-td>
          </template>
        </q-table>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend } from 'chart.js';
import { useBookingStore } from 'stores/booking';
import { usePropertyStore } from 'stores/property';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const router = useRouter();
const $q = useQuasar();
const bookingStore = useBookingStore();
const propertyStore = usePropertyStore();

const loading = ref(false);
const calendarRef = ref(null);

// Métricas
const revenueMonth = computed(() => bookingStore.totalRevenue);
const confirmedBookingsCount = computed(() => bookingStore.confirmedBookings);
const pendingBookingsCount = computed(() => bookingStore.pendingBookings);
const occupancyRate = ref(75); // TODO: Calcular dinámicamente

// Configuración del calendario
const calendarOptions = ref({
  plugins: [dayGridPlugin, interactionPlugin],
  locale: esLocale,
  initialView: 'dayGridMonth',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek'
  },
  events: [],
  eventClick: (info: any) => {
    router.push(`/dashboard/bookings/${info.event.id}`);
  },
  dateClick: (info: any) => {
    router.push({ 
      path: '/dashboard/bookings/new', 
      query: { date: info.dateStr } 
    });
  }
});

// Datos del gráfico
const revenueChartData = computed(() => ({
  labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
  datasets: [{
    label: 'Ingresos (€)',
    data: [1200, 1900, 3000, 2500, 2800, 3200], // TODO: Datos reales
    backgroundColor: 'rgba(59, 130, 246, 0.7)',
    borderColor: 'rgba(59, 130, 246, 1)',
    borderWidth: 1
  }]
}));

const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  }
});

// Columnas de la tabla
const columns = [
  { name: 'guest_name', label: 'Huésped', field: 'guest_details.name', align: 'left' },
  { name: 'property', label: 'Inmueble', field: (row: any) => row.property?.name || '-', align: 'left' },
  { name: 'check_in', label: 'Entrada', field: 'check_in', format: (val: string) => formatDate(val), align: 'center' },
  { name: 'check_out', label: 'Salida', field: 'check_out', format: (val: string) => formatDate(val), align: 'center' },
  { name: 'total_amount', label: 'Total', field: 'total_amount', format: (val: number) => formatCurrency(val), align: 'right' },
  { name: 'status', label: 'Estado', field: 'status', align: 'center' },
  { name: 'payment_status', label: 'Pago', field: 'payment_status', align: 'center' }
];

// Últimas reservas
const recentBookings = computed(() => bookingStore.bookings.slice(0, 10));

// Funciones auxiliares
const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(value);
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('es-ES');
};

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    confirmed: 'positive',
    pending: 'warning',
    cancelled: 'negative',
    completed: 'info'
  };
  return colors[status] || 'grey';
};

const getStatusLabel = (status: string) => {
  const labels: Record<string, string> = {
    confirmed: 'Confirmada',
    pending: 'Pendiente',
    cancelled: 'Cancelada',
    completed: 'Completada'
  };
  return labels[status] || status;
};

const getPaymentStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    paid: 'positive',
    partial: 'warning',
    unpaid: 'negative',
    refunded: 'info'
  };
  return colors[status] || 'grey';
};

const getPaymentStatusLabel = (status: string) => {
  const labels: Record<string, string> = {
    paid: 'Pagado',
    partial: 'Parcial',
    unpaid: 'No Pagado',
    refunded: 'Reembolsado'
  };
  return labels[status] || status;
};

const goToBooking = (evt: Event, row: any) => {
  router.push(`/dashboard/bookings/${row.id}`);
};

// Cargar datos iniciales
onMounted(async () => {
  loading.value = true;
  try {
    await Promise.all([
      bookingStore.fetchBookings({ limit: 10 }),
      propertyStore.fetchProperties()
    ]);
    
    // Cargar eventos del calendario
    calendarOptions.value.events = bookingStore.bookings.map((booking: any) => ({
      id: booking.id.toString(),
      title: `${booking.guest_details?.name || 'Reserva'} - ${booking.property?.name || ''}`,
      start: booking.check_in,
      end: booking.check_out,
      backgroundColor: booking.status === 'confirmed' ? '#4CAF50' : '#FF9800'
    }));
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Error cargando datos del dashboard'
    });
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.q-card {
  transition: all 0.3s ease;
}

.q-card:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
</style>
