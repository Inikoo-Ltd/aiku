---
title: Who makes what
summary: Teach aiku which artisans usually make each category or artefact, so the To produce list sorts itself into people's piles. A recommendation, never a lock.
date: 2026-09-08
tags: production, crafts, hr
category: production
help_routes: grp.org.productions.show.crafts.artefact_departments, grp.org.productions.show.crafts.artefact_families, grp.org.productions.show.crafts.artefacts
series: Ordering from partners
order: 5
---

<aside class="tldr">
For the factory manager or planner. Two small pieces of setup make the <a href="/docs/fulfilling-partner-orders">To produce</a> list useful: put artefacts into <b>categories</b> (called departments on screen), and attach the <b>artisans</b> who usually make each category or artefact. After that the <i>By artisan</i> view builds each person's pile on its own. Nothing here stops a job going to someone else; it only says who normally does it.
</aside>

## Categories and families

An artefact is one thing the factory makes. A category, a **department** on screen, is a shelf of them: bath bombs, soap, essential oils, a brand range. Every artefact belongs to at most one department. Inside a department, **families** are a finer grouping, the same as the product families in the catalogue: the lavender bath bombs, the citrus ones. Families are optional and only help you find things; nothing is planned by family.

- **Factory → Crafts → Artefacts** has three tabs: **Departments**, **Families** and **All artefacts**. Open a department or a family to see its artefacts, and use the **new** button on either tab to create one.
- To move artefacts, tick them on any artefact list and use **Move to department** or **Move to family**.
- Deleting a family leaves its artefacts in place, without a family.
- A whole catalogue of artefacts, or of raw materials, can be loaded at once with the **upload** button on the list.

Departments drive two things: the *By category* view of the To produce list, and the fallback for artisans, explained next.

## Artisans

On every department page and every artefact page there is a row under the title: **Usually made by**. Families do not have one.

- Pick a name from **Add artisan…** to attach someone. Only working employees of your organisation are offered.
- Attach as many people as you like. The first one is highlighted; that is the default owner.
- Click the small cross on a chip to detach. Order matters: the first person attached stays first until removed.

aiku reads it like this. For a line in To produce it looks at the artefact first. If the artefact has artisans, the first one owns the line. If not, it looks at the artefact's department and takes the first artisan there. If neither has anyone, the line sits under *Unassigned*, and on the Board the *Who makes it?* question comes with no name proposed.

So the cheap way to set up a factory is: attach artisans to departments, and only touch individual artefacts for the exceptions. One person makes all the soap except the one loaf that needs the other pair of hands.

## What it is not

- **Not a guard.** Job orders and task sessions do not check it. Anyone can make anything, and the planner can pick any name when a card is dropped in *Assigned*.
- **Not a skills record.** It says who usually does it, which is a fair hint of who is good at it, but nobody is scored on it.
- **Not history.** Who actually made what is under **Factory → Artisans → Performance**, built from closed task sessions.
- **Not the roster.** Who counts as an artisan at all is the *Open job orders per artisan* strip on To produce, where a cross hides someone who does not make things.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Departments and families:</b> your organisation → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → <b>Departments</b> or <b>Families</b> tab.</li>
<li><b>Move artefacts:</b> tick artefacts on any artefact list → <b>Move to department</b> or <b>Move to family</b>.</li>
<li><b>Attach an artisan:</b> open a department or an artefact → <b>Usually made by</b> → <b>Add artisan…</b>. Detach with the cross on the chip.</li>
<li><b>See the effect:</b> <b>Factory</b> → <b>To produce</b> → <b>By artisan</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Attaching and detaching artisans: the <b>Production floor supervisor</b> position for the factory, or organisation supervisor. Positions are set on the employee record under Human Resources. Everyone who can see the page sees the names.</li>
<li>Creating departments and families, moving artefacts, uploading: organisation supervisor. No factory position covers this yet.</li>
</ul>
</aside>
