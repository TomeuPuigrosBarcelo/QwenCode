<template>
  <q-page class="q-pa-md">
    <div class="row justify-between items-center q-mb-lg">
      <div class="text-h5">Gestión de Inmuebles</div>
      <q-btn
        color="primary"
        label="Nuevo Inmueble"
        icon="add_home"
        @click="goToNewProperty"
      />
    </div>

    <!-- Filtros y Búsqueda -->
    <q-card class="q-mb-md">
      <q-card-section>
        <div class="row q-col-gutter-md">
          <div class="col-12 col-md-6">
            <q-input
              v-model="search"
              outlined
              dense
              label="Buscar por nombre o dirección"
              clearable
            >
              <template v-slot:prepend>
                <q-icon name="search" />
              </template>
            </q-input>
          </div>
          <div class="col-12 col-md-4">
            <q-select
              v-model="filterStatus"
              :options="statusOptions"
              outlined
              dense
              label="Estado"
              clearable
            />
          </div>
          <div class="col-12 col-md-2">
            <q-btn
              color="secondary"
              label="Filtrar"
              icon="filter_list"
              class="full-width"
              @click="applyFilters"
            />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Lista de Inmuebles -->
    <div class="row q-col-gutter-md">
      <div
        v-for="property in filteredProperties"
        :key="property.id"
        class="col-12 col-md-6 col-lg-4"
      >
        <q-card class="cursor-pointer" @click="editProperty(property)">
          <q-img
            :src="property.images?.[0]?.url || '/placeholder-property.jpg'"
            height="200px"
          >
            <div class="absolute-top-right bg-black q-pa-sm">
              <q-badge :color="property.is_active ? 'positive' : 'negative'">
                {{ property.is_active ? 'Activo' : 'Inactivo' }}
              </q-badge>
            </div>
          </q-img>

          <q-card-section>
            <div class="text-h6 ellipsis">{{ property.name }}</div>
            <div class="text-subtitle2 text-grey-7 ellipsis">
              <q-icon name="location_on" size="xs" />
              {{ property.address }}
            </div>
          </q-card-section>

          <q-card-section horizontal>
            <q-card-section class="col-6 q-pa-none">
              <div class="text-caption text-grey">Superficie Casa</div>
              <div>{{ property.area_house_m2 || '-' }} m²</div>
            </q-card-section>
            <q-card-section class="col-6 q-pa-none">
              <div class="text-caption text-grey">Superficie Finca</div>
              <div>{{ property.area_land_m2 || '-' }} m²</div>
            </q-card-section>
          </q-card-section>

          <q-card-actions align="right">
            <q-btn flat dense color="primary" icon="edit" label="Editar" />
            <q-btn flat dense color="secondary" icon="calendar_today" label="Tarifas" @click.stop="goToRates(property.id)" />
            <q-btn flat dense color="negative" icon="visibility" label="Ver" @click.stop="viewProperty(property.id)" />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Mensaje si no hay resultados -->
    <div v-if="filteredProperties.length === 0 && !loading" class="text-center q-pa-xl">
      <q-icon name="home_work" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">No se encontraron inmuebles</div>
      <q-btn
        flat
        color="primary"
        label="Crear primer inmueble"
        @click="goToNewProperty"
      />
    </div>

    <!-- Loading -->
    <q-inner-loading :showing="loading">
      <q-spinner-gears size="50px" color="primary" />
    </q-inner-loading>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import { usePropertyStore } from 'stores/property';

const router = useRouter();
const $q = useQuasar();
const propertyStore = usePropertyStore();

const loading = ref(false);
const search = ref('');
const filterStatus = ref<boolean | null>(null);

const statusOptions = [
  { label: 'Activos', value: true },
  { label: 'Inactivos', value: false }
];

const filteredProperties = computed(() => {
  let properties = propertyStore.properties;

  // Filtro por búsqueda
  if (search.value) {
    const searchTerm = search.value.toLowerCase();
    properties = properties.filter(p =>
      p.name.toLowerCase().includes(searchTerm) ||
      p.address.toLowerCase().includes(searchTerm)
    );
  }

  // Filtro por estado
  if (filterStatus.value !== null) {
    properties = properties.filter(p => p.is_active === filterStatus.value);
  }

  return properties;
});

const applyFilters = () => {
  // La computada filteredProperties ya aplica los filtros automáticamente
};

const goToNewProperty = () => {
  router.push('/dashboard/properties/new');
};

const editProperty = (property: any) => {
  router.push(`/dashboard/properties/${property.id}/edit`);
};

const viewProperty = (id: number) => {
  window.open(`/property/${id}`, '_blank');
};

const goToRates = (propertyId: number) => {
  router.push(`/dashboard/rates?property_id=${propertyId}`);
};

onMounted(async () => {
  loading.value = true;
  try {
    await propertyStore.fetchProperties();
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: 'Error cargando los inmuebles'
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
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
  transform: translateY(-2px);
}

.ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
