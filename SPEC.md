# aCAN Régie — Suspension d'accès pour recouvrement

Outil interne permettant à l'équipe de recouvrement de suspendre l'accès des
utilisateurs d'une entreprise cliente à nos SAAS, avec un motif affiché au client.

Première plateforme raccordée : **TVe** (`/Users/mac/Sites/acanvod-lara-vue`).

---

## 1. Besoin

Certaines entreprises clientes restent 3 à 4 mois sans payer. L'équipe de
recouvrement dispose de la liste des impayés et doit pouvoir couper l'accès
elle-même, sans passer par la technique, et sans accéder au back-office du SAAS.

Le client bloqué doit comprendre **pourquoi** dès sa tentative de connexion.

L'équipe n'est pas à l'aise avec l'informatique : l'interface prime sur la
richesse fonctionnelle.

---

## 2. Décisions structurantes

| Sujet | Décision | Raison |
|---|---|---|
| Architecture | App autonome + contrat API REST sur chaque SAAS | Un 2ᵉ SAAS = implémenter 3 endpoints, zéro changement dans aCAN Régie |
| Hébergement | aCAN Régie et chaque SAAS sur des serveurs distincts | L'API transite par Internet : sécurité renforcée obligatoire (§4.4) |
| Granularité | Par **Groupe**, jamais par utilisateur | `Groupe` est le terme commun à tous les SAAS |
| Périmètre | Back-office uniquement | La diffusion publique (apps mobiles, site client) reste intacte : pas d'impact sur les téléspectateurs finaux |
| Sessions actives | Éjection immédiate via middleware | Sans ça, un blocage du vendredi soir est sans effet sur une session ouverte |
| Motif | Modèles pré-rédigés FR/EN, modifiables | Garantit des messages corrects vus par les clients |
| Rôles | `admin` et `collector`, tous deux peuvent bloquer | L'admin gère en plus les comptes et les plateformes |
| Identité | Comptes locaux, email `@acan.email` obligatoire | Pas de SSO disponible aujourd'hui |
| Emails | Aucun envoi en v1 | Pas de SMTP fiable en production actuellement |
| Blocage programmé | Hors v1 | À réévaluer après usage réel |

---

## 3. Périmètre du blocage

Un groupe bloqué :

- ✅ ses `owner` et `manager` ne peuvent plus se connecter au back-office
- ✅ ceux déjà connectés sont éjectés à la requête suivante
- ✅ le motif s'affiche sur l'écran de connexion, dans la langue du groupe
- ❌ les comptes `super` et `admin` (internes) ne sont **jamais** affectés
- ❌ les endpoints `myapiv2` restent servis : apps mobiles et site public
  du client continuent de fonctionner normalement

---

## 4. Modifications côté TVe

### 4.1 Migration

Deux colonnes sur `groupes` :

```php
$table->timestamp('access_blocked_at')->nullable();
$table->text('access_block_reason')->nullable();
```

Volontairement **distinctes de `enabled`**, qui reste le levier des admins TVe.
Deux motifs de blocage différents, deux responsables différents, aucune
interférence : un admin ne peut pas annuler par inadvertance une décision de
recouvrement.

Aucune table d'historique côté TVe — l'historique vit dans aCAN Régie.

### 4.2 Point de décision unique

La règle « ce groupe est-il bloqué ? » ne doit exister qu'à un seul endroit,
sinon le login et le middleware divergeront tôt ou tard.

```php
final class GroupeAccessGuard
{
    public function assertAllowed(User $user): void;  // throws GroupeAccessBlocked
}
```

Consommée par :

- `App\Http\Requests\Auth\LoginRequest::authenticate()` — remplace le bloc
  `enabled` actuel (ligne ~57), qui reste vérifié en plus du nouveau champ
- `App\Http\Middleware\EnsureGroupeAccess` — nouveau

### 4.3 Middleware

`EnsureGroupeAccess` s'applique aux routes `web` authentifiées et au préfixe
`/admin`. **Jamais** sur `myapiv2`, ni sur la route de déconnexion.

⚠️ Le front TVe est une SPA Vue : vérifier que le 403 renvoyé sur un appel XHR
redirige bien vers l'écran de connexion avec le motif, et n'affiche pas une
erreur brute.

### 4.4 API consommée par aCAN Régie

Ce contrat est **identique sur toutes les plateformes**. Le terme `groupe` est
le vocabulaire partagé, et celui que reprend l'interface d'aCAN Régie.

```
GET  /api/access/groupes                → id, code, name, lang, users_count,
                                           enabled, access_blocked_at, access_block_reason
POST /api/access/groupes/{id}/block     → { reason: string }
POST /api/access/groupes/{id}/unblock
```

**Idempotence obligatoire** : bloquer un groupe déjà bloqué met à jour le motif
sans erreur ; débloquer un groupe actif renvoie un succès silencieux. Deux
personnes peuvent agir en parallèle, et un appel rejoué reste sans danger.

#### Sécurité — l'API est exposée sur Internet

aCAN Régie et les SAAS vivent sur des serveurs distincts : ces endpoints sont
publiquement joignables et permettent de couper l'accès de n'importe quel client.
Le token seul ne suffit pas.

**Authentification**
- Token aléatoire de 64 caractères, propre à chaque plateforme
- Comparaison via `hash_equals()` — jamais `==`, qui expose au timing attack
- Stocké chiffré côté aCAN Régie (cast `encrypted`), en clair dans le `.env` du SAAS
- Rotation possible sans redéploiement

**Pas de filtrage par IP — décision assumée**

L'infrastructure est gérée par un administrateur réseau extérieur à l'équipe, et
les projets sont déplacés de serveur en cas d'incident. Une allowlist d'IP
casserait le service à chaque opération réseau, sans que l'équipe recouvrement
ne puisse comprendre ni corriger. Une protection qu'on désactive en urgence ne
protège pas.

**Pas de signature HMAC non plus.** Elle protège de l'interception réseau, déjà
couverte par HTTPS, alors que le vecteur réaliste de fuite d'un secret est un
commit Git, un log de debug ou un accès au serveur — cas où le secret HMAC
aurait fui à l'identique. Complexité à reproduire sur chaque SAAS pour un gain
nul.

**Ce qui protège réellement : limiter le dégât et restaurer vite**

- HTTPS obligatoire, requête en clair refusée
- Token distinct par plateforme, jamais commité, rotation par commande artisan
- **Plafond métier** : un SAAS refuse au-delà de N blocages par heure
  (défaut 20, configurable). L'équipe en fait quelques-uns par jour ; un token
  compromis ne peut donc pas couper tout le parc client d'un coup. C'est la
  mesure la plus efficace du dispositif, et elle est indépendante du réseau.
- Rate limit HTTP sur les trois endpoints, en complément
- Journalisation côté SAAS de chaque appel : IP source, action, groupe, horodatage
- Entrée de log de niveau `warning` au dépassement du plafond — point d'accroche
  pour une alerte le jour où le SMTP existe
- **Commande de secours** `php artisan groupe:access-restore` sur chaque SAAS :
  débloque tous les groupes en une fois, utilisable par la technique sans passer
  par aCAN Régie. Plan de restauration en cas de token compromis, de bug, ou de
  aCAN Régie indisponible.

**Le motif est une donnée non fiable**

Il provient d'un système externe et s'affiche sur la page de connexion. Sans
précaution, quelqu'un qui atteint l'API peut y injecter du HTML et défigurer
l'écran de login, voire y placer un faux formulaire de saisie.

- Traité comme texte brut, jamais interprété comme HTML
- Longueur bornée (500 caractères), validée côté SAAS **et** côté aCAN Régie
- Échappé à l'affichage

### 4.5 Message affiché

Quand `access_blocked_at` est renseigné, le message rendu est
`access_block_reason`. Fallback sur un message générique si le motif est vide.
La clé `auth.group_inactive` existante reste utilisée pour le flag `enabled`.

---

## 5. Application aCAN Régie

### 5.1 Stack

Laravel + Vue 3 (composition API) + Vuetify 3 + Vite — identique à TVe.

### 5.2 Ouverture aux futurs SAAS

Seul endroit où l'abstraction est justifiée :

```php
interface SaasConnector {
    public function fetchGroupes(): Collection;
    public function block(string $externalId, string $reason): void;
    public function unblock(string $externalId): void;
}
```

Une seule implémentation, `HttpSaasConnector` : tant qu'un SAAS respecte le
contrat REST, on n'écrit **pas** de connecteur dédié. L'interface existe pour le
jour où un SAAS ne pourra pas l'exposer. Partout ailleurs : du Laravel ordinaire.

### 5.3 Modèle de données

**`users`** — équipe interne
`id`, `name`, `email` (unique, domaine `@acan.email` imposé), `password`,
`role` (`admin` | `collector`), `must_change_password`, timestamps

**`saas_platforms`**
`id`, `name`, `base_url`, `api_token` (chiffré), `active`, `last_reachable_at`

**`groupes`** — miroir local
`id`, `platform_id`, `external_id`, `code`, `name`, `lang`, `users_count`,
`is_blocked`, `blocked_at`, `block_reason`, `platform_enabled`, `synced_at`
— unique(`platform_id`, `external_id`)

**`block_reason_templates`**
`id`, `label`, `body_fr`, `body_en`, `position`

**`block_actions`** — journal immuable
`id`, `groupe_id`, `groupe_name`, `platform_name`, `action` (`block` | `unblock`),
`reason`, `actor_name`, `actor_email`, `created_at`

Les colonnes `groupe_name`, `actor_name` et `actor_email` sont des **instantanés**
figés à l'écriture : l'historique reste lisible après suppression d'un compte ou
disparition d'un groupe. Cette table n'est jamais mise à jour.

Tous les horodatages sont stockés en UTC — les serveurs sont distincts et n'ont
aucune raison de partager un fuseau. Conversion à l'affichage uniquement.

### 5.4 Miroir local et résilience réseau

Les groupes sont copiés localement, rafraîchis par une commande artisan et un
bouton « Actualiser ». Avec des plateformes réparties sur plusieurs serveurs,
ce miroir n'est pas un confort mais une nécessité : sans lui, une plateforme
injoignable rendrait toute l'interface inutilisable.

- Timeout court (10 s) sur chaque appel sortant
- Une plateforme injoignable n'empêche pas l'affichage : la liste sort du miroir,
  signalée par un bandeau « dernière synchronisation il y a X »
- Aucun retry automatique sur `block` / `unblock`, pour éviter les actions en
  double ; l'idempotence (§4.4) rend un nouvel essai manuel sans danger
- La synchronisation traite les plateformes indépendamment : l'échec de l'une
  n'interrompt pas les autres

**Le blocage lui-même est toujours un appel live, jamais différé.**

**Le groupe est relu juste avant d'être suspendu.** Le motif est résolu dans sa
langue puis figé côté plateforme jusqu'au rétablissement : partir d'une copie
datée enverrait durablement au client un message dans la mauvaise langue, et
annoncerait à l'agent un décompte d'utilisateurs faux. Une plateforme
injoignable n'empêche pas d'agir — la copie connue est utilisée, signalée comme
telle.

**Synchronisation manuelle en v1, sans cron.** Elle ne rafraîchit que ce qui
change hors de cet outil — création ou renommage d'un groupe, effectif, flag
`enabled` d'un admin de plateforme — soit des événements rares. L'état de
blocage décidé ici est appliqué immédiatement et n'en dépend pas. L'en-tête
annonce l'âge de la liste et le signale au-delà du seuil ; c'est ce rappel qui
remplace le cron. Un groupe disparu entre-temps produit un 404 traité
proprement (« Ce groupe n'existe plus, actualisez la liste »).

### 5.5 Langue du motif

aCAN Régie connaît la langue de chaque groupe (remontée par `fetchGroupes`),
résout la version FR ou EN **avant** l'appel, et n'envoie qu'une seule chaîne.
TVe ne stocke qu'une colonne et ne porte aucune logique de traduction.

Cas dégradé accepté : si un admin change la langue du groupe après le blocage,
le message reste dans la langue d'origine.

### 5.6 Gestion des comptes

- L'admin crée un compte : email `@acan.email` validé, mot de passe temporaire
  généré et affiché **une seule fois**, changement imposé à la première connexion
- Pas de « mot de passe oublié » en self-service : l'admin régénère un mot de
  passe temporaire
- Suppression réelle du compte au départ d'un employé ; l'historique conserve
  nom et email figés
- Un middleware éjecte à la requête suivante tout compte supprimé — même
  principe que côté TVe
- Le dernier admin ne peut pas être supprimé
- Premier admin créé par `php artisan regie:create-admin`

---

## 6. Interface

### 6.1 Écran principal — liste des groupes

Toutes plateformes confondues, recherche par nom ou code, filtre par statut.

Chaque ligne affiche **deux états distincts** :

- `Bloqué (recouvrement)` — décidé ici
- `Désactivé (admin TVe)` — le flag `enabled`

Sans cette distinction, un recouvreur débloque, le client reste dehors, et
appelle le support en disant que ça ne marche pas.

### 6.2 Blocage

1. Bouton **Bloquer l'accès** sur la ligne
2. Modale : choix du modèle, texte ajustable, **aperçu de l'écran exact que
   verra le client**
3. Confirmation nommée : « Vous allez bloquer 14 utilisateurs de *Groupe X* »
4. Appel live à la plateforme, puis mise à jour de l'affichage et journalisation

### 6.3 Déblocage

Un clic, sans confirmation lourde : l'erreur est bénigne dans ce sens.

### 6.4 Historique

Drawer latéral par groupe : qui, quand, quel motif, blocage ou déblocage.
Sert à répondre à un client en litige.

### 6.5 Administration (rôle `admin`)

Comptes de l'équipe, plateformes raccordées, modèles de motifs.

**Les plateformes doivent être modifiables sans redéploiement.** Les projets
changent de serveur en cas d'incident : l'URL d'un SAAS n'est pas une constante.
L'écran des plateformes permet donc de corriger `base_url` et `api_token`, avec
un bouton **Tester la connexion** qui appelle `GET /api/access/groupes` et
affiche un résultat lisible : joignable, URL introuvable, token refusé.

Sans ce bouton, un déménagement de serveur se traduirait par une interface qui
échoue sans explication utilisable par l'équipe recouvrement.

---

## 7. Points de vigilance

**L'état local reflète la plateforme, jamais l'intention.**
`is_blocked` n'est écrit en base qu'après réponse 200 du SAAS. En cas d'échec,
l'écran indique clairement que le blocage n'a pas été appliqué. C'est le scénario
le plus dangereux du système : croire qu'un client est bloqué alors qu'il ne
l'est pas, ou l'inverse.

**Double verrou.** Voir 6.1 — `enabled` et `access_blocked_at` coexistent et
doivent rester lisibles séparément.

**Idempotence.** Voir 4.4 — deux personnes peuvent agir en parallèle.

**Réversibilité.** Une erreur coupe l'accès d'un client payant. Garde-fous :
confirmation nommée, aperçu obligatoire, déblocage immédiat, historique complet.

**Surface d'attaque.** Voir 4.4 — chaque SAAS expose publiquement de quoi couper
l'accès de tous ses clients, et le motif est du texte externe rendu sur la page
de connexion. Ces deux points sont les plus sensibles du système. Le filtrage
réseau étant exclu, la défense repose sur le plafond horaire, la journalisation
et la commande de restauration.

**Mobilité des serveurs.** Les projets sont déplacés en cas d'incident, par une
équipe réseau extérieure. Aucun mécanisme ne doit dépendre d'une adresse IP, et
toute URL de plateforme doit rester modifiable depuis l'interface (§6.5).

**Divergence du miroir.** Si un admin TVe modifie `enabled` directement, le
miroir local est périmé jusqu'à la synchronisation suivante. Acceptable, à
condition que l'interface date toujours l'information affichée.

---

## 8. Découpage

**Lot 1 — TVe** (le plus risqué, à faire en premier)
Migration, `GroupeAccessGuard`, branchement dans `LoginRequest`, middleware
`EnsureGroupeAccess`, endpoints API + middleware token, plafond horaire de
blocages, rate limit, journalisation des appels, commande `groupe:access-restore`,
gestion du 403 côté SPA.

Ce lot est aussi le **modèle de référence** pour raccorder les prochains SAAS :
il doit rester assez petit pour être reproduit en une journée sur une autre
plateforme.

**Lot 2 — aCAN Régie, socle**
App Laravel, authentification, modèle de données, `SaasConnector` +
`HttpSaasConnector`, commande de synchronisation, création du premier admin.

**Lot 3 — aCAN Régie, interface**
Liste des groupes, modale de blocage avec aperçu, drawer d'historique.

**Lot 4 — administration**
Comptes, plateformes raccordées, modèles de motifs.

---

## 9. Hors périmètre v1

- Blocage programmé à une date future
- Notification email des owners au moment du blocage
- Intégration à l'outil de facturation (montants, échéances dans les motifs)
- SSO Google Workspace / Microsoft 365
- Coupure de la diffusion publique (`myapiv2`)
