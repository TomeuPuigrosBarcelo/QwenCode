<template>
  <q-page class="q-pa-md">
    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12">
        <div class="row items-center justify-between">
          <div>
            <h4 class="q-ma-none">Configuración de Stripe</h4>
            <p class="text-grey-7 q-mt-xs q-mb-none">Configura tus claves API para recibir pagos</p>
          </div>
          <q-badge :color="configStatus.color" class="q-pa-sm">
            {{ configStatus.label }}
          </q-badge>
        </div>
      </div>
    </div>

    <div class="row q-col-gutter-md">
      
      <!-- Columna Izquierda: Formulario -->
      <div class="col-12 col-md-8">
        <q-card>
          <q-card-section>
            <div class="text-h6 q-mb-md">Credenciales API</div>
            
            <q-form @submit="saveConfig" class="q-gutter-md">
              
              <!-- Selector Modo Test/Producción -->
              <div class="row q-gutter-sm q-mb-md">
                <q-btn-toggle
                  v-model="isTestMode"
                  toggle-color="primary"
                  :options="[
                    {label: '🧪 Modo Prueba', value: true},
                    {label: '🔒 Modo Producción', value: false}
                  ]"
                  unelevated
                  class="full-width"
                />
              </div>

              <q-alert 
                :type="isTestMode ? 'info' : 'warning'"
                :icon="isTestMode ? 'science' : 'warning'"
              >
                <div v-if="isTestMode">
                  <strong>Modo Prueba:</strong> Usa las claves de prueba de Stripe. 
                  Los pagos no son reales. Puedes usar la tarjeta 4242 4242 4242 4242.
                </div>
                <div v-else>
                  <strong>Modo Producción:</strong> Usa las claves reales de Stripe. 
                  Los pagos serán procesados con dinero real. ¡Verifica bien las credenciales!
                </div>
              </q-alert>

              <!-- Public Key -->
              <q-input
                v-model="form.pk"
                label="Public Key (pk_...)"
                outlined
                :type="showPk ? 'text' : 'password'"
                :rules="[val => !!val || 'La Public Key es requerida']"
              >
                <template v-slot:prepend>
                  <q-icon name="vpn_key" />
                </template>
                <template v-slot:append>
                  <q-btn flat dense icon="visibility_off" v-if="showPk" @click="showPk = false" />
                  <q-btn flat dense icon="visibility" v-else @click="showPk = true" />
                </template>
              </q-input>

              <!-- Secret Key -->
              <q-input
                v-model="form.sk"
                label="Secret Key (sk_...)"
                outlined
                :type="showSk ? 'text' : 'password'"
                :rules="[val => !!val || 'La Secret Key es requerida']"
              >
                <template v-slot:prepend>
                  <q-icon name="lock" />
                </template>
                <template v-slot:append>
                  <q-btn flat dense icon="visibility_off" v-if="showSk" @click="showSk = false" />
                  <q-btn flat dense icon="visibility" v-else @click="showSk = true" />
                </template>
              </q-input>

              <!-- Webhook Secret -->
              <q-input
                v-model="form.whsec"
                label="Webhook Signing Secret (whsec_...)"
                outlined
                :type="showWhsec ? 'text' : 'password'"
                hint="Necesario para recibir notificaciones automáticas de Stripe"
                :rules="[val => !!val || 'El Webhook Secret es requerido']"
              >
                <template v-slot:prepend>
                  <q-icon name="webhook" />
                </template>
                <template v-slot:append>
                  <q-btn flat dense icon="visibility_off" v-if="showWhsec" @click="showWhsec = false" />
                  <q-btn flat dense icon="visibility" v-else @click="showWhsec = true" />
                </template>
              </q-input>

              <!-- Botones de Acción -->
              <div class="row q-gutter-sm q-mt-md">
                <q-btn
                  type="submit"
                  color="primary"
                  label="Guardar Configuración"
                  icon="save"
                  :loading="saving"
                  :disable="!form.pk || !form.sk || !form.whsec"
                />
                
                <q-btn
                  color="secondary"
                  label="Verificar Credenciales"
                  icon="check_circle"
                  :loading="verifying"
                  @click="verifyCredentials"
                  :disable="!form.pk || !form.sk"
                />
              </div>

            </q-form>
          </q-card-section>
        </q-card>

        <!-- Guía de Obtención de Claves -->
        <q-card class="q-mt-md">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">¿Cómo obtener tus claves?</div>
            
            <q-list>
              <q-item>
                <q-item-section avatar>
                  <q-icon name="1" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Inicia sesión en Stripe Dashboard</q-item-label>
                  <q-item-label caption>
                    <a href="https://dashboard.stripe.com" target="_blank">dashboard.stripe.com</a>
                  </q-item-label>
                </q-item-section>
              </q-item>
              
              <q-item>
                <q-item-section avatar>
                  <q-icon name="2" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Navega a Developers → API Keys</q-item-label>
                  <q-item-label caption>Copia la "Publishable key" y la "Secret key"</q-item-label>
                </q-item-section>
              </q-item>
              
              <q-item>
                <q-item-section avatar>
                  <q-icon name="3" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Configura el Webhook</q-item-label>
                  <q-item-label caption>
                    Ve a Developers → Webhooks, añade endpoint: 
                    <code class="bg-grey-2 q-px-sm">{{ webhookUrl }}</code>
                  </q-item-label>
                </q-item-section>
              </q-item>
              
              <q-item>
                <q-item-section avatar>
                  <q-icon name="4" color="primary" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Copia el Signing Secret</q-item-label>
                  <q-item-label caption>Después de crear el webhook, copia el "Signing secret"</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>
      </div>

      <!-- Columna Derecha: Estado e Información -->
      <div class="col-12 col-md-4">
        
        <!-- Estado de la Configuración -->
        <q-card class="q-mb-md">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">Estado</div>
            
            <div class="column q-gutter-md">
              <div class="row items-center">
                <q-icon 
                  :name="configStatus.icon" 
                  :color="configStatus.color" 
                  size="2em" 
                  class="q-mr-sm"
                />
                <div>
                  <div class="text-weight-bold">{{ configStatus.label }}</div>
                  <div class="text-caption text-grey-7">
                    {{ configStatus.description }}
                  </div>
                </div>
              </div>
              
              <q-separator />
              
              <div v-if="lastVerified">
                <div class="text-caption text-grey-7">Última verificación</div>
                <div class="text-subtitle2">{{ formatDate(lastVerified) }}</div>
              </div>
              
              <div v-if="currentMode">
                <div class="text-caption text-grey-7">Modo actual</div>
                <q-badge :color="isTestMode ? 'orange' : 'positive'">
                  {{ isTestMode ? 'Prueba' : 'Producción' }}
                </q-badge>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <!-- Métodos de Pago Habilitados -->
        <q-card>
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">Métodos de Pago</div>
            
            <q-list dense>
              <q-item>
                <q-item-section avatar>
                  <q-icon name="credit_card" color="deep-purple" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Tarjetas de Crédito/Débito</q-item-label>
                  <q-item-label caption>Visa, Mastercard, Amex</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-icon name="check" color="positive" v-if="isConfigured" />
                </q-item-section>
              </q-item>
              
              <q-item>
                <q-item-section avatar>
                  <q-icon name="phone_android" color="blue" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Bizum (vía Stripe)</q-item-label>
                  <q-item-label caption>Automático con Stripe</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-icon name="check" color="positive" v-if="isConfigured" />
                </q-item-section>
              </q-item>
              
              <q-item>
                <q-item-section avatar>
                  <q-icon name="account_balance" color="grey-7" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Transferencia</q-item-label>
                  <q-item-label caption>Manual (sin Stripe)</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-icon name="check" color="positive" />
                </q-item-section>
              </q-item>
              
              <q-item>
                <q-item-section avatar>
                  <q-icon name="money" color="green" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>Efectivo</q-item-label>
                  <q-item-label caption>Pago en llegada</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-icon name="check" color="positive" />
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>

        <!-- Enlaces Rápidos -->
        <q-card class="q-mt-md">
          <q-card-section>
            <div class="text-subtitle2 q-mb-md">Enlaces Rápidos</div>
            
            <div class="column q-gutter-sm">
              <q-btn
                flat
                color="primary"
                label="Stripe Dashboard"
                icon="open_in_new"
                align="left"
                @click="openStripeDashboard"
              />
              
              <q-btn
                flat
                color="primary"
                label="Documentación API"
                icon="menu_book"
                align="left"
                @click="openDocs"
              />
              
              <q-btn
                flat
                color="primary"
                label="Soporte Stripe"
                icon="support"
                align="left"
                @click="openSupport"
              />
            </div>
          </q-card-section>
        </q-card>

      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { useTenantStore } from 'src/stores/tenant'
import api from 'src/services/api'

const $q = useQuasar()
const tenantStore = useTenantStore()

// Estado del formulario
const form = ref({
  pk: '',
  sk: '',
  whsec: ''
})

const isTestMode = ref(true)
const showPk = ref(false)
const showSk = ref(false)
const showWhsec = ref(false)

const saving = ref(false)
const verifying = ref(false)
const lastVerified = ref<string | null>(null)
const currentMode = ref<boolean | null>(null)

// URL del webhook (se calcula basada en el dominio actual)
const webhookUrl = computed(() => {
  const domain = window.location.hostname
  return `https://${domain}/api/webhooks/stripe`
})

// Estado de configuración
const isConfigured = computed(() => {
  return !!lastVerified.value
})

const configStatus = computed(() => {
  if (!isConfigured.value) {
    return {
      color: 'negative',
      label: 'No Configurado',
      description: 'Debes añadir tus credenciales de Stripe',
      icon: 'error'
    }
  }
  
  return {
    color: 'positive',
    label: 'Configurado',
    description: 'Las credenciales fueron verificadas correctamente',
    icon: 'check_circle'
  }
})

// Métodos
const loadConfig = async () => {
  try {
    const response = await api.get('/stripe-config')
    const config = response.data
    
    if (config) {
      // No cargamos las claves por seguridad, solo metadata
      lastVerified.value = config.last_verified_at
      currentMode.value = config.test_mode
      isTestMode.value = config.test_mode ?? true
    }
  } catch (error) {
    // Config no existe aún, es normal
  }
}

const verifyCredentials = async () => {
  if (!form.value.pk || !form.value.sk) {
    $q.notify({
      color: 'warning',
      message: 'Completa las claves API antes de verificar',
      icon: 'warning'
    })
    return
  }
  
  verifying.value = true
  try {
    await api.post('/stripe-config/verify', {
      pk: form.value.pk,
      sk: form.value.sk,
      test_mode: isTestMode.value
    })
    
    $q.notify({
      color: 'positive',
      message: '¡Credenciales verificadas correctamente!',
      icon: 'check_circle',
      html: true,
      caption: 'Puedes guardar la configuración ahora'
    })
    
    lastVerified.value = new Date().toISOString()
  } catch (error: any) {
    $q.notify({
      color: 'negative',
      message: error.response?.data?.message || 'Error al verificar credenciales',
      icon: 'error',
      caption: 'Verifica que las claves sean correctas y correspondan al modo (test/prod)'
    })
  } finally {
    verifying.value = false
  }
}

const saveConfig = async () => {
  saving.value = true
  try {
    await api.put('/stripe-config', {
      pk: form.value.pk,
      sk: form.value.sk,
      whsec: form.value.whsec,
      test_mode: isTestMode.value
    })
    
    $q.notify({
      color: 'positive',
      message: 'Configuración guardada exitosamente',
      icon: 'check'
    })
    
    loadConfig()
  } catch (error: any) {
    $q.notify({
      color: 'negative',
      message: error.response?.data?.message || 'Error al guardar configuración',
      icon: 'error'
    })
  } finally {
    saving.value = false
  }
}

const formatDate = (dateString: string) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleString('es-ES')
}

const openStripeDashboard = () => {
  const url = isTestMode.value 
    ? 'https://dashboard.stripe.com/test' 
    : 'https://dashboard.stripe.com'
  window.open(url, '_blank')
}

const openDocs = () => {
  window.open('https://stripe.com/docs/api', '_blank')
}

const openSupport = () => {
  window.open('https://support.stripe.com', '_blank')
}

onMounted(() => {
  loadConfig()
})
</script>

<style scoped>
code {
  font-family: 'Courier New', monospace;
  font-size: 0.85em;
}
</style>
