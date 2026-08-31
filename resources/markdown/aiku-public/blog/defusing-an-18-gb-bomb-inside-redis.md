---
title: Defusing an 18 GB bomb inside Redis
summary: One morning every page in the back office threw errors for about two seconds, and the trail led somewhere strange — a queue that had been renamed in June and never given a worker, quietly growing to 6.9 million jobs and 18 GB inside Redis until the box was living in swap and one routine Lua script stalled past the busy threshold. How we traced a frontend error bucket to a key nobody was reading, why deleting it had to happen before fixing it, the eager-loading bug hiding behind the first fix, and the one-hour check that would have caught the whole thing in week one.
date: 2026-08-29
tags: redis, horizon, queues, ops, incident, reliability
---

<aside class="tldr"><strong>TL;DR</strong>A queue was renamed in June; no Horizon supervisor was ever pointed at it. An hourly command kept feeding it: 6.9M delayed jobs, 18 GB in one Redis key, the server 31 GB into swap. On 28 Aug a routine Lua script stalled past Redis's 5 s busy threshold and every in-flight request 500'd for ~2 seconds — 577 errors, then silence. The fixes, in order: <code>UNLINK</code> the key <em>before</em> deploying the supervisor (a worker meeting 6.9M due jobs would have blocked Redis for minutes), wrap the audit-log dispatch in <code>rescue()</code> so a Redis hiccup degrades logging instead of failing pages, and an hourly check that shouts when any queue holds jobs with nobody assigned to read them.</aside>

At 07:07:38 UTC on 28 August, for about two seconds, every request into the back office returned a 500. Refund pages, dispatch screens, the chat dock, webhooks — everything, on both application servers, at once. Then it stopped, and the system carried on as if nothing had happened.

Two seconds is an interesting duration for an outage. Long enough that 577 requests failed and 29 people saw error toasts; short enough that by the time anyone looked, there was nothing to see. This note is about how we traced it, because the trail is more useful than the incident — it goes through a misleading error bucket, a queue nobody was reading, and eighteen gigabytes of Redis that had no reason to exist.

## The bucket that pointed the wrong way

The report arrived as a frontend Sentry issue: an axios 500, ~1,400 events since June, recent ones on refund pages. The obvious suspect was the endpoint those pages call on mount — a payments table for the refund screen. We read the action, ran its generated SQL against production data, checked the exact invoice in the events. All healthy.

The issue turned out to be a **catch-all bucket**: every axios 500 in the app, from any page, grouped under one stack trace — because the stack trace of "a request failed" is the same regardless of which request failed. The refund-page events were really the staff chat dock's 60-second poll dying underneath whatever page happened to be open. The lesson for anyone triaging frontend Sentry: when the grouped frames are all inside <code>axios/lib/core</code>, the issue tells you *that* requests fail, never *which* — go to the traces.

The traces led to the backend twin: <code>RedisException: BUSY Redis is busy running a script</code>, thrown from the middleware that queues an audit log of every authenticated request. That middleware runs on everything — which is why everything failed, and why the errors named every page in the app except the one that caused them.

## Eighteen gigabytes nobody was reading

<code>BUSY</code> means a Lua script has been executing for more than five seconds and Redis is refusing all other clients until it finishes. So: which script? The script cache held only the ten standard Laravel and Horizon scripts, 4.7 KB between them, p99.9 of 266 µs over 1.6 billion calls. There was no big script. Something had made a tiny one slow.

The answer was one key: a <code>:delayed</code> sorted set holding **6,947,956 jobs — 18.08 GB**, in a Redis instance whose entire footprint was 18.1 GB, on a box that was 31 GB into swap and intermittently failing its RDB snapshots. Under that much fork and swap pressure, a routine script only needs to touch a few cold pages to stall past five seconds once. It did, once, and 185 connected clients got <code>BUSY</code> for two seconds.

How does a delayed set reach seven million jobs? In June, a non-essential external call — a periodic data refresh against a third-party service — was moved onto its own queue — a reasonable tidy-up — but the new queue name was never added to any [Horizon supervisor](/blog/twenty-six-queues-and-the-feeling-of-cpu-at-100). No worker ever polled it. An hourly command kept dispatching a few thousand delayed jobs into it, the delayed-to-ready migration only runs when a worker polls, and so the set only ever grew: ~86,000 jobs a day, for eighty days, in a lane nothing was reading.

Worth saying plainly: this was a low-traffic refresh lane, a call whose worst failure mode is data going a little stale — nothing user-facing depended on it, and everything around it ran normally the whole time. Degraded, not down; which is exactly why nobody noticed for three months. The things that fail loudest get fixed fastest. The things that fail silently get a blog post.

## Delete first, deploy second

The tempting fix — add the queue to a supervisor, deploy — would have caused a much bigger outage than the one we were fixing. The first worker to poll the queue runs the migrate-expired-jobs script, which moves **all** due jobs in one atomic Lua call. All 6.9 million were due. That script would have held Redis for minutes, not seconds.

So the order mattered, and it went in the incident report in bold:

1. <code>UNLINK</code> the key — not <code>DEL</code>, which frees an 18 GB value synchronously and *is* the outage; <code>UNLINK</code> hands it to a background thread. Memory fell from 18.1 GB to 1.2 GB in under a minute, and the server climbed out of swap.
2. Deploy the supervisor change, into a now-empty queue.
3. Wrap the audit-log dispatch in <code>rescue()</code>. Request logging is a nice-to-have; the request is not. A Redis hiccup now degrades telemetry instead of failing every page — the same shape as [only flushing the cache you changed](/blog/only-flush-the-cache-you-changed): blast radius is a design decision.

Two aftershocks, both worth their own line. The unique-job locks from all those dispatches had no TTL and only release when a job runs — 2.47 million orphaned lock keys, 99.9% of that database, swept with a SCAN + TTL + UNLINK command that now lives in the repo. And when the queue finally got its worker, every job failed anyway: a <code>morphTo</code> relation whose name didn't match its method name loaded fine lazily but came back <code>null</code> after the queue serializer restored it eagerly. Fixed, plus one more of the same species found by sweeping the models. The lane's first full run in three months went through cleanly the same evening — 4,737 jobs, zero failures.

## The check that was missing

Every piece of this had a guard except the first one. Deploys are gated, [tests run against a real database](/blog/tests-that-touch-the-real-database), Horizon's dashboard shows every queue it knows about — and that last clause is the hole. A queue with no supervisor is invisible to the dashboard precisely because nobody claimed it. The system had no way to say "jobs are arriving somewhere nobody is assigned to look".

Now it does, and it is embarrassingly small: an hourly command that scans every <code>:delayed</code> and <code>:reserved</code> set in the queue Redis, compares queue names against the supervisor config, and posts to the ops channel when anything unclaimed holds jobs or anything claimed holds too many. It would have fired in the first week of June with a count of a few thousand, and this post would not exist.

That is the general rule we keep re-learning, one incident at a time: every buffer needs a reader, and every buffer without one needs an alarm. A queue, a cache, a log table, an inbox — anything that absorbs writes without a consumer is not infrastructure, it is a bomb on a slow fuse — and this one ticked for eighty days before we heard it.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Supervisors: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/config/horizon.php">config/horizon.php</a> — a queue name in a job class means nothing until a supervisor lists it.</li>
<li>Middleware hardening: <code>rescue()</code> around the queued dispatch in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Http/Middleware/LogUserRequestMiddleware.php">LogUserRequestMiddleware</a>.</li>
<li>Backlog guardrail and orphan-lock sweep: <code>MonitorQueueBacklogs</code> and <code>PruneOrphanedUniqueJobLocks</code> under <a href="https://github.com/Inikoo-Ltd/aiku/tree/main/app/Actions">app/Actions</a>.</li>
<li>The <code>morphTo</code> trap: a relation named differently from its method (<code>morphTo('platform_user')</code> inside <code>user()</code>) survives lazy loading and silently returns <code>null</code> on eager loading — which is how queued jobs restore serialized models. Use <code>morphTo(__FUNCTION__, 'type_column', 'id_column')</code>.</li>
<li>Redis triage that paid off: <code>SLOWLOG GET</code>, <code>INFO commandstats</code> / <code>latencystats</code> for script percentiles, <code>MEMORY USAGE key SAMPLES 0</code>, and <code>UNLINK</code> over <code>DEL</code> for anything large.</li>
</ul></aside>
