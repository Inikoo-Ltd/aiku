---
title: Adding staff to your agent organisation
summary: For agent administrators — how to give your colleagues their own aiku login, choose what they may do, and close an account when someone leaves.
date: 2026-09-02
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 2
---

<aside class="tldr">
For the administrator of an agent organisation. Once you can log in, you no longer need the buying company to add people: you create your colleagues yourself in <b>HR → Employees</b>, give them a position that matches their job, and a username and password. The buying company's side, creating your own first account, is in <a href="/docs/giving-an-agent-their-first-login">giving an agent their first login</a>.
</aside>

## What your colleagues will see

Everyone in your organisation sees the same aiku as you, narrowed by their position: the **Procurement** menu with the supplier purchase orders, stock deliveries and the shopping list board, and, for administrators, the **HR** menu. Nobody in your organisation can see the buying company's shops or customers, or other agents.

## Adding a colleague

Open **HR → Employees** and press **Create Employee**. The form is one page; the parts that matter for you are:

- **Employment**: a **worker number** and an **alias**, both unique within your organisation (first names are fine), and the state **Working**.
- **Job → Position**: choose what the person may do. **Buyer** is enough for someone who works purchase orders and deliveries. Give **Organisation Administrator** only to people who should be able to add and remove colleagues, because it grants everything in the organisation.
- **User credentials**: leave this empty for someone who does not need to log in. Fill in a **username** and a **password** and they can sign in immediately; aiku asks them to pick their own password on first entry.

Save, and pass the username and starting password on to them.

## Changing what someone may do

Open the employee from **HR → Employees**, press **Edit** and change their **Position**. The change applies the next time they load a page.

## When someone leaves

Open their employee record, press **Edit** and change the state to **Left**. Then open their user from the employee's page, press **Edit** and switch off **Can login**. Changing the state alone keeps the door open.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Add a colleague:</b> <b>HR → Employees</b> → <b>Create Employee</b>.</li>
<li><b>Change what someone may do:</b> open the employee → <b>Edit</b> → <b>Position</b>.</li>
<li><b>Someone left:</b> open the employee → <b>Edit</b> → State <b>Left</b>, then the employee's user → <b>Edit</b> → <b>Can login</b> off.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>The <b>Organisation Administrator</b> position carries HR edit rights in your organisation, which is all of the above. Buyers cannot add or edit people.</li>
</ul>
</aside>
