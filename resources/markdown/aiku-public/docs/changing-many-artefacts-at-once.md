---
title: Changing many artefacts at once
summary: Tick the artefacts, then use the picker at the right of the bar that appears to set a batch size or shelf life, give them all the same manufacture steps, move them to another family or department, discontinue them, or bring them back.
date: 2026-10-08
tags: production, crafts
category: production
help_routes: grp.org.productions.show.crafts.artefacts.index, grp.org.productions.show.crafts.artefact_families.show, grp.org.productions.show.crafts.artefact_departments.show
---

<aside class="tldr">
For whoever looks after the factory's catalogue. Every artefact list has tick boxes. Tick some rows and a bar appears above the table with a picker on its right. The picker holds the jobs: <b>Batch size</b>, <b>Shelf life</b>, <b>Manufacture task</b>, <b>Move to family</b>, <b>Move to department</b> and <b>Discontinue</b>, plus <b>Make active</b> to undo the last one. Editing artefacts one at a time still works, this is the same edit done to fifty rows in one go.
</aside>

## Finding it

There is nothing to switch on and no button that opens it. The bar is hidden until you tick something, which is why most people never meet it.

Tick the box at the left of any row. A bar appears above the table saying how many artefacts you have picked, with **Clear** next to it and the action picker at the far right. Tick the box in the table header to take every artefact on the page at once.

You get the same bar in three places. It looks the same each time, but not every job is offered everywhere:

| Where | What it offers |
| --- | --- |
| **Crafts → All artefacts** | every job, on any artefact in the factory |
| A family page, **Artefacts** tab | batch size, manufacture task, move to family, discontinue and make active |
| A department page, **Artefacts** tab | batch size, manufacture task, move to family or department, discontinue and make active |

## Choosing what to do

The picker names the job. Change it and the control beside it changes with it. The picker itself never moves and never changes width, so once you know where it is you can work quickly.

**Batch size** gives you a box and a **Set** button. Type the number of units a normal run makes and press **Set**. It has to be one or more. This is the number the factory sees when it plans a job order, and an artefact without one shows up in the red columns on the families list.

**Shelf life** works the same way, in days: 365 is a year, 730 is two years. It is how long an artefact keeps once it is made, and it caps how much the factory is told to make in one go. Only the **All artefacts** list offers it.

**Manufacture task** gives you a button, **Make a unified manufacture task**, that opens a window where you set up the steps once for every artefact you ticked. It has its own section below.

**Move to family** and **Move to department** give you a search box. Start typing a code or a name, pick the target, press **Move**. There is a **New family** link beside it if the family you want does not exist yet. A family belongs to one department, so moving artefacts into a family moves them into that family's department as well, whatever they were in before.

**Discontinue** asks before it does anything. The dialog tells you how many artefacts you are about to discontinue, and nothing happens until you press **Yes, discontinue**.

**Make active** brings discontinued artefacts back. It does not ask, because it is the safe direction.

## Giving many artefacts the same manufacture steps

Every artefact has a list of steps an artisan follows to make it, such as pouring, shrinking and boxing. Each step is a **manufacture task**, set up once for the factory and reused by every artefact that needs it. You can set the steps on one artefact from its **Manufacture tasks** tab. When a whole range is made the same way, the **Manufacture task** job does it for all of them at once.

The window has the steps on the left and the artefacts you ticked on the right.

**Setting up the steps.** Each step card has:

- **Task**: the piece of work, picked from the factory's manufacture tasks. Type to search by name or code.
- **Units per artefact**: how many units of that task one artefact needs. A job order for 10 artefacts at 2 units per artefact asks the artisan for 20 units of work.
- **Target units per hour**: how many units an artisan is expected to make in an hour on this step, at the base pay rate. Leave it empty to use the task's own figure. More on this below.
- **Raw materials**: what the step uses up for each unit of work, with a quantity for each.

Press **Add step** for another card. The arrows on a card move it up or down, and the bin removes it. The steps are numbered in the order they are done, step 1 first, and artisans on the floor see them in that order. A task can only be used once in the list. If the task you need is not there yet, the **Task missing? Create a manufacture task** link takes you to the page where tasks are made.

**Giving one artefact different raw materials.** Most of the time every artefact uses the same materials, and that is what the list on the right starts on: **All artefacts**, with the shared materials. When one artefact needs something else, for example a different fragrance oil:

1. Click that artefact in the list on the right. Every step now shows its materials for that artefact.
2. On the step that differs, press **Use different materials for** that artefact. It starts as a copy of the shared materials.
3. Change, add or remove materials for that artefact only.

An artefact with its own materials gets an **Own materials** tag in the list, and every step where the materials differ has a red border and a red asterisk in its corner. Hover over the asterisk to see which artefacts differ. To go back to the shared materials, pick the artefact again and press **Use the same materials as all artefacts** on that step.

**Saving replaces what was there.** This is the part to be careful with. When you save, each artefact you ticked ends up with exactly the steps in the window, no more:

- Steps they already had that are not in the list are **removed, together with their raw materials**. This includes the standard **Production (PROD)** step that aiku gives every new artefact.
- Steps they already had that are in the list stay, but take the new units per artefact and the new raw materials.
- There is no undo. To go back, you would set the old steps again.

The window reminds you of this in a yellow box above the buttons. The save button stays greyed out until you tick **I understand the existing steps will be replaced**. The button says how many artefacts it will change, for example **Replace steps on 5 artefacts**.

**Job orders already running.** Job orders that are still open pick up the new steps. A step that has been removed disappears from those job orders only if nobody has started it yet. Work already recorded against it on the floor is kept.

## Targets and pay tiers

The **Target units per hour** on a step is the lower target for that product. Artisans are paid by the hour, and the rate goes up as they make more per hour: the factory's pay tiers (Tier 0 to Tier 3) each start at a multiple of this target. On an artefact's **Manufacture tasks** tab, every step with a target lists the units per hour needed for each tier and the hourly rate it pays. The tiers and their rates are the same for the whole factory, so the target is the only figure to set per product.

The target can be set on one artefact from its **Manufacture tasks** tab, or for a whole range at once in the unified window above. Tasks themselves no longer carry costs, targets or rewards: a task is just the name of a piece of work.

**Runs below target.** When an artisan finishes a step and their units per hour are below the step's target, the entry is marked **Under target / action required** on the **Performance** tab. A production manager opens it with **Log reason**, picks why (machine breakdown, material shortage, quality or rework, operator training, other) and can add a note. Tick **Under target only** above the list to see just those entries. Each worker's line shows how many of theirs still need a reason.

## What discontinuing does and does not do

A discontinued artefact leaves the working lists. It keeps everything else: its recipe, its tasks, its history and the job orders it has been on. Nothing is deleted and no stock moves.

The artefact lists open on **In process** and **Active** only, so a discontinued artefact disappears from view. To see them again, use the **State** chips above the table and tick **Discontinued**.

Families take their state from the artefacts inside them. A family is active while any artefact in it is active or in process, and only turns discontinued once all of them have. That means discontinuing the last artefact in a family quietly removes the family from the families list as well, and the same **State** chips bring it back.

## Asking your AI assistant

If your account is enrolled, the AI assistant you connect to aiku can set production up for you. Tell it what you need in your own words, for example *"give ACLB-01 to ACLB-13 these steps: pour, label, pack"*. It shows you what it will change first and only writes after you confirm.

- It can create and edit artefacts, raw materials (including their unit cost) and manufacture tasks.
- A new artefact always comes with its SKO. If the SKO does not exist yet, the assistant creates the stock, its trade unit and the SKO together with the artefact.
- Recipes work as on this page: the artefacts you name get exactly the steps you give, and steps left out are removed with their raw materials. Each step has its order, how much of it one artefact counts as, the target per hour and the raw materials it uses per artefact. The assistant also tells you the materials cost of each artefact.
- Every change is logged with your request and can be undone: ask the assistant to revert it, or an administrator can do it from the AI changes log. A record the assistant created is not removed by undo; set it as discontinued instead.
- Enrolment is a switch on your user account that an administrator turns on. It works for group and organisation admins, people who can edit the factory, and the shop admins and shopkeepers of the factory's organisation.

## Things worth knowing

- **Artefacts already in the state you chose are skipped.** Discontinuing a selection that is half discontinued reports only the ones that actually changed.
- **The selection is only what you can see.** Ticking the header box takes the rows on the page, not every artefact behind the filter. Change the page size first if you want more.
- **Nothing here touches stock.** These are recipe records, not the goods in the warehouse.
- **The counts on the families list catch up straight away.** Set a batch size on twenty artefacts and the red column on the families list drops by twenty as soon as the page reloads.
- **There is no undo for a move.** Moving artefacts to the wrong family is fixed by moving them back, which is the same two clicks.
- **Replacing steps cannot be undone either.** Before you save a manufacture task on many artefacts, check the list on the right is the artefacts you meant.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Every artefact:</b> your organisation → <b>Factory</b> → <b>Crafts</b> → <b>All artefacts</b>.</li>
<li><b>One family:</b> <b>Crafts</b> → <b>Families</b> → the family → <b>Artefacts</b> tab.</li>
<li><b>One department:</b> <b>Crafts</b> → <b>Departments</b> → the department → <b>Artefacts</b> tab.</li>
<li><b>Start:</b> tick a row → the bar appears → pick the job on the right.</li>
<li><b>Same steps for many:</b> pick <b>Manufacture task</b> → <b>Make a unified manufacture task</b>.</li>
<li><b>Create a manufacture task:</b> <b>Factory</b> → <b>Operations</b> → <b>Tasks</b>.</li>
<li><b>See discontinued ones:</b> the <b>State</b> chips above the table → tick <b>Discontinued</b>.</li>
<li><b>Let someone set up production through their AI assistant (administrators):</b> <b>Sysadmin → Users</b> → open the user → <b>Edit</b> → <b>Access</b> → switch on <b>Can connect AI assistant</b>, then <b>Can set up artefacts, raw materials and recipes through their AI assistant</b>.</li>
<li><b>One artefact only:</b> open it and use the pencil, the same fields are there. Its steps are on its <b>Manufacture tasks</b> tab.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing the lists: a production position for that factory, or organisation supervisor.</li>
<li>Using the bar: the same position with editing rights on the factory, or organisation supervisor. Without it the tick boxes do not appear at all.</li>
</ul>
</aside>
