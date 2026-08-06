<script setup>
defineProps({
  reason: { type: String, default: '' },
  lang: { type: String, default: 'fr' },
})
</script>

<template>
  <!--
    Reproduit l'écran de connexion de la plateforme.

    C'est le garde-fou principal de l'outil : une équipe non technique doit
    voir, avant de valider, le texte exact qu'un client va lire. Le rendu imite
    volontairement la page réelle plutôt que le style d'aCAN Régie.
  -->
  <div class="preview">
    <div class="preview-window">
      <div class="preview-titlebar">
        <span class="dot" /><span class="dot" /><span class="dot" />
      </div>

      <div class="preview-body">
        <div class="preview-brand">TVe</div>

        <div class="preview-alert">
          <strong>{{ lang === 'en' ? 'Access suspended' : 'Accès suspendu' }}</strong>
          <p>{{ reason || (lang === 'en' ? 'No reason yet.' : 'Aucun motif saisi pour le moment.') }}</p>
        </div>

        <label>{{ lang === 'en' ? 'Username or email' : "Nom d'utilisateur ou email" }}</label>
        <div class="preview-input" />

        <label>{{ lang === 'en' ? 'Password' : 'Mot de passe' }}</label>
        <div class="preview-input" />

        <div class="preview-button">{{ lang === 'en' ? 'Sign in' : 'Se connecter' }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.preview {
  background: #eceff1;
  padding: 1rem;
  border-radius: 6px;
}

.preview-window {
  background: #fff;
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .18);
}

.preview-titlebar {
  background: #e0e0e0;
  padding: .4rem .6rem;
  display: flex;
  gap: .3rem;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #bdbdbd;
}

.preview-body {
  padding: 1.1rem;
  font-family: system-ui, sans-serif;
  color: #37474f;
}

.preview-brand {
  font-weight: 700;
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: .9rem;
}

.preview-alert {
  background: #fdecea;
  border: 1px solid #f5c6c2;
  border-radius: 4px;
  padding: .65rem .8rem;
  margin-bottom: 1rem;
  color: #8c1d18;
  font-size: .82rem;
}

.preview-alert p {
  margin: .3rem 0 0;
  white-space: pre-line;
}

.preview-body label {
  display: block;
  font-size: .75rem;
  margin-bottom: .2rem;
  color: #607d8b;
}

.preview-input {
  height: 30px;
  border: 1px solid #cfd8dc;
  border-radius: 4px;
  margin-bottom: .7rem;
  background: #fafafa;
}

.preview-button {
  background: #f0ad4e;
  color: #fff;
  text-align: center;
  padding: .45rem;
  border-radius: 4px;
  font-size: .82rem;
  font-weight: 600;
}
</style>
