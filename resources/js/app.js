import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import vuetify from './plugins/vuetify'
import App from './App.vue'
import GroupesPage from './pages/GroupesPage.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'groupes', component: GroupesPage },
    // Les écrans d'administration viendront s'ajouter ici.
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

const mount = document.getElementById('app')

createApp(App, {
  user: {
    name: mount.dataset.userName,
    email: mount.dataset.userEmail,
    isAdmin: mount.dataset.userAdmin === '1',
  },
  logoutUrl: mount.dataset.logoutUrl,
})
  .use(router)
  .use(vuetify)
  .mount(mount)
