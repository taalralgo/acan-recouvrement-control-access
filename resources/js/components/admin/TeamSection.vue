<script setup>
import { onMounted, ref } from 'vue'
import TemporaryPasswordDialog from '../TemporaryPasswordDialog.vue'
import { appUrl, http } from '../../plugins/http'
import { useNotifier } from '../../composables/useNotifier'
import { useCurrentUser } from '../../composables/useCurrentUser'

const notifier = useNotifier()
const me = useCurrentUser()

// Écran Blade hors de la SPA : lien classique, pas de route interne.
const passwordUrl = appUrl('/mot-de-passe')

const members = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const editing = ref(null)
const credentials = ref(null)
const errors = ref({})

const form = ref({ name: '', email: '', role: 'collector' })

const roles = [
  { value: 'collector', title: 'Recouvrement' },
  { value: 'admin', title: 'Administrateur' },
]

async function load() {
  loading.value = true

  try {
    members.value = (await http.get('/api/team')).data
  } finally {
    loading.value = false
  }
}

onMounted(load)

function openCreate() {
  editing.value = null
  errors.value = {}
  form.value = { name: '', email: '', role: 'collector' }
  dialog.value = true
}

function openEdit(member) {
  editing.value = member
  errors.value = {}
  form.value = { name: member.name, email: member.email, role: member.role }
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}

  try {
    const payload = editing.value
      ? await http.put(`/api/team/${editing.value.id}`, form.value)
      : await http.post('/api/team', form.value)

    // Le mot de passe n'accompagne que la création : il est montré une fois.
    if (payload.temporary_password) {
      credentials.value = {
        name: payload.data.name,
        email: payload.data.email,
        password: payload.temporary_password,
      }
    } else {
      notifier.success(payload.message)
    }

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

async function resetPassword(member) {
  try {
    const payload = await http.post(`/api/team/${member.id}/reset-password`)

    credentials.value = {
      name: member.name,
      email: member.email,
      password: payload.temporary_password,
    }

    await load()
  } catch (error) {
    notifier.failure(error.message)
  }
}

async function remove(member) {
  if (!window.confirm(`Supprimer définitivement le compte de ${member.name} ? Ses décisions passées resteront visibles dans l'historique.`)) {
    return
  }

  try {
    notifier.success((await http.delete(`/api/team/${member.id}`)).message)
    await load()
  } catch (error) {
    notifier.failure(error.message)
  }
}
</script>

<template>
  <v-card flat border>
    <v-card-title class="d-flex flex-wrap align-center justify-space-between ga-2">
      <span class="text-subtitle-1 font-weight-bold">Comptes de l'équipe</span>
      <v-btn color="primary" size="small" prepend-icon="mdi-account-plus" @click="openCreate">
        Ajouter un compte
      </v-btn>
    </v-card-title>

    <v-card-subtitle class="pb-3">
      Un départ de l'entreprise se traduit par la suppression du compte ici.
    </v-card-subtitle>

    <v-progress-linear v-if="loading" indeterminate color="primary" />

    <v-table>
      <thead>
        <tr>
          <th>Nom</th>
          <th>Adresse</th>
          <th>Rôle</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="member in members" :key="member.id">
          <td>
            {{ member.name }}
            <v-chip v-if="me.isMe(member)" size="x-small" color="primary" variant="tonal" class="ml-2">
              vous
            </v-chip>
            <v-chip v-if="member.must_change_password" size="x-small" variant="tonal" class="ml-2">
              mot de passe à définir
            </v-chip>
          </td>
          <td class="text-medium-emphasis">{{ member.email }}</td>
          <td>
            <v-chip size="small" :color="member.role === 'admin' ? 'primary' : undefined" variant="tonal">
              {{ member.role === 'admin' ? 'Administrateur' : 'Recouvrement' }}
            </v-chip>
          </td>
          <!--
            Régénérer un mot de passe et supprimer un compte servent à agir sur
            un collègue. Appliqués à soi-même, ils coupent l'accès de la
            personne en train d'administrer : les boutons sont donc absents de
            sa propre ligne, et le serveur refuse de toute façon.
          -->
          <td class="text-right text-no-wrap">
            <v-btn icon="mdi-pencil" variant="text" size="small" title="Modifier" @click="openEdit(member)" />

            <template v-if="!me.isMe(member)">
              <v-btn icon="mdi-key-variant" variant="text" size="small" title="Nouveau mot de passe temporaire" @click="resetPassword(member)" />
              <v-btn icon="mdi-delete" variant="text" size="small" color="error" title="Supprimer" @click="remove(member)" />
            </template>

            <v-btn
              v-else
              variant="text"
              size="small"
              prepend-icon="mdi-key-variant"
              :href="passwordUrl"
            >
              Mon mot de passe
            </v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>

    <v-dialog v-model="dialog" max-width="480">
      <v-card>
        <v-card-title class="pt-4">
          {{ editing ? 'Modifier le compte' : 'Nouveau compte' }}
        </v-card-title>

        <v-card-text class="d-flex flex-column ga-4">
          <v-text-field
            v-model="form.name"
            label="Nom complet"
            :error-messages="errors.name"
          />

          <v-text-field
            v-model="form.email"
            label="Adresse professionnelle"
            :error-messages="errors.email"
            hint="Seules les adresses de l'entreprise sont acceptées."
            persistent-hint
          />

          <v-select
            v-model="form.role"
            :items="roles"
            label="Rôle"
            :error-messages="errors.role"
            hint="Les deux rôles peuvent bloquer et rétablir un accès. Seul l'administrateur gère cette page."
            persistent-hint
          />
        </v-card-text>

        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Annuler</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">Enregistrer</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <TemporaryPasswordDialog :credentials="credentials" @close="credentials = null" />
  </v-card>
</template>
