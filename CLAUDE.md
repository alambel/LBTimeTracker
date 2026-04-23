# LB Time Tracker — notes Claude

Petit outil perso de suivi du temps par créneau (4 par jour). PHP + MariaDB, zéro dépendance Composer.

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

## Schéma MariaDB
```sql
projects(
    id INT UNSIGNED AUTO_INCREMENT PK,
    name VARCHAR(100) UNIQUE,
    color VARCHAR(7) DEFAULT '#4a90e2',
    archived TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB utf8mb4_unicode_ci

entries(
    id INT UNSIGNED AUTO_INCREMENT PK,
    date DATE,
    period ENUM('AM','PM','EV','NT'),
    project_id INT UNSIGNED FK → projects(id) ON DELETE CASCADE,
    note VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (date, period)
) ENGINE=InnoDB utf8mb4_unicode_ci
```
- **4 créneaux par jour** : `AM` (Matin), `PM` (Après-midi), `EV` (Soir), `NT` (Nuit). Codes et labels centralisés dans `helpers.php` (`period_codes()`, `period_labels()`, `valid_period()`).
- `period` est un `ENUM` (plus propre qu'un CHECK, validation côté DB). Ajouter un nouveau créneau = `ALTER TABLE entries MODIFY COLUMN period ENUM(...)` + update `period_codes()`/`period_labels()`.
- `set_entry(..., projectId=null)` supprime l'entrée (la "case vide").
- `set_entry()` utilise **DELETE + INSERT dans une transaction** pour rester portable (pas de dépendance à `ON DUPLICATE KEY UPDATE` MySQL-only).
- `ON DELETE CASCADE` sur la FK → supprimer un projet efface ses saisies.
- `db_migrate()` se contente de `CREATE TABLE IF NOT EXISTS`. L'user DB doit avoir `CREATE, ALTER, INDEX, REFERENCES` en plus de `SELECT/INSERT/UPDATE/DELETE`.

## Sécurité
- `config.php` en mode 0600, contient `password_hash` (bcrypt) ET les credentials MariaDB
- Session : `httponly=true`, `samesite=Lax`, nom `lbtt`
- Login : `hash_equals` sur username + `password_verify` sur mot de passe
- `session_regenerate_id(true)` après login OK
- Tout l'affichage user-provided passe par `e()` (htmlspecialchars)
- Requêtes SQL : 100% prepared statements (PDO avec `ATTR_EMULATE_PREPARES = false`)
- Ignoré par git : `config.php`, `.idea/`

## Pièges connus
- **Formulaires projets** : une `<form>` par ligne (pas de forms imbriquées dans `<table>`). 2 boutons `name="op"` avec `value=update|delete` partagent le même form.
- **`session_set_cookie_params` AVANT `session_start`**, sinon sans effet.
- **Dialog natif** : `<dialog>` + `dialog.showModal()`. Fallback `setAttribute('open')` si pas supporté.
- **Semaine démarre lundi** : `cursor->format('N')` retourne 1-7 (lundi=1), décalage = N-1 cellules vides avant le 1er du mois.
- **Timezone** : lue depuis `config.timezone` (défaut `Europe/Zurich`), appliquée avant `session_start`.
- **GROUP BY strict** : MySQL en mode `ONLY_FULL_GROUP_BY` (défaut 5.7+) exige que toute colonne sélectionnée soit dans le GROUP BY ou agrégée. La query `summary_between()` inclut `p.name, p.color` dans le GROUP BY pour cette raison.
- **DB credentials perdus** : supprimer `config.php` → setup se relance. Les données MariaDB sont préservées (tables intactes).
- **Ajouter un créneau** : `ALTER TABLE entries MODIFY COLUMN period ENUM('AM','PM','EV','NT','XX')` + mettre à jour `period_codes()`/`period_labels()` dans `helpers.php`. Pas de migration data puisque ENUM étendu ne casse pas les valeurs existantes.

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
