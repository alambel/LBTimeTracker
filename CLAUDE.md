# LB Time Tracker — notes Claude

Outil multi-user de suivi du temps par créneau. PHP + MariaDB, zéro dépendance Composer.

Signup public ouvert. Chaque user choisit sa granularité (`hd2`=2×4h, `hd4`=4×4h, `hr10`=10×1h). Projets partageables avec rôles `admin`/`member`. Pas de suppression : users et projets s'archivent uniquement.

## Stack
- PHP 8+ (utilise `str_starts_with`, nullsafe, union types)
- **MariaDB / MySQL** via PDO (connexion mysql, charset utf8mb4)
- Pas de Composer, pas de framework, pas de build
- Vanilla JS + CSS (fichiers statiques dans `assets/`)

## Lancer localement
```bash
php -S localhost:8000
```
1er accès → page de setup qui demande **admin (user/pwd)** et **connexion MariaDB (host, port, dbname, user, password)**. La page teste la connexion, crée les tables, puis écrit `config.php`. Pour tester localement, installer MariaDB (`brew install mariadb && brew services start mariadb`), créer une base et un user dédié.

## Routing
Tout passe par `index.php?action=X` — pas de rewrite rule nécessaire.

Actions :
- `calendar` (défaut) — grille mensuelle perso, grille = slot_mode de l'user (hd2/hd4/hr10)
- `summary` — agrégats par projet (en heures), filtre période, export CSV
- `projects` — liste des projets dont je suis membre, partage/rôles (POST `op=create|update|archive_toggle|add_member|set_member_role|remove_member`)
- `team&id=N` — vue équipe d'un projet : calendrier avec initiales des membres + résumé par membre
- `profile` — changer slot_mode ou mot de passe
- `users` — app admin uniquement : archive/promouvoir app admin
- `login` / `signup` / `logout`
- `api_entries` (GET) — JSON des entries d'une plage (scopé user courant)
- `api_save_entry` (POST JSON) — upsert/suppression d'un créneau

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

## Schéma MariaDB
```sql
users(
    id, username UNIQUE, password_hash,
    is_app_admin TINYINT(1),
    slot_mode VARCHAR(8) DEFAULT 'hd4',   -- 'hd2' | 'hd4' | 'hr10'
    archived TINYINT(1) DEFAULT 0,
    created_at
)

projects(
    id, name UNIQUE, color, archived, created_by → users(id) NULL,
    created_at
)

project_members(
    project_id FK → projects(id) CASCADE,
    user_id    FK → users(id)    CASCADE,
    role ENUM('admin','member'),
    PK (project_id, user_id)
)

entries(
    id, user_id FK → users(id) CASCADE,
    date DATE, period VARCHAR(4),          -- 'D1/D2' | 'AM/PM/EV/NT' | 'H01..H10'
    project_id FK → projects(id) CASCADE,
    note, updated_at,
    UNIQUE (user_id, date, period)
)
```

- **Slot modes** (granularité par *user*, pas par projet) — centralisés dans `helpers.php#slot_modes()` :
  - `hd2` : `D1`, `D2` (240 min chacun)
  - `hd4` : `AM`, `PM`, `EV`, `NT` (240 min chacun — legacy)
  - `hr10` : `H01`..`H10` (60 min chacun)
- Les **codes sont uniques entre modes**, donc la durée d'une entrée s'infère du code (`slot_minutes_for_code()`). Les agrégations multi-users totalisent en **heures** (pas en jours), ce qui gère l'hétérogénéité des modes.
- `set_entry($userId, ..., $projectId=null)` supprime l'entrée si `projectId` null. Toujours scopé au user courant.
- `db_migrate()` idempotent via `information_schema` (has_column/has_index/has_fk). Migre automatiquement le schéma legacy mono-user (entries.period ENUM → VARCHAR, ajout user_id, backfill depuis `config.php` username+password_hash).
- L'user DB doit avoir `SELECT/INSERT/UPDATE/DELETE/CREATE/ALTER/INDEX/REFERENCES`.

## Rôles et visibilité
- **App admin** (flag `users.is_app_admin`) : le 1er user créé au setup l'est. Accès à `/users` pour archiver/promouvoir. Toujours ≥ 1 actif.
- **Project admin** (`project_members.role = 'admin'`) : renomme/archive le projet, ajoute/retire des membres, change les rôles. Créateur = admin auto. Toujours ≥ 1 par projet.
- **Member** : saisit ses propres entrées sur les projets dont il est membre. Voit la vue équipe (lecture seule).
- **Archivage** : `users.archived = 1` → login refusé, session tuée côté lecture. Entrées conservées, visibles en vue équipe avec badge *archivé*. Pareil côté projet.

## Sécurité
- `config.php` en mode 0600, contient uniquement les credentials MariaDB + `timezone` + `session_name`. Plus de hash user (migré en DB).
- Session : `httponly=true`, `samesite=Lax`, nom `lbtt`. `$_SESSION['uid']` + `$_SESSION['user']`.
- Login : lookup `users` par username, `password_verify` bcrypt. `session_regenerate_id(true)` après login.
- Signup : rate-limit partagé avec login. Username regex `[A-Za-z0-9._-]{2,64}`. Password 6–128.
- Toute action projet vérifie `user_is_project_member` / `user_is_project_admin`.
- API sauvegarde entrée : vérifie que le user est membre du projet cible.
- Affichage user-provided → `e()` (htmlspecialchars). SQL → 100% prepared statements.
- Ignoré par git : `config.php`, `backups/`, `data/`, `.idea/`

## Pièges connus
- **Formulaires projets** : une `<form>` par ligne (pas de forms imbriquées dans `<table>`). 2 boutons `name="op"` avec `value=update|delete` partagent le même form.
- **`session_set_cookie_params` AVANT `session_start`**, sinon sans effet.
- **Dialog natif** : `<dialog>` + `dialog.showModal()`. Fallback `setAttribute('open')` si pas supporté.
- **Semaine démarre lundi** : `cursor->format('N')` retourne 1-7 (lundi=1), décalage = N-1 cellules vides avant le 1er du mois.
- **Timezone** : lue depuis `config.timezone` (défaut `Europe/Zurich`), appliquée avant `session_start`.
- **GROUP BY strict** : MySQL en mode `ONLY_FULL_GROUP_BY` (défaut 5.7+) exige que toute colonne sélectionnée soit dans le GROUP BY ou agrégée. La query `summary_between()` inclut `p.name, p.color` dans le GROUP BY pour cette raison.
- **DB credentials perdus** : supprimer `config.php` → setup se relance. Les données MariaDB sont préservées (tables intactes).
- **Ajouter un slot_mode** : éditer `slot_modes()` dans `helpers.php`. Les codes doivent être **uniques** entre modes (pas de collision AM↔AM).
- **Migration legacy** : si `config.php` legacy a `username`+`password_hash`, la 1ère ouverture de DB crée l'user #1 (app_admin) depuis ces valeurs et backfill `entries.user_id = 1` / `projects.created_by = 1` / `project_members(user=1, role=admin)` pour tous les projets existants.

## Déploiement serveur PHP
1. Upload le dossier (sans `config.php`).
2. Pointer le vhost sur le dossier (DocumentRoot) ou sur `index.php`.
3. Créer la base MariaDB côté serveur avant la première visite.
4. 1ère visite → setup (admin + creds DB), puis login.

### Action de déploiement Plesk (recommandée)
Pour que le footer affiche les infos du **dernier commit déployé**, cocher « Activer des actions de déploiement supplémentaires » dans la config du dépôt Plesk et ajouter :
```bash
git log -1 --format='%H%n%cI%n%s' > version.txt
```
Ce fichier 3-lignes (hash, date ISO, subject) est lu par `lib/version.php`. Sans cette action, le code fallback sur `exec('git log')` puis sur la lecture directe de `.git/HEAD` (hash + mtime seulement, pas de subject).

### Sécurité d'accès web
- **Apache** : le `.htaccess` racine couvre tout (blocage de `config.php`, `lib/`, `views/`, `.md`, `.sql`, `.bak`).
- **Plesk** (nginx proxy Apache) : les fichiers statiques (`.md`, `.sql`) peuvent être servis par nginx **sans passer par Apache** → `.htaccess` contourné. Il faut ajouter des directives nginx.

Dans Plesk : domaine → **Paramètres Apache & nginx** → *Directives nginx supplémentaires* :

```nginx
# LB Time Tracker — blocage des fichiers et dossiers sensibles
location ~* \.(md|log|bak|sql|env)$ {
    deny all;
    return 404;
}
location = /config.php { deny all; return 404; }
location ^~ /lib/   { deny all; return 404; }
location ^~ /views/ { deny all; return 404; }
location ^~ /data/  { deny all; return 404; }
location ^~ /design_handoff_lbtt_redesign/ { deny all; return 404; }
location ~ /\.(?!well-known) { deny all; return 404; }
```

Vérifier après déploiement : `curl -I https://<domaine>/config.php` doit renvoyer 403/404, **pas** 200.

Ajouter aussi (dump DB + scripts) :
```nginx
location ^~ /backups/ { deny all; return 404; }
location ^~ /scripts/ { deny all; return 404; }
```

## Backup DB journalier
Script shell : `scripts/backup.sh` — dump gzip vers `backups/YYYY-MM-DD.sql.gz`, rotation 14 jours (`LBTT_BACKUP_RETENTION` pour override).
Le script lit les creds DB depuis `config.php` (zéro secret en dur) et utilise `--defaults-extra-file` pour ne pas exposer le mot de passe dans `ps`.

**Crontab** (user app) :
```cron
0 3 * * * /chemin/vers/LBTimeTracker/scripts/backup.sh >> /chemin/vers/LBTimeTracker/data/backup.log 2>&1
```
Sur Plesk : **Outils & paramètres → Tâches planifiées**, même ligne.

`backups/` est déjà ignoré par git + bloqué côté web (`.htaccess` + nginx).

## Test end-to-end rapide (nécessite MariaDB local)
```bash
# 1. Créer la base
mysql -e "CREATE DATABASE lbtt CHARACTER SET utf8mb4; CREATE USER 'lbtt'@'localhost' IDENTIFIED BY 'lbtt'; GRANT ALL ON lbtt.* TO 'lbtt'@'localhost';"
# 2. Lancer le serveur
php -S 127.0.0.1:8765 &
# 3. Setup (POST tous les champs)
curl -c /tmp/c -X POST -d 'username=admin&password=testtest&password2=testtest&db_host=127.0.0.1&db_port=3306&db_name=lbtt&db_user=lbtt&db_password=lbtt&timezone=Europe/Zurich' http://127.0.0.1:8765/index.php
# 4. Login + save entry (cf versions précédentes)
```

Sans MariaDB local : valider la syntaxe PHP (`php -l`), la page de setup affiche les erreurs de connexion clairement.

## Conventions de collaboration
- **Commit ET push automatiques autorisés sur ce projet** (décidé 2026-04-22). Après un bloc de modifications terminé et vérifié : commit avec message clair, puis `git push origin main`. Le push déclenche le déploiement automatique Plesk via webhook GitHub.
- UI en français, code/comments en français ou anglais indifféremment.
- Pas de dépendance externe à ajouter sans raison forte (l'esprit = 1-dossier-upload-ça-marche).
- **Remote** : `git@github.com:alambel/LBTimeTracker.git` (SSH).
