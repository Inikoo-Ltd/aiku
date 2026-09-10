---
title: Changing many artefacts at once
summary: Tick the artefacts, then use the picker at the right of the bar that appears to set a batch size, move them to another family or department, discontinue them, or bring them back.
date: 2026-09-09
tags: production, crafts
category: production
help_routes: grp.org.productions.show.crafts.artefacts.index, grp.org.productions.show.crafts.artefact_families.show, grp.org.productions.show.crafts.artefact_departments.show
---

<aside class="tldr">
For whoever looks after the factory's catalogue. Every artefact list has tick boxes. Tick some rows and a bar appears above the table with a picker on its right. The picker holds four jobs: <b>Batch size</b>, <b>Move to family</b>, <b>Move to department</b> and <b>Discontinue</b>, plus <b>Make active</b> to undo the last one. Editing artefacts one at a time still works, this is the same edit done to fifty rows in one go.
</aside>

## Finding it

There is nothing to switch on and no button that opens it. The bar is hidden until you tick something, which is why most people never meet it.

Tick the box at the left of any row. A bar appears above the table saying how many artefacts you have picked, with **Clear** next to it and the action picker at the far right. Tick the box in the table header to take every artefact on the page at once.

You get the same bar in three places, and it is the same bar each time:

| Where | What it offers |
| --- | --- |
| **Crafts → All artefacts** | all four jobs, on any artefact in the factory |
| A family page, **Artefacts** tab | everything except moving to a department |
| A department page, **Artefacts** tab | all four jobs, on that department's artefacts |

## Choosing what to do

The picker names the job. Change it and the control beside it changes with it. The picker itself never moves and never changes width, so once you know where it is you can work quickly.

**Batch size** gives you a box and a **Set** button. Type the number of units a normal run makes and press **Set**. It has to be one or more. This is the number the factory sees when it plans a job order, and an artefact without one shows up in the red columns on the families list.

**Move to family** and **Move to department** give you a search box. Start typing a code or a name, pick the target, press **Move**. There is a **New family** link beside it if the family you want does not exist yet. A family belongs to one department, so moving artefacts into a family moves them into that family's department as well, whatever they were in before.

**Discontinue** asks before it does anything. The dialog tells you how many artefacts you are about to discontinue, and nothing happens until you press **Yes, discontinue**.

**Make active** brings discontinued artefacts back. It does not ask, because it is the safe direction.

## What discontinuing does and does not do

A discontinued artefact leaves the working lists. It keeps everything else: its recipe, its tasks, its history and the job orders it has been on. Nothing is deleted and no stock moves.

The artefact lists open on **In process** and **Active** only, so a discontinued artefact disappears from view. To see them again, use the **State** chips above the table and tick **Discontinued**.

Families take their state from the artefacts inside them. A family is active while any artefact in it is active or in process, and only turns discontinued once all of them have. That means discontinuing the last artefact in a family quietly removes the family from the families list as well, and the same **State** chips bring it back.

## Things worth knowing

- **Artefacts already in the state you chose are skipped.** Discontinuing a selection that is half discontinued reports only the ones that actually changed.
- **The selection is only what you can see.** Ticking the header box takes the rows on the page, not every artefact behind the filter. Change the page size first if you want more.
- **Nothing here touches stock.** These are recipe records, not the goods in the warehouse.
- **The counts on the families list catch up straight away.** Set a batch size on twenty artefacts and the red column on the families list drops by twenty as soon as the page reloads.
- **There is no undo for a move.** Moving artefacts to the wrong family is fixed by moving them back, which is the same two clicks.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Every artefact:</b> your organisation → <b>Factory</b> → <b>Crafts</b> → <b>All artefacts</b>.</li>
<li><b>One family:</b> <b>Crafts</b> → <b>Families</b> → the family → <b>Artefacts</b> tab.</li>
<li><b>One department:</b> <b>Crafts</b> → <b>Departments</b> → the department → <b>Artefacts</b> tab.</li>
<li><b>Start:</b> tick a row → the bar appears → pick the job on the right.</li>
<li><b>See discontinued ones:</b> the <b>State</b> chips above the table → tick <b>Discontinued</b>.</li>
<li><b>One artefact only:</b> open it and use the pencil, the same fields are there.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing the lists: a production position for that factory, or organisation supervisor.</li>
<li>Using the bar: the same position with editing rights on the factory, or organisation supervisor. Without it the tick boxes do not appear at all.</li>
</ul>
</aside>
