<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AccessStatus from '../components/AccessStatus.vue'
import BlockDialog from '../components/BlockDialog.vue'
import UnblockDialog from '../components/UnblockDialog.vue'
import HistoryDrawer from '../components/HistoryDrawer.vue'
import { useGroupes } from '../composables/useGroupes'
import { useNotifier } from '../composables/useNotifier'

const { groupes, loading, syncing, page, lastPage, total, syncState, load, block, unblock, sync } = useGroupes()
const notifier = useNotifier()

const search = ref('')
const status = ref('all')

const blocking = ref(null)
const unblocking = ref(null)
const historyFor = ref(null)

const filters = [
  { value: 'all', title: 'Toutes' },
  { value: 'blocked', title: 'Bloquées' },
  { value: 'active', title: 'Actives' },
]

/**
 * En v1 la synchronisation est manuelle : cette mention est le seul rappel que
 * la liste peut avoir vieilli depuis la dernière visite.
 */
const freshness = computed(() => {
  if (!syncState.value.last_at) {
    return 'Liste jamais actualisée'
  }

  const minutes = Math.floor((Date.now() - new Date(syncState.value.last_at)) / 60000)

  if (minutes < 2) return "Liste actualisée à l'instant"
  if (minutes < 60) return `Liste actualisée il y a ${minutes} min`

  const hours = Math.floor(minutes / 60)

  if (hours < 24) return `Liste actualisée il y a ${hours} h`

  const days = Math.floor(hours / 24)

  return `Liste actualisée il y a ${days} jour${days > 1 ? 's' : ''}`
})

function refresh(pageNumber = 1) {
  return load({ search: search.value, status: status.value, pageNumber })
}

// La recherche se déclenche à la frappe, mais sans lancer une requête par
// caractère.
let debounce
watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => refresh(), 300)
})

watch(status, () => refresh())

onMounted(() => refresh())

async function confirmBlock(reason) {
  const groupe = blocking.value

  try {
    notifier.success(await block(groupe, reason))
    blocking.value = null
  } catch (error) {
    // Le motif reste saisi : l'agent réessaie sans tout retaper.
    notifier.failure(error.message)
  }
}

async function confirmUnblock() {
  const groupe = unblocking.value

  try {
    notifier.success(await unblock(groupe))
    unblocking.value = null
  } catch (error) {
    notifier.failure(error.message)
  }
}

async function refreshFromPlatforms() {
  try {
    const payload = await sync()

    await refresh(page.value)

    if (payload.all_succeeded) {
      notifier.success('Liste actualisée.')
    } else {
      const failed = payload.data.filter(result => !result.success)

      notifier.failure(
        failed.map(result => `${result.platform} : ${result.message}`).join(' — '),
      )
    }
  } catch (error) {
    notifier.failure(error.message)
  }
}
</script>

<template>
  <v-container class="py-6" style="max-width: 1200px">
    <div class="d-flex flex-wrap align-center justify-space-between ga-4 mb-5">
      <div>
        <h1 class="text-h5 font-weight-bold">Entreprises</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ total }} entreprise{{ total > 1 ? 's' : '' }}
        </p>
      </div>

      <div class="d-flex align-center ga-3">
        <span
          class="text-body-2"
          :class="syncState.is_stale ? 'text-warning font-weight-medium' : 'text-medium-emphasis'"
        >
          <v-icon v-if="syncState.is_stale" icon="mdi-alert-outline" size="small" class="mr-1" />
          {{ freshness }}
        </span>

        <v-btn
          variant="outlined"
          prepend-icon="mdi-refresh"
          :loading="syncing"
          @click="refreshFromPlatforms"
        >
          Actualiser
        </v-btn>
      </div>
    </div>

    <v-card flat border class="mb-4">
      <v-card-text class="d-flex flex-wrap ga-4">
        <v-text-field
          v-model="search"
          label="Rechercher une entreprise"
          prepend-inner-icon="mdi-magnify"
          clearable
          style="min-width: 260px; flex: 1 1 260px"
        />

        <v-select
          v-model="status"
          :items="filters"
          label="Statut"
          style="max-width: 200px"
        />
      </v-card-text>
    </v-card>

    <v-card flat border>
      <v-progress-linear v-if="loading" indeterminate color="primary" />

      <v-table>
        <thead>
          <tr>
            <th>Entreprise</th>
            <th class="d-none d-md-table-cell">Plateforme</th>
            <th class="text-center">Utilisateurs</th>
            <th>Accès</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="groupe in groupes" :key="groupe.id">
            <td class="py-3">
              <div class="font-weight-medium">{{ groupe.name }}</div>
              <div v-if="groupe.code" class="text-caption text-medium-emphasis">
                {{ groupe.code }}
              </div>
            </td>

            <td class="d-none d-md-table-cell text-medium-emphasis">
              {{ groupe.platform }}
            </td>

            <td class="text-center">{{ groupe.users_count }}</td>

            <td class="py-3">
              <AccessStatus :groupe="groupe" />
            </td>

            <td class="text-right text-no-wrap">
              <v-btn
                v-if="!groupe.is_blocked"
                color="error"
                size="small"
                prepend-icon="mdi-lock"
                @click="blocking = groupe"
              >
                Bloquer l'accès
              </v-btn>

              <v-btn
                v-else
                color="success"
                size="small"
                prepend-icon="mdi-lock-open-variant"
                @click="unblocking = groupe"
              >
                Rétablir
              </v-btn>

              <v-btn
                icon="mdi-history"
                variant="text"
                size="small"
                class="ml-1"
                title="Historique"
                @click="historyFor = groupe"
              />
            </td>
          </tr>

          <tr v-if="!loading && groupes.length === 0">
            <td colspan="5" class="text-center text-medium-emphasis py-8">
              Aucune entreprise ne correspond à cette recherche.
            </td>
          </tr>
        </tbody>
      </v-table>

      <v-divider v-if="lastPage > 1" />

      <v-card-actions v-if="lastPage > 1" class="justify-center">
        <v-pagination
          :model-value="page"
          :length="lastPage"
          :total-visible="7"
          density="comfortable"
          @update:model-value="refresh"
        />
      </v-card-actions>
    </v-card>

    <BlockDialog
      :groupe="blocking"
      @cancel="blocking = null"
      @confirm="confirmBlock"
    />

    <UnblockDialog
      :groupe="unblocking"
      @cancel="unblocking = null"
      @confirm="confirmUnblock"
    />

    <HistoryDrawer
      :groupe="historyFor"
      @close="historyFor = null"
    />
  </v-container>
</template>
