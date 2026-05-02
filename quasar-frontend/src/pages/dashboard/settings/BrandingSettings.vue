<template>
  <q-page class="q-pa-md">
    <div class="row q-col-gutter-md items-center q-mb-md">
      <div class="col-xs-12 col-sm-6">
        <h4 class="q-my-none text-primary">Configuración de Marca y Web</h4>
      </div>
    </div>

    <q-form @submit="onSubmit" class="q-gutter-md">
      <div class="row q-col-gutter-md">
        <!-- Columna Izquierda: Branding -->
        <div class="col-xs-12 col-lg-8">
          <q-card class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Identidad Visual</div>
              
              <div class="row q-col-gutter-md">
                <div class="col-xs-12 col-sm-6">
                  <div class="text-caption q-mb-sm">Logo Principal</div>
                  <q-file
                    v-model="form.logo"
                    outlined
                    label="Subir Logo"
                    accept="image/*"
                    @update:model-value="previewLogo = $event ? URL.createObjectURL($event) : ''"
                  >
                    <template v-slot:prepend>
                      <q-icon name="cloud_upload" />
                    </template>
                  </q-file>
                  <div v-if="currentLogo" class="q-mt-md text-center">
                    <img :src="currentLogo" alt="Logo actual" style="max-height: 80px; max-width: 100%;" />
                    <div class="text-caption text-grey-7 q-mt-xs">Logo actual</div>
                  </div>
                  <div v-if="previewLogo && !currentLogo" class="q-mt-md text-center">
                    <img :src="previewLogo" alt="Vista previa" style="max-height: 80px; max-width: 100%;" />
                    <div class="text-caption text-grey-7 q-mt-xs">Vista previa</div>
                  </div>
                </div>

                <div class="col-xs-12 col-sm-6">
                  <div class="text-caption q-mb-sm">Favicon</div>
                  <q-file
                    v-model="form.favicon"
                    outlined
                    label="Subir Favicon"
                    accept="image/x-icon,image/png"
                    @update:model-value="previewFavicon = $event ? URL.createObjectURL($event) : ''"
                  >
                    <template v-slot:prepend>
                      <q-icon name="cloud_upload" />
                    </template>
                  </q-file>
                  <div v-if="currentFavicon" class="q-mt-md text-center">
                    <img :src="currentFavicon" alt="Favicon actual" style="height: 32px; width: 32px;" />
                    <div class="text-caption text-grey-7 q-mt-xs">Favicon actual</div>
                  </div>
                  <div v-if="previewFavicon && !currentFavicon" class="q-mt-md text-center">
                    <img :src="previewFavicon" alt="Vista previa" style="height: 32px; width: 32px;" />
                    <div class="text-caption text-grey-7 q-mt-xs">Vista previa</div>
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <q-card class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Colores de la Marca</div>
              
              <div class="row q-col-gutter-md">
                <div class="col-xs-12 col-sm-6">
                  <q-input
                    v-model="form.primary_color"
                    type="color"
                    outlined
                    label="Color Primario"
                    hint="Color principal de botones y enlaces"
                  >
                    <template v-slot:append>
                      <q-avatar square size="md" :style="{ backgroundColor: form.primary_color }" />
                    </template>
                  </q-input>
                </div>

                <div class="col-xs-12 col-sm-6">
                  <q-input
                    v-model="form.secondary_color"
                    type="color"
                    outlined
                    label="Color Secundario"
                    hint="Color para elementos secundarios"
                  >
                    <template v-slot:append>
                      <q-avatar square size="md" :style="{ backgroundColor: form.secondary_color }" />
                    </template>
                  </q-input>
                </div>
              </div>

              <div class="q-mt-md p-3 bg-grey-2 rounded-borders">
                <div class="text-caption text-grey-7 q-mb-sm">Vista Previa de Colores</div>
                <div class="row q-col-gutter-sm items-center">
                  <div class="col-xs-4">
                    <q-btn :color="hexToQuasar(form.primary_color)" label="Botón Primario" class="full-width" />
                  </div>
                  <div class="col-xs-4">
                    <q-btn :color="hexToQuasar(form.secondary_color)" label="Botón Secundario" class="full-width" />
                  </div>
                  <div class="col-xs-4 text-center">
                    <span :style="{ color: form.primary_color, fontWeight: 'bold' }">Texto Primario</span>
                    <br />
                    <span :style="{ color: form.secondary_color, fontWeight: 'bold' }">Texto Secundario</span>
                  </div>
                </div>
              </div>

              <div class="q-mt-md">
                <q-btn outline color="primary" icon="auto_fix_high" label="Sugerir Colores con IA" @click="suggestColors" :loading="suggestingColors" />
              </div>
            </q-card-section>
          </q-card>

          <q-card>
            <q-card-section>
              <div class="text-h6 q-mb-md">Textos Legales y Políticas</div>
              
              <q-input
                v-model="form.terms_text"
                type="textarea"
                outlined
                label="Términos y Condiciones"
                rows="4"
                hint="Texto que aparecerá en el footer y checkout"
              />

              <q-input
                v-model="form.privacy_text"
                type="textarea"
                outlined
                label="Política de Privacidad"
                rows="4"
                class="q-mt-md"
              />

              <q-input
                v-model="form.cancellation_policy_text"
                type="textarea"
                outlined
                label="Política de Cancelación Personalizada"
                rows="4"
                class="q-mt-md"
                hint="Si se deja vacío, se usará la política estándar seleccionada"
              />
            </q-card-section>
          </q-card>
        </div>

        <!-- Columna Derecha: Configuración Web -->
        <div class="col-xs-12 col-lg-4">
          <q-card class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Configuración del Sitio</div>
              
              <q-input
                v-model="form.site_name"
                outlined
                label="Nombre del Sitio"
                hint="Aparece en el título de la página y emails"
              />

              <q-input
                v-model="form.site_description"
                type="textarea"
                outlined
                label="Descripción del Sitio"
                rows="3"
                hint="Meta descripción para SEO"
                class="q-mt-md"
              />

              <q-select
                v-model="form.default_locale"
                :options="localeOptions"
                outlined
                label="Idioma por Defecto"
                option-label="label"
                option-value="value"
                emit-value
                map-options
                class="q-mt-md"
              />

              <q-toggle
                v-model="form.enable_multi_language"
                label="Habilitar Multi-idioma"
                class="q-mt-md"
              />
            </q-card-section>
          </q-card>

          <q-card class="q-mb-md">
            <q-card-section>
              <div class="text-h6 q-mb-md">Redes Sociales</div>
              
              <q-input
                v-model="form.social_facebook"
                outlined
                label="Facebook URL"
                placeholder="https://facebook.com/..."
              />

              <q-input
                v-model="form.social_instagram"
                outlined
                label="Instagram URL"
                placeholder="https://instagram.com/..."
                class="q-mt-md"
              />

              <q-input
                v-model="form.social_twitter"
                outlined
                label="Twitter/X URL"
                placeholder="https://twitter.com/..."
                class="q-mt-md"
              />
            </q-card-section>
          </q-card>

          <q-card>
            <q-card-section>
              <div class="text-h6 q-mb-md">Contacto Público</div>
              
              <q-input
                v-model="form.contact_email"
                type="email"
                outlined
                label="Email de Contacto"
              />

              <q-input
                v-model="form.contact_phone"
                type="tel"
                outlined
                label="Teléfono de Contacto"
                class="q-mt-md"
              />

              <q-input
                v-model="form.contact_whatsapp"
                type="tel"
                outlined
                label="WhatsApp (con código de país)"
                hint="Ej: +34600123456"
                class="q-mt-md"
              />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div class="row q-mt-md">
        <div class="col-12 text-right">
          <q-btn color="grey" label="Cancelar" @click="$router.back()" class="q-mr-sm" />
          <q-btn color="primary" label="Guardar Configuración" type="submit" :loading="submitting" icon="save" />
        </div>
      </div>
    </q-form>
  </q-page>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useQuasar } from 'quasar'
import { useTenantStore } from 'src/stores/tenant'

const $q = useQuasar()
const tenantStore = useTenantStore()

const submitting = ref(false)
const suggestingColors = ref(false)
const previewLogo = ref('')
const previewFavicon = ref('')

const form = ref({
  logo: null as File | null,
  favicon: null as File | null,
  primary_color: '#1976D2',
  secondary_color: '#26A69A',
  site_name: '',
  site_description: '',
  default_locale: 'es',
  enable_multi_language: true,
  terms_text: '',
  privacy_text: '',
  cancellation_policy_text: '',
  social_facebook: '',
  social_instagram: '',
  social_twitter: '',
  contact_email: '',
  contact_phone: '',
  contact_whatsapp: ''
})

const localeOptions = [
  { label: 'Español', value: 'es' },
  { label: 'English', value: 'en' },
  { label: 'Français', value: 'fr' },
  { label: 'Deutsch', value: 'de' }
]

const currentLogo = computed(() => tenantStore.tenant?.branding?.logo_url || '')
const currentFavicon = computed(() => tenantStore.tenant?.branding?.favicon_url || '')

onMounted(async () => {
  await tenantStore.fetchTenantConfig()
  
  const branding = tenantStore.tenant?.branding || {}
  form.value = {
    ...form.value,
    primary_color: branding.primary_color || '#1976D2',
    secondary_color: branding.secondary_color || '#26A69A',
    site_name: tenantStore.tenant?.site_name || '',
    site_description: tenantStore.tenant?.site_description || '',
    default_locale: tenantStore.tenant?.default_locale || 'es',
    enable_multi_language: tenantStore.tenant?.enable_multi_language ?? true,
    terms_text: tenantStore.tenant?.terms_text || '',
    privacy_text: tenantStore.tenant?.privacy_text || '',
    cancellation_policy_text: tenantStore.tenant?.cancellation_policy_text || '',
    social_facebook: tenantStore.tenant?.social_facebook || '',
    social_instagram: tenantStore.tenant?.social_instagram || '',
    social_twitter: tenantStore.tenant?.social_twitter || '',
    contact_email: tenantStore.tenant?.contact_email || '',
    contact_phone: tenantStore.tenant?.contact_phone || '',
    contact_whatsapp: tenantStore.tenant?.contact_whatsapp || ''
  }
})

const hexToQuasar = (hex: string) => {
  // Conversión simplificada para vista previa
  return hex
}

const suggestColors = async () => {
  suggestingColors.value = true
  try {
    // Simulación de sugerencia de colores con IA
    await new Promise(resolve => setTimeout(resolve, 1500))
    
    // Colores sugeridos (en producción llamaría a la API de IA)
    const suggestions = [
      { primary: '#FF6B6B', secondary: '#4ECDC4' },
      { primary: '#45B7D1', secondary: '#FFA07A' },
      { primary: '#96CEB4', secondary: '#FFEEAD' }
    ]
    
    const suggestion = suggestions[Math.floor(Math.random() * suggestions.length)]
    
    form.value.primary_color = suggestion.primary
    form.value.secondary_color = suggestion.secondary
    
    $q.notify({
      type: 'positive',
      message: 'Colores sugeridos aplicados. ¡Revisa la vista previa!'
    })
  } catch (error) {
    $q.notify({ type: 'negative', message: 'Error al generar sugerencias' })
  } finally {
    suggestingColors.value = false
  }
}

const onSubmit = async () => {
  submitting.value = true
  try {
    const formData = new FormData()
    
    // Añadir campos de texto
    Object.keys(form.value).forEach(key => {
      const val = (form.value as any)[key]
      if (val !== null && typeof val !== 'object') {
        formData.append(key, val.toString())
      }
    })
    
    // Añadir archivos
    if (form.value.logo) formData.append('logo', form.value.logo)
    if (form.value.favicon) formData.append('favicon', form.value.favicon)
    
    await tenantStore.updateBranding(formData)
    
    $q.notify({ type: 'positive', message: 'Configuración guardada correctamente' })
  } catch (error: any) {
    $q.notify({ 
      type: 'negative', 
      message: error.response?.data?.message || 'Error al guardar configuración' 
    })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.rounded-borders {
  border-radius: 8px;
}
</style>
