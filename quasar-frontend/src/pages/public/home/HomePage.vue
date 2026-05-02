<template>
  <q-page class="q-pa-md">
    <!-- Hero Section con Buscador -->
    <div class="hero-section q-pa-xl text-center text-white q-mb-lg" :style="{ background: `linear-gradient(135deg, ${tenantStore.primaryColor}, ${tenantStore.secondaryColor})` }">
      <h1 class="text-h3 text-weight-bold q-mb-md">Encuentra tu alojamiento perfecto</h1>
      <p class="text-h6 q-mb-xl">Explora nuestras propiedades exclusivas</p>

      <!-- Buscador Principal -->
      <div class="search-container bg-white rounded-borders shadow-2 q-pa-md inline-block" style="min-width: 800px; max-width: 100%;">
        <div class="row q-col-gutter-md items-end">
          <div class="col-12 col-md-4">
            <q-input
              v-model="search.location"
              outlined
              label="Ubicación"
              dense
            >
              <template v-slot:prepend>
                <q-icon name="location_on" />
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-3">
            <q-input
              v-model="search.checkIn"
              outlined
              label="Llegada"
              dense
            >
              <template v-slot:append>
                <q-icon name="event" class="cursor-pointer">
                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                    <q-date v-model="search.checkIn" mask="YYYY-MM-DD">
                      <div class="row items-center justify-end">
                        <q-btn v-close-popup label="Cerrar" color="primary" flat />
                      </div>
                    </q-date>
                  </q-popup-proxy>
                </q-icon>
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-3">
            <q-input
              v-model="search.checkOut"
              outlined
              label="Salida"
              dense
            >
              <template v-slot:append>
                <q-icon name="event" class="cursor-pointer">
                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                    <q-date v-model="search.checkOut" mask="YYYY-MM-DD">
                      <div class="row items-center justify-end">
                        <q-btn v-close-popup label="Cerrar" color="primary" flat />
                      </div>
                    </q-date>
                  </q-popup-proxy>
                </q-icon>
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-2">
            <q-btn
              :loading="loading"
              :label="loading ? 'Buscando...' : 'Buscar'"
              color="primary"
              class="full-width"
              size="lg"
              @click="performSearch"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Filtros Rápidos -->
    <div class="row q-col-gutter-sm q-mb-lg">
      <div class="col-auto">
        <q-btn-toggle
          v-model="filters.propertyType"
          toggle-color="primary"
          :options="[
            {label: 'Todos', value: 'all'},
            {label: 'Casa', value: 'house'},
            {label: 'Apartamento', value: 'apartment'},
            {label: 'Villa', value: 'villa'}
          ]"
          outline
        />
      </div>
      
      <div class="col-auto">
        <q-select
          v-model="filters.zone"
          :options="zones"
          outlined
          dense
          label="Zona"
          emit-value
          map-options
          style="min-width: 200px"
        />
      </div>
    </div>

    <!-- Listado de Propiedades -->
    <div class="row q-col-gutter-lg">
      <div 
        v-for="property in properties" 
        :key="property.id" 
        class="col-12 col-md-6 col-lg-4"
      >
        <q-card class="property-card cursor-pointer" @click="goToProperty(property.id)">
          <q-img
            :src="property.images?.[0]?.url || 'https://cdn.quasar.dev/img/parallax2.jpg'"
            class="card-image"
          >
            <div class="absolute-top-right bg-primary text-white q-pa-xs rounded-borders-left">
              {{ property.price_per_night }}€ / noche
            </div>
            
            <q-badge v-if="property.is_available" color="green" class="absolute-bottom-left q-ma-md">
              Disponible
            </q-badge>
          </q-img>

          <q-card-section>
            <div class="text-h6 text-weight-bold">{{ property.name }}</div>
            <div class="text-caption text-grey-7">
              <q-icon name="location_on" size="xs" />
              {{ property.address }}
            </div>
          </q-card-section>

          <q-card-section horizontal>
            <q-card-section class="col-6 q-pa-sm">
              <q-icon name="bed" size="sm" /> {{ property.bedrooms }} hab
            </q-card-section>
            <q-card-section class="col-6 q-pa-sm">
              <q-icon name="people" size="sm" /> {{ property.max_guests }} huéspedes
            </q-card-section>
          </q-card-section>

          <q-separator />

          <q-card-actions align="right">
            <q-btn flat color="primary" label="Ver detalles" />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Estado vacío -->
    <div v-if="!loading && properties.length === 0" class="text-center q-pa-xl">
      <q-icon name="search_off" size="64px" color="grey-5" />
      <p class="text-h6 text-grey-7 q-mt-md">No se encontraron propiedades</p>
      <p class="text-caption text-grey-6">Intenta ajustar los filtros de búsqueda</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots color="primary" size="50px" />
    </div>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore } from 'stores/tenant'
import { api } from 'boot/axios'

const router = useRouter()
const tenantStore = useTenantStore()

const loading = ref(false)
const properties = ref([])
const zones = ref([])

const search = reactive({
  location: '',
  checkIn: '',
  checkOut: ''
})

const filters = reactive({
  propertyType: 'all',
  zone: null
})

onMounted(async () => {
  await loadProperties()
  await loadZones()
})

const loadProperties = async () => {
  loading.value = true
  try {
    const response = await api.get('/public/properties')
    properties.value = response.data
  } catch (error) {
    console.error('Error cargando propiedades:', error)
  } finally {
    loading.value = false
  }
}

const loadZones = async () => {
  try {
    // Cargar zonas disponibles
    zones.value = [
      { label: 'Todas las zonas', value: null },
      { label: 'Centro', value: 'center' },
      { label: 'Playa', value: 'beach' },
      { label: 'Montaña', value: 'mountain' }
    ]
  } catch (error) {
    console.error('Error cargando zonas:', error)
  }
}

const performSearch = async () => {
  loading.value = true
  try {
    const params = {
      ...search,
      property_type: filters.propertyType !== 'all' ? filters.propertyType : null,
      zone: filters.zone
    }
    
    const response = await api.get('/public/properties', { params })
    properties.value = response.data
  } catch (error) {
    console.error('Error en búsqueda:', error)
  } finally {
    loading.value = false
  }
}

const goToProperty = (propertyId) => {
  router.push({ name: 'property-detail', params: { id: propertyId } })
}
</script>

<style scoped>
.hero-section {
  border-radius: 0 0 20px 20px;
  padding: 60px 20px;
}

.search-container {
  border-radius: 12px;
}

.property-card {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.property-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.card-image {
  height: 200px;
}

.rounded-borders-left {
  border-radius: 0 0 0 8px;
}
</style>
