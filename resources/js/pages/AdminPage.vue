<script setup>
import { ref } from 'vue'
import TeamSection from '../components/admin/TeamSection.vue'
import PlatformSection from '../components/admin/PlatformSection.vue'
import TemplateSection from '../components/admin/TemplateSection.vue'

const tab = ref('team')
</script>

<template>
  <v-container class="py-6" style="max-width: 1200px">
    <h1 class="text-h5 font-weight-bold mb-1">Administration</h1>
    <p class="text-body-2 text-medium-emphasis mb-5">
      Comptes de l'équipe, plateformes raccordées et motifs types.
    </p>

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="team" prepend-icon="mdi-account-group">Comptes</v-tab>
      <v-tab value="platforms" prepend-icon="mdi-server">Plateformes</v-tab>
      <v-tab value="templates" prepend-icon="mdi-text-box-multiple">Motifs types</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="team"><TeamSection /></v-window-item>
      <v-window-item value="platforms"><PlatformSection /></v-window-item>
      <v-window-item value="templates"><TemplateSection /></v-window-item>
    </v-window>
  </v-container>
</template>
