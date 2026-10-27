#!/usr/bin/env bash
# Deploy Skool Monitor from this git clone to the live site.
#
#   cd ~/repos/accademics && ./deploy/deploy.sh            # pull from GitHub, then copy into the site
#   ./deploy/deploy.sh --no-pull                           # copy what is checked out now
#   ./deploy/deploy.sh --dry-run                           # show what would change, change nothing
#
# Only the built frontend (dist/) and backend/ are copied. These stay as the server has them:
#   site .htaccess, php.ini, .user.ini, .well-known/, database/   (outside what is copied)
#   backend/config/*.local.php   (database login, signing key, platform settings)
#   backend/uploads/             (photos and documents)
#   backend error_log files
# backend/tests and backend/tools are never put on the web.
# Database changes are not run here: run the new backend/migrations/NN_*.php by hand after a backup.
set -euo pipefail

SITE="${SITE:-$HOME/public_html/accademics}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
PULL=1
DRY=""
for arg in "$@"; do
  case "$arg" in
    --no-pull) PULL=0 ;;
    --dry-run) DRY="--dry-run" ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

cd "$REPO"
if [ "$PULL" = 1 ]; then
  git pull --ff-only
fi
echo "Deploying $(git log -1 --format='%h %s') to $SITE ${DRY:+(dry run)}"

[ -d "$SITE/backend" ] || { echo "No site at $SITE" >&2; exit 1; }
[ -f dist/index.html ] || { echo "dist/ is missing: build the frontend (npm run build) and commit it" >&2; exit 1; }
# The site's database login must be in database.local.php before Database.php from git goes live.
[ -f "$SITE/backend/config/database.local.php" ] || { echo "Create $SITE/backend/config/database.local.php first (see Database.php)" >&2; exit 1; }

# Backend first (new API files), keeping the server's own files.
rsync -a --delete $DRY \
  --exclude 'config/*.local.php' \
  --exclude 'uploads/' \
  --exclude 'tests/' \
  --exclude 'tools/' \
  --exclude 'error_log' \
  backend/ "$SITE/backend/"

# Frontend: new assets, then index.html last so the page never points at files not there yet.
# Old asset files are kept so pages already open in a browser keep working.
rsync -a $DRY dist/assets/ "$SITE/assets/"
rsync -a $DRY --exclude 'assets/' --exclude 'index.html' dist/ "$SITE/"
rsync -a $DRY dist/index.html "$SITE/index.html"

echo "Done. If this release adds backend/migrations, back up the database and run them."
