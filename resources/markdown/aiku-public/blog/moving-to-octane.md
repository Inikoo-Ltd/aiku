---
title: Moving a 6,000-action Laravel app to Octane
summary: In February 2025 we stopped booting the framework on every request. What we gained, what bit us (state leaks, a deploy that kept deleting the running code, big headers), and the "anchor" directory that makes zero-downtime deploys boring.
date: 2025-02-28
tags: octane, roadrunner, deploy, performance
---

<aside class="tldr"><strong>TL;DR</strong>We moved production to <a href="https://laravel.com/docs/octane">Laravel Octane</a> on RoadRunner on 4 February 2025, cutting time to first byte and machine count but exposing state leaks, oversized headers, and an Octane response-handling bug. The hardest fix: deploys were deleting code out from under running workers, solved by an <code>anchor/octane</code> directory the deploy rsyncs into and <code>octane:reload</code> against, with <code>stopwaitsecs=3600</code> so in-flight jobs finish.</aside>

Classic PHP‑FPM boots the whole framework for every request: autoload, service providers, config, routes — thousands of files — then throws it all away. For a small app that is a few milliseconds. For aiku, with around six thousand action classes, hundreds of routes per sub‑app and a long list of providers, it was a tax on every single page, and it was paid most heavily by the pages customers see first.

[Laravel Octane](https://laravel.com/docs/octane) changes the model: the application boots once into long‑lived workers and each request is handled by an already‑warm process. We moved production to it on **4 February 2025**, running on RoadRunner behind nginx.

## What it bought us

- **Time to first byte** on the staff app and the storefronts dropped to a fraction of what it was; the bootstrap that used to dominate small requests disappeared from the profile.
- **Fewer machines doing the same work.** Warm workers serve far more requests per core than FPM children, so the same hosts absorb traffic spikes — and the warehouse's busiest hour — without adding boxes.
- **Predictable latency.** A boot that sometimes stalled on a cold opcache is gone; the slowest requests are now slow for application reasons, which are the ones we can fix.

We do not publish exact figures here, but the change was large enough that it was visible to staff on the day without being announced.

## What bit us

**State leaks.** Anything stored in a singleton or static property survives to the next request. A "current group" cached in a service, a per‑request memo that never cleared — those became cross‑request bugs overnight. The rule that stuck: *never store request‑specific state in a singleton*; use scoped bindings, and if something must be memoised per request, clear it in Octane's request‑terminated hook.

**Big headers.** A long‑running server has its own limits. A handful of pages set cookies and custom headers that together were larger than the default buffers and failed with an opaque 5xx. We raised the buffer, trimmed the headers, and now keep an eye on header size as a thing that can break.

**An upstream bug** in Octane's handling of one response type had to be worked around for a release or two (octane issue #996). Long‑running servers surface edge cases that FPM hid by restarting; reporting them upstream was part of the cost.

## The anchor: deploying without cutting the branch you sit on

Our deploys are the usual releases/ + current symlink arrangement. With FPM, flipping the symlink is enough: the next request loads the new code. With Octane it is not enough, and it is worse than that — the workers are *running from the old release directory*, and when the deploy prunes old releases it deletes the code out from under a live process. Requests kept working for a while (files already loaded in memory) and then failed in baffling ways.

The fix is a directory that never moves. The deploy **rsyncs the new release into `anchor/octane`** and Octane is always started from there:

```ini
[program:aiku-octane-production]
command=/usr/bin/php8.4 /home/aiku/aiku/anchor/octane/artisan octane:start -q --workers=... --max-requests=...
autorestart=true
stopwaitsecs=3600
```

Then the deploy asks the running server to reload its workers:

```php
task('artisan:octane:reload', function () {
    artisan('octane:reload')();
});
```

Workers finish their current request, exit, and come back on the new code. Nothing is ever deleted underneath a running worker, because the anchor directory is not a release — it is a mirror of one. The rsync is the expensive step (the release is ~150k files, and on the staging disk it runs for a quarter of an hour), so the deploy's SSH keepalives are tuned to survive it, and we deliberately do not print progress: that would cost a hundred thousand lines of CI log per deploy.

`stopwaitsecs=3600` is not a typo. A worker in the middle of a long import must be allowed to finish; the supervisor restart waits for it. The pairing rule we learned from Horizon applies here too: *the supervisor's stop timeout must exceed the job's*.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The anchor sync and reload are deploy tasks in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/deploy/deploy.php">deploy/deploy.php</a>: <code>deploy:sync-octane-anchor</code> rsyncs the release into <code>anchor/octane</code>, and <code>artisan:octane:reload</code> runs <code>octane:reload</code> afterwards, tolerating a box that has no running process yet.</li>
</ul></aside>

## Things we would tell our past selves

- Read every singleton before you switch. All of them.
- Put the served code somewhere that the release cleanup cannot touch.
- Reload, don't restart, on deploy — and make the reload non‑fatal, so a box without the process yet does not break the deploy for everyone.
- A long‑lived process is a different animal from FPM. Memory, headers, connection pools, caches of "current user" — they all need a second look.

We would not go back.

<aside class="tldr bottom"><strong>In one paragraph</strong>Octane made aiku faster and steadier, but a long-running process demands respect for state, headers and how deploys touch running code — the anchor directory is the fix that made zero-downtime deploys boring again.</aside>
