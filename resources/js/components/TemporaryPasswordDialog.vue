<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  credentials: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const copied = ref(false)

const open = computed({
  get: () => props.credentials !== null,
  set: value => {
    if (!value) {
      copied.value = false
      emit('close')
    }
  },
})

async function copy() {
  await navigator.clipboard.writeText(props.credentials.password)
  copied.value = true
}
</script>

<template>
  <!--
    Ce mot de passe n'est affiché qu'une fois : aucun email n'est envoyé et il
    n'est stocké nulle part en clair. La fenêtre insiste donc pour qu'il soit
    transmis avant fermeture.
  -->
  <v-dialog v-model="open" max-width="520" persistent>
    <v-card v-if="credentials">
      <v-card-title class="d-flex align-center ga-2 pt-4">
        <v-icon icon="mdi-key-variant" color="warning" />
        Mot de passe temporaire
      </v-card-title>

      <v-card-text>
        <p class="text-body-2 mb-4">
          Transmettez ces identifiants à <strong>{{ credentials.name }}</strong>.
          Le mot de passe devra être remplacé à la première connexion.
        </p>

        <v-text-field
          :model-value="credentials.email"
          label="Adresse"
          readonly
          class="mb-3"
        />

        <v-text-field
          :model-value="credentials.password"
          label="Mot de passe temporaire"
          readonly
          append-inner-icon="mdi-content-copy"
          @click:append-inner="copy"
        />

        <v-alert type="warning" variant="tonal" density="compact" class="mt-4 text-body-2">
          Ce mot de passe ne sera plus affiché. En cas de perte, vous pourrez en
          générer un nouveau depuis la liste des comptes.
        </v-alert>

        <p v-if="copied" class="text-success text-body-2 mt-2 mb-0">Copié.</p>
      </v-card-text>

      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn color="primary" @click="open = false">J'ai transmis le mot de passe</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
