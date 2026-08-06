# aCAN Régie

Outil interne permettant à l'équipe de recouvrement de suspendre l'accès des
utilisateurs d'une entreprise cliente à nos SAAS, avec un motif affiché au client.

Conception et décisions : [SPEC.md](SPEC.md).

## Démarrer

Prérequis : PHP 8.3+, MySQL, et **Node `^20.19` ou `>=22.12`** (contrainte de
Vite 8, déclarée dans `package.json`).

```bash
composer install
npm install
npm run build
php artisan migrate --seed
php artisan regie:create-admin      # premier compte, mot de passe affiché une fois
php artisan serve
```

## Déployer

L'interface est une application Vue compilée : **`npm run build` est
obligatoire**, sinon `@vite()` ne trouve pas son manifeste et la page reste
vide. Les fichiers produits vont dans `public/build`, qui n'est pas versionné.

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Déploiement dans un sous-dossier

Si l'application est servie depuis `https://exemple.com/regie` plutôt que
depuis la racine d'un domaine, **la commande de build ne change pas** :

```bash
yarn build      # ou npm run build
```

Tout se règle par `APP_URL`, qui doit contenir le sous-dossier :

```dotenv
APP_URL=https://exemple.com/regie
```

Laravel en déduit alors les URL des assets et des routes, et transmet le
préfixe à l'interface (`data-base-path`), qui l'applique à sa navigation et à
ses appels. Sans cela, la SPA viserait `/api/…` au lieu de `/regie/api/…`.

**`APP_URL` doit être correct au moment de `yarn build`**, et pas seulement à
l'exécution : les URL des polices et des images sont écrites dans le CSS à la
compilation et ne peuvent plus s'adapter ensuite. `vite.config.js` les préfixe
à partir d'`APP_URL`. Un build fait avec la mauvaise valeur donne des icônes
absentes et, en console, `OTS parsing error: invalid sfntVersion` — le serveur
répond une page HTML là où le navigateur attend une police.

Pour servir les fichiers depuis un CDN, `ASSET_URL` prime sur cette déduction.

Après tout changement d'`APP_URL` : `php artisan config:cache`, **puis
`yarn build`**.

Vérifier que le préfixe est bien pris en compte :

```bash
php artisan tinker --execute="echo route('login');"
# doit afficher https://exemple.com/regie/login
```

Côté serveur web, la racine documentaire doit pointer sur le dossier `public`
du projet, et non sur le projet lui-même.

### Node trop ancien sur le serveur

```
SyntaxError: The requested module 'node:util' does not provide an export named 'styleText'
```

Ce message signifie que Node est antérieur à 20.19 : `styleText` n'existe que
depuis Node 20.12, et Rolldown (le compilateur de Vite 8) s'en sert. Mettre
Node à jour, par exemple en 22 LTS sur Debian/Ubuntu :

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs
node -v      # doit afficher v22.x
```

**Alternative sans toucher au serveur** : compiler ailleurs et n'y déposer que
le résultat. Le serveur n'a alors besoin ni de Node ni de `node_modules`.

```bash
npm run build                                   # sur un poste à jour
rsync -av public/build/ user@serveur:/chemin/public/build/
```

## Raccorder une plateforme

Chaque SAAS expose le contrat `/api/access/groupes` (voir la documentation de
TVe : `docs/implementations/groupe-access-suspension.md` dans son dépôt).

Enregistrer la plateforme, puis synchroniser :

```bash
php artisan regie:sync
```

`base_url` et `api_token` sont modifiables sans redéploiement : les projets
changent de serveur, et l'équipe doit pouvoir corriger elle-même.

### Pas de cron en v1

La synchronisation est déclenchée à la main, par le bouton **Actualiser** de
l'écran des entreprises. C'est suffisant : elle ne rafraîchit que ce qui change
en dehors de cet outil — création ou renommage d'un groupe, effectif, flag
`enabled` d'un admin de plateforme — c'est-à-dire des événements rares. L'état
de blocage décidé ici, lui, est appliqué immédiatement, sans attendre de
synchronisation.

L'en-tête indique depuis combien de temps la liste n'a pas été rafraîchie, et
le signale en orange au-delà de `REGIE_STALE_AFTER_MINUTES` (60 par défaut).

Si un cron devenait souhaitable, une fréquence **quotidienne ou horaire**
suffirait, et `REGIE_STALE_AFTER_MINUTES` devrait alors valoir environ trois
fois l'intervalle — sinon l'avertissement resterait allumé en permanence et
serait ignoré. La commande sort en code 1 dès qu'une plateforme n'a pas
répondu, ce qui permet d'alerter.

## Comptes

Deux rôles. Un `collector` suspend et rétablit les accès ; un `admin` fait de
même et gère en plus les comptes, les plateformes et les modèles de motifs.

- Adresse `@acan.email` obligatoire — un compte sur une adresse personnelle
  survivrait au départ de son titulaire
- Mot de passe temporaire généré à la création, changement imposé à la première
  connexion
- Pas de « mot de passe oublié » : l'admin régénère un mot de passe temporaire
- Un compte supprimé perd l'accès immédiatement, y compris session ouverte
  (Laravel ne retrouve plus l'utilisateur en base)

## Ce qui structure le code

**`AccessSuspender` porte la règle la plus sensible.** L'état local n'est écrit
qu'après confirmation de la plateforme. Si l'appel échoue, l'exception remonte
et rien n'est enregistré — ni la suspension, ni le journal. Croire un client
suspendu alors qu'il ne l'est pas, ou l'inverse, est le pire défaut possible
pour cet outil.

**Le miroir local n'est pas un cache de confort.** Les plateformes vivent sur
d'autres serveurs ; sans copie locale, une seule injoignable rendrait toute
l'interface inutilisable. Les suspensions, elles, restent toujours des appels
live, jamais différés.

**`block_actions` conserve des instantanés.** Nom du groupe, nom et email de
l'agent sont recopiés à l'écriture. C'est ce qui permet de répondre à un client
en litige des mois plus tard, alors que le compte de l'agent a été supprimé ou
que le groupe a disparu. Cette table n'est jamais mise à jour.

**Deux verrous coexistent et restent distincts à l'écran.** `is_blocked` vient
du recouvrement, `platform_enabled` des admins de la plateforme. Sans cette
distinction, un agent débloque, le client reste dehors, et appelle le support.

**`SaasConnector` a une seule implémentation, volontairement.** Tant qu'un SAAS
respecte le contrat REST, il n'y a pas de connecteur à écrire. L'interface
existe pour le jour où une plateforme ne pourra pas l'exposer.

## Tests

```bash
php artisan test
```

La suite s'exécute sur `acan_regie_test` et refuse de démarrer sur toute
autre base (`tests/TestCase.php`).
