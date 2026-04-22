# LB Time Tracker

Outil léger de suivi du temps de travail **par demi-journée et par projet**. Saisie rapide dans un calendrier mensuel, résumé et export CSV. PHP + SQLite, aucune dépendance externe.

## Fonctionnalités

- **Calendrier mensuel** — clic sur une demi-journée (AM/PM) → choix du projet + note facultative, enregistrement AJAX.
- **Résumé par période** — demi-journées, jours équivalents, pourcentages, barres de répartition.
- **Export CSV** de la période filtrée.
- **Gestion des projets** — nom, couleur, archivage, suppression (cascade sur les saisies).
- **Authentification simple** — un seul utilisateur, mot de passe bcrypt, session PHP.
- **UI responsive** (desktop / tablette / mobile).

## Prérequis

- PHP 8.0 ou plus récent
- Extensions `pdo_sqlite` (standard)
- Pas de Composer, pas de base de données à configurer, pas de build

## Installation locale

```bash
git clone <repo> LBTimeTracker
cd LBTimeTracker
php -S localhost:8000
```

Ouvrir http://localhost:8000/. La première visite affiche une page de **configuration initiale** qui crée le compte administrateur et écrit `config.php`.

## Déploiement sur serveur PHP

1. Upload le dossier (sans `config.php` ni `data/`) sur le serveur.
2. Pointer le vhost sur la racine du dossier (DocumentRoot).
3. Vérifier que le dossier est accessible en écriture par PHP (pour créer `config.php` et `data/timetrack.sqlite`).
4. Accéder à l'URL → page de setup → login.

Aucune règle de réécriture d'URL (`.htaccess`, nginx rewrite) n'est nécessaire.

## Usage

### Saisir une demi-journée

1. Ouvrir **Calendrier**.
2. Cliquer sur le créneau **AM** ou **PM** d'un jour.
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
│   ├── db.php             # Schéma et helpers SQLite
│   ├── auth.php           # Login / session
│   ├── api.php            # Endpoints JSON (AJAX)
│   ├── setup.php          # Page de configuration initiale
│   ├── render.php         # Handlers des pages HTML
│   └── helpers.php        # Utilitaires (escape, dates)
├── views/
│   ├── layout.php         # Layout commun (nav + main)
│   ├── login.php
│   ├── calendar.php       # Grille mensuelle + dialog de saisie
│   ├── summary.php        # KPIs + tableau filtrable
│   └── projects.php       # CRUD projets
├── assets/
│   ├── style.css
│   └── app.js             # Handler du dialog calendrier
├── data/                  # SQLite (auto-créé, gitignore)
│   └── timetrack.sqlite
├── config.php             # Généré au setup, gitignore
└── .gitignore
```

## Sauvegarde

Toute la donnée tient dans **un seul fichier** : `data/timetrack.sqlite`. Pour sauvegarder :

```bash
cp data/timetrack.sqlite data/timetrack.sqlite.$(date +%Y%m%d).bak
```

Ou simplement copier le fichier ailleurs.

## Réinitialiser

- **Changer le mot de passe** : supprimer `config.php`, recharger la page, refaire le setup.
- **Tout effacer** : supprimer `data/timetrack.sqlite` (les projets et saisies seront perdus).

## Sécurité

- Mot de passe stocké en hash bcrypt dans `config.php` (permissions 0600).
- Session avec cookie `HttpOnly` et `SameSite=Lax`.
- Toutes les entrées utilisateur sont échappées (`htmlspecialchars`).
- `config.php` et `data/` sont exclus de git.

> ⚠️ Pour un usage en production exposé à Internet, servir impérativement en **HTTPS** et envisager une IP allowlist ou un reverse-proxy avec auth additionnelle.

## Licence

Usage personnel.
