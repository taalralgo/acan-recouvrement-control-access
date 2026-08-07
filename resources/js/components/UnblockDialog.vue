<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  groupe: { type: Object, default: null },
})

const emit = defineEmits(['cancel', 'confirm'])

const submitting = ref(false)

const open = computed({
  get: () => props.groupe !== null,
  set: value => {
    if (!value) {
      emit('cancel')
    }
  },
})

watch(() => props.groupe, () => {
  submitting.value = false
})

async function submit() {
  submitting.value = true

  try {
    await emit('confirm')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <!--
    Rétablir un accès est bénin : une erreur dans ce sens ne coûte rien, alors
    qu'un blocage à tort coupe un client payant. La confirmation reste donc
    légère, sans double étape ni aperçu.
  -->
  <v-dialog v-model="open" max-width="460">
    <v-card v-if="groupe">
      <v-card-title class="d-flex align-center ga-2 pt-4">
        <v-icon icon="mdi-lock-open-variant" color="success" />
        Rétablir l'accès
      </v-card-title>

      <v-card-text class="text-body-2">
        <p>
          Les {{ groupe.users_count }} utilisateur{{ groupe.users_count > 1 ? 's' : '' }}
          de <strong>{{ groupe.name }}</strong> pourront à nouveau se connecter.
        </p>

        <v-alert
          v-if="groupe.disabled_by_platform"
          type="warning"
          variant="tonal"
          density="compact"
          class="mt-3"
        >
          Attention : ce groupe reste désactivé sur la plateforme par
          ses administrateurs. Le rétablissement ne suffira pas à lui rendre
          l'accès.
        </v-alert>
      </v-card-text>

      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="emit('cancel')">Annuler</v-btn>
        <v-btn color="success" :loading="submitting" @click="submit">
          Rétablir l'accès
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
