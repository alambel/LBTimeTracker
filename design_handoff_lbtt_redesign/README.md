# Handoff — LBTimeTracker redesign

## Overview

LBTimeTracker is an existing single-user PHP / MariaDB time-tracking app. The
user logs time in four **créneaux** (slots) per day — Matin (AM), Après-midi
(PM), Soir (EV), Nuit (NT) — and assigns each slot to a project. The app
supports project CRUD with archiving, CSV export, and a minimal bcrypt-based
auth flow.

This handoff proposes a **visual + interaction redesign** of the existing app.
The data model, URL structure, and PHP backend are unchanged. Only the
frontend views (`views/layout.php`, `views/login.php`, `views/calendar.php`,
`views/summary.php`, `views/projects.php`) are being reworked, plus the
install flow.

## About the design files

The `.html` / `.jsx` / `.css` files bundled here are **design references built
in HTML+React for fast iteration** — not production code. The task is to
port these designs into the existing LBTimeTracker PHP codebase using its
current server-rendered Twig/PHP templates + vanilla JS stack (or jQuery if
already in use). Keep all existing backend endpoints and form posts; only the
markup, CSS and client-side interactions change.

If a component's behavior requires richer client-side state (drag-to-fill on
the calendar, sheet editor), add vanilla JS modules — do not introduce
React/Vue/a build system unless already present.

## Fidelity

**High-fidelity.** Colors, typography, spacing, borders, and the full
interaction model are final. Recreate pixel-perfectly.

## Design intent

- Visual register: **editorial ledger / warm accounting paper**. Think
  carnet-style, printed grid, no rounded cards, no gradients.
- Language stays **French** throughout the UI.
- Typography: **Fraunces** (serif, display), **Inter** (sans, UI),
  **JetBrains Mono** (monospace, labels/numbers/chips).
- Heavy rule-based dividers, very small uppercase mono labels,
  ledger-style tabular numerals for figures.

---

## Design tokens

All tokens are declared in `lbtt-styles.css`. Copy these verbatim.

### Colors — light theme
| Token | Hex | Usage |
|---|---|---|
| `--lbtt-paper`    | `#fafaf7` | Base background (warm off-white) |
| `--lbtt-paper-2`  | `#f2f0e8` | Zebra rows, weekend cells, panels |
| `--lbtt-paper-3`  | `#e9e6dc` | Empty-state slot fill, chart track |
| `--lbtt-ink`      | `#1a1a1a` | Primary text, borders-strong |
| `--lbtt-ink-2`    | `#333333` | Secondary text |
| `--lbtt-muted`    | `#8a8578` | Meta labels |
| `--lbtt-faint`    | `#c8c3b4` | Very faint separators / handles |
| `--lbtt-rule`     | `#dcd8cb` | Default 1px rules |
| `--lbtt-accent`   | `#c45a2e` | Burnt orange — primary accent |
| `--lbtt-accent-ink` | `#7a3216` | Destructive / accent-text |

### Colors — dark theme (class `.lbtt-theme-dark` on `<html>`)
| Token | Hex |
|---|---|
| `--lbtt-paper`    | `#141413` |
| `--lbtt-paper-2`  | `#1d1c1a` |
| `--lbtt-paper-3`  | `#2a2926` |
| `--lbtt-ink`      | `#f2efe6` |
| `--lbtt-ink-2`    | `#c8c4b8` |
| `--lbtt-muted`    | `#7a7568` |
| `--lbtt-faint`    | `#3c3a34` |
| `--lbtt-rule`     | `#2a2926` |
| `--lbtt-accent`   | `#e3733f` |
| `--lbtt-accent-ink` | `#f5b894` |

### Project palette (default project colors)
Users can pick any hex via the projects screen. Defaults offered:
`#c45a2e`, `#2d4a3e`, `#c9a24a`, `#5a6f8a`, `#8a6a4a`, `#a8a094`, `#a14a3a`, `#6a8a5a`.

### Typography
- Serif: `"Fraunces", "Tiempos Headline", "Times New Roman", serif` — weight
  300 for large numerals, 400 for titles. `letter-spacing: -0.02em` on
  display sizes.
- Sans: `"Inter", -apple-system, sans-serif` — 13–15px body, 300–500.
- Mono: `"JetBrains Mono", ui-monospace, Menlo, monospace` — **all uppercase
  labels use mono**, 9–11px, `letter-spacing: 0.10em`–`0.15em`.
- Ledger numbers (day totals, KPIs): Fraunces 300,
  `font-feature-settings: "tnum", "lnum"`, `letter-spacing: -0.03em`.

### Type scale
| Role | Font | Size | Case |
|---|---|---|---|
| Page title (desktop) | Fraunces 400 | 56–64 / 0.95 | lowercase |
| Page title (mobile) | Fraunces 400 | 28 / 1 | lowercase |
| Section title | Fraunces 400 | 22–24 | lowercase |
| KPI number | Fraunces 300 | 32–44 / 1 | — |
| Body | Inter 400 | 13–15 | as-is |
| Small meta | Inter 400 | 11–12 | as-is |
| Label (uppercase) | JB Mono | 10 / 0.12em | UPPER |
| Micro label | JB Mono | 9 / 0.15em | UPPER |

### Spacing / border / misc
- All radii are **0** — strictly rectangular. Pills (`lbtt-tag`) use
  `border-radius: 99px` exceptionally for legend chips.
- Default border: `1px solid var(--lbtt-rule)`.
- Emphasised border: `1px solid var(--lbtt-ink)`.
- Drop shadows: none, except the bottom-sheet lift (`0 1px 0 rgba(0,0,0,0.06)`).
- Focus state on inputs: border color becomes `var(--lbtt-ink)`.

### Button styles (`.lbtt-btn`, in `lbtt-styles.css`)
- All buttons: mono 11px, letter-spacing 0.10em, UPPERCASE,
  padding `10px 14px`, border `1px solid ink`, transparent bg.
- `.lbtt-btn-primary`: filled ink bg, paper text. Hover → accent bg.
- `.lbtt-btn-ghost`: no border, transparent. Hover → paper-2 bg.

---

## Data model (unchanged from current app)

- **users**: id, username, password (bcrypt)
- **projects**: id, user_id, name, color (hex), archived (bool), created_at
- **entries**: id, user_id, date (YYYY-MM-DD), slot (`AM`|`PM`|`EV`|`NT`),
  project_id, note (<=200 chars). `UNIQUE(user_id, date, slot)`.

Deleting a project **cascades** to its entries. Warn the user.

Slot hours (for display only):
- `AM` Matin · 08–12
- `PM` Après-midi · 13–17
- `EV` Soir · 17–21
- `NT` Nuit · 22–02

Each filled slot counts as **0.5 jour (demi-journée)**. Fully-filled day = 2.0 j.

---

## Screens

### S1 · Login (`/login`)

**Purpose**: connect the single user.

**Desktop** — split layout, 1.2fr left / 1fr right.
- Left: ink background, burnt-orange 16×16 square top-left, mono subtitle
  "LB — TIME TRACKER · V1.0", giant lowercase "carnet d'heures." wordmark
  (Fraunces 88/0.9, second line accent orange). Bottom-right decorative
  48px-wide column of 4 stacked slot colors (orange / dark-green / blue /
  near-black).
- Right: centered form, max-width 320px. Monostyled label "Utilisateur",
  ink-border input; same for password. Primary button full-width
  "SE CONNECTER →". Fine print below: "BCRYPT · HTTPONLY · SAMESITE=LAX".

**Mobile** — single column, 50px top padding. Wordmark at 44/0.95. Form at
the bottom.

See `mobile.jsx` → `MobileLogin`, `web.jsx` → `WebLogin`.

### S2 · Calendar / mois en cours (`/` or `/calendar`)

The centerpiece. **Replaces** the day-card list from the current app with a
month grid. Week starts **Monday**.

**Layout (desktop)**
- Left rail nav `200px` — same on all authed screens. Mini logo,
  lowercase "carnet d'heures.", three items numbered `01 02 03` (Calendrier,
  Résumé, Projets). Current page: underlined, weight 500. Session block
  bottom with 28×28 ink avatar showing "A" + "adrien" + mono
  "DÉCONNEXION →".
- Main content: `24px 28px` padding.
- Page header row: left side holds mono label "Mois en cours · 2026" and a
  huge lowercase month word ("avril.") in Fraunces 56. Right side holds:
  - "Saisi" stat (jours équiv. à 1 décimale),
  - vertical rule,
  - "Dominant" (color square + project name — the most-logged project of
    the period),
  - vertical rule,
  - prev / AUJ. / next buttons (ghost).
- Tip banner below header: paper-2 background, 1px rule border, "Astuce"
  chip + message "Cliquez un créneau pour l'éditer. Maintenez et glissez
  pour remplir plusieurs créneaux à la fois."
- Grid: outer `1px solid ink` border. Header row is **ink background**,
  paper text, 7 columns "Lundi … Dimanche" as 9.5px mono 0.15em UPPER.
- Day cells: 7 cols × 5–6 rows, minHeight 110. Weekend cells use `paper-2`.
  Empty out-of-month cells also use `paper-2` with no day number.
- Each day cell contains:
  - Top row: ledger number for the day (Fraunces 20). If today,
    accent-orange and a small "Auj." accent chip to the right.
  - Four horizontal slot strips stacked vertically, gap 2px, flex:1.
    Each strip is either filled with project color (white text inside
    showing `AM/PM/EV/NT` label + project name, ellipsis) or transparent
    with a `1px dashed rule` border and muted text.
  - While dragging, strips under the pointer get `2px outline: ink`
    inside the border.
- Legend bar under grid: horizontal list of all active projects as pill
  tags (`.lbtt-tag`).

**Layout (mobile)**
- Compact header: mono "Journal · 2026" + Fraunces "avril." + prev/next.
- "Aujourd'hui" card: 30×30 ink tile with today's day number, label
  "Aujourd'hui · 2/4" showing filled count, and 4 mini color strips.
- Weekday strip: single-letter columns "L M M J V S D".
- Grid: 7 columns, minimal padding. Each cell shows a centered 9.5px day
  number and 4 thin (9px) color strips. Tap or long-press any strip to edit.

**Edit sheet / modal**
- Mobile: bottom sheet, slide-up 220ms `cubic-bezier(.2,.8,.3,1)`. Drag handle
  36×3 faint. Header shows slot label ("Matin · 08–12") and the date as
  lowercase Fraunces. 2-column grid of project buttons (color swatch +
  name). Full-width dashed ghost button "× EFFACER". If single-slot, Note
  input below (200 char max).
- Desktop: centered modal 500px, overlay `rgba(26,26,26,0.55)`. 3-column
  project grid. Same structure otherwise.
- On multi-cell drag, header becomes "Remplissage groupé" + "N créneaux
  sélectionnés" and the note input is hidden.

**Interactions**
- **Single click / tap on a slot** → edit sheet.
- **Pointer-down + drag across slots** → collects a path of unique
  `(date, slot)` pairs; on pointer-up, opens the sheet in "group" mode.
  Implementation is in `mobile.jsx` `MobileCalendar` and `web.jsx`
  `WebCalendar` — see the `pointerDown` / `onMove` / `onUp` handlers.
  `touch-action: none` on slot elements.
- **Today** auto-scroll not needed; month view always shows current
  month by default.
- Prev / next month buttons rebuild the `entries` map from the server for
  that month.
- Selecting a project persists via POST `/entries` → backend writes or
  replaces the row (`UNIQUE(user_id, date, slot)`).
- Clicking `×` Effacer DELETEs the entry.

See `mobile.jsx` → `MobileCalendar`, `web.jsx` → `WebCalendar`, `WebSheet`.

### S3 · Résumé (`/summary`)

**Purpose**: agrégation par projet sur une période, CSV export.

**Desktop layout**
- Page header: "Rapport · période filtrée" label + "résumé." Fraunces 56.
  Right: primary button "↓ EXPORT CSV" (triggers current PHP CSV endpoint).
- Filter bar (1px rule border): "Du" + "Au" date inputs, "Filtrer →"
  primary button, right-aligned meta like "30 JOURS · 60 DEMI-JOURNÉES".
- KPI strip — 4 equal cards, 1px rule border, padding 13×16:
  1. "Demi-journées" — total count, sub "SUR 120 POSSIBLES".
  2. "Jours équiv." — to 1 decimal, sub "120 J. MAX".
  3. "Projets actifs" — count of projects with at least one entry.
  4. "Complétude" — total / max possible, as %.
  All numbers are Fraunces 40/1. Sub-label is 9.5px mono muted.
- **Distribution quotidienne** — a ribbon chart: 1 thin column per day,
  4 horizontal bands per column (one per slot), colored by project or
  `paper-3` if empty. End labels "01 AVR." / "30 AVR." in mono 9px.
- Table: columns `Projet | Demi-journées | Jours éq. | % | Répartition`.
  Header is paper-2. Each row shows the project color square, name,
  ledger numbers, % in mono, and a thin 8px bar indicating the project's
  share. Sorted descending by demi-journées.

**Mobile layout**
- Compact header "Résumé · avril 2026" + "temps."
- Segmented range control (full-width, 3 buttons): Mois / Trim. / Année.
  Selected = ink fill, paper text.
- Two KPI cards side-by-side ("Jours éq." / "Demi-j.").
- A single 24px stacked horizontal bar showing the 100% distribution by
  project (ink border).
- List of projects with 4-col grid: swatch, name + "N demi-j." subtext,
  large ledger-number of days, % in mono muted.
- Bottom: full-width primary "↓ Export CSV".

See `mobile.jsx` → `MobileSummary`, `web.jsx` → `WebSummary`.

### S4 · Projets (`/projects`)

**Purpose**: CRUD projects + archiver.

**Desktop layout**
- Left rail + content padding like Calendar.
- Header "Taxonomie" + "projets." Fraunces 56.
- "Nouveau projet" bar: ink border, paper-2 bg, row containing: name
  input (flex 1), color swatch picker (7 preset squares, selected one gets
  2px ink border), primary button "Ajouter →".
- Table (1px rule border): columns
  `swatch | name (inline editable) | hex | saisies count | Archiver | Supprimer`.
  Archived rows: paper-2 bg, 0.5 opacity.
- Footer note in mono muted: "⚠ SUPPRIMER UN PROJET EFFACE AUSSI TOUTES SES
  SAISIES (CASCADE)."

**Mobile layout**
- Stacked list, each row: swatch, name + (archived | hex), one ghost
  action on the right ("▢ Archiver" / "↑ Restaurer").
- Tap "+ Nouveau projet" dashed row at bottom → inline edit form with name
  input, color swatches, Enregistrer / Annuler.

See `mobile.jsx` → `MobileProjects`, `web.jsx` → `WebProjects`.

### S5 · Installation (first-run setup, mobile shown — desktop mirrors)

4-step wizard with a thin segmented progress bar (4 segments):
1. **Utilisateur admin** — username + password (bcrypt-hashed server-side).
   Note: "Hash bcrypt stocké dans `config.php` (chmod 0600)."
2. **Base de données** — host / port / db / user / password fields in a
   2-col grid.
3. **Timezone** — default `Europe/Zurich`. Note: "Utilisée pour
   l'agrégation quotidienne et les bornes de période."
4. **Success** — 64×64 accent-orange tick square + "Connexion établie."
   + meta "Tables créées · config.php généré". Button "Se connecter →".

See `mobile.jsx` → `MobileSetup`.

### S6 · Constellation annuelle (mobile, optional feature)

A year-view heatmap. Each column = 1 ISO week, each square = 1 day with 4
tiny stacked slot strips colored by project. Bottom: 12 mono initials
(j f m a m j j a s o n d) as axis labels, then the project legend.

Useful as a yearly overview page at `/year` or as a tab inside summary.

See `mobile.jsx` → `MobileHeatmap`.

---

## Components to extract

When porting to PHP templates:
- `site-header.php` — inline SVG mark + lowercase Fraunces wordmark.
- `nav-rail.php` (desktop) — reusable left rail with numbered items.
- `slot-strip.php` — single slot bar (AM/PM/EV/NT + project name or dashed
  empty state).
- `edit-sheet.php` / `edit-modal.php` — project picker with note input;
  accepts one or many `(date, slot)` targets.
- `project-tag.php` — pill with color dot + name.
- `chip.php` — uppercase mono chip, variants: default + `accent` filled.
- `ledger-number.php` — Fraunces 300 with tabular numerals, arbitrary size.
- `kpi-card.php` — label + ledger number + micro-meta.

Share `lbtt-styles.css` across all templates.

---

## Interactions & behavior (must-implement)

- **Drag-to-fill on the calendar** (desktop and mobile): pointer-down on a
  slot starts a drag path; while moving, every slot element under the
  pointer (`document.elementFromPoint` + `closest('[data-slot]')`) is
  added to the path (deduplicated). On pointer-up, open the edit modal in
  group mode. Disable native selection during drag
  (class `.lbtt-no-select`). `touch-action: none` on slot elements so
  mobile drag works without scrolling.
- **Prev / next month**: refetch entries for the new month from the server
  (e.g. `/api/entries?year=&month=`).
- **Autosave on project assignment**: modal close commits the write,
  with optimistic UI update.
- **Archive / restore**: toggles `projects.archived`. Archived projects
  are excluded from the "Add entry" picker but still show in the summary
  if they have historical data.
- **CSV export**: existing backend endpoint, unchanged.

Animation specs (minimal):
- Mobile bottom sheet: `translateY(100% → 0)`, 220ms
  `cubic-bezier(.2,.8,.3,1)`.
- Button hover: 100ms background + color.
- Input focus: 120ms border color.
- No spring animations, no page transitions.

## Responsive behavior

- ≥ 900px: desktop layout (left rail + content). Calendar becomes the
  full month grid.
- < 900px: single column, mobile layout from `mobile.jsx`. Left rail
  becomes a bottom tab bar or a menu button (implementer's choice — a
  3-tab bottom bar is consistent with the aesthetic if kept rectangular).

## Files in this bundle

- `LBTimeTracker Redesign.html` — entry point, opens the full design canvas
  with all screens side-by-side. Open this first.
- `lbtt-styles.css` — **all design tokens + base classes.** Copy verbatim
  into the PHP app as a shared stylesheet.
- `lbtt-data.jsx` — sample data + helpers (`lbttMonthGrid`,
  `lbttAggregate`, etc.) — reference only; reimplement server-side in PHP.
- `mobile.jsx` — mobile views (Calendar, Summary, Projects, Login, Setup,
  Heatmap). Use as visual + interaction reference.
- `web.jsx` — desktop views (Calendar, Summary, Projects, Login).
- `ios-frame.jsx`, `design-canvas.jsx` — mock scaffolding for the HTML
  preview, **not needed in production**.

## Assets

None — the design uses only typography (three Google-hosted families) and
flat color. Google Fonts import string:

```
https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&family=JetBrains+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap
```

The brand mark in login / rail is the 14–18px orange square + lowercase
wordmark — no SVG required.

---

## Acceptance checklist

- [ ] Login, Calendar, Summary, Projects, Setup re-implemented in PHP
      templates using the design tokens from `lbtt-styles.css`.
- [ ] French UI preserved everywhere.
- [ ] Calendar month grid, drag-to-fill, single/group edit sheet.
- [ ] Desktop left rail + mobile adaptive layout at < 900px.
- [ ] KPI strip + daily ribbon + project table on Summary.
- [ ] Project archive/restore + delete-with-cascade warning.
- [ ] 4-step install wizard with progress bar.
- [ ] Dark theme via `.lbtt-theme-dark` class on `<html>` (optional but
      tokens are already defined).
