<script setup>
import { onMounted, ref } from 'vue'
import { http } from '../../plugins/http'
import { useNotifier } from '../../composables/useNotifier'

const notifier = useNotifier()

const templates = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const editing = ref(null)
const errors = ref({})

const form = ref({ label: '', body_fr: '', body_en: '', position: 0 })

async function load() {
  loading.value = true

  try {
    templates.value = (await http.get('/api/reason-templates')).data
  } finally {
    loading.value = false
  }
}

onMounted(load)

function openCreate() {
  editing.value = null
  errors.value = {}
  form.value = { label: '', body_fr: '', body_en: '', position: templates.value.length + 1 }
  dialog.value = true
}

function openEdit(template) {
  editing.value = template
  errors.value = {}
  form.value = { ...template }
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}

  try {
    const payload = editing.value
      ? await http.put(`/api/reason-templates/${editing.value.id}`, form.value)
      : await http.post('/api/reason-templates', form.value)

    notifier.success(payload.message)
    dialog.value = false
    await load()
  } catch (error) {
    if (error.errors) {
      errors.value = error.errors
    } else {
      notifier.failure(error.message)
    }
  } finally {
    saving.value = false
  }
}

async function remove(template) {
  if (!window.confirm(`Supprimer le motif « ${template.label} » ? Les suspensions déjà prononcées gardent leur message.`)) {
    return
  }

  try {
    notifier.success((await http.delete(`/api/reason-templates/${template.id}`)).message)
    await load()
  } catch (error) {
    notifier.failure(error.message)
  }
}
</script>

<template>
  <v-card flat border>
    <v-card-title class="d-flex flex-wrap align-center justify-space-between ga-2">
      <span class="text-subtitle-1 font-weight-bold">Motifs types</span>
      <v-btn color="primary" size="small" prepend-icon="mdi-text-box-plus" @click="openCreate">
        Ajouter un motif
      </v-btn>
    </v-card-title>

    <v-card-subtitle class="pb-3">
      Ces textes sont lus par vos clients. La version affichée dépend de la
      langue du groupe concerné.
    </v-card-subtitle>

    <v-progress-linear v-if="loading" indeterminate color="primary" />

    <v-list lines="two">
      <v-list-item v-for="template in templates" :key="template.id">
        <v-list-item-title class="font-weight-medium">{{ template.label }}</v-list-item-title>
        <v-list-item-subtitle class="text-wrap">{{ template.body_fr }}</v-list-item-subtitle>

        <template #append>
          <v-btn icon="mdi-pencil" variant="text" size="small" title="Modifier" @click="openEdit(template)" />
          <v-btn icon="mdi-delete" variant="text" size="small" color="error" title="Supprimer" @click="remove(template)" />
        </template>
      </v-list-item>

      <v-list-item v-if="!loading && templates.length === 0">
        <v-list-item-subtitle>
          Aucun motif type. L'équipe devra rédiger chaque message à la main.
        </v-list-item-subtitle>
      </v-list-item>
    </v-list>

    <v-dialog v-model="dialog" max-width="640">
      <v-card>
        <v-card-title class="pt-4">
          {{ editing ? 'Modifier le motif' : 'Nouveau motif' }}
        </v-card-title>

        <v-card-text class="d-flex flex-column ga-4">
          <v-text-field
            v-model="form.label"
            label="Nom du motif"
            hint="Visible par l'équipe uniquement, pas par le client."
            persistent-hint
            :error-messages="errors.label"
          />

          <v-textarea
            v-model="form.body_fr"
            label="Message en français"
            rows="4"
            counter
            :maxlength="500"
            :error-messages="errors.body_fr"
          />

          <v-textarea
            v-model="form.body_en"
            label="Message en anglais"
            rows="4"
            counter
            :maxlength="500"
            :error-messages="errors.body_en"
          />
        </v-card-text>

        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Annuler</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">Enregistrer</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>
