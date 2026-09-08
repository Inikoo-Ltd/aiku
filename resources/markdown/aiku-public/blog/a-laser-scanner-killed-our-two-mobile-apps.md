---
title: A laser scanner killed our two mobile apps. Their names were Maya and Han
summary: Maya ran warehouse pallet and stock work; Han handled clocking in and out. Both React Native apps were finished, tested and production-ready, and neither was ever launched. What beat them was not a new technology but a question nobody asked early enough, because the answer had been sitting on every supermarket checkout since the eighties. How we proved in production that nothing had ever called them, the two threads out of 58,000 lines that still reached back into the live app, and what a year of good work that never met a user is actually worth.
date: 2026-09-08
tags: warehouse, mobile, deletion, architecture, history
---

<aside class="tldr"><strong>TL;DR</strong>Two React Native apps — Maya for the warehouse floor and Han for clocking, <code>311</code> files and <code>58,241</code> lines between them — were finished and production-ready and never switched on. They existed because barcode scanning had to happen on the phone, and nobody checked that premise until someone pointed a laser scanner at the web app and watched the barcode arrive as keystrokes. Production proved they were never used: zero API tokens ever issued to a device, the pairing column NULL on every machine in the fleet, no traces in ninety days. Deleting them touched only two things in the live app, which is the measure of how cleanly they were built. The lesson is not about timing or quality — it is that no amount of craft downstream rescues a premise nobody checked.</aside>

We deleted two React Native apps from our warehouse platform this week. They had names, so we may as well use them: **Maya** ran stock and pallet operations on the floor, and **Han** handled clocking in and out. Together they were **311 files and 58,241 lines**.

They were finished. They were production-ready. They were never launched. Not deprecated, not replaced, not switched off after a good run — never switched on. They died before they were born.

By the time they were ready to roll out, the thing they existed to solve had been solved another way — by a **laser barcode scanner**, a piece of hardware that has existed for decades and sits on every supermarket checkout, plugged into the web app we already had.

Nothing new arrived. Somebody just noticed that the boring answer was enough.

## They were the right answer to the question we had

When the work started, the warehouse floor needed something a browser could not give it. Phone-camera scanning inside a mobile web page was slow and unreliable on a dim shelf with a scuffed label. Native gave us the camera at full frame rate, a device pairing flow, a token per device, and a UI shaped for one thumb and a cold aisle.

That was a correct decision, made with the information available.

You can see the care in the file listing, which is the part that makes this hard. Launcher icons generated at five screen densities, for three separate build flavours. A hand-written patch against the camera library, carried in the repo because upstream had not fixed it yet. A session-expiry screen, because somebody thought about what happens when a picker's shift runs past their token. Drawer menus split per role, so an agent and a fulfilment operator each saw their own world. A login flow you could complete by scanning a code, so nobody had to type a password with cold hands.

None of that is glamorous work. All of it is the work. Months of it. Every one of those details is somebody imagining [a person on a warehouse floor](/blog/staff-chat-for-people-holding-a-scanner) and trying to make their day slightly easier.

Nobody ever had that day.

<figure><img src="/art/readme/draw-note-never-launched.svg" alt="Watercolor sketch: a flower bowed over on a broken stem with its petals fallen across the ground, and to the right, lit by a low dawn, a small new shoot with two fresh leaves" width="1200" height="700" loading="lazy"><figcaption>Maya and Han, finished and never switched on. They died before they were born.</figcaption></figure>

## What overtook them

Not a new technology. A realisation.

The apps existed because scanning had to happen on the phone, and doing that well required native. Nobody questioned the premise until someone did: a plain laser scanner, commodity hardware since the eighties, reads in well under a tenth of a second, works on damaged labels and in poor light, and lets a picker keep their hand on the grip instead of framing a shot. It beats camera scanning by a margin that is not close. Point it at a web page and the barcode arrives as keystrokes. No app required, no pairing flow, no device tokens, nothing to install.

The premise had been wrong the whole time. **Scanning never had to happen on the phone.** Once that was obvious, the main thing native was buying us evaporated.

And meanwhile the web UI kept growing. Booking pallets in, marking damaged and lost, picking returns, stored-item audits, location stock counts — each one shipped into the browser because that is where the work happened to be going. Nobody decided to replace the apps. The ground just closed over them, one feature at a time, while they were being finished.

That is the cruel shape of it. The apps were not beaten by a better app, or by some technology that did not exist when they were started. Everything that replaced them had been sitting there in plain sight the entire time. They were beaten by a question nobody thought to ask early enough: *does this need to be an app at all?*

## How we knew they were dead

We did not trust a grep. The callers would have been compiled apps on physical devices, invisible to static analysis. So we checked production:

- Not one API token had ever been issued to a clocking device. Han authenticated the machines, so a device that had run it would have left one.
- The `device_name` column was NULL on **every** clocking machine in the fleet. That field is written by the pairing flow, so pairing had never once succeeded.
- Clocking traffic from a "mobile app" device type turned out to be server-side sync from the legacy system, not Han.
- No errors and no traces for either app's URL prefix, ever.

Reading those results in order is a strange experience. Each query returns nothing, and each nothing is another confirmation that a year of somebody's work never touched a single real user.

There was one last detail. One of Maya's actions had already fallen behind its web equivalent — it changed a pallet's state but skipped closing the associated recurring-bill transactions, something the web version learned to do somewhere along the way. **Code that never ran in production had already gone stale.** That is how long the window had been shut.

## The deletion

Three adversarial reviews before touching anything. Between them they found two things that would have broken the build or thrown at runtime: a stray import reaching from a live web component into one app's source tree, and two authorisation checks reading a flag that was about to be removed.

Two threads, out of 58,000 lines. That number deserves saying plainly: these apps had **clean edges**. A tangled codebase does not come apart like that. The reason this took a day instead of a quarter is that whoever built them did the unshowy work of keeping them properly separate — discipline that, as it turns out, only ever paid off on the way out.

## What we would tell our past selves

The lesson is not about timing, and it is certainly not about quality. It is about **premises**. The whole project rested on one unexamined assumption — that scanning had to happen on the phone — and every month of good work after that inherited it. No amount of craft downstream can rescue a premise nobody checked.

Ship something rough early and the floor tells you within a week. A picker would have put down the phone, picked up the scanner sitting on the desk behind them, and the question would have answered itself. [Shipping constantly](/blog/369-production-releases-in-five-months) is not about shipping less. It is about finding out what you were wrong about while it is still cheap.

But the part worth saying, for anyone who has had this happen to them: work that never launched is not wasted work, and it is not bad work. The judgement was sound when it was made. The craft was real — you can measure it in how cleanly the thing came out again. The window closed, which is a thing that happens to good engineers on good projects, and it says nothing about them.

Two apps were built properly, for reasons that were sound when they were chosen, and never got a single day of use. No pickers, no shifts, no bug reports from the floor, no small satisfactions of watching someone lean on the thing you made.

That is life in this trade, and everyone gets a turn eventually. It is still sad as hell, and it deserved saying out loud before the branch was deleted.

## Do we do this for the result, or for the doing?

Deleting this much finished work forces the question, so it is worth answering properly rather than reaching for the comfortable line.

The comfortable line is that the journey was its own reward. It is not true, and anyone who has shipped something knows it is not true. We build things to be used. A tool nobody holds is not a tool, it is a sculpture of a tool. The whole point of a session-expiry screen is a picker at the end of a long shift, and if that picker never exists, something real is missing — not a feeling, a fact. Pretending otherwise is how people end up polishing things nobody asked for and calling it craft. Results matter. That is not a compromise with commerce, it is what separates engineering from decoration.

And yet: if results were the *only* thing, almost nobody would last in this trade. Most of what we write is deleted eventually. Features get cut, products get repositioned, whole companies stop. The average line of code has a shorter working life than a pair of boots. Fred Brooks told us this in 1975 and we still flinch at it:

> Plan to throw one away; you will, anyhow.
>
> — Fred Brooks, *The Mythical Man-Month*

He meant the first system teaches you the real problem. Ours taught us the real problem was a question, not a codebase. Same lesson, higher tuition.

The older version of the thought is sharper, and it is not about software at all:

> You have a right to your actions, but never to your actions' fruits.
>
> — Bhagavad Gita, 2.47

It is easy to read that as consolation — *never mind the outcome, just enjoy the work* — and that reading is soft enough to be useless to an engineer. The stricter reading is better. It does not say the fruit is worthless. It says the fruit is not yours to own, because it never depended on you alone. Whether these apps met a picker was contingent on hardware prices, on what the web app grew into, on a question somebody else eventually asked. What was fully theirs — the only part that ever was — is whether the work was done well.

That is not a lower standard. It is a harder one. You owe the action everything, and you are owed nothing back. A craftsman who accepts that keeps their standards through the projects that die, and there will be many.

So the honest answer is that it is both, and that the two are not in tension the way they look.

The pleasure is not a consolation prize you accept when the result fails. It is the thing that makes the result possible. Nobody generates launcher icons at five densities for three build flavours because a spreadsheet told them to. That gets done because somebody cared how it would feel to use, and that caring is indistinguishable from enjoying the work. The care that made these two apps come apart in a single day — clean edges, no tangle — was not a business requirement. Nobody would have noticed if it had been absent, right up until the week it saved us a quarter.

That is the part worth keeping. The pleasure of doing it well is not sentiment; it is a load-bearing part of how good software gets made, and it pays out on a schedule nobody can predict — sometimes to a user, sometimes to whoever has to remove it years later, sometimes only to the person who did it.

The trap is not caring about the craft. The trap is letting the pleasure of building substitute for the discipline of checking whether it should be built. Enjoy the work, and interrogate the premise. These apps failed at the second thing, not the first.

If you program only for results, this story is a pure loss: a year, gone. If you program only for the doing, it costs nothing, which is a lie that would let it happen again next year. The useful position is uncomfortable and sits in the middle. The months were not wasted, because the craft was real and some of it paid out on the way out. And the months were a loss, because nobody was ever served by them, and being served is the point.

The Japanese have a phrase for the feeling left over — *mono no aware*, the gentle ache at things passing. It is not despair and it is not acceptance. It is what you feel looking at a thing that was well made and is over. Software gets this less often than it should, because we usually delete our dead quietly, with a commit message that says "cleanup" and no ceremony at all.

This one got a note instead. Hold both truths. Then go and ask the awkward question about whatever you are building right now — while it is still cheap to hear the answer.

<aside class="tldr bottom"><strong>In one paragraph</strong>Check the premise before you check the code — two well-built apps died unlaunched because the question was never "can we build this well" but "does this need to be an app at all", and the answer had been sitting on a supermarket checkout the whole time; the craft was still worth having, because it is what makes good results possible, but it is never a substitute for asking whether the thing should exist.</aside>
