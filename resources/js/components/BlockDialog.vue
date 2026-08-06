<script setup>
import { computed, ref, watch } from 'vue'
import LoginPreview from './LoginPreview.vue'
import { http } from '../plugins/http'

const props = defineProps({
  groupe: { type: Object, default: null },
})

const emit = defineEmits(['cancel', 'confirm'])

const MAX_LENGTH = 500

const templates = ref([])
const selectedTemplate = ref(null)
const reason = ref('')
const submitting = ref(false)
const confirming = ref(false)

const open = computed({
  get: () => props.groupe !== null,
  set: value => {
    if (!value) {
      emit('cancel')
    }
  },
})

const canSubmit = computed(() => reason.value.trim().length > 0 && reason.value.length <= MAX_LENGTH)

async function loadTemplates() {
  if (templates.value.length > 0) {
    return
  }

  const payload = await http.get('/api/reason-templates')

  templates.value = payload.data
}

// Le motif est rédigé dans la langue du groupe, celle que lira le client.
function applyTemplate(template) {
  if (!template) {
    return
  }

  reason.value = props.groupe?.lang === 'en' ? template.body_en : template.body_fr
}

watch(selectedTemplate, id => {
  applyTemplate(templates.value.find(template => template.id === id))
})

watch(
  () => props.groupe,
  groupe => {
    if (!groupe) {
      return
    }

    confirming.value = false
    submitting.value = false
    selectedTemplate.value = null
    reason.value = ''
    loadTemplates()
  },
)

async function submit() {
  submitting.value = true

  try {
    await emit('confirm', reason.value.trim())
  } finally {
    submitting.value = false
    confirming.value = false
  }
}
</script>

<template>
  <v-dialog v-model="open" max-width="880" scrollable persistent>
    <v-card v-if="groupe">
      <v-card-title class="d-flex align-center ga-2 pt-4">
        <v-icon icon="mdi-lock" color="error" />
        Bloquer l'accès de {{ groupe.name }}
      </v-card-title>

      <v-divider />

      <v-card-text>
        <v-row>
          <v-col cols="12" md="6">
            <p class="text-body-2 text-medium-emphasis mb-4">
              Choisissez un motif type puis ajustez-le si nécessaire.
              Ce texte sera lu par le client.
            </p>

            <v-select
              v-model="selectedTemplate"
              :items="templates"
              item-title="label"
              item-value="id"
              label="Motif type"
              class="mb-4"
            />

            <v-textarea
              v-model="reason"
              label="Message affiché au client"
              rows="7"
              counter
              :maxlength="MAX_LENGTH"
              :rules="[v => (v || '').trim().length > 0 || 'Un motif est obligatoire.']"
            />

            <v-alert
              v-if="groupe.disabled_by_platform"
              type="info"
              variant="tonal"
              density="compact"
              class="mt-4 text-body-2"
            >
              Cette entreprise est déjà désactivée sur la plateforme par ses
              administrateurs. Votre blocage s'ajoute à cette désactivation.
            </v-alert>
          </v-col>

          <v-col cols="12" md="6">
            <p class="text-body-2 font-weight-medium mb-2">
              Ce que verra le client
              <span class="text-medium-emphasis">
                ({{ groupe.lang === 'en' ? 'anglais' : 'français' }})
              </span>
            </p>

            <LoginPreview :reason="reason" :lang="groupe.lang" />
          </v-col>
        </v-row>
      </v-card-text>

      <v-divider />

      <!--
        Confirmation nommée, avec le nombre de personnes concernées : c'est le
        chiffre qui rend l'action concrète pour l'agent avant de valider.
      -->
      <v-card-actions v-if="!confirming" class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="emit('cancel')">Annuler</v-btn>
        <v-btn color="error" :disabled="!canSubmit" @click="confirming = true">
          Continuer
        </v-btn>
      </v-card-actions>

      <div v-else class="pa-4">
        <v-alert type="warning" variant="tonal" class="mb-3">
          Vous allez bloquer <strong>{{ groupe.users_count }}</strong>
          utilisateur{{ groupe.users_count > 1 ? 's' : '' }} de
          <strong>{{ groupe.name }}</strong>. Ils ne pourront plus se connecter
          et verront le message ci-dessus.
        </v-alert>

        <div class="d-flex justify-end ga-2">
          <v-btn variant="text" @click="confirming = false">Revenir</v-btn>
          <v-btn color="error" :loading="submitting" @click="submit">
            Confirmer le blocage
          </v-btn>
        </div>
      </div>
    </v-card>
  </v-dialog>
</template>
