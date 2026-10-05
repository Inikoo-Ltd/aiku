---
title: Starostlivosť o zákazníkov
summary: Zorientujte sa v zozname zákazníkov obchodu, pridajte nového zákazníka, prečítajte si stránku zákazníka a pochopte stavy, prihlásenia a prospektov, ktoré ju obklopujú.
date: 2026-09-29
source_date: 2026-09-29
tags: crm, customers
category: crm
---

<aside class="tldr">
Každý obchod si drží vlastný zoznam <b>Customers</b>, dostupný zo sekcie **CRM** obchodu. Odtiaľ môžete vytvoriť zákazníka, otvoriť jeho stránku a vidieť o ňom všetko, spravovať prihlásenia, ktoré používa na webe, a — samostatne — viesť zoznam <b>Prospects</b> (prospektov), ktorí sa ešte nestali zákazníkmi.
</aside>

## Zoznam zákazníkov

Otvorte obchod a prejdite na **CRM → Customers**. Zoznam zobrazuje každého zákazníka daného obchodu, so stĺpcami **Ref**, **Name**, dátum pridania (**Since**), dátum **Last Invoice**, odhadovaný dátum **Next order** (ďalšia objednávka), počet **Invoices** a **Sales**. Zoznam môžete vyhľadávať a zoraďovať podľa ktoréhokoľvek z týchto stĺpcov.

Zoznam môžete filtrovať pomocou globálneho vyhľadávacieho poľa (zodpovedá menám a poštovým smerovacím číslam, ak ich zadáte), podľa **Tag**, podľa **Country** a podľa toho, či zákazník niekedy zadal objednávku. Panel filtrov nad zoznamom pridáva ďalšie, vrátane **Due to Reorder** (na dokúpenie).

## Zákazníci na dokúpenie

Pre každého zákazníka s aspoň dvoma objednávkami, fakturovanými za posledný rok, si aiku vypočíta, koľko dní si zvyčajne necháva medzi objednávkami: počet dní od prvej po poslednú faktúru vydelený počtom medzier medzi nimi. Viacero faktúr v ten istý deň sa počíta ako jedna objednávka a dobropisy nie sú objednávky.

Ich odhadovaný **Next order** je dátum poslednej faktúry plus tento zvyčajný odstup. Zobrazuje sa v zozname zákazníkov aj na **Overview** zákazníka a prepočítava sa znova každú noc a vždy, keď je zákazníkovi vystavená faktúra.

Zákazník je **due to reorder** (na dokúpenie), keď tento dátum pripadá na najbližších 7 dní, alebo už uplynul, ale o menej než jeden ich zvyčajný odstup. Zákazník, ktorému uplynulo viac, sa skôr vzďaľuje, než že by bol na dokúpenie. Zákazník, ktorý už má podanú objednávku na ceste skladom, sa už dokúpil, takže na dokúpenie nie je. Filter **Due to Reorder** v zozname zákazníkov zobrazí tých na dokúpenie, zoradenie podľa **Next order** ukáže, kto je na rade prvý, a ten istý filter môžete použiť aj na výber príjemcov mailshotu.

## Pripomienkové e-maily na dokúpenie

Obchod môže zákazníkom automaticky posielať e-mail aj vtedy, keď sú na dokúpenie. V sekcii **Comms** obchodu otvorte **Push** a potom outbox **Due to reorder reminder**. Tam e-mail navrhnite a nastavte ho ako aktívny. Odosiela sa raz denne, popoludní.

V e-maile **[Products]** zobrazuje až päť vlastných opakovane kupovaných produktov zákazníka, tých, ktoré má na dokúpenie ako prvé. Každý má obrázok, odkaz na svoju stránku na webe, množstvo, ktoré zákazník zvyčajne objednáva, a cenu. **[Last Invoice Date]** je dátum jeho poslednej faktúry. **Days before the expected next order** určuje, o koľko skôr sa e-mail odošle. Ak sa ponechá prázdne, odošle sa 7 dní pred odhadovaným **Next order** zákazníka.

Aby sa tomu istému zákazníkovi neposielalo viacero podobných e-mailov:

- Zákazník dostane tento e-mail len raz medzi dvoma svojimi faktúrami. Keď si znova objedná a je mu vystavená faktúra, ďalšia pripomienka počká, kým opäť nebude na dokúpenie.
- Zákazník, ktorý už má objednávku na ceste skladom, nie je na dokúpenie, takže sa mu e-mail neposiela.
- E-maily **Gold reward reminder** sa zákazníkom posielajú tiež po ich poslednej objednávke. Zákazník, ktorý dostal jeden z nich za posledných 7 dní, alebo má jeden naplánovaný na najbližších 7 dní, sa zatiaľ preskočí. Ak si ani po skončení Gold reward reminderov znova neobjedná, dostane vtedy pripomienku na dokúpenie.
- Zákazníkom, ktorí sa z e-mailu odhlásia, alebo ktorým je vypnutý odber **Reorder Reminders** v **Subscriptions** na ich **Overview**, sa už znova neposiela. Zákazníci si ho môžu vypnúť aj sami vo svojom účte na webe.

Outbox zobrazuje každý beh — koľko e-mailov bolo odoslaných, otvorených a preklikaných. Objednávky zadané po tom, čo zákazník klikne na odkaz v e-maile, sa v marketingových reportoch pripíšu tomuto e-mailu.

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
- **Reorders** (dokúpenia) — produkty, ktoré si zákazník kúpil aspoň v dvoch rôznych dňoch: koľkokrát, priemerné množstvo, priemerný počet dní medzi objednávkami daného produktu, kedy ho objednal naposledy a kedy si ho pravdepodobne objedná nabudúce. Produkty, ktoré má na dokúpenie, nesú označenie **Due**.
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
