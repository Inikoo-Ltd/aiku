---
title: Setting up a new employee
summary: Add a new starter to aiku — their personal details, employment terms and job positions — and, if they need it, give them their own login in the same form.
date: 2026-08-31
tags: hr
category: hr
help_routes: grp.org.hr.employees.index, grp.org.hr.employees.create, grp.org.hr.employees.show, grp.org.hr.employees.edit
---

<aside class="tldr">
Every person who works in your organisation — on the payroll or not — gets an <em>employee</em> record in aiku. You create it once, in <b>HR → Employees → Create Employee</b>, and everything else hangs off it: their contract, their timesheets, their clocking PIN, and (if you fill in the last section of the form) the username and password they will use to sign in to aiku itself.
</aside>

## Before you start

Have three things ready: the person's full name, a **worker number** (your own internal staff reference — it has to be unique within the organisation), and an **alias** — a short nickname the rest of aiku uses to refer to them, so pick something recognisable like `maria` or `j.smith`. If they will sign in to aiku, also decide their username and a starting password.

## Creating the employee

Open your organisation, go to **HR → Employees**, and press **Create Employee**. The form is one page in five sections; only a handful of fields are compulsory, and you can come back later to fill in the rest through **Edit**.

### Personal information

Only the **Name** is required here. The rest — date of birth, personal email, phone, home address, emergency contact, identity document, and free-text notes — is worth capturing while the paperwork is in front of you, but nothing stops you saving without it.

### Employment

This section is where the required fields live:

- **Worker Type** — is this person an *employee*, a *volunteer*, or a *temporal worker*? This is about who they are to the organisation, not their hours.
- **Employment Type** — *full time* or *part time*.
- **Worker number** and **Alias** — the unique references described above.
- **Work email** — their company address, if they have one. If you give them a login below, this becomes the email on their user account.
- **State** — choose **Hired** for someone who has signed but starts on a future date, or **Working** for someone already on the job. (Later in their life the record can move to *Leaving* and finally *Left* — leavers are never deleted, so their history stays intact.)
- **Employment start at** — their first day.

### Job

- **Job Title** — free text; the field suggests titles already used elsewhere so your naming stays consistent.
- **Position** — this is the important one. Positions are the roles from your organisation's job-position list, and they are what decide **what the person can see and do in aiku**. Some positions apply to the whole organisation; others are scoped, so you pick *which* shops, fulfilments or warehouses the role covers — a shop supervisor for one shop only, a picker in one warehouse but not another. A person can hold several positions at once. If they will sign in, choose these carefully: their aiku menus are built from them.

### Contract

Optional: a contract start and end date plus their **annual leave days**. If you give a contract start date, aiku files a proper contract record for them, which you can find later under the employee's **Contracts** tab — along with any future contracts as terms change.

### User credentials

Leave this section alone and the person exists in HR but has no login — right for warehouse or shop-floor staff who only ever touch a clocking machine. Fill in a **Username** and **Password** and aiku creates their user account at the same moment it creates the employee. The account picks up their name and work email automatically, and the first time they sign in aiku makes them choose a new password of their own — so the one you type here is only a door-opener, not a secret to protect forever.

## What happens when you save

Pressing save drops you on the new employee's page, and a few things have already happened behind the scenes:

- They appear in the **HR → Employees** list, in your headcount numbers, and in the job-position lists for whatever roles you gave them.
- If their state is **Working**, aiku has already issued them a **clocking PIN**, so they can clock in and out at a clocking machine from day one. You can see the PIN — or issue a fresh one — from their employee page.
- Their **timesheets** begin collecting as soon as they start clocking, under the employee's **Timesheets** tab.
- If you created a login, they can sign in straight away with the username and password you set (changing the password on first entry), seeing exactly what their positions allow.

Anything you skipped — address, contract dates, extra positions — lives one click away under **Edit** on their page.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Add someone:</b> your organisation → <b>HR → Employees</b> → <b>Create Employee</b>.</li>
<li><b>Fix or finish later:</b> open the employee → <b>Edit</b>. Contracts, timesheets and positions have their own tabs on the employee's page.</li>
<li><b>Clocking PIN:</b> on the employee's page — view it or regenerate it there.</li>
<li><b>Many starters at once:</b> the Employees list also offers a spreadsheet template you can download, fill and upload.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Creating and editing employees needs <b>HR edit</b> rights in the organisation — typically the HR role or an organisation admin.</li>
</ul>
</aside>
