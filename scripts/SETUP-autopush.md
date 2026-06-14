# Gridiron Gazette — secure daily auto-push setup

## How it works now

The publishing is split in two, so **no GitHub token ever lives in the sandbox,
in git config, or in a remote URL**:

1. **Sandbox scheduled task `tgg-daily-news`** (runs ~10:01 AM daily) researches
   the day's 3 stories in Doc's voice and writes `news.json` into this repo
   folder. It does *no* git work and holds *no* token.
2. **Local launchd job `com.gridirongazette.pushnews`** (runs 10:15 AM on your
   Mac) detects the changed `news.json`, commits it, and `git push`es using the
   token in your **macOS keychain** (via the `osxkeychain` credential helper you
   already configured). The token's only home is the keychain.

## One-time install (run these on your Mac)

```bash
# 1. Make the push script executable
chmod +x ~/Meatbag_Labs/thegridirongazette/scripts/push-news.sh

# 2. Install the launchd job (symlink the plist into LaunchAgents)
ln -sf ~/Meatbag_Labs/thegridirongazette/scripts/com.gridirongazette.pushnews.plist \
       ~/Library/LaunchAgents/com.gridirongazette.pushnews.plist

# 3. Load it
launchctl unload ~/Library/LaunchAgents/com.gridirongazette.pushnews.plist 2>/dev/null
launchctl load   ~/Library/LaunchAgents/com.gridirongazette.pushnews.plist

# 4. Confirm it's registered
launchctl list | grep gridirongazette
```

## Seed the keychain with your NEW token (one time)

Your first manual push will prompt and store the token; after that it's silent.
Easiest path — do one manual push so the keychain captures it:

```bash
cd ~/Meatbag_Labs/thegridirongazette
git push origin main
# When prompted:
#   Username: kevin-sotka   (your GitHub username)
#   Password: <paste the NEW personal-access token>
# The osxkeychain helper stores it. You won't be asked again.
```

(If macOS already has an old entry, clear it first:
`printf "protocol=https\nhost=github.com\n\n" | git credential-osxkeychain erase`
then run the push above to store the new one.)

## Test it without waiting for 10:15 AM

```bash
# Touch news.json so the script sees a change, then run it directly:
~/Meatbag_Labs/thegridirongazette/scripts/push-news.sh
cat ~/Meatbag_Labs/thegridirongazette/scripts/push-news.log
```

## Notes / safety

- The script refuses to push if `news.json` is invalid JSON.
- It only pushes when `news.json` actually changed (safe to run repeatedly).
- `scripts/*.log`, `scripts/token*`, `scripts/*.secret`, and `.env` are
  gitignored — a stray token file can't be committed.
- To pause auto-push: `launchctl unload ~/Library/LaunchAgents/com.gridirongazette.pushnews.plist`
- To rotate the token later: delete the old one on GitHub, then re-seed the
  keychain with the "erase + push" steps above. Nothing else changes.
