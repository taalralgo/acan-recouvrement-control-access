import { reactive } from 'vue'

/**
 * File d'annonces affichée en bas d'écran.
 *
 * Les échecs restent affichés jusqu'à fermeture manuelle : dans cet outil, ne
 * pas voir passer un message d'erreur revient à croire qu'un client est
 * suspendu alors qu'il ne l'est pas.
 */
const state = reactive({
  visible: false,
  message: '',
  color: 'success',
  timeout: 4000,
})

export function useNotifier() {
  return {
    state,

    success(message) {
      Object.assign(state, { visible: true, message, color: 'success', timeout: 4000 })
    },

    failure(message) {
      Object.assign(state, { visible: true, message, color: 'error', timeout: -1 })
    },
  }
}
