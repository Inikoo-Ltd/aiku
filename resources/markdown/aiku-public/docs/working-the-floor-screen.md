---
title: Working the floor screen
summary: The artisan's guide - the My tasks screen, the job list that updates on its own, START and DONE, what to press when you made fewer than asked, and taking a break.
date: 2026-10-23
tags: production, floor, artisan
category: production
help_routes: grp.org.productions.show.floor
series: Ordering from partners
order: 9
---

<aside class="tldr">
For the people who make things. <b>Factory → Jobs</b> is your whole day on one screen: the jobs addressed to you down the left, the one you are working on in the middle. Press <b>START</b>, make it, type how many, press <b>DONE</b>. If you made fewer than asked, the screen asks one question: pause and carry on later, close the job and move the rest to a new one, or finish it here. Nothing else to fill in.
</aside>

## The screen

The page is laid out like an inbox.

**Left, always visible:** the list. Two counters at the top - units made today and tasks finished today. Below them **Your jobs**, the work addressed to you: whole jobs with your name on them, and single steps the supervisor gave you on the day's plan (see [Planning the production day](/docs/planning-the-production-day)). A job can have its making with one person and its packing with another; each sees only their own step. If your position lets you pick from the open pool, an **Open jobs** section follows. At the bottom, **Finished today**, what you have already closed.

**Right:** whatever you have selected. Before you start, it is the job's details with a big **START**. Once started, it is the working card with the clock.

The list does not need refreshing. When a planner assigns you something, when a colleague starts one of the open jobs, when you close one, the list changes on its own, on every screen showing that factory.

## One row in the list

Each row is one task on one job order:

| Line | Meaning |
| --- | --- |
| Code | the product to make - SKO-01 |
| Name | its name |
| Task · job order | the step (Production, Labelling...) and the job order reference |
| 0/25 | made so far / asked for |

A row with a green ▶ and a time is the one you are working on, and when you started it. An amber note under a row means it is waiting for a mix, or someone else already has it open.

## Doing a job

1. Tap the row. The details appear on the right.
2. Press **START**. The clock starts and the row gets its green marker.
3. Make it.
4. Type the number you made in **Quantity made**. If you are a foreman or above, there is also a **Rejected** box for pieces that did not pass.
5. Press **DONE**.

Made everything that was asked? That is it. The task closes, the job order is finished and the warehouse is told there is something to put away - see [Putting away finished production](/docs/putting-away-finished-production).

## Steps counted in packs

Some steps, packing above all, are counted in packs and not in single pieces: a tray of 8 bath bombs counts as 1. The screen then says **Counted in PACKS of 8** in amber, and the box is called **Packs of 8 made**. Type how many full packs you filled: for 56 bath bombs in 7 trays type 7, not 56. Under the box the screen shows what your number means, *7 packs of 8 = 56 × ABB-06a*, and the confirmation shows it again. Only whole packs are accepted; the one exception is the exact number still to do, which you fill by tapping the big number. When a manager has to authorise extra, the window shows the extra in packs and in pieces before they approve.

## When you made fewer than asked

Type the real number and press **DONE**. The input row is replaced by a short panel: *19 done · 6 to do* and four buttons.

- **Pause** - same mix, you carry on later. The job stays open with the same reference and the same batch code, and the count carries on from 19. Use this when the day ends before the mix does.
- **Close at 19 and move remaining 6 to new job …** - the job is closed at 19 and the 6 go to a new job order with the next letter (0127 becomes 0127a) and its own batch code. Use this when the rest will be a new mix. The screen asks you to confirm with **Yes, close and move**, because a closed job cannot be continued.
- **Job finished** - the job is closed at 19 and that is the end of it. The planner sees a job order that asked for 25 and got 19.
- **Back** - change the number.

A job moved to a new one shows **Continued from 0127** on its card, and the one it came from shows **Continued in 0127a**. The new job stays with the same people and on the same day's plan.

## When you made more than asked

Type the real number and press **DONE**. The screen warns *18 above target, a manager must authorise it* and opens **Manager authorisation required for overproduction**.

- **Scan the badge** - a manager or supervisor holds their personal QR (the same one they clock in with) to the camera. It is accepted as soon as it is read. **Switch camera** flips between front and back camera.
- **Use manager PIN** - if the camera will not read it, the manager types their clocking PIN instead.

Only someone who runs this factory floor can authorise, and never for their own work. After five wrong badges or PINs the window refuses for 15 minutes.

Once authorised the job grows to what was really made: the warehouse puts away all of it, what no order asked for goes to stock, and the raw materials are deducted for the whole batch. Later steps of the same job grow too, so the next person can finish all of it. The job order page shows who made the extra, who authorised it, how and when.

A job carried to another day is the exception. There, no manager can authorise a step past what the step before it made on that part: if Making finished 138 there, Labelling stops at 138, and the screen names the other part of the job (for example JOaroma-0152a) where the rest was made and must be entered.

Typed the wrong number, for example pieces where packs were asked? A manager opens the **Performance** tab, finds the entry under the worker and presses **Correct quantity**. The entry can only be lowered. The job goes back to what was really made, the authorised extra is reduced with it and the pay for that entry is worked out again. It works until the warehouse starts putting the job away.

## One batch for several lines

Sometimes one batch serves more than one job. For example, 100 HCS-48 loaves and 100 SLHCS-48 sliced loaves in the same scent are mixed, poured and moulded together as 200 loaves.

**Combining (managers).** Tap one of the lines on the floor screen. Under its details, **Combine Production with other lines into one batch** lists the other open lines waiting for the same step. Tick the ones that go in the same batch and press **Combine**. A line that is already combined, finished or being worked on is not offered.

**Working it (artisans).** The lines now show as one row with a 🔗 and the codes together, *HCS-48 + SLHCS-48*, and the total, *0/200*. The details list each line with its job order. Press **START** once, make the batch, type the total made - 200 - and press **DONE** once.

**What Aiku does with it.** The total is shared between the lines by what each still had to make, in whole units: 100 to HCS-48, 100 to SLHCS-48. The time is shared the same way. Each line then moves on to its own next steps - shrinking for one, slicing then shrinking for the other. The job order page shows the step as *One batch with* the other line.

**Pay and targets** are worked out on the batch as a whole: 200 loaves in the hours it took, against the step's target. Combining pays exactly what separate batches at the same speed would. When the lines have different targets for the step, the batch target is the one that takes the same hours as the separate batches would.

Making more than all the lines asked for needs a manager's badge or PIN, as above; the extra is shared between the lines too. A manager can **Separate** the lines again whenever nobody is working on them. What was already made together stays with each line.

## Mixing and other steps weighed in kg

Mixing is a step of its own at the start of the recipe, and it is counted in kg, not in pieces. The screen shows **kg** beside the numbers and the box is called **Kg made**: for a 50 kg mix type 50. Under the box the screen shows what that makes, *50 kg of mix = 500 × ABB-06a*. A mix that comes out a little over what the job asked for is accepted as it is, with no manager.

Only the artisans named on the step see it and can start it; it is not on anybody else's list. It is paid by the hour at the base rate: no target, no pay tier and no under-target note, and the time still counts in the cost of the product.

The next step stays locked with **Waiting for** and the name of the mixing step until some mix has been recorded, and opens as soon as the first mix is entered. One mix for several lines works as in the section above: a manager combines the mixing lines, the mixer types the kg once, and each line gets its share.

**Mixes made in whole batches.** When the product has a mix batch size (25 kg or 50 kg), the kg asked is rounded up to whole batches and shown as batches: 94.1 kg in 50 kg batches shows *2 × 50 kg batches = 100 kg*. The box is called **Batches of 50 kg made** and you type the number of batches, 2, not the kg. For a combined mix the rounding is on the total of all the lines. The extra over what the job asked for does not change the job, the forming quantities or the pay. Managers set it on the recipe step of the product, under **Mix batch size**.

**Setting it up (managers).** On the page of the manufacture task, **Only these artisans can do it** lists who may do it; add or remove people there. Its edit form has **Paid by piece rate** (off for hourly work) and **Recorded in kg**. The kg of mix one unit takes goes on the recipe step of each product.

## Taking a break

Under the working area there is a row **Break:** with **5m**, **20m** and **30m**. Tap the length you are taking and confirm with **Yes, start break**.

- If you are clocked in, the break clocks you out.
- If a job is open, its clock stops for the break: break time is not counted as time on the job.
- The screen turns into a large **On break** panel: the time since the break started, **Planned:** with the length you chose, and a big **Finish break** button.

**The break does not end on its own.** When you are back at your bench, press **Finish break**. That clocks you back in and records how long the break really was. Clocking in with your QR code at the kiosk also ends the break.

When a break runs past the planned length, the panel says how far over it is. Once it is more than a few minutes over (5 unless the Production Manager has changed it), the panel turns red, and the break shows as overrun to the supervisors and the manager on the **Performance** tab.

Hourly artisans have no break buttons. They take breaks by clocking out with their QR code, and the job carries on when they clock back in.

**For supervisors and managers.** The **Performance** tab has a **Breaks** table: for each artisan and day, the number of breaks, the minutes planned, the minutes actually taken and the minutes over the plan. Tick **Breaks over plan only** and press **Apply** to see only the breaks that ran over. A red box at the top lists breaks running over right now and breaks still open after the shift ended. For a break left open after the shift, press **Set end**, enter the time the artisan really came back and save; the hours and pay of the jobs the break overlaps are worked out again, and the end first recorded is kept. Clockings are not changed there: they are corrected in Human Resources. The Production Manager sets the allowance with the cog next to the **Breaks** heading.

## Things worth knowing

- **One job at a time.** While you have a job open the START buttons are disabled. Close it first.
- **Your pay for the task** follows the number in Quantity made, as explained in [Factory positions](/docs/factory-positions). Rejected pieces are not paid.
- **Open jobs** are jobs and steps with nobody named, or with someone else named. Taking one is fine if your position allows it; it is then yours until closed.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Your screen:</b> your organisation → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Start:</b> tap the row → <b>START</b>.</li>
<li><b>Finish:</b> type <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Fewer than asked:</b> <b>DONE</b> → <b>Pause</b>, <b>Close at … and move remaining …</b> or <b>Job finished</b>.</li>
<li><b>Taking a break:</b> <b>Break:</b> <b>5m</b>, <b>20m</b> or <b>30m</b> → <b>Yes, start break</b>. Back: <b>Finish break</b>.</li>
<li><b>Break left open after the shift (supervisors):</b> <b>Factory</b> → <b>Artisans</b> → <b>Performance</b> → <b>Set end</b>.</li>
<li><b>One batch for several lines:</b> tap a line → <b>Combine … with other lines into one batch</b> → tick the lines → <b>Combine</b>. To undo: tap the combined row → <b>Separate</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing your own jobs, START and DONE: the <b>Operative</b> position for the factory.</li>
<li>Seeing and taking <b>Open jobs</b>, recording rejects: <b>Foreman</b>, <b>Mix preparer</b> or <b>Floor supervisor</b>.</li>
<li>Combining and separating lines: the managers who run the factory floor, the same people who can authorise overproduction.</li>
</ul>
</aside>
