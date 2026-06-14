#!/bin/bash
# push-news.sh — local auto-push for The Gridiron Gazette daily news.
#
# Runs ON your Mac (via launchd), NOT in the sandbox. It commits and pushes
# news.json whenever the sandbox scheduled task has refreshed it.
#
# Security model:
#   - No token in this script, in git config, or in the remote URL.
#   - The push authenticates via the macOS keychain credential helper
#     (git config --global credential.helper osxkeychain), which you set up.
#   - The token's only home is the macOS keychain.
#
# Idempotent: if news.json hasn't changed, it does nothing and exits 0.

set -euo pipefail

REPO="/Users/kevinsotka/Meatbag_Labs/thegridirongazette"
LOG="$REPO/scripts/push-news.log"
BRANCH="main"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >>"$LOG"; }

cd "$REPO" || { log "ERROR: cannot cd to $REPO"; exit 1; }

# Only act if news.json has uncommitted changes.
if git diff --quiet -- news.json && git diff --cached --quiet -- news.json; then
  log "No change to news.json — nothing to push."
  exit 0
fi

# Sanity: news.json must be valid JSON before we publish anything.
if ! python3 -c "import json,sys; json.load(open('news.json'))" 2>/dev/null; then
  log "ERROR: news.json is not valid JSON — refusing to push."
  exit 1
fi

# Build a short headline summary for the commit message.
HEADLINES=$(python3 -c "import json; d=json.load(open('news.json')); print('; '.join(s['headline'] for s in d['stories']))" 2>/dev/null || echo "daily refresh")
TODAY=$(python3 -c "import json; print(json.load(open('news.json'))['updated'])" 2>/dev/null || date +%Y-%m-%d)

git -c user.email="doc@thegridirongazette.com" -c user.name="Doc (auto)" \
    commit -qm "Daily wire ${TODAY}: ${HEADLINES}" -- news.json

if git push -q origin "$BRANCH"; then
  log "Pushed ${TODAY}: ${HEADLINES}  (commit $(git rev-parse --short HEAD))"
else
  log "ERROR: git push failed — token may need re-auth in keychain. Commit is local."
  exit 1
fi
