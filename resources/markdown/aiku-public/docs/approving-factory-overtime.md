---
title: Approving factory overtime
summary: How the production manager plans and approves overtime on Daily hours, for one artisan or several at once, chooses pay or time off in lieu, and how weekend overtime, TOIL and refused overtime show on Payroll.
date: 2026-11-10
tags: production, payroll, overtime, artisan
category: production
help_routes: grp.org.productions.show.artisans.daily_hours, grp.org.productions.show.artisans.payroll
---

<aside class="tldr">
On <b>Factory → Artisans → Daily hours</b>, press <b>Plan overtime</b> to enter the planned times for a day and approve them for one or several artisans, or tick days that went over the shift and press <b>Overtime for … selected</b>. Choose <b>Paid</b> or <b>Time off in lieu</b>. Saturday and Sunday overtime approved as paid is paid at 1.5 × Band 0; weekday overtime is paid as normal hours. Time off in lieu is not paid: it goes into the artisan's TOIL balance. Overtime nobody has approved or rejected is flagged on Payroll, and managers get a reminder on Monday morning, before the pay week locks at the end of Monday. A factory can also be set to pay only the time clocked inside the shift, so an early start or a late finish is paid only when its overtime is approved.
</aside>

## What counts as overtime

Overtime is the time an artisan clocked beyond their shift for that day (the shift in their work schedule, less the unpaid break). On a Saturday or Sunday with no shift, every clocked hour is overtime. Bank holidays are not treated as weekends.

## Planning overtime ahead

1. Open **Factory → Artisans → Daily hours** and press **Plan overtime**.
2. Pick the **Day** and the **Artisans** (several at once is fine).
3. Enter **Planned from** and **Planned to**, for example 07:00 and 12:00.
4. Choose **Approve overtime**, then **Paid** or **Time off in lieu**.
5. Press **Save**.

Each artisan gets their own record of who approved it, when, and the planned times. Once they have clocked, **Daily hours** shows the planned times beside the clocked times. If someone clocks in before or out after the planned times and the shift by more than the tolerance, the day shows **Outside planned times** and is flagged again until you check it and save the overtime again. Only the planned hours are paid as overtime or added as TOIL: time clocked beyond them is paid as normal hours until you save the overtime again after the day.

## Approving overtime after the day

Days more than the tolerance over the shift show **Over shift time**. Either press **Approve or not** on the day, or tick several days and press **Overtime for … selected** to answer them in one go. The planned times are optional here.

## Not authorising overtime

Choose **Do not authorise** and give the reason. The hours are not taken off the pay: they stay on **Payroll**, marked **Overtime not authorised** with your reason, for the accountant and HR to deal with.

## Paid or time off in lieu

- **Paid, Saturday or Sunday**: every overtime hour is paid at 1.5 × the Band 0 rate (1.5 × £12.71 = £19.07 an hour), whatever the piece work. There is no band pay and no Guru bonus on these hours. When the Band 0 rate changes, the weekend rate follows it from the same date.
- **Paid, weekday**: paid as normal clocked hours, with the bands from the piece work as usual.
- **Time off in lieu (TOIL)**: the overtime hours are not paid. They are added to the artisan's TOIL balance, times the TOIL ratio.

## The TOIL balance

Press **Time off in lieu** on Daily hours to see what each artisan is owed and to **Record TOIL taken** (artisan, day and hours). You cannot record more than the balance. The production manager or the accountant sets **TOIL hours per overtime hour** in the same window (1 by default). Overtime already given as TOIL keeps the ratio it was approved with.

## What the accountant sees on Payroll

- **Weekend overtime**: the hours and the amount at 1.5 × Band 0, included in the total. The CSV also gives the rate and the band split of those hours, for information only.
- **TOIL hours (not paid)** and **TOIL balance**: the CSV adds the hours owed, the TOIL taken in the period and the balance at its end.
- **Overtime not authorised** and **Overtime awaiting decision** next to the artisan's name, and as hours in the CSV.

The Payroll page and the CSV do not decide how TOIL counts towards the minimum wage: that is for the accountant.

## Factories that pay inside the shift only

The production manager or the accountant can set a factory to pay only the time clocked inside the shift. On **Daily hours**, press the cog next to **Every clocked hour is paid**, turn on **Pay inside the shift only**, choose the rounding step and the day it starts from, and press **Save**. The day must be in a week not yet locked for payroll: weeks already paid stay as they were.

From that day on:

- Time clocked before the shift starts or after it ends is not paid. **Daily hours** shows it under the clocked time as **not paid**, and **Payroll** shows **Outside the shift, not paid** next to the artisan's name.
- To pay it, approve the overtime for that day as described above. Approving the day pays the time clocked outside the shift, up to the planned times when it was planned ahead. On a Saturday or Sunday with no shift, nothing is paid until the overtime is approved.
- **Do not authorise** means the time outside the shift stays unpaid.
- Somebody who clocks in late is paid from the clock in time, rounded to the nearest step counted from the shift start (5 minutes unless you change it): 08:02 is paid from 08:00, 08:37 from 08:35.
- Work recorded on the tablet before or after the shift is not paid either.
- The Guru bonus is paid on the paid hours.

Pay follows each artisan's working hours, so check they are right on the employee. Changing somebody's working hours later does not change a week already locked for payroll. The Payroll CSV gives the paid hours, and at the end of each row the clocked hours and the hours clocked outside the shift and not paid.

## The Monday reminder

On Monday morning the factory's managers get an email listing every day of last week whose overtime nobody has approved or rejected yet, with a link to those days on Daily hours. The pay week locks at the end of Monday; after that only the accountant can change it, with a reason.
