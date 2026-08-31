---
title: Setting up a clocking machine
summary: Create a clocking machine in a few clicks, put its kiosk link on a tablet or its QR codes on the wall, and know where every clock-in ends up.
date: 2026-08-31
tags: hr, clocking
help_routes: grp.org.hr.clocking_machines, grp.org.hr.workplaces.show.clocking_machines
series: Clocking in and out
order: 2
---

<aside class="tldr">
Creating a machine takes a name, a type and a workplace — that's the whole form. The real setup is the step after: for <b>PIN</b>, <b>Barcode Scanner</b> and <b>Camera QR</b> machines you generate a <b>kiosk link</b> and open it on the tablet by the door; for a <b>QR Code</b> machine you generate printable QR codes and stick them on the wall. Every clocking then shows up under the machine, the workplace, and the employee's own timesheet. Not sure which type you want? Read <a href="/docs/types-of-clocking-machines">the types guide</a> first.
</aside>

## Before you start

A clocking machine always belongs to a **workplace** — the physical site whose door it guards. If your organisation has no workplace yet, create one first under **Human Resources → Working place**; the machine form will not let you skip it. If there is exactly one workplace, aiku pre-selects it for you.

## Creating the machine

Go to **Human Resources → Clocking machines** and press the **Clocking machine** create button. The form asks for three things:

- **Name** — what your team calls it: "Warehouse door", "Office front", "Packing hall tablet". Names must be unique within the organisation.
- **Type** — pick one of the four everyday types: **QR Code**, **PIN**, **Barcode Scanner** or **Camera QR Scanner**.
- **Workplace** — the site it lives in.

Save, and the machine appears in the list, already active. That is genuinely all — the machine's identity codes are generated for you behind the scenes.

## Connecting the device

What happens next depends on the type you chose.

### PIN, Barcode Scanner and Camera QR: the kiosk link

These three run on a shared tablet, and the tablet gets there through a **kiosk link**. In the machine's row on the clocking machines list, press the small **tablet** button, then **Generate link**. aiku creates a private web address just for this machine; **Copy** it and open it in the browser on the tablet you are leaving by the door.

The kiosk page needs no login — the secret is in the link itself — and it shows exactly one thing:

- a **keypad** on a PIN machine,
- a **scan field** on a Barcode Scanner machine (plug the barcode scanner into the tablet),
- the **camera view** on a Camera QR machine.

Staff walk up, type or scan, and see an immediate clocked-in / clocked-out confirmation. Two housekeeping notes: the **Regenerate** button replaces the link — the old one stops working at once, which is exactly what you want if a tablet goes missing — and the list's **Kiosk** column shows at a glance whether each machine's kiosk method is switched on.

Each employee's PIN, barcode and QR live on their own **Employee Clocking** page in aiku, so there is nothing for you to print or hand out unless you want badge cards.

### QR Code machines: print and stick

A QR Code machine has no tablet at all — the wall does the work. Open the machine and use **Generate QR code**: give the code a **label** ("Main entrance", "Fire door") and aiku produces a QR image you can print. Generate as many as you have doors; the machine's QR code list shows each one with its label and an **active** switch.

An employee scans the printed code with their phone camera, lands on their Employee Clocking page in aiku, and clocks in or out as themselves. If a printed code is compromised — photographed, shared, taken home — you can switch it off or **regenerate** it, and the old printout politely refuses: *"This QR Code is no longer active."*

On the machine's **edit** page a QR Code machine gains extra settings: **Allow Coordinates Matching** turns on the location check, the **map picker** lets you drop a pin on your building, and **Radius (meters)** says how close the phone must be. A QR Code machine also gets a **Clocking policies** tab, where onsite / remote / hybrid rules can be set for the people who use it.

## Where the clockings appear

Every clock-in and clock-out, whatever the method, becomes a **clocking** record you can see from three angles:

- **The machine:** open the machine → **Clockings** tab — everything this device recorded.
- **The workplace:** open the workplace → its clockings list — everything recorded on site, across all its machines.
- **The person:** the clocking pairs up into the employee's **timesheet**, which is where worked hours come from.

HR can also open any single clocking to review or correct it — useful for the classic "I forgot to clock out" conversation.

## Day-to-day management

The machine's **edit** page holds the ongoing controls: rename it, flip its **status** between Connected and Disconnected (a disconnected machine stays in the list but is resting), and enable or disable each clocking method. A machine that has served its time can be deleted from the list with the usual confirmation — its recorded clockings stay on the timesheets, because history is history.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Create a machine:</b> your organisation → <b>Human Resources → Clocking machines</b> → <b>Clocking machine</b> create button. Or from a site: <b>Working place</b> → your workplace → <b>Clocking machines</b> → create.</li>
<li><b>Get the tablet link:</b> in the machine's row, the <b>tablet</b> button → <b>Generate link</b> → <b>Copy</b> (PIN, Barcode and Camera QR machines only).</li>
<li><b>Print QR codes:</b> open a QR Code machine → <b>Generate QR code</b>, give it a label; manage labels and the active switch in its QR code list.</li>
<li><b>Location check:</b> the machine's <b>edit</b> page → QR Settings → <b>Allow Coordinates Matching</b>, map pin and radius.</li>
<li><b>See clockings:</b> the machine's <b>Clockings</b> tab, the workplace's clockings list, or the employee's timesheet.</li>
</ul>
<strong>Permissions you need</strong>
<ul>
<li>Creating, editing and generating kiosk links or QR codes needs Human Resources <b>edit</b> access; viewing machines and clockings needs Human Resources <b>view</b> access.</li>
</ul>
</aside>
