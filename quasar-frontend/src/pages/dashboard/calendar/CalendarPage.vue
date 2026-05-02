<template>
  <q-page class="q-pa-md">
    <div class="row justify-between items-center q-mb-lg">
      <div class="text-h5">Calendario de Reservas</div>
      <div>
        <q-btn-toggle
          v-model="viewMode"
          toggle-color="primary"
          :options="[
            {label: 'Mes', value: 'dayGridMonth'},
            {label: 'Semana', value: 'timeGridWeek'},
            {label: 'Día', value: 'timeGridDay'}
          ]"
          unelevated
        />
        <q-btn
          color="primary"
          label="Nueva Reserva"
          icon="add"
          class="q-ml-md"
          @click="goToNewBooking"
        />
      </div>
    </div>

    <q-card>
      <q-card-section>
        <!-- Filtros -->
        <div class="row q-col-gutter-md q-mb-md">
          <div class="col-12 col-md-4">
            <q-select
              v-model="selectedProperty"
              :options="propertyOptions"
              option-value="id"
              option-label="name"
              label="Filtrar por Inmueble"
              outlined
              clearable
              dense
            />
          </div>
          <div class="col-12 col-md-4">
            <q-select
              v-model="selectedStatus"
              :options="statusOptions"
              label="Filtrar por Estado"
              outlined
              clearable
              dense
            />
          </div>
          <div class="col-12 col-md-4">
            <q-btn
              color="secondary"
              label="Aplicar Filtros"
              icon="filter_list"
              @click="applyFilters"
            />
          </div>
        </div>

        <!-- Calendario FullCalendar -->
        <FullCalendar
          ref="calendarRef"
          :options="calendarOptions"
          style="height: 650px;"
        />
      </q-card-section>
    </q-card>

    <!-- Diálogo de detalles de reserva -->
    <q-dialog v-model="showBookingDialog" persistent>
      <q-card style="min-width: 400px;">
        <q-card-section>
          <div class="text-h6">Detalles de Reserva</div>
        </q-card-section>

        <q-card-section v-if="selectedBooking" class="q-pt-none">
          <div class="text-subtitle2 q-mb-sm">{{ selectedBooking.guest_details?.name }}</div>
          <div class="row q-col-gutter-sm">
            <div class="col-6">
              <div class="text-caption text-grey">Entrada</div>
              <div>{{ formatDate(selectedBooking.check_in) }}</div>
            </div>
            <div class="col-6">
              <div class="text-caption text-grey">Salida</div>
              <div>{{ formatDate(selectedBooking.check_out) }}</div>
            </div>
            <div class="col-6">
              <div class="text-caption text-grey">Inmueble</div>
              <div>{{ selectedBooking.property?.name || '-' }}</div>
            </div>
            <div class="col-6">
              <div class="text-caption text-grey">Total</div>
              <div>{{ formatCurrency(selectedBooking.total_amount) }}</div>
            </div>
            <div class="col-12">
              <div class="text-caption text-grey">Estado</div>
              <q-badge :color="getStatusColor(selectedBooking.status)">
                {{ getStatusLabel(selectedBooking.status) }}
              </q-badge>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cerrar" color="grey" v-close-popup />
          <q-btn
            flat
            label="Ver Detalles"
            color="primary"
            @click="viewBookingDetails"
            v-close-popup
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';
import { useBookingStore } from 'stores/booking';
import { usePropertyStore } from 'stores/property';

const router = useRouter();
const $q = useQuasar();
const bookingStore = useBookingStore();
const propertyStore = usePropertyStore();

const viewMode = ref('dayGridMonth');
const selectedProperty = ref<number | null>(null);
const selectedStatus = ref<string | null>(null);
const showBookingDialog = ref(false);
const selectedBooking = ref<any>(null);
const calendarRef = ref(null);

const statusOptions = [
  { label: 'Confirmada', value: 'confirmed' },
  { label: 'Pendiente', value: 'pending' },
  { label: 'Cancelada', value: 'cancelled' },
  { label: 'Completada', value: 'completed' }
];

const propertyOptions = computed(() => 
  propertyStore.properties.map(p => ({ id: p.id, name: p.name }))
);

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  locale: esLocale,
  initialView: viewMode.value,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay'
  },
  buttonText: {
    today: 'Hoy',
    month: 'Mes',
    week: 'Semana',
    day: 'Día'
  },
  events: [],
  eventClick: (info: any) => {
    const booking = bookingStore.bookings.find((b: any) => b.id.toString() === info.event.id);
    if (booking) {
      selectedBooking.value = booking;
      showBookingDialog.value = true;
    }
  },
  dateClick: (info: any) => {
    router.push({
      path: '/dashboard/bookings/new',
      query: { date: info.dateStr, property_id: selectedProperty.value || undefined }
    });
  },
  eventContent: (eventInfo: any) => {
    return {
      html: `
        <div style="font-size: 12px; padding: 2px;">
          <strong>${eventInfo.event.title}</strong><br>
          ${eventInfo.event.extendedProps.guest || ''}
        </div>
      `
    };
  }
}));

watch(viewMode, (newMode) => {
  if (calendarRef.value) {
    const calendarApi = (calendarRef.value as any).getApi();
    calendarApi.changeView(newMode);
  }
});

const applyFilters = async () => {
  await bookingStore.fetchBookings({
    property_id: selectedProperty.value,
    status: selectedStatus.value
  });
  updateCalendarEvents();
};

const updateCalendarEvents = () => {
  if (calendarRef.value) {
    const calendarApi = (calendarRef.value as any).getApi();
    calendarApi.removeAllEvents();
    
    bookingStore.bookings.forEach((booking: any) => {
      calendarApi.addEvent({
        id: booking.id.toString(),
        title: `${booking.guest_details?.name || 'Reserva'}`,
        start: booking.check_in,
        end: booking.check_out,
        backgroundColor: getEventColor(booking.status),
        extendedProps: {
          guest: booking.guest_details?.name,
          property: booking.property?.name
        }
      });
    });
  }
};

const getEventColor = (status: string) => {
  const colors: Record<string, string> = {
    confirmed: '#4CAF50',
    pending: '#FF9800',
    cancelled: '#F44336',
    completed: '#2196F3'
  };
  return colors[status] || '#9E9E9E';
};

const goToNewBooking = () => {
  router.push('/dashboard/bookings/new');
};

const viewBookingDetails = () => {
  if (selectedBooking.value) {
    router.push(`/dashboard/bookings/${selectedBooking.value.id}`);
  }
};

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(value);
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
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

onMounted(async () => {
  try {
    await Promise.all([
      bookingStore.fetchBookings(),
      propertyStore.fetchProperties()
    ]);
    updateCalendarEvents();
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Error cargando el calendario'
    });
  }
});
</script>

<style scoped>
.fc-event {
  cursor: pointer;
  border: none;
}

.fc-daygrid-day-number {
  font-weight: 500;
}
</style>
