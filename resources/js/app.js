import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import vuetify from './plugins/vuetify'
import App from './App.vue'
import GroupesPage from './pages/GroupesPage.vue'
import AdminPage from './pages/AdminPage.vue'

const mount = document.getElementById('app')
const isAdmin = mount.dataset.userAdmin === '1'

// Sous-dossier de déploiement : sans cette base, le routeur interpréterait
// « /regie/administration » comme une route inconnue.
const basePath = mount.dataset.basePath || '/'

const router = createRouter({
  history: createWebHistory(basePath),
  routes: [
    { path: '/', name: 'groupes', component: GroupesPage },
    // Le serveur refuse déjà les appels d'administration à un non-admin ;
    // cette garde évite seulement d'afficher un écran voué à échouer.
    {
      path: '/administration',
      name: 'admin',
      component: AdminPage,
      beforeEnter: () => (isAdmin ? true : { name: 'groupes' }),
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

createApp(App, {
  user: {
    name: mount.dataset.userName,
    email: mount.dataset.userEmail,
    isAdmin,
  },
  logoutUrl: mount.dataset.logoutUrl,
})
  .use(router)
  .use(vuetify)
  .mount(mount)
