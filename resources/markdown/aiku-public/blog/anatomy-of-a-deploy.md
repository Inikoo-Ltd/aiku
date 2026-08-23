---
title: Anatomy of a deploy
summary: Twenty‑nine steps between "push to production" and "the warehouse is on the new code", and not one of them makes a picker wait. A walk through one deploy as it actually runs — releases and symlinks, caches, migrations on one host only, a front‑end build that is skipped when nothing changed, checksums that decide what to flush, queues told to finish, workers reloaded from an anchor, SSR restarted only if it must, a broadcast that tells open tabs to refresh, and a few chores at the end.
date: 2026-08-19
tags: deploy, ci, ops, octane, zero-downtime
---

<aside class="tldr"><strong>TL;DR</strong>Push to <code>production</code> → GitHub Actions bumps the semver and hands to Deployer. Twenty steps in order: prepare, vendors, stop crawls, set release, build caches into the new dir, migrate on one host only, diff the front end and build‑or‑rsync, save the SSR checksum, publish, prune, Horizon terminate, sync the Octane anchor, restart telemetry if its code changed, Octane reload, SSR restart‑if‑changed + health check, log, tell open tabs, flush Varnish‑if‑changed + warm, chores. No half‑deployed request, no killed job, rollback is a redeploy.</aside>

<figure><img src="/art/readme/draw-note-deploy.svg" alt="Sketch of a release symlink swap and workers reloading one by one" width="1200" height="750" loading="lazy"><figcaption>Releases, a symlink flip, and workers that finish before they come back.</figcaption></figure>

Other notes cover *why* we ship [seventeen times a week](/blog/369-production-releases-in-five-months), *why* the application server reloads from an [anchor directory](/blog/moving-to-octane), and *why* the storefront cache is [flushed only when it changed](/blog/only-flush-the-cache-you-changed). This one is the *how*: the exact sequence a deploy runs, in order, and what each step is protecting. It is a Deployer script in the repository; anyone can read it, which is part of the point.

## Before the script: the push

A push to the `production` branch starts a GitHub Actions workflow under a single concurrency key (two pushes queue, never race). The workflow bumps the semantic version, writes it into an environment file that travels with the release, and hands off to Deployer against the production hosts — the primary and the replica — over SSH with keepalives tuned for the one step that runs a long time.

## The sequence

1. **Unlock, check writable, prepare.** A new `releases/N` directory, code checked out at the exact revision, shared directories (storage, the environment file, the RoadRunner binary and config) symlinked in.
2. **Vendors.** Composer install from the lock file.
3. **Stop crawls.** Any storefront cache‑warming crawl in flight is told to stop; a deploy will start its own later.
4. **Set release.** The semantic version is written into the release's environment file, so the running app knows which version it is (it shows on the status bar).
5. **Storage link, config cache, route cache, view cache, event cache.** Laravel's caches, built into the new release directory — not the live one — so the running workers are untouched.
6. **Migrate — on one host only.** The schema is shared; the replica host skips this step by alias. Migrations that need heavy locks declare that they must run outside a transaction.
7. **Check front‑end changes.** A diff between this revision and the previous release's, limited to the front‑end source and the Vite configs. If nothing changed, the next step is skipped entirely.
8. **Build — or copy.** When the front end changed, `npm ci` and the production build run per app (staff, customer portal, storefronts, embedded app, public site) plus the SSR bundle. When it did not, the previous release's built assets are rsynced forward — a deploy without front‑end changes costs no build time at all.
9. **Save SSR checksums.** A hash of the storefront SSR outputs is written to a file. Later steps read it to decide whether anything customer‑facing actually changed. If any component cannot be hashed, the file is not written and those later steps fail loudly rather than guess.
10. **Publish.** The `current` symlink flips to the new release. From here on, new PHP‑FPM‑style entrants would see new code — but our workers are long‑lived, so the next steps are what actually switches them.
11. **Prune.** `node_modules` is removed from all but the five newest releases, because a release directory is forever and disk is not.
12. **Horizon terminate.** Queue workers are told to finish their current job and exit; the supervisor brings them back on the new code. The deploy does this; a person never has to remember it.
13. **Sync the anchor.** The whole release is rsynced into `anchor/octane`, the directory the application server actually runs from and that release cleanup can never delete. This is the slow step — ~150k files — and it is why the SSH keepalives exist; progress is deliberately not printed, because it would be a hundred thousand lines of log.
14. **Restart the telemetry agent.** It runs from the anchor too, so it needs a nudge, gated on whether its own code or config changed — the restart briefly drops a listener and a few telemetry batches, so it is not done for free.
15. **Octane reload.** Workers finish their request, exit, and come back from the anchor on the new code. Non‑fatal, so a host that does not run the server yet cannot fail everyone's deploy.
16. **Restart SSR — only if the checksum changed.** And regardless of checksum, the SSR port is health‑checked and the process restarted if it is dead, because a supervisor can report *running* over a dead listener (we learned that from a silent client‑side‑rendering incident).
17. **Log the deployment.** A row with the commit, the version and the time, so the dashboard and the status bar can say "v2.369.0, deployed 14:02".
18. **Refresh open tabs.** A WebSocket broadcast tells every open staff session that a new version exists; the tab offers a refresh. Tolerant of the socket server being down.
19. **Flush the storefront cache — only if the checksum changed.** Then, on the replica, a warming crawl of the most‑visited pages.
20. **Chores.** Guess languages for new translation keys; index the public site's notes for search. Each non‑fatal.

That is the list. It is long because each line is a thing that once hurt; it is boring because that is what we wanted.


<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The script: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/deploy/deploy.php">deploy/deploy.php</a> — the <code>deploy</code> task lists every step in order; read it top to bottom.</li>
<li>Trigger: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/.github/workflows/deploy.yml">.github/workflows/deploy.yml</a> (<code>concurrency: production_deployment</code>).</li>
<li>Anchor: <code>rsync -ahHq --delete {release}/ {deploy_path}/anchor/octane</code>; Octane and SSR run from <code>anchor/octane</code>.</li>
<li>SSR checksum: sha256 of <code>ssr-manifest.json</code> + <code>ssr-iris.mjs</code> + the storefront manifest → <code>SSR_CHECKSUM</code>; flush and SSR restart read it.</li>
</ul></aside>

## What "zero downtime" means here

No request ever hits a half‑deployed release: the symlink flips atomically and the workers reload one at a time from a directory that does not move. No queue job is killed mid‑flight: workers are asked to finish. No customer sees a cold storefront unless the storefront actually changed. No migration runs twice. If any step fails before *publish*, nothing is live; if one fails after, the failing step is isolated and the previous behaviour persists (the old workers keep serving, the old cache keeps serving). Rollback is a redeploy of the previous tag.

## What it is not

It is not blue/green infrastructure, not a container orchestrator, not a canary system. It is a script, a symlink, a supervisor and two servers, written to the shape of this application. We would rather have thirty steps we can read than three we cannot.

<aside class="tldr bottom"><strong>In one paragraph</strong>A script, a symlink, a supervisor, two servers: each of the steps is a thing that once hurt, and the whole is boring on purpose.</aside>
