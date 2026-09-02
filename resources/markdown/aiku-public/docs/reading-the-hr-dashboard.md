---
title: Reading the HR dashboard
summary: The first screen under HR — who is in today, who is late, who is off and why, plus the week's leave, this month's leave types and birthdays. Every number is a link to the people behind it.
date: 2026-09-02
tags: hr, attendance, leave, clocking
category: hr
help_routes: grp.org.hr.dashboard
---

<aside class="tldr">
Open <b>HR</b> and this is what you land on. The top strip counts what your HR module contains. The five cards under it answer the daily question — <em>who is here, who is late, who is off, who is missing</em> — and clicking any card lists the names. The table below the cards is the day's attendance, and you can step back to any past day. The bottom row is about leave and birthdays. If you only manage the people, read the attendance sections. If you also run the clocking machines, see <a href="/docs/setting-up-a-clocking-machine">Setting up a clocking machine</a> for where the "present" and "late" figures come from.
</aside>

Open it at **your organisation → HR**. The dashboard is the HR home page, so it is also where the sidebar's HR link takes you.

## The strip at the top: what the module holds

Six small counters, each a link to its own list:

- **Employees** — people currently *working* (not those who have left or not yet started). Opens the employee list already filtered to working staff.
- **Working places** — the sites people clock in at.
- **Responsibilities** — the job positions people can hold.
- **Clocking machines** — the kiosks and QR points that record arrivals.
- **Timesheets** — every recorded working day.
- **Staff chat** — messages in the last thirty days, opening the chat analytics.

These are inventory, not status. Nothing here changes during the day.

## The five cards: who is where today

This row is the part to read every morning. The heading on each card says **today** while you are looking at today, and drops the word when you step back to an earlier date.

- **Present** — employees with a timesheet for the day, meaning they clocked in at least once. Someone who arrived and already left still counts as present.
- **Annual leave** — employees on an *approved* leave whose leave type is in the annual category and which covers the day.
- **Sick leave** — the same, for leave types in the medical category.
- **Late** — present employees whose *first* clock-in of the day was late. Lateness is decided at the moment they clock in: later than the scheduled start plus a fifteen-minute grace period. Part-time staff and days marked as non-working in the schedule are never late.
- **Absent** — working employees who have neither clocked in nor an approved leave covering the day. This is the card that matters: it is the list of people you may need to call.

**Click any card** to see the names. The card gets a highlighted ring and the table below switches to that group:

- Present and Late show the attendance table (Late shows only the late rows).
- Annual leave and Sick leave show each person with their leave type and dates.
- Absent shows each person and their job title. Click a name to open the employee.

A **Show all** link next to the table heading clears the selection. The selection lives in the page address, so you can send a colleague the link to "absent today".

Two things to know about the arithmetic. An employee on leave who clocks in anyway appears in Present *and* in their leave card. And Present counts anyone who clocked in, including someone whose employment state is not "working" — so on a day with visitors or leavers the cards need not add up to the Employees counter exactly.

## The attendance table

Below the cards, the day's attendance, **earliest arrivals first**. Each row is one employee's day:

- **Name** and job title. The name opens the timesheet. The picture next to it is the avatar the person has set on their own aiku profile; without one, it is their initials on a plain circle. aiku never invents a face for anyone, and nobody but the person themselves chooses their picture.
- **Start at** — first clock-in, shown in red when it was late.
- **End at** — last clock-out, or *Still working* if their last action was a clock-in.
- **Status** — *Late*, *Working* (still on site) or *On time*.
- **Notes** — whatever the person typed at the clocking machine on their first clock-in, if anything. Late arrivals are usually asked for a reason; it lands here.
- **Working** and **Breaks** — hours and minutes so far, from the timesheet.
- **Clock in** and **Clock out** — how many of each. A mismatch of one means they are still in.

### Looking at another day

The arrows and the date picker next to the heading move the whole view — cards and table — to that day. You cannot go past today. The green **N present** pill and the card counts follow the chosen date, so "Absent" for last Tuesday is exactly who was missing last Tuesday. **Today** brings you back.

## Leave and birthdays

The bottom row does not depend on the chosen date; it always describes now.

- **Leave overview** — a bar per weekday, Monday to Friday of the current week, showing how many employees have approved leave that day. Today's bar is green.
- **Employee leaves** — the next twenty approved leaves that have not ended yet, soonest first, with the type and the dates. This is your "who is off soon" list.
- **Leave types** — a donut of this month's approved leave, one slice per type, counting employees rather than days. The centre shows the total number of employees who have some leave this month.
- **Birthdays this month** — working employees with a birthday this month, in date order, today's marked with a cake.

Leave that is still *pending* approval appears nowhere on this page. Approve it under **Leave requests** first.

## Quick actions

The panel on the right is the four things HR does most:

- **Create employee** — the form described in <a href="/docs/setting-up-a-new-employee">Setting up a new employee</a>.
- **Record leave** — enter leave on someone's behalf; it is approved as you save it, so it counts on the dashboard immediately.
- **Leave requests** — the queue of what staff have asked for.
- **Leave calendar** — the month view of who is off.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>The dashboard:</b> your organisation → <b>HR</b>.</li>
<li><b>Who is absent:</b> click the <b>Absent</b> card; click a name to open the employee.</li>
<li><b>Why someone was late:</b> click the <b>Late</b> card and read the <b>Notes</b> column, or open the timesheet from their name.</li>
<li><b>A photo instead of initials:</b> only the person can do this, on their own profile (top right → <b>Profile</b> → <b>Edit</b> → avatar). Staff without an aiku login always show as initials.</li>
<li><b>Another day:</b> the arrows or the date picker above the table; <b>Today</b> to return.</li>
<li><b>The start time lateness is measured against:</b> <b>HR → Shift Schedules</b>. The fifteen-minute grace period is fixed; ask aiku support if your organisation needs a different one.</li>
<li><b>Get pending leave onto the dashboard:</b> <b>HR → Leave requests</b> → approve.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Seeing the dashboard needs <b>HR view</b> rights in the organisation, or the HR supervisor role.</li>
<li>The quick actions (creating employees, recording leave) need <b>HR edit</b> rights.</li>
</ul>
</aside>
