<template>
  <q-card class="property-card cursor-pointer" @click="$emit('click')">
    <q-img
      :src="mainImage || '/placeholder-property.jpg'"
      :alt="property.name"
      style="height: 200px"
    >
      <template v-slot:error>
        <div class="full-height flex flex-center bg-grey-5">
          <q-icon name="apartment" size="48px" color="grey-5" />
        </div>
      </template>
    </q-img>

    <q-card-section>
      <div class="text-h6 text-weight-medium ellipsis">{{ property.name }}</div>
      
      <div class="row items-center q-gutter-xs q-mt-sm text-caption text-grey-7">
        <q-icon name="location_on" size="xs" />
        <span class="ellipsis col">{{ property.address }}</span>
      </div>

      <div class="row items-center q-gutter-md q-mt-md">
        <div class="row items-center q-gutter-xs">
          <q-icon name="bed" size="xs" color="primary" />
          <span class="text-caption">{{ property.bedrooms || '-' }} hab</span>
        </div>
        <div class="row items-center q-gutter-xs">
          <q-icon name="people" size="xs" color="primary" />
          <span class="text-caption">{{ property.max_guests || '-' }} huéspedes</span>
        </div>
        <div class="row items-center q-gutter-xs">
          <q-icon name="square_foot" size="xs" color="primary" />
          <span class="text-caption">{{ property.area_house_m2 || '-' }} m²</span>
        </div>
      </div>

      <div class="row justify-between items-center q-mt-lg">
        <div>
          <span class="text-h5 text-weight-bold text-primary">{{ formatPrice(property.price_per_night) }}</span>
          <span class="text-caption text-grey-7"> / noche</span>
        </div>
        
        <q-btn
          flat
          color="primary"
          label="Ver detalle"
          icon-right="arrow_forward"
          size="sm"
        />
      </div>
    </q-card-section>
  </q-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Property {
  id: number
  name: string
  address: string
  price_per_night?: number
  bedrooms?: number
  max_guests?: number
  area_house_m2?: number
  images?: Array<{ url: string }>
}

const props = defineProps<{
  property: Property
  compact?: boolean
}>()

defineEmits<{
  (e: 'click'): void
}>()

const mainImage = computed(() => {
  return props.property.images?.[0]?.url || null
})

function formatPrice(price?: number): string {
  if (!price) return 'Consultar'
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
  }).format(price)
}
</script>

<style scoped>
.property-card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.property-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
}
</style>
