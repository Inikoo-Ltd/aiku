---
title: Changing what a user can see and do
summary: Permissions in aiku are job positions. Where to find a user's positions, how to add or remove one, what each grade means, and why some access comes bundled with another department.
date: 2026-09-02
tags: hr, sysadmin, permissions
category: hr
help_routes: grp.sysadmin.users, grp.org.hr.employees.edit
---

<aside class="tldr">
Nobody in aiku is given a permission directly. People are given <em>job positions</em> (Accounting worker, Warehouse supervisor, Organisation administrator...) and each position carries a fixed bundle of permissions. To change what someone can see, you change their positions. There are two doors to the same screen: <b>Sysadmin → Users</b> for any user in the group, and <b>HR → Employees</b> for an employee of your organisation. Menus rebuild the next time the person loads a page.
</aside>

## Two doors, one screen

**From Sysadmin.** Open **Sysadmin → Users**, click the username, press **Edit**, and open the **Permissions** tab. You can open this for any user in the group, whichever organisation they work in. You need to be able to see the Sysadmin menu to get here.

**From HR.** Open your organisation, go to **HR → Employees**, click the person, press **Edit** and scroll to **Job Positions (permissions)**. This is the same control, limited to the organisation you are in.

Either way, what you see is a list of departments with a grade to pick in each, and a **save** icon. Nothing changes until you press save.

## Reading the permissions screen

The screen has two parts.

**Group permissions** sit at the top. These apply everywhere, in every organisation:

- **Group admin** — sees and can do everything, in every organisation. Choosing it greys out every other option, because there is nothing left to grant.
- **Group sysadmin** — user accounts, the Sysadmin menu, system settings.
- **Group webmaster** — websites and web content across the group.
- **Supply Chain**, **Goods**, **Masters** — the shared catalogue and purchasing that sit above individual organisations. Masters has four grades: Manager, Clerk, Media and Viewer.

**Organisations** are listed underneath with a count of positions the person holds in each. Click an organisation name to expand it and you get its departments, one row per department:

| Department | Grades on offer |
|---|---|
| Org admin | Organisation Administrator — everything in this organisation |
| Human Resources | Supervisor, Worker |
| Accounting | Supervisor, Worker |
| Shop admin | Shop Administrator — everything in the chosen shops |
| Shopkeeping | Supervisor, Worker |
| Marketing | Supervisor, Worker |
| PPC | PPC |
| Customer Service | Supervisor, Worker, Viewer |
| Buyer | Buyer |
| Warehouse | Supervisor, Stock Controller |
| Goods out | Supervisor, Picker, Replenisher, Packer |
| Production | Supervisor, Worker |
| Fulfilment | Supervisor, Warehouse Clerk, Office Clerk |

Departments that belong to a shop, warehouse or fulfilment (Shopkeeping, Marketing, Customer Service, Warehouse, Goods out, Fulfilment...) ask you *which* shops or warehouses the position covers. Press **Show details** on the row to tick them (the button only appears when the organisation has more than one to choose from). A person can be Customer Service worker in one shop and nothing in another.

**Supervisor versus Worker.** A worker can see and edit the day-to-day records of the department. A supervisor has the same plus the department's management screens and settings. Pick one or the other; picking a supervisor grade replaces the worker grade in that department.

**Organisation Administrator** ticks every department in that organisation at once, so if you choose it, the other rows in that organisation stop mattering.

## Making a change

1. Open the user's permissions by either door above.
2. Expand the organisation.
3. Click the grade you want in the department row. Clicking a grade that is already selected removes it, so a department with nothing selected means no access to it.
4. For shop- or warehouse-scoped departments, press **Show details** and tick the shops or warehouses.
5. Press the **save** icon for that organisation. Group permissions have their own save icon at the top.

The person does not have to log out. Their menus are rebuilt the next time they load a page.

## Access that comes bundled with another department

Some positions include read-only sight of a neighbouring department, because the work needs it. The one people ask about most: **Accounting worker and Accounting supervisor can see Human Resources**, read-only. That is why the HR menu appears for finance staff who were never given an HR position. It is part of the Accounting position itself and cannot be switched off per person. Removing it means changing what the Accounting position contains, for everyone, which is a change to aiku rather than a setting.

Read-only Human Resources access on its own is not a position you can pick from the screen. If someone should only *view* HR, the choice today is Accounting worker or nothing.

## Checking who has access to something

To find out who can see a department across an organisation, the quickest route is **HR → Employees**, where each employee's positions are listed, and **Sysadmin → Users** for accounts that are not employees of your organisation. Remember that anyone with **Group admin** or **Organisation Administrator** has access without a department position showing.

<aside class="wayfinder">
<h3>Where to click in aiku</h3>
<ul>
<li><b>Sysadmin → Users → </b><i>username</i><b> → Edit → Permissions</b> — any user in the group.</li>
<li><i>Organisation</i><b> → HR → Employees → </b><i>name</i><b> → Edit → Job Positions (permissions)</b> — employees of your organisation.</li>
<li>In each organisation block: click the grade, <b>Show details</b> to pick shops or warehouses, then the <b>save</b> icon for that organisation.</li>
</ul>
</aside>

<aside class="wayfinder">
<h3>Permissions you need</h3>
<ul>
<li>The Sysadmin door needs the <b>Group sysadmin</b> position (or Group admin).</li>
<li>The HR door needs a <b>Human Resources</b> position, Worker or Supervisor, in that organisation (or Organisation Administrator).</li>
</ul>
</aside>
