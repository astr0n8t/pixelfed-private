# Syncing with pixelfed-glitch

Handoff notes for bringing this fork up to date with
[pixelfed-glitch](https://github.com/pixelfed-glitch/pixelfed).

## Current state

- Last synced: **2026-10-27**, upstream `main` at `v0.15.2-glitch.1.13.0` (`5fae0069d`).
- This branch *contains upstream's real history*, so future syncs are ordinary merges.
- The pre-2026 fork history (based on `v0.12.5+glitch.1.10.1`) is attached with an
  `ours` merge (`Merge old pixelfed-private history`). It is kept for reference only; none of its
  file contents are used. Its last meaningful commit was `f8714e49`; everything after that was
  dependency bumps and Dockerfile changes upstream already had.

## Routine sync

```sh
git remote add upstream https://github.com/pixelfed-glitch/pixelfed   # once
git fetch upstream --tags
git checkout -b sync/upstream-<version> main
git merge upstream/main          # use a release tag instead of main if preferred
```

Resolve conflicts using the list of fork changes below, then run the checks in
[After merging](#after-merging).

### If a merge is ever hopeless

That is what happened in 2026: the fork had drifted ~4,700 commits behind, with conflicts
everywhere. The way out:

1. Branch from `upstream/main` and re-apply each feature below as its own commit.
2. `git merge -s ours main`. This records the old history as a parent but keeps the new tree,
   so `main` can fast-forward to the result with no force-push.
3. Anything on `main` that was not re-applied is dropped by step 2. Check
   `git log <last sync>..main` first.

## What the fork changes

Each item was one commit in the 2026 port (see `git log --first-parent`), and those commits are
the best reference when re-applying by hand.

### 1. Cache TTLs capped at 60 minutes
Cached payloads embed signed media URLs that expire after 60 minutes, so no cache may outlive them.
- `scripts/cap-cache-ttl.py app` rewrites every literal `Cache::remember` TTL above an hour and
  lists the TTLs it cannot evaluate (variables, constants). Check those by hand: anything over
  an hour that caches statuses, media, accounts, avatars, stories, notifications or
  collections needs capping.
- Hand edits outside `Cache::remember`: `NotificationService::ITEM_CACHE_TTL = 3600`, and
  `CollectionService` `Cache::put(..., 3600)`.
- Short TTLs (rate limits, tokens) are deliberately left alone.

### 2. Signed storage URLs (local media is never public)
- `signed_storage_url($path, $query = [])` in `app/helpers.php` makes a 60 minute
  `temporarySignedRoute('storage.file', ...)` link.
- Route `storage/{file}` → `SignedStorageController@show` (middleware `signed`) in `routes/web.php`.
- nginx (`docker/rootfs/nginx/.../conf.d/default.conf`) serves only static asset paths
  directly; everything else, `/storage` included, goes to PHP.
- **On every sync, run `grep -rn "Storage::url(" app`.** Any new local media/avatar/story URL
  must use `signed_storage_url()`. Leave `no-preview.png`, default avatar/header images,
  banner config and URLs for non-local disks (S3/CDN) alone.
- `Media::thumbnailUrl()` signs `thumbnail_path` *before* falling back to the stored
  `thumbnail_url`. A stored signed URL would expire.
- `GroupService::get()` re-signs group avatar/header from their stored `path`.
- Caches keyed per viewer (`... . ':' . Auth::id()`): `StatusService::get`,
  `MediaService::get`, `GroupMediaService::get`, `Profile::avatarUrl`. Trade-off: deleting or
  editing a post, or changing an avatar, can look stale to others for up to an hour.
- `AccountService::settings()` is not cached.

### 3. Restricted mode (`RESTRICTED_INSTANCE=true`)
- `bootstrap/app.php`: `'restricted'` is in the `api` middleware group (upstream only has it
  on `web`).
- `app/Http/Middleware/RestrictedAccess.php` also accepts the `api` guard (mobile apps) and allows
  `api/v1.1/auth/invite/*`, `api/service/health-check`, `api/nodeinfo*` and `storage/*`.
- `ApiV1Controller::instance()` returns only `uri` to anonymous clients (checked *before*
  the cache).
- `Nodeinfo::get()` returns only software and version.
- `InstanceActorController` returns 404 for profile, inbox and outbox.

### 4. User invites (`PF_USER_INVITES=true`)
- `UserInviteController` (rewritten), `Jobs/UserInvitePipeline/DispatchUserInvitePipeline`,
  `Mail/UserInviteMessage` (reuses upstream's `emails.user.invite`), `UserInvite::url()` →
  `/auth/invite/u/{token}`.
- Routes: `routes/web-api.php` (`api/invites/{create,delete,get}`), `routes/api.php`
  (`invite/user/{verify,uc,ec}`), `routes/web.php` (`i/invites`, `i/invites/create`,
  `auth/invite/u/{code}`, `api/v1.1/auth/invite/user/re`). The old `settings/invites` routes
  and views are removed.
- Frontend: `components/InvitesHome.vue`, `InvitesCreate.vue`, `invite/UserInvite.vue`,
  `js/invites.js` (replaces `admin_invite.js` in `webpack.mix.js`), SPA routes in `js/spa.js`,
  nav links in `partials/navbar.vue` and `js/components/NavMenu.vue`, `navmenu.invites` in
  `i18n/en-US.json`.
- `invite/AdminInvite.vue` only shows instance stats when present (restricted mode hides them).

### 5. NSFW and pronoun flags (`PF_NSFW_ENABLED`, `PF_PRONOUNS_ENABLED`, default off)
- `config/pixelfed.php` holds the `nsfw` and `pronouns` keys, which `Util/Site/Config.php`
  exposes to the frontend.
- Vue templates are gated on `config.nsfw.enabled` / `config.pronouns.enabled`. Components
  that use them need `config: window.App.config` in `data()`. To find them:
  `grep -rln "config.nsfw\|config.pronouns" resources/assets`.
- `settings/home.blade.php` wraps the pronoun selector in `@if`.

### 6. Autofollow defaults
`config/account.php` reads `AUTOFOLLOW_ENABLED` and `AUTOFOLLOW_USERNAMES`.

### 7. Branding, CI and tooling
- `README.md` and this file.
- `.github/workflows/docker.yml` builds fpm and nginx images for amd64 only, from `main` and
  tags, pushes only to GHCR and takes the version from `v*-private` tags. **Upstream workflows
  and `.github` files are deleted. Delete any that a merge brings back** (tests, larastan,
  pint, db, docker-cleanup, dependabot, FUNDING, ISSUE_TEMPLATE).
- `renovate.json` (vulnerability alerts only).
- `SoftwareUpdateService` checks `astr0n8t/pixelfed-private` releases. Diagnostics shows the
  plain version. `config/pixelfed.php` has a `+private` fallback version.
- `02-check-config.sh` runs dottie with `LOG_LEVEL=info`.
- Nix: multi-system `flake.nix` (flake-utils); `dgoss` and `mkcert -install` dropped
  from `shell.nix`.

## After merging

1. `php -l` every changed PHP file, or better, run the test suite with PHP ≥ 8.4.
2. `grep -rn "Storage::url(" app` (see section 2) and `python3 scripts/cap-cache-ttl.py app`.
3. Check `ls .github/workflows` holds only `docker.yml`.
4. **Rebuild the frontend.** The Docker image ships the committed `public/` bundles
   (`BUILD_FRONTEND=0`):
   ```sh
   npm ci && npm run production
   git add public && git commit -m "chore: rebuild frontend assets"
   ```
   When `public/` conflicts during a merge, take either side and rebuild.
5. Deploy and smoke-test on a dev instance with `RESTRICTED_INSTANCE=true`:
   - logged out: everything redirects to `/login`; `/api/v1/instance` returns only `uri`
   - images, avatars and stories load, and their URLs carry `signature=`
   - the mobile app can log in and upload
   - an invite email arrives and signing up through it works
6. Tag the release as `v<upstream version>-private-<n>`, e.g. `v0.15.2-private-0.2.0`.
