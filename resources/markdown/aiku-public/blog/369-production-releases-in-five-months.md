---
title: 369 production releases in five months
summary: A warehouse cannot stop for a deploy, and neither can we. How a nineteen-person team ships a monolith with six thousand actions to production seventeen times a week — the pipeline, the tests that gate it, the deploy that never cuts the branch it sits on, and why AI agents made the cadence faster without making it looser.
date: 2026-08-22
tags: delivery, ci, deploy, github-actions
---

Version 2.0.0 of aiku went to production on 20 March 2026. Version 2.369.0 went on 21 August. That is **369 production releases in 154 days** — about seventeen a week, every week, on a system that runs live warehouses, storefronts and a sales floor in several countries.

Some more numbers, since the repository is public and you can check them: about **14,000 commits so far this year** across 221 working days; roughly **68 commits a day** since June; **nineteen people** have landed code in 2026; about one in every six files touched since June was a test. None of this is a sprint. It is what a normal Tuesday looks like.

This note is about the machinery that makes that cadence safe rather than reckless.

## One branch, one button

There is no release branch, no release manager, no Friday freeze. We pick our battles: work that changes money, stock or a contract with another system goes through a pull request and a second pair of eyes; the rest — the copy fix, the new column on a report, the hydrator that was missing a case — lands on `main` directly, because the pipeline is a well‑oiled machine and the tests are the reviewer that never gets tired. The judgement about which is which is the team's, not a rule's. When `main` is ready, it is pushed to `production` and a GitHub Actions workflow takes it from there: bump the semantic version, build the front ends, and hand the release to Deployer. The deploy workflow runs under a single concurrency key, so two pushes queue rather than race.

The version bump is automatic and semantic; the number on the status bar of the staff app is the number of the release that is serving you. When someone reports "it broke", the first question is already answered.

## Tests that gate, not decorate

Every push and pull request runs the test suite — thousands of feature tests against a real PostgreSQL, restored from a seeded dump so each run starts from the same world, in parallel across ten processes. A second job re‑runs, in isolation, any test *file* that the push touched: a test that only passes because of what ran before it does not get to hide in the parallel run.

The rule on the team is simple: *every change ships with a test, and the run is green before it merges.* Not because a rule says so, but because at seventeen releases a week you cannot remember which one changed the thing.

## A deploy that cannot break the running app

The deploy itself is boring, which took work. Each release lands in its own directory; the database migrates; the queue workers are told to finish and restart; the application server — long‑lived [Octane workers](/blog/moving-to-octane) — reloads from an *anchor* directory that the release cleanup can never touch, so no worker ever has its code deleted out from under it. The storefront cache is flushed [only if the storefront actually changed](/blog/only-flush-the-cache-you-changed), and a warmer re‑fills the hot pages afterwards.

If a step fails, the release does not go live and the previous one keeps serving. There is no moment where customers see half of a deploy.

## Small releases are the safety feature

Seventeen releases a week sounds riskier than one. It is the opposite. A release with four commits in it has four places to look when something is off; a release with four hundred has four hundred. Rollback is a redeploy of the previous tag. Most "incidents" at this cadence are a bug, a fix, and a second release within the hour, and the people who noticed are usually the ones who asked for the change that morning.

It also changes how the business talks to engineering. Nobody asks "when is the next release"; the answer is always "after lunch". Requests get smaller because they can.

## What AI changed, and what it didn't

A good part of this year's velocity comes from engineers working with AI coding agents: for the greps and surveys, for the first draft of a migration, for writing the test that proves the fix. We are open about that.

What it did not change is the gate. An agent's diff is a diff like any other: reviewed by a person, tested in CI, and released with a version number someone can point at. The cadence went up; the bar did not come down. We believe AI is a tool to empower human ingenuity, and the most ingenious thing about it here is how much more of the engineers' day goes to deciding *what* to build.

## What we would tell a team that wants this

- Make the deploy so boring that nobody is nervous about it; then do it constantly.
- Let the tests be the gate, and run them against a real database.
- Put the release number where the users can see it.
- Keep the monolith if it is a good monolith. One repository, one pipeline, one version number is a feature when you ship this often.
- Count. The numbers at the top of this note are ten minutes with `git log`; knowing them keeps the cadence honest.
