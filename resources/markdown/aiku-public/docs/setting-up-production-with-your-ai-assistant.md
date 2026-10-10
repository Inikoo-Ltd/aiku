---
title: Setting up production with your AI assistant
summary: Ask the AI assistant you connect to aiku to create artefacts, raw materials and tasks, set unit costs, and give one artefact, a list or whole families their manufacture steps and ingredients. It always shows you the change first, and every change can be undone.
date: 2026-10-08
tags: production, crafts, ai
category: production
---

<aside class="tldr">
For whoever designs what the factory makes. Once an administrator switches it on for you, the AI assistant you connect to aiku can do the setup work of the <b>Crafts</b> pages for you, in plain words. It can create and edit <b>artefacts</b>, <b>raw materials</b> and <b>manufacture tasks</b>, set <b>unit costs</b>, and give artefacts their <b>recipe</b>: the steps in order, how much of each step one artefact counts as, the target per hour, and the raw materials each step uses. It can do this for one artefact, a list, or whole families at once. It always shows you what it is about to change and only saves after you say yes. Every change is logged with your words and can be undone.
</aside>

## What it is for

Setting up a new product line by hand means a lot of clicking: create the tasks, create the raw materials, create the artefacts, open each one, add each step, add each ingredient. With the assistant you describe the result instead:

> *"Give all the ACLB lip balms these steps: pour, label, pack. Packing is counted in boxes of six."*

The assistant works out which artefacts that means, shows you the plan, and after you confirm it makes exactly the same changes the Crafts pages would make, through the same rules. Open job orders that are not received yet pick up the new steps straight away, the same as when you change steps by hand.

It works in any assistant that can connect to aiku: Claude, ChatGPT or similar. Talk to it in any language. Codes such as `ACLB-01`, `POUR` or `RAWM-03` stay as they are.

## Before you start

**1. Ask an administrator to switch it on.** On your user account, under <b>Access</b>, they turn on <b>Can connect AI assistant</b> and then <b>Can set up artefacts, raw materials and recipes through their AI assistant</b>. Without the second switch the assistant can still read reports but cannot change anything in production. It will tell you so if you ask.

**2. You also need to be one of the people who set up that factory.** The switch alone is not enough. With it, the assistant changes a factory only for:

- **group admins**;
- **organisation admins** of the factory's organisation;
- people with a production position that can **edit** that factory;
- **shop admins and shopkeepers** of the shops in the factory's organisation (for awa: AROMA, ACAR, ACFE, ARFE and EZC), because they sell what it makes.

People with a production position that can only view the factory can still ask the assistant to *show* recipes and costs.

**3. Connect your assistant to aiku.** Add aiku as a connector in your assistant with the address `https://app.aiku.io/mcp/aiku` and sign in with your aiku account when asked. You do this once.

**4. Know your factory's code.** Every request needs to say which factory it is about, for example `awa`. If you name one that does not exist or that you cannot reach, the assistant answers with the list of factories you do have access to.

## How a conversation goes

Every change follows the same three steps:

1. **You ask** in your own words.
2. **The assistant shows you the plan**: which artefacts, which steps in which order, the numbers, the ingredients. If anything is missing (a task that does not exist yet, a family code that matches two families), it tells you before changing anything.
3. **You confirm** in your own words: *"yes, go ahead"*. Only then does it save. The assistant passes your request to aiku, and it is stored with the change so anyone can see later what was asked.

If you say *"no, PACK should be 0.25"*, it corrects the plan and asks again. Nothing is saved until you agree.

<aside class="tip">
Ask the assistant to <b>show before it changes</b> whenever you are unsure: <i>"Show me the current recipe of ACLB-01 first."</i> Reading never changes anything.
</aside>

## The words aiku uses

You will see these in the assistant's answers.

| Word | What it means | Example |
|---|---|---|
| **Artefact** | Something the factory makes. Each artefact belongs to a SKO in the warehouse. | `ACLB-01`, a lip balm tin |
| **Family** | A group of similar artefacts. | `ACLB`, A&C Lip balms |
| **Manufacture task** | A kind of work people record on the tablets. | `POUR` Pouring, `LABEL` Labelling (+ lids), `PACK` Packing |
| **Step** | A task placed in an artefact's recipe, with its position. | step 1 POUR, step 2 LABEL, step 3 PACK |
| **Units per artefact** | How much of a step one artefact counts as. | 1 for one tin; 0.1667 when packing is counted in boxes of six |
| **Target per hour** | How many units of that step one person should do in an hour. | POUR 216 tins, PACK 11 boxes |
| **Raw material** | What an artefact is made from, with its unit and unit cost. | `RAWM-03`, a wax in kilograms |
| **Quantity per artefact** | How much of a raw material one artefact uses, in the raw material's unit. | 0.0129 kg of wax per tin |
| **Materials cost** | The sum of quantity × unit cost of every raw material in the recipe, per artefact. | 0.0757 |

### Units per artefact, worked through

The tablets count work in the unit of the step. For lip balms, pourers and labellers count tins and packers count boxes of six. One tin is one unit of POUR and one unit of LABEL, but only one sixth of a box, so:

| Step | Units per artefact | Why |
|---|---|---|
| POUR | 1 | one tin poured |
| LABEL | 1 | one tin labelled |
| PACK | 0.1667 | one tin is 1/6 of a box of six |

A job order for 600 tins then asks for 600 POUR, 600 LABEL and 100 PACK. Up to six decimals are kept, so 0.1667 is stored exactly.

The **target per hour** is in the same unit as the step: PACK at 11 means 11 boxes an hour, not 11 tins.

## Looking things up

Reading is safe. Use it as much as you like.

> *"Show me the recipe and materials cost of ACLB-01 and ACLB-03 in awa."*

The assistant lists each artefact's steps with units per artefact, target per hour, each raw material with its quantity, unit cost and line cost, and the total materials cost.

> *"Which artefacts are in the ACLB family?"*

> *"List the manufacture tasks in awa."*

> *"Find raw materials with 'beeswax' in the description."*

> *"What does RAWM-03 cost now, and is it linked to a SKO?"*

> *"Show me the artefact ABB-05a: its SKO, family, batch size and shelf life."*

## Manufacture tasks

Tasks are shared by the whole factory, so create each one once and use it in as many recipes as you like.

**Create tasks**

> *"Create three manufacture tasks in awa: POUR 'Pouring', LABEL 'Labelling (+ lids)', PACK 'Packing'."*

**Rename or describe a task**

> *"Rename the task LABEL to 'Labelling and lidding' and add the description 'Label goes on before the lid'."*

**Switch a task off**

> *"Make the task OLDPACK inactive."*

An inactive task stays in the recipes that already use it. To take it out of those recipes, change their steps (see below).

## Raw materials

**Create one**

> *"Create a raw material RAWM-90 in awa: type stock, description 'Shea butter', unit kilogram, unit cost 6.40."*

The type is one of *stock*, *consumable* or *intermediate*. The unit is one of *unit*, *pack*, *carton*, *liter* or *kilogram*. Costs are in your organisation's currency, per unit.

**Change a cost**

> *"Shea butter RAWM-90 now costs 6.85 per kilo."*

**Link a raw material to its SKO**

> *"Link RAWM-90 to the SKO SHEA-25."*

Once a raw material is linked to a SKO, its unit cost comes from the preferred supplier of that SKO and follows every supplier price change on its own. The assistant then refuses to set a cost by hand, because it would be overwritten. Change the supplier's cost instead, or unlink the SKO first if the cost really should be set by hand.

**Correct a description or unit**

> *"RAWM-05 is in grams on the sheet but aiku has kilogram. Keep kilogram, and change the description to 'Calendula oil (kg)'."*

## Artefacts

### A new artefact whose SKO already exists

> *"Create artefact ACLB-14 'Lip balm Mango' in awa, family ACLB, linked to SKO ACLB-14, batch size 120, shelf life 730 days."*

The trade unit is taken from the SKO automatically when the SKO has exactly one.

### A new artefact with a new SKO

An artefact always needs its SKO. One created without it leaves a half-made artefact that later gets in the way of the real one. If the SKO does not exist yet, ask the assistant to create it at the same time:

> *"Create artefact ACLB-15 'Lip balm Cherry', family ACLB, and create its SKO too: one tin per SKO."*

The assistant creates, in one go:

- the **stock** and its **trade unit**, both with the artefact's code, for the whole group;
- the **SKO** in your organisation;
- the **artefact**, linked to both.

The number of units per SKO is the one thing it needs from you. One tin per SKO is *units 1*. A SKO that is a box of six tins is *units 6*.

### Editing artefacts

> *"Set the batch size of ACLB-01 to 240."*

> *"Shelf life of ABB-01a is 540 days."*

> *"Move ACLB-14 to the family ACLB-NEW."*

> *"Mark ACLB-08 as discontinued."*

The state is one of *in_process*, *active*, *dormant* or *discontinued*.

<aside class="tip">
For the same simple change on many artefacts at once (batch size, shelf life, state, family), the bar on the artefacts list is often quicker. See <a href="/docs/changing-many-artefacts-at-once">Changing many artefacts at once</a>. The assistant is strongest at recipes, where each artefact needs several steps and ingredients.
</aside>

## Recipes: steps and ingredients

A recipe change always **replaces the whole recipe** of the artefacts you name:

- steps you give are added, or updated if the artefact already has them;
- steps the artefact has that are **not** in your list are **removed, together with their raw materials**;
- every step must say its units per artefact and its target per hour (or "no target"). The assistant copies the current values for steps you are not changing, so nothing is reset by accident.

### One artefact

> *"Give ACLB-01 these steps: 1 POUR, 1 per tin, target 216 an hour; 2 LABEL, 1 per tin, target 236; 3 PACK, counted in boxes of six, target 11 boxes."*

### A list of artefacts

This is the request that set up the lip balm line:

> *"For ACLB-01, ACLB-03, ACLB-04, ACLB-05, ACLB-06, ACLB-07, ACLB-08_, ACLB-09, ACLB-10_, ACLB-12_ and ACLB-13_: remove the step PROD Making and add POUR 1 per artefact at 216 an hour, LABEL 1 at 236, PACK 0.1667 at 11 boxes of six."*

The assistant shows the eleven artefacts with their new steps:

| Artefact | Step 1 | Step 2 | Step 3 |
|---|---|---|---|
| ACLB-01 | POUR ×1 @ 216/h | LABEL ×1 @ 236/h | PACK ×0.1667 @ 11/h |
| ACLB-03 | POUR ×1 @ 216/h | LABEL ×1 @ 236/h | PACK ×0.1667 @ 11/h |
| … | … | … | … |
| ACLB-13_ | POUR ×1 @ 216/h | LABEL ×1 @ 236/h | PACK ×0.1667 @ 11/h |

After your yes it saves all eleven at once. You do not have to name PROD: it goes because it is not in the new list. But see the warning about raw materials below.

### Whole families at once

You do not need to list the artefacts. Name the family:

> *"Give every artefact in the family ABB these steps: MIX 1 at 40 an hour, MOULD 1 at 120, WRAP 1 at 200, PACK counted in boxes of twelve at 9."*

The assistant takes every artefact in the family that is **not discontinued**, and tells you how many that is and which ones before saving.

**Several families**

> *"Same steps for the families ABB and ABBL."*

**A family except some artefacts**

> *"The whole ACLB family except ACLB-13_, which is made differently."*

**A family plus a few artefacts from elsewhere**

> *"The ABB family and also ABBTH-01 and ABBTH-02."*

**Families that share a code**

Two families can have the same code. In awa, *ACLB* is both the retail lip balms and the testers. The assistant then stops and asks which one you mean, showing each with its name, number of artefacts and first artefact code, for example:

- `agnes-cat-aclb`: A&C Lip balms, 11 artefacts starting ACLB-01
- `washes-lotions-aclb`: A&C Lip balms, 11 artefacts starting TACLB-01

Answer with the one you want (*"the one starting ACLB-01"*) and it carries on with the right family. Nothing is changed while it is unclear.

Up to 200 artefacts can be done in one go. For more, do one family at a time.

### Steps with ingredients

Raw materials belong to a step: the wax is used when pouring, the box when packing. Give each step its ingredients with the quantity **per artefact**:

> *"For the ACLB family: POUR uses RAWM-03 0.0129 kg and RAWM-05 0.0016 kg; LABEL uses BOKG-03 0.0003 and FLKG-06 0.0002; PACK uses CST-427 0.1667 (one box per six tins). Keep the same units and targets."*

The assistant shows the materials cost each artefact will have, so you can check it before saying yes.

### Similar artefacts with one different ingredient

Flavours, scents and colours usually share every step and differ in one ingredient. Say what is common, then what differs:

> *"Whole ACLB family: POUR uses wax RAWM-03 0.0129 and oil RAWM-05 0.0016. Except ACLB-01, which uses RAWM-06 0.0006 instead of RAWM-05, and ACLB-07, which uses RAWM-07 0.0016 instead of RAWM-05. LABEL and PACK as before."*

The assistant gives everybody the common list. For ACLB-01 and ACLB-07 it uses their own complete list for that step: the wax plus their own oil. The plan it shows has one line per artefact, so you can see that ACLB-01 has RAWM-06 and not RAWM-05.

<aside class="tip">
When the differences are large (a different number of steps, other tasks), do those artefacts in a separate request, or leave them out of the family request with <i>except</i>.
</aside>

### Changing one number

Because a recipe is always replaced as a whole, a small change still sends every step. The assistant does that for you: it reads the current recipe and changes only what you asked.

> *"Raise the PACK target of the ACLB family to 12 boxes an hour, everything else as it is."*

> *"In ACLB-05, POUR uses 0.0135 of RAWM-03 instead of 0.0129."*

Check the plan it shows. Every other number should be the same as before.

### Adding a step in the middle

> *"Add a step CURE between POUR and LABEL for the whole ACLB family: 1 per tin, no target."*

The assistant renumbers the steps 1 POUR, 2 CURE, 3 LABEL, 4 PACK.

### Removing a step

> *"Take LABEL out of ACLB-12_, keep the rest."*

LABEL and its raw materials are removed from ACLB-12_. The task itself stays in the factory for other recipes.

### Replacing a step that has ingredients

<aside class="warning">
When a step is removed, the raw materials on it are removed too. Many artefacts brought over from the old system carry their whole ingredient list on a single <b>PROD Making</b> step. In awa, for example, seven of the ACLB lip balms have six raw materials on PROD. Replacing PROD with POUR, LABEL and PACK without saying where those six go would leave those artefacts with <b>no ingredients</b>, so stock would no longer be used up when their job orders are received.
</aside>

Before replacing such a step, ask:

> *"Show me the raw materials on the PROD step of the ACLB family."*

Then say where each one goes in the same request as the new steps:

> *"Replace PROD in the ACLB family with POUR, LABEL and PACK as above. Move the raw materials: RAWM-03, RAWM-05 and RAWM-06 to POUR, BOKG-03 and FLKG-06 to LABEL, CST-427 to PACK, same quantities."*

If you really want to drop the ingredients and add them later, say so: *"drop the ingredients, I will add the recipe later"*. If you change your mind, the change can be undone (see below).

## Safety checks

Some changes are easy to get wrong by accident, so aiku stops them until you have seen what they do. The assistant then shows you a warning and changes nothing. If you read it and still want the change, say so (*"yes, I know, go ahead"*) and only then does it save.

| aiku stops and warns when… | Example | What to check |
|---|---|---|
| a recipe change would **remove raw materials** from artefacts | replacing PROD on ACLB-01 drops RAWM-03, RAWM-05, RAWM-06, BOKG-03, FLKG-06 and CST-427 | Did you mean to drop them, or should they move to the new steps? |
| a **whole family** is changed | *"the ACLB family"* turns out to be 11 artefacts, listed one by one | Is that the right family, and are the testers left out? |
| a number looks like a **typo** | units per artefact 1667 instead of 0.1667; a target of 2,160 an hour; 500 kg of a raw material per artefact | Is the decimal point in the right place? |
| a **unit cost** moves by more than half | RAWM-90 from 6.40 to 64.00 | Per kilo or per gram? |
| an artefact is **renamed** | ACLB-14 becomes ACLB-14M | Labels and sheets still carry the old code. |
| an artefact still in **open job orders** is made dormant or discontinued | ACLB-05 is in two open job orders | Those job orders are not cancelled by this. |

One mistake is always refused: giving an artefact a **SKO that another artefact already has**. One SKO belongs to one artefact. That is how a half-made ACLB-08 ended up next to the real ACLB-08_. Edit the artefact that already has the SKO instead.

## Undoing a change

Every change the assistant makes is logged: who asked, when, your exact words, and what it was before and after.

> *"Undo the last change you made."*

> *"What did I change in production today?"*

> *"Revert the recipe change on the ABB family from this morning."*

The assistant lists the changes and puts back how things were, after you confirm.

Undo is refused in two cases:

- **Something was changed again since.** If someone edited the same artefact afterwards, by hand or through an assistant, undo stops rather than overwrite their work. Look at it and fix it by hand.
- **The change created something.** A new artefact, raw material, task or SKO is not deleted by undo, because job orders, recipes and stock may already hang off it. Set it as discontinued or inactive instead.

You can undo changes made in factories you are allowed to set up. Administrators can see and undo every change from the AI changes log.

## Asking well

| Instead of | Say | Why |
|---|---|---|
| *"set up the lip balms"* | *"set up the ACLB family in awa"* | a code leaves nothing to guess |
| *"packing is 6"* | *"packing is counted in boxes of six"* | 6 per artefact and 1/6 per artefact are very different |
| *"add pour"* | *"add POUR as step 1, 1 per tin, 216 an hour, keep the other steps"* | a recipe is replaced as a whole |
| *"use the wax"* | *"use RAWM-03, 0.0129 kg per tin"* | the quantity is per artefact, in the raw material's unit |
| *"yes"* to a long plan you did not read | read the table, then *"yes"* | the plan is what will be saved |

Good habits:

- **Read before you write.** *"Show me the recipe first"* costs nothing.
- **One family per request** when families need different steps.
- **Check the count.** If the assistant says 22 artefacts and you expected 11, a code probably matched the testers too.
- **Check the materials cost** it shows. A cost ten times higher than its neighbours usually means a quantity in grams where the unit is kilograms.

## When the assistant says no

| What it says | What to do |
|---|---|
| Setting up production is not enabled for this user | Ask an administrator to switch on the production setting on your account. |
| This user cannot set up production … | You are not one of the people who set up this factory (see *Before you start*). Ask an administrator. |
| There is no task / raw material … | Create it first: *"create the task CURE"*, then repeat the request. |
| More than one family is coded … | Pick the family it lists, by its first artefact or its name. |
| A new artefact needs its SKO | Name the existing SKO, or ask it to create the SKO with the artefact and say how many units per SKO. |
| … is linked to a SKO, so its unit cost comes from the preferred supplier | Change the supplier product's cost, or unlink the SKO first. |
| A raw material is listed twice in step … | Give it once with the quantities added up. |
| That is … artefacts; do at most 200 per call | Do one family at a time. |
| This was changed again after the AI change | Someone edited it since. Fix it by hand on the Crafts pages. |

## What it does not do

- It does not **delete** artefacts, raw materials or tasks. Discontinue or deactivate them instead.
- It does not change **pay bands** or the factory's reward tiers. Targets per hour are the base those tiers multiply.
- It does not create **job orders** or change what is being made today. It changes what the next and open job orders ask for.
- It does not touch **testers** or any other artefact you did not name or include through a family.
- It cannot see or change factories you have no access to.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Switch it on for someone (administrators):</b> <b>Sysadmin → Users</b> → open the user → <b>Edit</b> → <b>Access</b> → switch on <b>Can connect AI assistant</b>, then <b>Can set up artefacts, raw materials and recipes through their AI assistant</b>.</li>
<li><b>See every change an assistant made (administrators):</b> <b>Sysadmin</b> → the <b>AI insights</b> box → <b>All queries & per-user stats</b> → <b>AI changes</b>. Filter by type <b>Artefact recipe</b> or <b>Artefact, raw material or task</b>.</li>
<li><b>Check a recipe by hand:</b> your organisation → <b>Factory</b> → <b>Crafts</b> → <b>All artefacts</b> → open the artefact → <b>Manufacture tasks</b> tab.</li>
<li><b>The same changes without the assistant:</b> <a href="/docs/changing-many-artefacts-at-once">Changing many artefacts at once</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>The switch <b>Can set up artefacts, raw materials and recipes through their AI assistant</b> on your account.</li>
<li>To change anything: group admin, organisation admin, a production position that can edit that factory, or shop admin or shopkeeper in one of the shops of the factory's organisation.</li>
<li>To only look at recipes and costs: a production position that can view that factory.</li>
</ul>
</aside>
