<script setup>
import { computed } from 'vue'

const props = defineProps({
  groupe: { type: Object, required: true },
})

const blockedSince = computed(() => {
  if (!props.groupe.blocked_at) {
    return ''
  }

  return new Date(props.groupe.blocked_at).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
})
</script>

<template>
  <div class="d-flex flex-column ga-1 align-start">
    <v-chip
      :color="groupe.is_blocked ? 'error' : 'success'"
      size="small"
      variant="flat"
      label
    >
      <v-icon start :icon="groupe.is_blocked ? 'mdi-lock' : 'mdi-lock-open-variant'" />
      {{ groupe.is_blocked ? `Bloqué depuis le ${blockedSince}` : 'Accès actif' }}
    </v-chip>

    <!--
      Second verrou, décidé par les administrateurs de la plateforme.
      Sans cette mention, un agent débloque, le client reste dehors, et
      appelle le support en disant que ça ne marche pas.
    -->
    <v-chip v-if="groupe.disabled_by_platform" size="small" variant="tonal" label>
      <v-icon start icon="mdi-power-plug-off" />
      Désactivé sur la plateforme
    </v-chip>

    <!--
      La fraîcheur des données est annoncée une fois en tête de liste, près du
      bouton qui la résout. Répétée sur chaque ligne, elle deviendrait un
      avertissement permanent que l'on apprend à ignorer.
    -->
  </div>
</template>
