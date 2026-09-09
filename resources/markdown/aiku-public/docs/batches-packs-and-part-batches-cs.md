---
title: Dávky, balení a částečné dávky
summary: Továrna vyrábí kusy v celých dávkách, sklad počítá balení. Co packed_in a velikost dávky dělají s výrobním příkazem, s tím, co přistane na regálu, a s tím, co by měl partner objednat.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, procurement, crafts
category: production
series: Ordering from partners
order: 12
---

<aside class="tldr">
U každého artefaktu se potkávají dva počty a nejsou stejné. Dílna pracuje v **units** (kusech) - jedna koupelová bomba, jeden kus mýdla - a smysluplně dokáže vyrobit jen celou **batch** (dávku), protože tolik pojme míchačka. Sklad a obchod pracují v **SKO**: balení, ve kterém se zboží prodává a skladuje, deset na krabici. **Packed in** (baleno po) je jediný most mezi oběma a aiku ho teď překonává na obou koncích, místo aby předstíralo, že jsou ta čísla zaměnitelná.
</aside>

## Tři čísla

- **Batch size** (velikost dávky) — kolik kusů továrna vyrobí najednou. Patří artefaktu, nastavuje se na jeho stránce nebo hromadně ze seznamu artefaktů, viz [Hromadná změna více artefaktů najednou](/docs/changing-many-artefacts-at-once).
- **Packed in** (baleno po) — kolik kusů jde do jednoho SKO. Patří SKO ve skladu.
- **Units a SKO** — dílna dostává zadání v kusech, všechno ostatní se počítá v SKO.

## Co se děje na každém konci

**Zadání výrobního příkazu.** Cokoli bylo požadováno — partnerský řádek, nedostatek u vlastního zákazníka, doplňovací řádek — je množství v SKO. aiku ho vynásobí *packed in*, aby získalo kusy, a pak zaokrouhlí **nahoru** na celou dávku. Požádáte-li o 1 SKO desetikusového balení vyráběného v dávkách po 16, řemeslník dostane zadání na 16 kusů, ne na 1 a ne na 10.

**Přijetí hotové zakázky.** Co řemeslník vyrobil, jsou kusy, a na cestě na regál se to dělí *packed in*. Těch 16 kusů desetikusového balení přistane jako 1,6 SKO: jedna zapečetěná krabice a šest volných kusů. Číslo na regálu je k tomuto zbytku upřímné, místo aby ho zaokrouhlilo pryč.

## Když dávka nezaplní celá balení

Dávka o 16 kusech a krabice o 10 nikdy nevyjdou přesně. Není to chyba a aiku to tak nebere: továrna nastavuje velikost dávky podle míchačky, obchod prodává v baleních, a obojí je správně. Stojí jen za to vědět, kde se to děje, a proto se to měří:

- sloupec **Batch in SKOs** (dávka v SKO) a filtr **Batch not whole SKOs** (dávka nedává celá SKO) v seznamu artefaktů;
- řádek na stránce artefaktu ukazující dávku v SKO a nejbližší velikost dávky, která by vyšla celá;
- statistika na crafts dashboardu počítající artefakty, u kterých k tomu dochází.

Nic vás nenutí velikost dávky měnit. Pokud je návrh snadné přijmout, přijměte ho a aritmetika přestane nechávat zbytky. Pokud dávku určuje míchačka, nechte to být.

## Co by měl partner objednat

Stejná aritmetika určuje nejúhlednější objednací množství, kterému aiku říká **order step** (objednávací krok): nejmenší počet SKO, který celé dávky zaplní přesně. Pro dávku o 16 kusech v baleních po 10 je to 8 SKO — osmdesát kusů, pět dávek, žádný zbytek.

Na straně partnera, v [nákupním seznamu](/docs/buying-from-a-partner-cs) a v seznamu skladových zásob:

- řádek uvádí *made in batches of N units* (vyrobeno v dávkách po N kusech) a, pokud se liší, *full batches every N SKO* (celé dávky po N SKO);
- malé tlačítko zaokrouhlí množství nahoru na nejbližší krok;
- množství **suggested** (navrženo) a vše, co navrhne Auto-fill, jsou už na kroku;
- objednávka mimo krok se stále přijímá, s poznámkou, že se stejně vyrobí celá dávka, takže objednávka může být zpožděná nebo se upraví množství.

Objednávka pod jeden celý krok nelze vyrobit samostatně vůbec. Na nástěnce [To produce](/docs/fulfilling-partner-orders-cs) čeká za *lines too small for a batch are waiting for company* (řádky příliš malé na dávku čekají na společnost), dokud se nenasbírá dost poptávky, dokud objednávka vlastního zákazníka zakázku stejně nespustí, nebo dokud se plánovač nerozhodne vyrobit to bez ohledu na to.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Nastavit velikost dávky:</b> vaše organizace → <b>Factory</b> (Továrna) → <b>Crafts</b> → <b>Artefacts</b> → otevřít artefakt, nebo zaškrtnout několik a nastavit to z výběrové lišty.</li>
<li><b>Najít ty nepohodlné:</b> seznam artefaktů → filtr <b>Batch not whole SKOs</b>, nebo sloupec <b>Batch in SKOs</b>.</li>
<li><b>Zobrazit návrh:</b> stránka artefaktu, pod velikostí dávky.</li>
<li><b>Nastavit packed in:</b> <b>Warehouse → Inventory</b> (Sklad → Zásoby) → otevřít SKO → <b>Edit SKO</b> (Upravit SKO).</li>
<li><b>Objednat na krok:</b> partnerský nákupní seznam → tlačítko vedle <i>full batches every N SKO</i>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Velikost dávky a trvanlivost na artefaktech: právo <b>research and development</b> (výzkum a vývoj) v továrně, nebo supervizor organizace.</li>
<li>Packed in na SKO: skladová pozice s právem upravovat zásoby.</li>
</ul>
</aside>
