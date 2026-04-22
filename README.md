# LB Time Tracker

Outil léger de suivi du temps de travail **par créneau et par projet**. 4 créneaux par jour (Matin, Après-midi, Soir, Nuit), saisie rapide dans un calendrier mensuel, résumé et export CSV. PHP + MariaDB, aucune dépendance externe.

## Fonctionnalités

- **Calendrier mensuel** — clic sur un créneau (Matin / Après-midi / Soir / Nuit) → choix du projet + note facultative, enregistrement AJAX. 4 créneaux par jour.
- **Résumé par période** — demi-journées, jours équivalents, pourcentages, barres de répartition.
- **Export CSV** de la période filtrée.
- **Gestion des projets** — nom, couleur, archivage, suppression (cascade sur les saisies).
- **Authentification simple** — un seul utilisateur, mot de passe bcrypt, session PHP.
- **UI responsive** (desktop / tablette / mobile).

## Prérequis

- PHP 8.0 ou plus récent
- Extension `pdo_mysql`
- Base **MariaDB 10.2+** ou **MySQL 5.7+** accessible
- Pas de Composer, pas de build

## Installation sur serveur (Plesk)

1. **Créer la base** : Plesk → Bases de données → Ajouter
   - Nom de la base (ex: `lbtt`)
   - Utilisateur dédié + mot de passe
   - Noter host / port / nom / user / pwd
2. **Déployer le code** : via Git (Plesk → Git) ou upload.
   - Le DocumentRoot doit pointer sur le dossier contenant `index.php`.
3. **Accéder à l'URL** → page de **configuration initiale** :
   - Renseigner le compte administrateur (user + mot de passe)
   - Renseigner les paramètres MariaDB (host, port, dbname, user, pwd)
   - Timezone (défaut `Europe/Zurich`)
   - La page **teste la connexion** puis crée les tables et `config.php`.
4. Login avec le compte admin créé → c'est parti.

Aucune règle de réécriture d'URL (`.htaccess` rewrite) n'est nécessaire.

## Installation locale

```bash
# Créer une base MariaDB locale
mysql -e "CREATE DATABASE lbtt CHARACTER SET utf8mb4;
          CREATE USER 'lbtt'@'localhost' IDENTIFIED BY 'lbtt';
          GRANT ALL ON lbtt.* TO 'lbtt'@'localhost';"

# Lancer le serveur PHP
cd LBTimeTracker
php -S localhost:8000
```

Ouvrir http://localhost:8000/ → setup avec `host=localhost`, `dbname=lbtt`, `user=lbtt`, `password=lbtt`.

## Usage

### Saisir un créneau

1. Ouvrir **Calendrier**.
2. Cliquer sur un créneau d'un jour : **AM** (matin), **PM** (après-midi), **EV** (soir) ou **NT** (nuit).
3. Sélectionner un projet (ou « Aucun / Effacer » pour vider).
4. Ajouter une note facultative (200 caractères max).
5. **Enregistrer**.

Navigation : `‹` / `›` pour changer de mois, **Aujourd'hui** pour revenir au mois courant.

### Gérer les projets

Onglet **Projets** :
- Ajouter : nom + couleur → Ajouter.
- Modifier : éditer les champs puis **Enregistrer**.
- Archiver : cocher *Archivé* (le projet disparaît du calendrier mais ses saisies restent).
- Supprimer : **Supprimer** → confirmation. ⚠️ Supprime aussi toutes les saisies liées.

### Consulter le résumé

Onglet **Résumé** :
- Choisir la plage de dates → **Filtrer**.
- Visualiser : demi-journées, jours équivalents, % du total, répartition par projet.
- **Export CSV** pour télécharger les données.

## Structure

```
LBTimeTracker/
├── index.php              # Front controller (routing, auth, dispatch)
├── lib/
│   ├── db.php             # Schéma et helpers MariaDB (PDO)
│   ├── auth.php           # Login / session
│   ├── api.php            # Endpoints JSON (AJAX)
│   ├── setup.php          # Setup initial (admin + DB)
│   ├── render.php         # Handlers des pages HTML
│   └── helpers.php        # Utilitaires (escape, dates, périodes)
├── views/
│   ├── layout.php         # Layout commun (nav + main)
│   ├── login.php
│   ├── calendar.php       # Grille mensuelle + dialog de saisie
│   ├── summary.php        # KPIs + tableau filtrable
│   └── projects.php       # CRUD projets
├── assets/
│   ├── style.css
│   └── app.js             # Handler du dialog calendrier
├── config.php             # Généré au setup, gitignore (creds DB + hash admin)
├── .htaccess              # Blocage accès web des fichiers sensibles
└── .gitignore
```

## Sauvegarde

Toutes les données sont dans MariaDB. Pour sauvegarder :

```bash
mysqldump -u lbtt -p lbtt > backup_$(date +%Y%m%d).sql
```

Restaurer :
```bash
mysql -u lbtt -p lbtt < backup_YYYYMMDD.sql
```

## Réinitialiser

- **Changer le mot de passe admin ou les creds DB** : supprimer `config.php`, recharger la page, refaire le setup. Les données MariaDB sont préservées.
- **Tout effacer** : `DROP DATABASE lbtt; CREATE DATABASE lbtt ...` puis supprimer `config.php` et refaire le setup.

## Sécurité

- Mot de passe admin stocké en hash bcrypt dans `config.php` (permissions 0600).
- Credentials MariaDB dans `config.php` (jamais exposés côté client).
- Session avec cookie `HttpOnly` et `SameSite=Lax`.
- Toutes les entrées utilisateur sont échappées (`htmlspecialchars`).
- Toutes les requêtes SQL utilisent des prepared statements (PDO, `EMULATE_PREPARES=false`).
- `config.php` exclu de git.
- **`.htaccess`** à la racine bloque l'accès web direct à `config.php`, `lib/`, `views/`, `.md`, `.sql`, `.bak`.

> ⚠️ **nginx** : si le serveur utilise nginx (seul ou en proxy devant Apache, cas typique Plesk), les fichiers statiques contournent `.htaccess`. Voir `CLAUDE.md` pour les directives nginx à ajouter.

> ⚠️ Pour un usage en production exposé à Internet, servir impérativement en **HTTPS** et envisager une IP allowlist ou un reverse-proxy avec auth additionnelle.

## Licence

Usage personnel.
