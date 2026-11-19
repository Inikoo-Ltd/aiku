---
title: Planning the production day
summary: For the production manager and the floor supervisor - putting jobs on a day's plan without naming anyone, giving each step to an operator, and what happens to work not finished on its day.
date: 2026-11-19
tags: production, planning, floor
category: production
help_routes: grp.org.productions.show.operations.day_plan, grp.org.productions.show.operations.materials_check
series: Ordering from partners
order: 13
---

<aside class="tldr">
Two people, two jobs. The <b>manager</b> decides what is made on a day: on the <b>To produce</b> board she picks the cards and presses <b>Plan for this day</b>, without naming anyone. The <b>supervisor</b> opens <b>Operations → Day plan</b> and gives every step of every planned job to an operator. Each operator then sees their own steps on the floor screen. Anything not finished on its day stays on the plan in amber until someone confirms it for a new day.
</aside>

## The manager plans the day

On **Factory → To produce**, the board works as before: cards go from **Backlog** to **Preparing**, where the quantity is set, and from **Preparing** to **Assigned**, where the job order is created.

When a card is dropped on **Assigned**, the small window that asks *Who makes it?* now starts with a date and a button:

1. Pick the day. It opens on today.
2. Press **Plan for this day**.

A job order is created for that day with nobody named on it. Each card becomes its own job order, so each can be ordered and moved on its own. Several cards selected and dropped together are planned together, one job order each.

Picking a name from the list below still works as it always did: the whole job goes to that person and is not on any day's plan.

A job that already has someone named can be put on a plan too: press the pencil next to the name on its card and then **Plan for this day**. The name is taken off the job and the supervisor gives out its steps.

## The supervisor gives out the steps

**Factory → Operations → Day plan** lists the jobs planned for the day, one row per product on a job.

| Column | Meaning |
| --- | --- |
| Order | the place of the job in the day - type a number to move it |
| Job | the job order and its state |
| Product | what is made |
| Qty | how many |
| Steps and operators | every step of the recipe: made so far / asked for, and who it is given to |
| Planned | the day it was planned for |

Each step has its own list of names. Choose one and that step appears on that person's floor screen, and on nobody else's. A step with an amber border has nobody yet; the bar at the top counts them, *3 steps without an operator*, so nothing is left out by mistake.

To change who does a step during the day, choose another name. To take a step back from someone, press the small cross in the list. If the person had already started, the work they did stays recorded under their name: the list only decides who sees the step from now on.

The date at the top moves to another day. Tomorrow's plan can be given out today.

## Following the day

The same page shows how the day is going, and refreshes on its own:

- the bar at the top: how many jobs, *12 of 18 steps done*, steps without an operator, jobs not finished on their day;
- beside each step: a grey circle (not started), an amber spinner (started) or a green tick (done), with the count made so far;
- *working now* and the name, when someone has the step open on the floor.

People who can see the page but do not run the floor see the names, not the lists.

## When the supervisor is away

The manager opens the same page and gives out the steps herself. Planning and giving out steps need the same position.

## What was not finished

A planned job with steps still open after its day is not dropped. From the next day on it shows on the Day plan with its old date in amber, and the bar counts it as *not finished on its day*. For each one, choose:

- **Confirm for** the day you are looking at - the job moves to that day's plan, at the end of the order;
- **Take off the plan** - the job stays as a job order but is on no day's plan.

Until one of the two is pressed, the job stays in front of you every day. Its steps also stay on the floor screens of the operators they were given to, so someone who paused a mix yesterday can carry on this morning.

When an operator closes a job short and moves the rest to a new job (see [Working the floor screen](/docs/working-the-floor-screen)), the new job keeps the planned day, the place in the order and the operator of each step.

## When a product is made from another product

Some products are made from another product of the factory: a Puckator jumbo bath bomb (PuckJBB-04) is a JBB-04 bath bomb from stock, shrink-wrapped and labelled. When there are not enough JBB-04 in stock for it, the card on the **To produce** board and its job on the **Day plan** say so in amber, for example *Needs 3 SKO JBB-04, not in stock: make JBB-04 first*. Jobs already on the plan take the stock first, then the backlog cards, oldest first.

Press **Create JBB-04 job** next to the message: the **Create job order** window opens with JBB-04 and the missing SKOs already filled in, and a note saying which product it is for. Check it, choose the artisan and save. Once a JBB-04 job is made for stock, the message turns blue, *Waits for 3 SKO JBB-04 from* the new job, until the bath bombs are made. A JBB-04 job made for a customer or partner order does not count, because those bath bombs are already promised.

The message goes once the step that uses JBB-04 is done.

## Asking the warehouse for materials

Whenever the factory needs something from the warehouse, anyone working in production asks for it at the top of **Factory → Operations → Materials check**, under **Ask the warehouse**. Each line is:

| Field | What to put |
| --- | --- |
| SKO | type the code or the name; the list shows how many SKOs the warehouse and the factory hold |
| Quantity and unit | how you would say it to the warehouse: *3 Boxes*, *1 Pallet*, *80 Bags*, *2 SKOs*, or *All there is* |
| Drop point | where in the factory to leave it (*Storage room*, *Clean room*, *Bath bomb area*...) |
| Urgent | the warehouse sees urgent lines first |
| Note | anything the warehouse should know (*250ml*, *outside on pallets please*) |

**Add line** asks for several things at once, and **Send to warehouse** sends them. **Asked this week** below shows every request with who asked and when, and for each line whether it is *To bring*, *Delivered* (how many SKOs, by whom, when) or *Can't bring* with the warehouse's reason.

The manager and the supervisor keep the list of drop points with the **Drop points** button: add, rename, move up and down, or delete. A deleted drop point keeps its name on the requests already sent there.

## The morning materials check

The warehouse brings materials to the factory in whole boxes, not in exact amounts for the day. **Factory → Operations → Materials check** lists what the day plan still needs, so the factory checks what is there and asks the warehouse for the rest in one clear list.

Each line is one material, added up over every job of the day, with the jobs that need it (*JOaroma-0141, JOaroma-0145*). It counts only the steps not done yet: when the making is finished and only the packing is left, the line shows the boxes and labels and no longer the bottles. Mixes are made in the factory and are not on the list. Quantities come from the recipes, so a job whose recipe lists no materials needs nothing: the amber bar above the list names those jobs, so the gap in the recipe is seen.

| Column | What it means |
| --- | --- |
| Material | the material and the warehouse stock code |
| For jobs | the jobs of the day that need it |
| Needed | how many the steps still to do use up |
| At the factory | how many are in the factory location now, and how many were already asked for today |
| Check | tick **Have enough**, or type roughly how many are missing |

When a missing number is typed, the line shows what the warehouse will bring, rounded up to its boxes: 1,200 missing with 500 to a box is *bring 3 boxes (1,500)*. A red *Warehouse short* means the warehouse does not hold that many, so you know straight away.

Press **Send to warehouse**. The missing lines go to the warehouse as one request, and the request appears under **Asked this week** with each material marked *To bring*, *Delivered* or *Can't bring*. Ticking everything as enough and sending records that the check was done. A second check later in the day only adds what is still missing.

The manager or the supervisor does the check, or gives it to an operator with **Checked by** at the top. That operator then sees a reminder on the floor screen that opens the check.

## The warehouse brings the materials

In the warehouse, the **For production** card under **Action needed** on **Inventory** shows how many lines the factory is waiting for, with the urgent ones on the small badge. It opens **Inventory → For production**: every line still to bring, urgent first and then oldest, with what was asked, the drop point, who asked and when, the note, and the shelf to take it from. The picker chooses the shelf, types the SKOs brought and presses **Delivered**. When the warehouse cannot bring it, **Can't bring** asks why (*out of stock*, *ordered from supplier*) and tells the factory. **Done** lists what was brought or refused, with how long it took.

If Aiku shows fewer SKOs on the shelf than were brought, the delivery still goes through: what Aiku had moves to the factory, and the rest is marked as *not in Aiku, count it*, so the SKO gets counted.

Delivered stock moves from the shelf to the factory location. It stays stock: it is counted and seen as before, and a customer order can still pick it there when the warehouse shelves are empty, the shelves always first. Materials not used can be moved back to a shelf from the factory location's page, like any other move between locations.

When a finished job is received into stock, its materials are used up from the factory location first, using the recipe quantities, and only then from other locations. Count the factory location now and then to correct breakages and waste.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Plan a job for a day:</b> <b>Factory</b> → <b>To produce</b> → drop the card on <b>Assigned</b> → pick the day → <b>Plan for this day</b>.</li>
<li><b>Give a step to an operator:</b> <b>Factory</b> → <b>Operations</b> → <b>Day plan</b> → the list beside the step.</li>
<li><b>Change the order of the day:</b> type a number in <b>Order</b>.</li>
<li><b>Yesterday's unfinished job:</b> <b>Confirm for</b> the day, or <b>Take off the plan</b>.</li>
<li><b>From the board to the plan:</b> the <b>Day plan</b> link above the board.</li>
<li><b>Ask the warehouse for something:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Ask the warehouse</b> → <b>Send to warehouse</b>.</li>
<li><b>Change the drop points:</b> <b>Materials check</b> → <b>Drop points</b>.</li>
<li><b>Check the day's materials:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Have enough</b> or a missing number → <b>Send to warehouse</b>.</li>
<li><b>Give the check to an operator:</b> <b>Materials check</b> → <b>Checked by</b>.</li>
<li><b>Bring the materials (warehouse):</b> <b>Inventory</b> → <b>For production</b> → <b>Delivered</b>, or <b>Can't bring</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Planning a day, giving out steps, changing the order: <b>Floor supervisor</b> for the factory.</li>
<li>Seeing the Day plan without changing it: <b>Foreman</b>.</li>
<li>Working a step given to you: <b>Operative</b>, on the floor screen.</li>
<li>Asking the warehouse for materials: anyone who sees the factory's Operations.</li>
<li>The materials check and the drop points: <b>Floor supervisor</b>; the check also by the operator it was given to.</li>
<li>Bringing materials to the factory: a warehouse position that works in the warehouse.</li>
</ul>
</aside>
