---
title: Working the floor screen
summary: The artisan's guide - the My tasks screen, the job list that updates on its own, START and DONE, and what to press when you made fewer than asked.
date: 2026-10-09
tags: production, floor, artisan
category: production
help_routes: grp.org.productions.show.floor
series: Ordering from partners
order: 9
---

<aside class="tldr">
For the people who make things. <b>Factory → Jobs</b> is your whole day on one screen: the jobs addressed to you down the left, the one you are working on in the middle. Press <b>START</b>, make it, type how many, press <b>DONE</b>. If you made fewer than asked, the screen asks one question: finish the job here, or carry the rest over to a new job. Nothing else to fill in.
</aside>

## The screen

The page is laid out like an inbox.

**Left, always visible:** the list. Two counters at the top - units made today and tasks finished today. Below them **Your jobs**, the work addressed to you. If your position lets you pick from the open pool, an **Open jobs** section follows. At the bottom, **Finished today**, what you have already closed.

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

## When you made fewer than asked

Type the real number and press **DONE**. The input row is replaced by a short panel: *19 done · 6 to do* and three buttons.

- **Continue later** - the job is closed at 19, and a new job order for the 6 appears in your list, addressed to you. Pick it up tomorrow, or whenever the material arrives.
- **Job finished** - the job is closed at 19 and that is the end of it. The planner sees a job order that asked for 25 and got 19.
- **Back** - change the number.

Either way the job you were on is closed and paid for at what you made. Nothing stays half open on your list.

## When you made more than asked

Type the real number and press **DONE**. The screen warns *18 above target, a manager must authorise it* and opens **Manager authorisation required for overproduction**.

- **Scan the badge** - a manager or supervisor holds their personal QR (the same one they clock in with) to the camera. It is accepted as soon as it is read. **Switch camera** flips between front and back camera.
- **Use manager PIN** - if the camera will not read it, the manager types their clocking PIN instead.

Only someone who runs this factory floor can authorise, and never for their own work. After five wrong badges or PINs the window refuses for 15 minutes.

Once authorised the job grows to what was really made: the warehouse puts away all of it, what no order asked for goes to stock, and the raw materials are deducted for the whole batch. Later steps of the same job grow too, so the next person can finish all of it. The job order page shows who made the extra, who authorised it, how and when.

## One batch for several lines

Sometimes one batch serves more than one job. For example, 100 HCS-48 loaves and 100 SLHCS-48 sliced loaves in the same scent are mixed, poured and moulded together as 200 loaves.

**Combining (managers).** Tap one of the lines on the floor screen. Under its details, **Combine Production with other lines into one batch** lists the other open lines waiting for the same step. Tick the ones that go in the same batch and press **Combine**. A line that is already combined, finished or being worked on is not offered.

**Working it (artisans).** The lines now show as one row with a 🔗 and the codes together, *HCS-48 + SLHCS-48*, and the total, *0/200*. The details list each line with its job order. Press **START** once, make the batch, type the total made - 200 - and press **DONE** once.

**What Aiku does with it.** The total is shared between the lines by what each still had to make, in whole units: 100 to HCS-48, 100 to SLHCS-48. The time is shared the same way. Each line then moves on to its own next steps - shrinking for one, slicing then shrinking for the other. The job order page shows the step as *One batch with* the other line.

**Pay and targets** are worked out on the batch as a whole: 200 loaves in the hours it took, against the step's target. Combining pays exactly what separate batches at the same speed would. When the lines have different targets for the step, the batch target is the one that takes the same hours as the separate batches would.

Making more than all the lines asked for needs a manager's badge or PIN, as above; the extra is shared between the lines too. A manager can **Separate** the lines again whenever nobody is working on them. What was already made together stays with each line.

## Things worth knowing

- **One job at a time.** While you have a job open the START buttons are disabled. Close it first.
- **Your pay for the task** follows the number in Quantity made, as explained in [Factory positions](/docs/factory-positions). Rejected pieces are not paid.
- **Open jobs** are jobs with no artisan named, or with someone else named. Taking one is fine if your position allows it; it is then yours until closed.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Your screen:</b> your organisation → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Start:</b> tap the row → <b>START</b>.</li>
<li><b>Finish:</b> type <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Fewer than asked:</b> <b>DONE</b> → <b>Continue later</b> or <b>Job finished</b>.</li>
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
