---
title: Starostlivosť o zákazníkov
summary: Zorientujte sa v zozname zákazníkov obchodu, pridajte nového zákazníka, prečítajte si stránku zákazníka a pochopte stavy, prihlásenia a prospektov, ktoré ju obklopujú.
date: 2026-09-01
source_date: 2026-09-01
tags: crm, customers
category: crm
---

<aside class="tldr">
Každý obchod si drží vlastný zoznam <b>Customers</b>, dostupný zo sekcie **CRM** obchodu. Odtiaľ môžete vytvoriť zákazníka, otvoriť jeho stránku a vidieť o ňom všetko, spravovať prihlásenia, ktoré používa na webe, a — samostatne — viesť zoznam <b>Prospects</b> (prospektov), ktorí sa ešte nestali zákazníkmi.
</aside>

## Zoznam zákazníkov

Otvorte obchod a prejdite na **CRM → Customers**. Zoznam zobrazuje každého zákazníka daného obchodu, so stĺpcami **Ref**, **Name**, dátum pridania (**Since**), dátum **Last Invoice**, počet **Invoices** a **Sales**. Zoznam môžete vyhľadávať a zoraďovať podľa ktoréhokoľvek z týchto stĺpcov.

Zoznam môžete filtrovať pomocou globálneho vyhľadávacieho poľa (zodpovedá menám a poštovým smerovacím číslam, ak ich zadáte), podľa **Tag**, podľa **Country** a podľa toho, či zákazník niekedy zadal objednávku.

## Pridanie zákazníka

Stlačte **Create Customer** v zozname. Formulár je krátky a nachádza sa v jednej sekcii, **Contact**:

- **Company** — názov spoločnosti zákazníka, ak nejakú má.
- **Contact name** — povinné. Osoba, s ktorou komunikujete.
- **Email**
- **Phone**
- **Address** — kompletný formulár adresy, predvyplnený krajinou samotného obchodu.
- **Tax number**

Uložením formulára sa zákazník vytvorí a prejdete na jeho stránku.

## Stránka zákazníka

Otvorením zákazníka zo zoznamu sa dostanete na jeho stránku, ktorá je usporiadaná do záložiek:

- **Overview** — súhrnný prehľad zákazníka.
- **Timeline** — história aktivity.
- **Journey** — cesta zákazníka obchodom.
- **History** — auditná stopa zmien.
- **Attachments** — súbory priložené k zákazníkovi.
- **Payments**
- **Credit transactions**
- **Favourites** — produkty, ktoré si zákazník obľúbil.
- **Reminders**
- **Dispatched emails** — e-maily, ktoré mu aiku odoslalo.
- **Offers**

Odtiaľto sa tiež dostanete k objednávkam, faktúram, dodacím listom, vráteniam, náhradám a nadchádzajúcim transakciám zákazníka — každá má svoju vlastnú obrazovku prepojenú zo stránky zákazníka.

## Stavy a status zákazníka

Každý zákazník nesie dve samostatné hodnoty, obe zobrazené ako farebné odznaky.

**State** sleduje, kde sa zákazník nachádza vo vzťahu s obchodom:

- **In Process** — stále sa nastavuje.
- **Registered** — má účet, ale ešte sa nestal pravidelným zákazníkom.
- **Active** — momentálne nakupuje.
- **Potential Comebacks** — kedysi nakupoval, teraz je ticho, ale mohol by sa vrátiť.
- **Dormant** — dlho nenakúpil.

**Status** sleduje schválenie:

- **Pre Registration**
- **Pending Approval**
- **Approved**
- **Rejected**
- **Banned**

Zákazník zvyčajne musí byť **Approved**, aby mohol bežne obchodovať — je to samostatné rozhodnutie od jeho state.

## Web users: webové prihlásenia zákazníka

Zákazník môže mať viac než jedno prihlásenie na webovú stránku obchodu — užitočné, keď potrebuje vlastný účet viac ľudí z tej istej spoločnosti. Spravujete ich zo zákazníckej obrazovky **Web Users**.

Každý web user má:

- **Type** — Customer alebo API user.
- **Username** — musí byť unikátne na danej webovej stránke obchodu.
- **Admin** — prepínač označujúci toto prihlásenie ako administrátorské pre zákazníka.
- **Password**

Nové pridáte stlačením **Create Web User** v zozname; otvorením web usera sa jeho username zobrazí ako názov stránky a umožní vám upraviť jeho detaily.

## Adresy

Zákazník si drží kontaktnú adresu (používanú na korešpondenciu a daňové účely) a môže mať doručovacie adresy používané na jeho objednávkach, obe zachytené ako kompletné formuláre adries — krajina, PSČ a ostatné — vo vlastnom zázname zákazníka aj pri vytváraní či úprave zákazníka.

## Prospekti: ľudia, ktorí ešte nie sú zákazníkmi

Prospekti sú samostatný zoznam od zákazníkov, vedený pod **CRM → Prospects** v tom istom obchode. Prospekt prechádza vlastnými stavmi, ako s ním pracujete:

- **No contacted**
- **Contacted**
- **Fail**
- **Success**

Prospekti majú vlastné tlačidlo **Create Prospect**, vlastný export a vlastné hromadné mailshoty na oslovenie. Keď sa prospekt stane skutočným zákazníkom, aiku ich spáruje namiesto toho, aby po nich zostali dva samostatné záznamy.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Vidieť alebo pridať zákazníkov:</b> váš obchod → <b>CRM → Customers</b> → <b>Create Customer</b>.</li>
<li><b>Otvoriť zákazníka:</b> kliknite na jeho riadok, aby ste videli záložky Overview, Timeline, Journey, History, Attachments, Payments, Credit transactions, Favourites, Reminders, Dispatched emails a Offers.</li>
<li><b>Spravovať jeho webové prihlásenia:</b> na stránke zákazníka otvorte <b>Web Users</b> → <b>Create Web User</b>.</li>
<li><b>Pracovať s prospektmi:</b> váš obchod → <b>CRM → Prospects</b> → <b>Create Prospect</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Aké oprávnenia potrebujete</strong>
Na zobrazenie zákazníkov potrebujete CRM prístup na zobrazenie pre daný obchod; na vytvorenie alebo úpravu zákazníka, jeho adresy alebo web usera potrebujete CRM prístup na úpravu pre daný obchod. Prospekti majú vlastné oprávnenia na zobrazenie a úpravu, oddelené od zákazníkov.
</aside>
