<script setup>
import { useNotifier } from './composables/useNotifier'
import { http } from './plugins/http'

const props = defineProps({
  user: { type: Object, required: true },
  logoutUrl: { type: String, required: true },
})

const notifier = useNotifier()

async function logout() {
  await http.post(props.logoutUrl)
  window.location.href = '/login'
}
</script>

<template>
  <v-app>
    <v-app-bar flat border density="comfortable">
      <v-app-bar-title class="font-weight-bold">blockAccess</v-app-bar-title>

      <span class="text-body-2 text-medium-emphasis mr-4 d-none d-sm-inline">
        {{ props.user.name }}
      </span>

      <v-btn variant="text" prepend-icon="mdi-logout" @click="logout">
        Se déconnecter
      </v-btn>
    </v-app-bar>

    <v-main class="bg-background">
      <router-view />
    </v-main>

    <v-snackbar
      v-model="notifier.state.visible"
      :color="notifier.state.color"
      :timeout="notifier.state.timeout"
      location="bottom"
    >
      {{ notifier.state.message }}

      <template #actions>
        <v-btn variant="text" @click="notifier.state.visible = false">Fermer</v-btn>
      </template>
    </v-snackbar>
  </v-app>
</template>
