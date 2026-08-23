---
title: Eight ways to clock in
summary: One warehouse wanted a fingerprint reader, another wanted to scan a QR with their own phone, the office wanted a PIN on a tablet, and a visiting colleague wanted to clock in wherever they happened to be. How the clocking system grew eight machine types by listening to managers, and the two quiet rules — order the timestamps, find the right employment — that keep the timesheets honest underneath all of them.
date: 2026-08-13
tags: hr, clocking, timesheets, ux, agile
---

<aside class="tldr"><strong>TL;DR</strong>The clocking system grew eight machine types — biometric, phone QR, tablet PIN, badge scan, NFC tag, camera-QR, handheld app, legacy import — by saying yes to each site cheaply, because the plumbing underneath is tiny and shared. Machines are shared group-wide across organisations. The two rules that actually keep timesheets honest: always order out-of-order timestamps before measuring a period, and resolve clockings to the current employment, not the first one found — a bug in each cost 479 negative periods and misattributed days respectively.</aside>

Clocking in sounds like the most solved problem in business software: a person arrives, a person leaves, subtract. It is also the feature where every site, every country and every manager has an opinion, because it touches pay and it touches habit. This note is how aiku ended up with **eight kinds of clocking machine**, why that is a success rather than a mess, and the two rules underneath them that matter more than any of the eight.

## How it grew

The first machines were the obvious ones: a biometric terminal at the warehouse door, and a *legacy* type to hold the history imported from the previous system. Then the requests started, one site at a time:

- A warehouse wanted people to use **their own phones**: scan a QR code on the wall, done. No shared touchscreen, no queue at the door.
- An office wanted the opposite: a tablet on the wall where you **type a PIN**, because not everyone had a phone on them.
- A site where pickers already carry a scanner wanted to **scan their own badge** on the bench scanner and be clocked in without walking anywhere.
- A small team wanted a **static NFC tag** they could tap.
- Managers wanted a **camera‑QR** mode — the wall tablet reads a code from the employee's phone — for places where the phone‑scans‑wall flow was awkward.
- And the warehouse handheld app got its own mode, so clocking rides the device people already hold.

Each of those is a `ClockingMachineType`. Each took days, not months, because the thing underneath a machine type is tiny: *given a code and a machine, which employee is this, and record a clocking with a timestamp and a source*. The eight types are eight ways to produce a code. A kiosk token, a QR hash that can be regenerated, a scan log for the auditors — the plumbing is shared.

We could have picked one and told every site to adapt. We did not, on purpose. The managers know their floor. The cost of a new machine type was low enough that saying yes was cheaper than arguing, and each yes bought us a site that *used* the system instead of keeping a paper sheet beside it.

## "Hey, you're in the wrong office — but we don't mind"

One request turned into a policy. Staff visit other sites: a supervisor covers for a week, a picker helps at the seasonal peak. They clock on whatever machine is in front of them, and we found that was already happening — five people, fifty‑five clockings — before anyone had decided it was allowed.

So it is allowed, structurally. Machines are **shared group‑wide**: a PIN or a QR is matched against every organisation in the machine's group, the machine's own organisation wins a tie, and the codes are verified unique across the group. The clocking carries the *machine's* organisation and the *employee's* organisation, and they are allowed to differ. The kiosk says so, cheerfully.

## The two rules underneath

The machines are the visible part. What makes the timesheets correct is two unglamorous rules, both written after finding out the hard way.

**Order the timestamps, then measure.** A machine that loses its connection uploads its clockings later, as a batch, *out of order*. Clocking ids arrive in upload order; the times on them do not. So a clocking stamped 10:50 could close a work period that opened at 11:06, and a signed difference made the period *negative* — and negative seconds were being added straight into the day's worked time. We found 479 such periods across 90 people going back nearly two years; historic hours had been slightly too low. Now every period passes through one normaliser that orders its two endpoints (swapping the clockings with them) and clamps at zero, on both the opening and the closing path. A repair command re‑ordered the old ones and re‑hydrated the days.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The eight kinds of clocking machine are the cases of <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Enums/HumanResources/ClockingMachine/ClockingMachineTypeEnum.php">app/Enums/HumanResources/ClockingMachine/ClockingMachineTypeEnum.php</a>.</li>
<li>Machines themselves are <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/HumanResources/ClockingMachine.php">app/Models/HumanResources/ClockingMachine.php</a>, with CRUD actions under <code>app/Actions/HumanResources/ClockingMachine</code>.</li>
<li>Ordering the two endpoints of a period before measuring is the same discipline PostgreSQL's own <code>LEAST()</code>/<code>GREATEST()</code> encourage at the SQL level: never subtract two timestamps without first deciding which is earlier.</li>
</ul></aside>

**Find the right employment, not the first one.** A person who leaves one company in the group and joins another has two employee records; the old one is *left*, the new one is *working*. A handful of call sites picked "the user's first employee record" — unordered — and sometimes attached a new clocking to the closed employment. Now there is one resolver, *current employee for this user* (working or leaving, preferring the organisation in context), and every one of the fifteen places that used to guess calls it. The repair for the handful of misattributed days was careful to *not* "fix" legitimate cross‑site clockings, which look similar and are fine.

## What a timesheet is

Clockings become **time trackers** (a start clocking, an end clocking, a duration), trackers roll up into a **timesheet** per person per day (working time, breaks, overtime computed by rule), and timesheets roll up into the payroll period. Manual entries and self‑checks are their own clocking types with their own audit, so a manager can always see which minutes came from a machine and which from a keyboard. Photos can be attached to a clocking where a site wants them.

## What we learned

Say yes to the floor when yes is cheap; make yes cheap by keeping the core tiny. Let people clock wherever they are, and record both organisations rather than forcing one. And spend the real engineering on the two invisible things — time that cannot go backwards, and a clocking that lands on the right employment — because those are what the payslip is made of.

<aside class="tldr bottom"><strong>In one paragraph</strong>Eight clocking machine types were cheap to add because the core underneath each is tiny; the two rules that actually protect the payslip — ordering out-of-order timestamps and resolving to the current employment — were the expensive, unglamorous work.</aside>
