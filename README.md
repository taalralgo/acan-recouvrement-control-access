# aCAN Régie

Outil interne permettant à l'équipe de recouvrement de suspendre l'accès des
utilisateurs d'une entreprise cliente à nos SAAS, avec un motif affiché au client.

Conception et décisions : [SPEC.md](SPEC.md).

## Démarrer

```bash
composer install
php artisan migrate --seed
php artisan regie:create-admin      # premier compte, mot de passe affiché une fois
php artisan serve
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
