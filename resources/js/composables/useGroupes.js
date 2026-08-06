import { ref } from 'vue'
import { http } from '../plugins/http'

/**
 * Liste des entreprises et actions associées.
 *
 * Aucune mise à jour optimiste : la ligne n'est modifiée à l'écran qu'avec la
 * réponse de la plateforme. Afficher « bloqué » avant confirmation reviendrait
 * à mentir à l'équipe sur l'état réel d'un client.
 */
export function useGroupes() {
  const groupes = ref([])
  const loading = ref(false)
  const syncing = ref(false)
  const page = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const sync_state = ref({ last_at: null, is_stale: true })

  async function load({ search = '', status = 'all', pageNumber = 1 } = {}) {
    loading.value = true

    try {
      const params = new URLSearchParams({ status, page: pageNumber })

      if (search) {
        params.set('search', search)
      }

      const payload = await http.get(`/api/groupes?${params}`)

      groupes.value = payload.data
      page.value = payload.meta.current_page
      lastPage.value = payload.meta.last_page
      total.value = payload.meta.total
      sync_state.value = payload.sync
    } finally {
      loading.value = false
    }
  }

  function replace(groupe) {
    const index = groupes.value.findIndex(item => item.id === groupe.id)

    if (index !== -1) {
      groupes.value[index] = groupe
    }
  }

  async function block(groupe, reason) {
    const payload = await http.post(`/api/groupes/${groupe.id}/block`, { reason })

    replace(payload.data)

    return payload.message
  }

  async function unblock(groupe) {
    const payload = await http.post(`/api/groupes/${groupe.id}/unblock`)

    replace(payload.data)

    return payload.message
  }

  async function sync() {
    syncing.value = true

    try {
      return await http.post('/api/sync')
    } finally {
      syncing.value = false
    }
  }

  return { groupes, loading, syncing, page, lastPage, total, syncState: sync_state, load, block, unblock, sync }
}
