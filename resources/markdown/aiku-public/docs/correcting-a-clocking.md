---
title: Correcting a clocking
summary: When a clock-in or clock-out is wrong or missing, the employee asks for a correction from their own timesheet and HR approves or rejects it. An approved correction changes the timesheet itself.
date: 2026-11-03
tags: hr, clocking, attendance
category: hr
help_routes: grp.org.hr.adjustments., grp.clocking_employees.adjustments
---

<aside class="tldr">
Forgot to clock out, or clocked in late because the machine was busy? The employee asks for a <b>clocking adjustment</b> from their own <b>Employee Clocking</b> page, giving the right times and a reason. HR sees it under <b>HR → Clocking adjustment</b> and approves or rejects it. Approving changes that day's timesheet to the requested times; nothing changes until then.
</aside>

## Asking for a correction (employee)

Everything is on your **Employee Clocking** page. Which tab you use depends on the day.

### A day that already has clockings

1. Open the **Timesheets** tab and find the day.
2. Press **Request correction** at the end of the row.
3. The **Request timesheet correction** box shows what was **Recorded** and starts with those times filled in. Change the **Check-in time**, the **Check-out time**, or both.
4. Write the **Reason**. It is required.
5. If you have proof, add it under **Supporting Document (Optional)**: PDF, JPG or PNG, up to 3 files of 5 MB each.
6. Press **Submit Request**.

The row now shows **Correction pending** instead of the button, until HR decides.

### A day with no clockings at all

Open the **Clocking adjustment** tab and press **Clocking adjustment**. Pick the **Date**, fill in the check-in time, the check-out time or both, give the reason and submit.

### What you can and can't ask for

- Requests go back **one month** at most, and never for a future date.
- The check-out time must be after the check-in time.
- One pending request per day. Wait for the decision, or cancel it, before asking again.
- Not for a day covered by approved leave.

### Following your request

The **Clocking adjustment** tab lists your requests with the original times, the requested times and the status. While a request is pending you can take it back with **Cancel request**. If it is rejected, HR's reason is shown in red under your own.

## Deciding a request (HR)

Open **HR → Clocking adjustment**. Each request is a card: the employee, the day, the times **Recorded** (or **No clockings**) next to the times **Requested**, the reason and any attachments. The times are shown in your organisation's time zone, which is named on the card.

Above the cards you can search by employee or reason, pick a status (**All**, **Pending**, **Approved**, **Rejected**) and set a **From** and **To** date. **Clear filters** resets them.

- **Approve** asks you to confirm, then changes the timesheet for that day: the first clock-in and the last clock-out move to the requested times and the working hours are worked out again. Breaks in the middle of the day stay as they were. For a day with no clockings, the missing clock-in and clock-out are added.
- **Reject** asks for a **Reason for rejection**, which the employee sees.

A decided request shows **Processed**, with who decided it and when, and can't be decided again.

### Artisans' weeks already paid

Artisans on the factory floor are paid for the hours they clock, and the accountant pays each week (Monday to Sunday) on the Tuesday after it. So once that Tuesday starts, the week is **Locked for payroll**: HR can no longer approve a correction for it, nor add, change or delete its clockings on the timesheet. Only the accountant and the Production Manager can, and Aiku asks them for the reason. The change is recorded, listed under **Factory** → **Artisans** → **Payroll changes**, and the difference in pay is paid or deducted in the next pay run. The current week, and last week until Monday 23:59, can be corrected as usual. Clockings of employees who don't work on a factory floor are not affected.

### The dashboard

The **Dashboard** tab on the same page is a month at a glance: one row per employee, one column per day, with a mark on each day that has a request, coloured by its status. Filter by employee or status, step through the months, and click a mark to see the **Adjustment Details**.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Correct a day you clocked on:</b> <b>Employee Clocking</b> → <b>Timesheets</b> → <b>Request correction</b>.</li>
<li><b>Add a day you didn't clock at all:</b> <b>Employee Clocking</b> → <b>Clocking adjustment</b> → <b>Clocking adjustment</b>.</li>
<li><b>Take a request back:</b> <b>Employee Clocking</b> → <b>Clocking adjustment</b> → <b>Cancel request</b>.</li>
<li><b>Approve or reject:</b> your organisation → <b>HR → Clocking adjustment</b>.</li>
<li><b>See the month:</b> <b>HR → Clocking adjustment</b> → <b>Dashboard</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Asking for a correction needs an employee record linked to your aiku login. You can only correct your own timesheet.</li>
<li>Seeing the requests and the dashboard needs <b>HR view</b> rights in the organisation.</li>
<li>Approving and rejecting need <b>HR edit</b> rights.</li>
</ul>
</aside>

Related: <a href="/docs/reading-the-hr-dashboard">Reading the HR dashboard</a> for the day's attendance, and <a href="/docs/setting-up-a-clocking-machine">Setting up a clocking machine</a> for where clockings come from.
