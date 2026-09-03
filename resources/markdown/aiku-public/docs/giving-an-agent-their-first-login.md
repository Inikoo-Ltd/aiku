---
title: Giving an agent their first login
summary: For the buying company — how to create the one account a sourcing agent needs to get started in aiku, after which they look after their own people.
date: 2026-09-02
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 1
help_routes: grp.org.hr.employees.create
---

<aside class="tldr">
Agents are organisations in aiku, just like a shop, and their people log in exactly like your own staff. You create <b>one</b> person in the agent organisation with the <b>Organisation Administrator</b> position and a username and password. From then on that person adds their colleagues themselves; their side is in <a href="/docs/adding-staff-to-your-agent-organisation">adding staff to your agent organisation</a>.
</aside>

## How agent logins work

Every sourcing agent is an organisation of type *agent*. When someone from that organisation logs in, they see only their own organisation: the **Procurement** menu with the supplier purchase orders, stock deliveries and the shopping list board that concern them, an **HR** menu for their own people, and their settings. They never see your shops, your customers or the other agents.

Nobody has a login until somebody gives it to them, and the first one has to come from you. After that, ownership passes to the agent.

## Creating the first agent user

You need HR edit rights in the agent organisation; group administrators have them for every organisation.

1. Switch to the agent organisation with the organisation picker at the top of the page.
2. Go to **HR → Employees** and press **Create Employee**.
3. In **Employment**, fill in the compulsory fields. The **worker number** and **alias** only need to be unique inside that agent organisation, so the person's first name is fine for both. Set the state to **Working**.
4. In **Job**, under **Position**, pick **Organisation Administrator**. This is the one step that turns an ordinary employee into someone who can run the organisation, including adding and removing other people. Skip it and they will log in to an empty screen.
5. In **User credentials**, type the **username** they will log in with and a starting **password**. aiku forces them to choose a new password the first time they sign in, so this one only needs to survive until you have passed it on.
6. Save.

Send them the address of the app, the username and the starting password through whatever channel you already use with that agent. That is all they need.

## Agents who had a login in Aurora

Agents who already had a login in the old system keep their username and password. Their account was carried over with the Organisation Administrator position, and their first sign-in to aiku converts the old password silently. There is nothing for you to do for them.

## If the agent locks themselves out

You keep HR edit rights in the agent organisation, so you can always open the agent's employee from **HR → Employees**, go to their user and set a new password, or create a second administrator the same way as the first.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Create the first agent user:</b> organisation picker → the agent organisation → <b>HR → Employees</b> → <b>Create Employee</b> → Position <b>Organisation Administrator</b> → fill in <b>User credentials</b>.</li>
<li><b>Reset a locked-out agent:</b> the agent organisation → <b>HR → Employees</b> → the person → their user → <b>Edit</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Creating a user in an agent organisation needs <b>HR edit</b> rights in that organisation. Group administrators have them everywhere.</li>
</ul>
</aside>
