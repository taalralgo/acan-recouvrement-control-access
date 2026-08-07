<script setup>
import { computed, ref, watch } from 'vue'
import { http } from '../plugins/http'

const props = defineProps({
  groupe: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const actions = ref([])
const loading = ref(false)

const open = computed({
  get: () => props.groupe !== null,
  set: value => {
    if (!value) {
      emit('close')
    }
  },
})

watch(
  () => props.groupe,
  async groupe => {
    if (!groupe) {
      return
    }

    loading.value = true
    actions.value = []

    try {
      const payload = await http.get(`/api/groupes/${groupe.id}/actions`)

      actions.value = payload.data
    } finally {
      loading.value = false
    }
  },
)

function formatDate(value) {
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <!--
    Sert à répondre à un client en litige : qui a décidé quoi, quand, et avec
    quel motif. Les noms affichés sont ceux enregistrés au moment de l'action,
    même si le compte a été supprimé depuis.
  -->
  <v-navigation-drawer v-model="open" location="right" temporary width="440">
    <template v-if="groupe">
      <v-toolbar flat density="comfortable">
        <v-toolbar-title class="text-subtitle-1 font-weight-bold">
          Historique — {{ groupe.name }}
        </v-toolbar-title>
        <v-btn icon="mdi-close" variant="text" @click="emit('close')" />
      </v-toolbar>

      <v-divider />

      <v-progress-linear v-if="loading" indeterminate color="primary" />

      <div v-if="!loading && actions.length === 0" class="pa-6 text-center text-medium-emphasis text-body-2">
        Aucune décision enregistrée pour ce groupe.
      </div>

      <v-timeline
        v-else
        side="end"
        density="compact"
        class="pa-4 history-timeline"
        truncate-line="both"
      >
        <v-timeline-item
          v-for="action in actions"
          :key="action.id"
          :dot-color="action.action === 'block' ? 'error' : 'success'"
          :icon="action.action === 'block' ? 'mdi-lock' : 'mdi-lock-open-variant'"
          size="small"
        >
          <div class="text-body-2 font-weight-medium">
            {{ action.action === 'block' ? 'Accès bloqué' : 'Accès rétabli' }}
          </div>

          <div class="text-caption text-medium-emphasis">
            {{ formatDate(action.created_at) }} — {{ action.actor_name }}
          </div>

          <div v-if="action.reason" class="text-body-2 mt-2 reason">
            {{ action.reason }}
          </div>
        </v-timeline-item>
      </v-timeline>
    </template>
  </v-navigation-drawer>
</template>

<style scoped>
/* Vuetify centre verticalement une timeline courte ; l'historique doit se lire
   du haut du panneau, comme une liste. */
.history-timeline :deep(.v-timeline) ,
.history-timeline {
  justify-content: flex-start;
  align-content: flex-start;
}

.reason {
  background: rgba(0, 0, 0, .04);
  border-radius: 4px;
  padding: .5rem .65rem;
  white-space: pre-line;
}
</style>
