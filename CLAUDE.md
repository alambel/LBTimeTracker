# LB Time Tracker — notes Claude

Petit outil perso de suivi du temps par demi-journée. PHP + SQLite, zéro dépendance.

## Stack
- PHP 8+ (utilise `str_starts_with`, nullsafe, union types)
- SQLite via PDO (auto-créé au 1er run dans `data/timetrack.sqlite`)
- Pas de Composer, pas de framework, pas de build
- Vanilla JS + CSS (fichiers statiques dans `assets/`)

## Lancer localement
```bash
php -S localhost:8000
```
1er accès → page de setup qui crée `config.php` (bcrypt hash). Après : login normal.

## Routing
Tout passe par `index.php?action=X` — pas de rewrite rule nécessaire.

Actions :
- `calendar` (défaut) — vue mensuelle, saisie AM/PM
- `summary` — agrégats par projet, filtre période, export CSV (`&format=csv`)
- `projects` — CRUD (POST `op=create|update|delete`)
- `login` / `logout`
- `api_entries` (GET) — JSON des entries d'une plage
- `api_save_entry` (POST JSON) — upsert/suppression d'une demi-journée

Actions préfixées `api_*` → JSON + `require_auth()` renvoie 401 JSON au lieu d'un redirect.

## Fichiers clés
| Fichier | Rôle |
|---|---|
| `index.php` | Front controller, bootstrap session, dispatch action |
| `lib/db.php` | Schéma + helpers CRUD (projects, entries, summary) |
| `lib/auth.php` | Session, `require_auth()`, login/logout |
| `lib/api.php` | Endpoints JSON |
| `lib/setup.php` | Page de config initiale (écrit `config.php`) |
| `lib/render.php` | Handlers des pages HTML (wrap `views/layout.php`) |
| `lib/helpers.php` | `e()` (escape), `month_title`, `shift_month`, `valid_date` |
| `views/calendar.php` | Grille + `<dialog>` de saisie, projets injectés en `data-projects` JSON |
| `assets/app.js` | Handler du dialog, appel `api_save_entry`, update DOM |

## Schéma SQLite
```sql
projects(id, name UNIQUE, color, archived, created_at)
entries(id, date, period CHECK IN ('AM','PM','EV','NT'), project_id FK, note, updated_at,
        UNIQUE(date, period))  -- un seul projet par créneau
```
- **4 créneaux par jour** : `AM` (Matin), `PM` (Après-midi), `EV` (Soir), `NT` (Nuit). Codes et labels centralisés dans `helpers.php` (`period_codes()`, `period_labels()`, `valid_period()`).
- `set_entry(..., projectId=null)` supprime l'entrée (la "case vide")
- `set_entry()` utilise **DELETE + INSERT dans une transaction** (pas `ON CONFLICT DO UPDATE`, non supporté sur SQLite < 3.24 que certains serveurs utilisent encore).
- `ON DELETE CASCADE` depuis projects → supprimer un projet efface ses saisies
- `foreign_keys = ON` activé à l'init
- **Migration auto** : `db_migrate()` détecte une ancienne table `entries` avec CHECK `('AM','PM')` uniquement et la recrée avec le nouveau CHECK (copie des données préservée).

## Sécurité
- `config.php` en mode 0600, contient `password_hash` (bcrypt via `password_hash(PASSWORD_BCRYPT)`)
- Session : `httponly=true`, `samesite=Lax`, nom `lbtt`
- Login : `hash_equals` sur username + `password_verify` sur mot de passe
- `session_regenerate_id(true)` après login OK
- Tout l'affichage user-provided passe par `e()` (htmlspecialchars)
- Ignoré par git : `config.php`, `data/`, `*.sqlite`, `.idea/`

## Pièges connus
- **Formulaires projets** : une `<form>` par ligne (pas de forms imbriquées dans `<table>`). 2 boutons `name="op"` avec `value=update|delete` partagent le même form.
- **`session_set_cookie_params` AVANT `session_start`**, sinon sans effet.
- **Dialog natif** : `<dialog>` + `dialog.showModal()`. Fallback `setAttribute('open')` si pas supporté.
- **Semaine démarre lundi** : `cursor->format('N')` retourne 1-7 (lundi=1), décalage = N-1 cellules vides avant le 1er du mois.
- **Timezone** : lue depuis `config.timezone` (défaut `Europe/Zurich`), appliquée avant `session_start`.
- **SQLite UPSERT** : bannir `INSERT ... ON CONFLICT DO UPDATE` (échoue sur SQLite < 3.24 avec *"syntax error near ON"*). Utiliser DELETE+INSERT en transaction ou `INSERT OR REPLACE`.
- **Ajouter un créneau** : le CHECK constraint SQLite n'est pas altérable — il faut recréer la table (pattern déjà en place dans `db_migrate()`). Toujours mettre à jour `period_codes()` et `period_labels()` dans `helpers.php` en même temps que le `CHECK`.

## Déploiement serveur PHP
1. Upload le dossier (sans `config.php` ni `data/`).
2. Pointer le vhost sur le dossier (DocumentRoot) ou sur `index.php`.
3. `data/` doit être writable par PHP (auto-créé par `db_init`).
4. 1ère visite → setup, puis login.

### Sécurité d'accès web
- **Apache** : le `.htaccess` racine couvre tout (blocage de `config.php`, `data/`, `lib/`, `views/`, `.md`, `.sqlite`).
- **Plesk** (nginx proxy Apache) : les fichiers statiques (`.sqlite`, `.md`) peuvent être servis par nginx **sans passer par Apache** → `.htaccess` contourné. Il faut ajouter des directives nginx.

Dans Plesk : domaine → **Paramètres Apache & nginx** → *Directives nginx supplémentaires* :

```nginx
# LB Time Tracker — blocage des fichiers et dossiers sensibles
location ~* \.(md|sqlite|sqlite-journal|db|log|bak|env)$ {
    deny all;
    return 404;
}
location = /config.php { deny all; return 404; }
location ^~ /data/  { deny all; return 404; }
location ^~ /lib/   { deny all; return 404; }
location ^~ /views/ { deny all; return 404; }
location ~ /\.(?!well-known) { deny all; return 404; }
```

Vérifier après déploiement : `curl -I https://<domaine>/data/timetrack.sqlite` doit renvoyer 403/404, **pas** 200.

## Test end-to-end rapide
```bash
php -S 127.0.0.1:8765 &
curl -c /tmp/c -X POST -d 'username=test&password=testtest&password2=testtest' http://127.0.0.1:8765/index.php
curl -c /tmp/c -b /tmp/c -X POST -d 'username=test&password=testtest' 'http://127.0.0.1:8765/index.php?action=login'
curl -c /tmp/c -b /tmp/c -X POST -H 'Content-Type: application/json' \
  -d '{"date":"2026-04-22","period":"AM","project_id":1,"note":"x"}' \
  'http://127.0.0.1:8765/index.php?action=api_save_entry'
```

## Conventions de collaboration
- **Commit ET push automatiques autorisés sur ce projet** (décidé 2026-04-22). Après un bloc de modifications terminé et vérifié : commit avec message clair, puis `git push origin main`. Le push déclenche le déploiement automatique Plesk via webhook GitHub.
- UI en français, code/comments en français ou anglais indifféremment.
- Pas de dépendance externe à ajouter sans raison forte (l'esprit = 1-dossier-upload-ça-marche).
- **Remote** : `git@github.com:alambel/LBTimeTracker.git` (SSH).
