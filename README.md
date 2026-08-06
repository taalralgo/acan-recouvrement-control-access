# blockAccess

Outil interne permettant à l'équipe de recouvrement de suspendre l'accès des
utilisateurs d'une entreprise cliente à nos SAAS, avec un motif affiché au client.

Conception et décisions : [SPEC.md](SPEC.md).

## Démarrer

```bash
composer install
php artisan migrate --seed
php artisan blockaccess:create-admin      # premier compte, mot de passe affiché une fois
php artisan serve
```

## Raccorder une plateforme

Chaque SAAS expose le contrat `/api/access/groupes` (voir la documentation de
TVe : `docs/implementations/groupe-access-suspension.md` dans son dépôt).

Enregistrer la plateforme, puis synchroniser :

```bash
php artisan blockaccess:sync
```

`base_url` et `api_token` sont modifiables sans redéploiement : les projets
changent de serveur, et l'équipe doit pouvoir corriger elle-même.

À planifier en cron (toutes les 15 min par exemple). Le code de sortie vaut 1
dès qu'une plateforme n'a pas répondu, pour permettre une alerte.

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

La suite s'exécute sur `acan_blockaccess_test` et refuse de démarrer sur toute
autre base (`tests/TestCase.php`).
