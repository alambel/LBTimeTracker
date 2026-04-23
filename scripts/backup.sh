#!/usr/bin/env bash
# LBTimeTracker — backup DB journalier.
#
# À planifier via cron (tous les jours à 03:00) :
#   0 3 * * * /chemin/vers/LBTimeTracker/scripts/backup.sh >> /chemin/vers/LBTimeTracker/data/backup.log 2>&1
#
# Lit les credentials depuis config.php (pas de secret en dur).
# Dump gzip → backups/YYYY-MM-DD.sql.gz, rotation 14 jours.

set -euo pipefail

# Racine du projet (2 niveaux au-dessus de ce script si lancé via cron)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
CONFIG_FILE="$APP_DIR/config.php"
BACKUP_DIR="$APP_DIR/backups"
RETENTION_DAYS="${LBTT_BACKUP_RETENTION:-14}"

if [[ ! -f "$CONFIG_FILE" ]]; then
    echo "[$(date -Iseconds)] ERREUR: config.php introuvable ($CONFIG_FILE)" >&2
    exit 1
fi

# Extrait les valeurs db depuis config.php via PHP (pas de parsing bash fragile)
read_cfg() {
    php -r '
        $c = require $argv[1];
        $k = $argv[2];
        if (!isset($c["db"][$k])) exit(2);
        echo $c["db"][$k];
    ' "$CONFIG_FILE" "$1"
}

DB_HOST="$(read_cfg host)"
DB_PORT="$(read_cfg port)"
DB_NAME="$(read_cfg dbname)"
DB_USER="$(read_cfg user)"
DB_PASS="$(read_cfg password)"

if [[ -z "${DB_NAME:-}" || -z "${DB_USER:-}" ]]; then
    echo "[$(date -Iseconds)] ERREUR: config.php incomplet (db)" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

# Bloque l'accès web aux backups (défense en profondeur — ajouter aussi en nginx/Apache)
if [[ ! -f "$BACKUP_DIR/.htaccess" ]]; then
    echo "Require all denied" > "$BACKUP_DIR/.htaccess"
fi

TIMESTAMP="$(date +%Y-%m-%d)"
OUT_FILE="$BACKUP_DIR/$TIMESTAMP.sql.gz"

# mysqldump via --defaults-extra-file pour éviter que le mot de passe apparaisse dans ps
TMP_CNF="$(mktemp -t lbtt-mysqldump-XXXXXX.cnf)"
trap 'rm -f "$TMP_CNF"' EXIT
cat > "$TMP_CNF" <<EOF
[client]
host=$DB_HOST
port=$DB_PORT
user=$DB_USER
password="$DB_PASS"
EOF
chmod 600 "$TMP_CNF"

echo "[$(date -Iseconds)] dump $DB_NAME → $OUT_FILE"
mysqldump --defaults-extra-file="$TMP_CNF" \
          --single-transaction \
          --quick \
          --skip-lock-tables \
          --routines \
          --triggers \
          --default-character-set=utf8mb4 \
          "$DB_NAME" \
    | gzip -9 > "$OUT_FILE.tmp"
mv "$OUT_FILE.tmp" "$OUT_FILE"
chmod 600 "$OUT_FILE"

echo "[$(date -Iseconds)] OK $(du -h "$OUT_FILE" | cut -f1)"

# Rotation : supprime les dumps > RETENTION_DAYS
find "$BACKUP_DIR" -maxdepth 1 -type f -name '*.sql.gz' -mtime "+$RETENTION_DAYS" -print -delete
echo "[$(date -Iseconds)] rotation (>$RETENTION_DAYS j) terminée"
