<script setup>
import { onMounted, ref } from 'vue'
import { http } from '../../plugins/http'
import { useNotifier } from '../../composables/useNotifier'

const notifier = useNotifier()

const platforms = ref([])
const loading = ref(false)
const saving = ref(false)
const testing = ref(null)
const dialog = ref(false)
const editing = ref(null)
const errors = ref({})
const diagnostics = ref({})

const form = ref({ name: '', base_url: '', api_token: '', active: true })

async function load() {
  loading.value = true

  try {
    platforms.value = (await http.get('/api/platforms')).data
  } finally {
    loading.value = false
  }
}

onMounted(load)

function openCreate() {
  editing.value = null
  errors.value = {}
  form.value = { name: '', base_url: '', api_token: '', active: true }
  dialog.value = true
}

function openEdit(platform) {
  editing.value = platform
  errors.value = {}
  // Le jeton n'est jamais réaffiché : laisser vide signifie « ne pas changer ».
  form.value = {
    name: platform.name,
    base_url: platform.base_url,
    api_token: '',
    active: platform.active,
  }
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}

  try {
    const payload = editing.value
      ? await http.put(`/api/platforms/${editing.value.id}`, form.value)
      : await http.post('/api/platforms', form.value)

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

/**
 * Diagnostic affiché sur la ligne plutôt qu'en annonce fugace : après un
 * déménagement de serveur, l'admin corrige l'adresse puis teste, et doit
 * pouvoir relire le résultat en ajustant.
 */
async function test(platform) {
  testing.value = platform.id

  try {
    diagnostics.value[platform.id] = await http.post(`/api/platforms/${platform.id}/test`)
    await load()
  } catch (error) {
    diagnostics.value[platform.id] = { success: false, message: error.message }
  } finally {
    testing.value = null
  }
}

async function remove(platform) {
  if (!window.confirm(`Retirer ${platform.name} ? Ses ${platform.groupes_count} groupe(s) disparaîtront de la liste, mais aucun accès client ne sera modifié.`)) {
    return
  }

  try {
    notifier.success((await http.delete(`/api/platforms/${platform.id}`)).message)
    await load()
  } catch (error) {
    notifier.failure(error.message)
  }
}
</script>

<template>
  <v-card flat border>
    <v-card-title class="d-flex flex-wrap align-center justify-space-between ga-2">
      <span class="text-subtitle-1 font-weight-bold">Plateformes raccordées</span>
      <v-btn color="primary" size="small" prepend-icon="mdi-server-plus" @click="openCreate">
        Ajouter une plateforme
      </v-btn>
    </v-card-title>

    <v-card-subtitle class="pb-3">
      Si un projet change de serveur, corrigez son adresse ici puis testez la connexion.
    </v-card-subtitle>

    <v-progress-linear v-if="loading" indeterminate color="primary" />

    <v-table>
      <thead>
        <tr>
          <th>Plateforme</th>
          <th>Adresse</th>
          <th class="text-center">Groupes</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="platform in platforms" :key="platform.id">
          <tr>
            <td>
              {{ platform.name }}
              <v-chip v-if="!platform.active" size="x-small" variant="tonal" class="ml-2">
                inactive
              </v-chip>
            </td>
            <td class="text-medium-emphasis">{{ platform.base_url }}</td>
            <td class="text-center">{{ platform.groupes_count }}</td>
            <td class="text-right text-no-wrap">
              <v-btn
                size="small"
                variant="outlined"
                prepend-icon="mdi-lan-connect"
                :loading="testing === platform.id"
                @click="test(platform)"
              >
                Tester la connexion
              </v-btn>
              <v-btn icon="mdi-pencil" variant="text" size="small" title="Modifier" @click="openEdit(platform)" />
              <v-btn icon="mdi-delete" variant="text" size="small" color="error" title="Retirer" @click="remove(platform)" />
            </td>
          </tr>

          <tr v-if="diagnostics[platform.id]">
            <td colspan="4" class="pb-3">
              <v-alert
                :type="diagnostics[platform.id].success ? 'success' : 'error'"
                variant="tonal"
                density="compact"
                class="text-body-2"
                closable
                @click:close="delete diagnostics[platform.id]"
              >
                {{ diagnostics[platform.id].message }}
              </v-alert>
            </td>
          </tr>
        </template>
      </tbody>
    </v-table>

    <v-dialog v-model="dialog" max-width="520">
      <v-card>
        <v-card-title class="pt-4">
          {{ editing ? 'Modifier la plateforme' : 'Nouvelle plateforme' }}
        </v-card-title>

        <v-card-text class="d-flex flex-column ga-4">
          <v-text-field v-model="form.name" label="Nom" :error-messages="errors.name" />

          <v-text-field
            v-model="form.base_url"
            label="Adresse"
            placeholder="https://exemple.com"
            :error-messages="errors.base_url"
          />

          <v-text-field
            v-model="form.api_token"
            label="Jeton d'accès"
            type="password"
            :error-messages="errors.api_token"
            :hint="editing ? 'Laissez vide pour conserver le jeton actuel.' : 'Fourni par l\'équipe technique de la plateforme.'"
            persistent-hint
          />

          <v-switch
            v-model="form.active"
            color="primary"
            label="Plateforme active"
            hide-details
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
