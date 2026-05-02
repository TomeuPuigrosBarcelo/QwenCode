<template>
  <q-page class="q-pa-md">
    <div class="row justify-between items-center q-mb-lg">
      <div class="text-h5">Tarifas por Temporada</div>
      <div>
        <q-select
          v-model="selectedProperty"
          :options="propertyOptions"
          option-value="id"
          option-label="name"
          label="Seleccionar Inmueble"
          outlined
          dense
          style="min-width: 300px;"
          @update:model-value="loadRates"
        />
        <q-btn
          color="primary"
          label="Nueva Tarifa"
          icon="add"
          class="q-ml-md"
          @click="showNewRateDialog = true"
          :disable="!selectedProperty"
        />
      </div>
    </div>

    <!-- Calendario de tarifas -->
    <q-card v-if="selectedProperty">
      <q-card-section>
        <div class="text-subtitle1 q-mb-md">
          Visualización de Tarifas - {{ selectedPropertyName }}
        </div>

        <!-- Tabla de tarifas -->
        <q-table
          :rows="rates"
          :columns="columns"
          row-key="id"
          flat
          bordered
          :loading="loading"
          :pagination="{ rowsPerPage: 20 }"
        >
          <template v-slot:body-cell-start_date="props">
            <q-td :props="props">
              {{ formatDate(props.row.start_date) }}
            </q-td>
          </template>

          <template v-slot:body-cell-end_date="props">
            <q-td :props="props">
              {{ formatDate(props.row.end_date) }}
            </q-td>
          </template>

          <template v-slot:body-cell-price_per_night="props">
            <q-td :props="props">
              <span class="text-weight-bold text-primary">
                {{ formatCurrency(props.row.price_per_night) }}
              </span>
            </q-td>
          </template>

          <template v-slot:body-cell-min_stay_override="props">
            <q-td :props="props">
              {{ props.row.min_stay_override || 'Default' }}
            </q-td>
          </template>

          <template v-slot:body-cell-actions="props">
            <q-td :props="props">
              <q-btn
                flat
                dense
                color="primary"
                icon="edit"
                @click="editRate(props.row)"
              />
              <q-btn
                flat
                dense
                color="negative"
                icon="delete"
                @click="confirmDelete(props.row)"
              />
            </q-td>
          </template>
        </q-table>
      </q-card-section>
    </q-card>

    <!-- Mensaje si no hay propiedad seleccionada -->
    <div v-else class="text-center q-pa-xl">
      <q-icon name="price_change" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">
        Selecciona un inmueble para gestionar sus tarifas
      </div>
    </div>

    <!-- Diálogo para añadir/editar tarifa -->
    <q-dialog v-model="showNewRateDialog" persistent>
      <q-card style="min-width: 400px;">
        <q-card-section>
          <div class="text-h6">{{ editingRate ? 'Editar' : 'Nueva' }} Tarifa</div>
        </q-card-section>

        <q-card-section>
          <q-form @submit="saveRate">
            <q-input
              v-model="rateForm.start_date"
              type="date"
              label="Fecha Inicio"
              outlined
              :rules="[val => !!val || 'Fecha inicio requerida']"
            />

            <q-input
              v-model="rateForm.end_date"
              type="date"
              label="Fecha Fin"
              outlined
              class="q-mt-md"
              :rules="[
                val => !!val || 'Fecha fin requerida',
                val => val >= rateForm.start_date || 'Debe ser posterior a la fecha inicio'
              ]"
            />

            <q-input
              v-model.number="rateForm.price_per_night"
              type="number"
              label="Precio por Noche (€)"
              outlined
              class="q-mt-md"
              prefix="€"
              :step="0.01"
              :rules="[
                val => !!val || 'Precio requerido',
                val => val > 0 || 'Debe ser mayor a 0'
              ]"
            />

            <q-input
              v-model.number="rateForm.min_stay_override"
              type="number"
              label="Mínimo de Noches (Opcional)"
              outlined
              class="q-mt-md"
              hint="Dejar vacío para usar el default del inmueble"
              :min="1"
            />

            <div class="q-mt-lg">
              <q-btn
                type="submit"
                color="primary"
                label="Guardar"
                :loading="saving"
              />
              <q-btn
                flat
                color="grey"
                label="Cancelar"
                class="q-ml-sm"
                v-close-popup
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useQuasar } from 'quasar';
import { usePropertyStore } from 'stores/property';
import { useRateStore } from 'stores/rate';

const route = useRoute();
const $q = useQuasar();
const propertyStore = usePropertyStore();
const rateStore = useRateStore();

const loading = ref(false);
const saving = ref(false);
const showNewRateDialog = ref(false);
const selectedProperty = ref<number | null>(null);
const editingRate = ref<any>(null);

const rateForm = ref({
  start_date: '',
  end_date: '',
  price_per_night: null as number | null,
  min_stay_override: null as number | null
});

const columns = [
  { name: 'start_date', label: 'Desde', field: 'start_date', align: 'left' },
  { name: 'end_date', label: 'Hasta', field: 'end_date', align: 'left' },
  { name: 'price_per_night', label: 'Precio/Noche', field: 'price_per_night', align: 'right' },
  { name: 'min_stay_override', label: 'Estancia Mín.', field: 'min_stay_override', align: 'center' },
  { name: 'actions', label: 'Acciones', field: 'actions', align: 'center' }
];

const rates = computed(() => rateStore.rates);

const propertyOptions = computed(() => 
  propertyStore.properties.map(p => ({ id: p.id, name: p.name }))
);

const selectedPropertyName = computed(() => {
  const prop = propertyStore.properties.find(p => p.id === selectedProperty.value);
  return prop?.name || '';
});

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(value);
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('es-ES');
};

const loadRates = async () => {
  if (!selectedProperty.value) return;
  
  loading.value = true;
  try {
    await rateStore.fetchRates(selectedProperty.value);
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Error cargando tarifas'
    });
  } finally {
    loading.value = false;
  }
};

const editRate = (rate: any) => {
  editingRate.value = rate;
  rateForm.value = {
    start_date: rate.start_date,
    end_date: rate.end_date,
    price_per_night: rate.price_per_night,
    min_stay_override: rate.min_stay_override
  };
  showNewRateDialog.value = true;
};

const saveRate = async () => {
  saving.value = true;
  try {
    const payload = {
      ...rateForm.value,
      property_id: selectedProperty.value
    };

    if (editingRate.value) {
      await rateStore.updateRate(editingRate.value.id, payload);
      $q.notify({
        type: 'positive',
        message: 'Tarifa actualizada correctamente'
      });
    } else {
      await rateStore.createRate(payload);
      $q.notify({
        type: 'positive',
        message: 'Tarifa creada correctamente'
      });
    }

    showNewRateDialog.value = false;
    resetForm();
    loadRates();
  } catch (error: any) {
    $q.notify({
      type: 'negative',
      message: error.response?.data?.message || 'Error guardando tarifa'
    });
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (rate: any) => {
  $q.dialog({
    title: 'Confirmar Eliminación',
    message: `¿Estás seguro de eliminar la tarifa del ${formatDate(rate.start_date)} al ${formatDate(rate.end_date)}?`,
    cancel: true,
    persistent: true
  }).onOk(async () => {
    try {
      await rateStore.deleteRate(rate.id);
      $q.notify({
        type: 'positive',
        message: 'Tarifa eliminada correctamente'
      });
      loadRates();
    } catch (error) {
      $q.notify({
        type: 'negative',
        message: 'Error eliminando tarifa'
      });
    }
  });
};

const resetForm = () => {
  editingRate.value = null;
  rateForm.value = {
    start_date: '',
    end_date: '',
    price_per_night: null,
    min_stay_override: null
  };
};

onMounted(async () => {
  try {
    await propertyStore.fetchProperties();
    
    // Si viene de la URL con property_id
    const propertyIdFromUrl = route.query.property_id as string;
    if (propertyIdFromUrl) {
      selectedProperty.value = parseInt(propertyIdFromUrl);
      loadRates();
    }
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Error cargando datos iniciales'
    });
  }
});
</script>

<style scoped>
.q-table__row:hover {
  background-color: rgba(0, 0, 0, 0.02);
}
</style>
