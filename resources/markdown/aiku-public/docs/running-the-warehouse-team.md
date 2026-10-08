---
title: Running the warehouse team
summary: The warehouse Team page — a dashboard for whoever runs the floor (who is in right now, the backlog waiting, today against a usual day, the last 30 days, and each person's output per worked hour, short picks and lateness) with a Clockings tab to see a day's clockings per person, close missed clock-outs and add or correct a clocking without going through HR.
date: 2026-10-07
tags: warehouse, clocking, picking, packing, performance
category: dispatch
help_routes: grp.org.warehouses.show.team.dashboard
---

<aside class="tldr">
Open <b>your warehouse → Team</b>. The <b>Dashboard</b> tab shows the floor right now (on site, clocked out, not in yet, did not clock in, on leave, day off), the backlog waiting to be picked and packed, the team's figures for today, yesterday, 7 or 30 days against the period before, charts of today by hour against a usual day and of the last 30 days, and a People table ranked by items per worked hour with short picks and late clock-ins. The <b>Clockings</b> tab shows one day's clockings per person, flags clock-ins that were never closed, and is where you add, change or delete a clocking. Only warehouse supervisors and people who can edit HR see this page.
</aside>

Open it at **your warehouse → Team**, the last item of the warehouse section in the sidebar.

## Who sees it

The page is for the people who run the warehouse floor. You see **Team** if you are a warehouse supervisor, dispatching supervisor or goods in supervisor for that warehouse. You also see it if you can edit HR in the organisation. Pickers and packers do not see it.

## Who is in the team

Everyone still working, or leaving, who holds a job position in the warehouse department for this warehouse: pickers, packers, exception pickers, stock controllers and supervisors. A position that is not tied to any warehouse counts for every warehouse. Someone who works only in another warehouse is not listed. To add or remove a person, change their job positions in HR.

Picking and packing are matched to a person through their user account. Someone with no user account in aiku shows hours but no picking or packing, and the People table says "no user" under their name.

## The Dashboard tab

Times follow the organisation's time zone.

### Floor now

Six counters, one per state, and the list of people under them. Press a counter to see only the people in that state; press it again to see everyone.

- **On site** — clocked in at this moment, with the time they clocked in and how long ago.
- **Clocked out** — were in today and have clocked out, with the time and the hours they worked.
- **Not in yet** — due today according to their work schedule (their own, or the organisation's if they have none) and the start time is less than 30 minutes ago.
- **Did not clock in** — due today, more than 30 minutes past their start time, and no clocking.
- **On leave** — an approved leave covers today.
- **Day off** — today is not a working day in their schedule.

The **+** at the end of a row adds a clocking for that person.

A yellow bar above the counters lists **clock-ins from earlier days that were never closed** (the last 14 days). Someone who forgot to clock out stays "on site" on that day for ever and their hours for it are wrong. Press the name to add the missing clock-out; the window opens on that day.

### Backlog the team faces

Delivery notes in this warehouse waiting for the team right now, with their item count: **To pick** (unassigned, queued or being picked), **Blocked**, **To pack** (picked or being packed) and **Packed, waiting dispatch**. **Open goods out** takes you to the full backlog.

### Throughput

Pick **Today**, **Yesterday**, **7 days** or **30 days**. The cards and the People table follow it; the floor status and the backlog are always about right now. Each card shows the figure for the period, the same figure for the period before (yesterday, the day before, the previous 7 or 30 days) and the change in percent, green when it moved the right way and red when it did not.

- **Delivery notes picked** and **Delivery notes packed** — notes finished in this warehouse by people in the team.
- **Items handled** — items picked plus items packed.
- **Hours worked** — the team's time on the clock from their timesheets, breaks excluded. Hover to see how many people clocked in.
- **Items per worked hour** — items handled divided by hours worked. Blank until the team has worked ten minutes.
- **Short picks** — pick lines the picker could not fulfil, with the share of all pick lines they make. Lower is better.
- **Late clock-ins** — clockings flagged late against the work schedule. Lower is better.

When nothing has been picked or packed yet today, a note says so and gives the last time something was.

### The charts

- **Today by hour** — delivery notes finished by the team each hour today (bars), against the average for the same weekday over the last four weeks (dashed lines). It tells you at a glance whether the day is ahead or behind a normal one.
- **Last 30 days** — delivery notes picked and packed per day.
- **Items per worked hour** — the team's productivity per day over the last 30 days, with the hours worked each day underneath, so a low day with few hours reads differently from a low day with the full team in.

### People

One row per person, ranked by items per worked hour; press a column header to sort differently. People with nothing recorded in the period are hidden; the switch in the heading shows them.

- **In** and **Out** (single day) are the first clock in and the last clock out. "…" means they are still on site. Over several days the table shows **Days** worked instead.
- **Worked** is their time on the clock, breaks excluded.
- **Picked** and **Packed** are items. Hover over the number to see how many delivery notes it covers.
- **Items/h** is picked plus packed items per worked hour, with a bar relative to the best in the team. A packer who also picks is measured on both.
- **Short picks** — lines they could not pick. Hover to see the share of their pick lines. A green 0 means they picked without a short.
- **Late** — how many of their clockings were late.

## The Clockings tab

One day at a time. Use the arrows, the date picker or **Today** to move. The line at the top gives how many people clocked that day, the hours worked, how many clockings were late and how many were added by hand. Only people with clockings are listed; the switch shows everyone.

Each person has a row with their worked time and break time, then their clockings in order: a green arrow for a clock in, a grey one for a clock out. A yellow clocking is a late one. Hover a time to see where it came from (the clocking machine, or who added it by hand) and its note. "No clock-out" in red under the name means their last clock-in on that day was never closed.

The yellow box **Missing clock-outs from earlier days** lists every clock-in from the last 14 days that was never closed. Press one to add the clock-out on that day.

### Adding, changing or deleting a clocking

- **Add** — press **+** at the end of a person's clockings, **+** on a row of the Dashboard, or **Add clocking** at the top and choose the person. Check the date and time (it starts at now, or at the day you are looking at, and cannot be in the future), add a note saying why, e.g. "forgot to clock in", and press **Add clocking**. Each clocking switches the person between clocked in and clocked out; the window tells you which one this will be.
- **Change** — press the pencil on a clocking and set the new time. The clocking stays on its day; only the time changes, and the working time it opened or closed is recalculated.
- **Delete** — press the bin on a clocking and confirm. The working time around it is recalculated. This cannot be undone.

A clocking added or changed here is the same as one done in HR: it goes on that day's timesheet and is recorded as done by you, with your name shown when someone hovers over it.
