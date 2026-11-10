---
title: Approving factory overtime
summary: How the production manager plans and approves overtime on Daily hours, for one artisan or several at once, chooses pay or time off in lieu, and how weekend overtime, TOIL and refused overtime show on Payroll.
date: 2026-11-10
tags: production, payroll, overtime, artisan
category: production
help_routes: grp.org.productions.show.artisans.daily_hours, grp.org.productions.show.artisans.payroll
---

<aside class="tldr">
On <b>Factory → Artisans → Daily hours</b>, press <b>Plan overtime</b> to enter the planned times for a day and approve them for one or several artisans, or tick days that went over the shift and press <b>Overtime for … selected</b>. Choose <b>Paid</b> or <b>Time off in lieu</b>. Saturday and Sunday overtime approved as paid is paid at 1.5 × Band 0; weekday overtime is paid as normal hours. Time off in lieu is not paid: it goes into the artisan's TOIL balance. Overtime nobody has approved or rejected is flagged on Payroll, and managers get a reminder on Monday morning, before the pay week locks at the end of Monday.
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

## The Monday reminder

On Monday morning the factory's managers get an email listing every day of last week whose overtime nobody has approved or rejected yet, with a link to those days on Daily hours. The pay week locks at the end of Monday; after that only the accountant can change it, with a reason.
