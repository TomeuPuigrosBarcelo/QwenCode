<template>
  <q-layout view="hHh lpR fFf">
    <q-header :style="{ backgroundColor: tenantStore.primaryColor }" elevated>
      <q-toolbar>
        <q-toolbar-title>
          <div class="row items-center no-wrap">
            <img v-if="tenantStore.logoUrl" :src="tenantStore.logoUrl" alt="Logo" class="q-mr-sm logo-header" style="height: 40px;">
            <span>{{ tenantStore.tenantName }}</span>
          </div>
        </q-toolbar-title>

        <q-btn flat round dense icon="language" @click="toggleLanguage">
          <q-menu>
            <q-list style="min-width: 100px">
              <q-item clickable v-close-popup @click="changeLocale('es')">
                <q-item-section>Español</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="changeLocale('en')">
                <q-item-section>English</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="changeLocale('fr')">
                <q-item-section>Français</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>

        <q-btn flat round dense icon="search" @click="showSearch = !showSearch" />
        
        <q-btn flat round dense icon="shopping_cart" badge-color="red" :badge="cartCount" />
      </q-toolbar>

      <!-- Barra de búsqueda -->
      <q-slide-transition>
        <div v-show="showSearch" class="q-pa-md bg-white text-grey-8">
          <q-input
            v-model="searchQuery"
            outlined
            dense
            placeholder="Buscar por ubicación, fechas..."
            @keyup.enter="performSearch"
          >
            <template v-slot:append>
              <q-btn round dense flat icon="search" @click="performSearch" />
            </template>
          </q-input>
        </div>
      </q-slide-transition>
    </q-header>

    <q-page-container>
      <router-view />
    </q-page-container>

    <q-footer class="bg-grey-8 text-white">
      <q-toolbar>
        <q-toolbar-title class="text-center text-caption">
          © {{ new Date().getFullYear() }} {{ tenantStore.tenantName }}. Todos los derechos reservados.
        </q-toolbar-title>
      </q-toolbar>
    </q-footer>
  </q-layout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useTenantStore } from 'stores/tenant'
import { useRouter } from 'vue-router'

const tenantStore = useTenantStore()
const router = useRouter()

const showSearch = ref(false)
const searchQuery = ref('')
const cartCount = ref(0)

const toggleLanguage = () => {
  // Implementar cambio de idioma
}

const changeLocale = (locale) => {
  // Implementar cambio de locale
  console.log('Cambiando a:', locale)
}

const performSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ path: '/', query: { q: searchQuery.value } })
  }
}
</script>

<style scoped>
.logo-header {
  max-height: 40px;
  width: auto;
}
</style>
