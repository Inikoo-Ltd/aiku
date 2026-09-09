---
title: Ukládání hotové výroby
summary: Průvodce pro sklad - kde se objevují hotové výrobní příkazy, jak aiku pozná, zda mají jít do partnerova shromažďovacího místa nebo do běžného skladu, a jak je naskladnit jedním kódem.
date: 2026-09-08
source_date: 2026-09-08
tags: dispatch, production, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 10
---

<aside class="tldr">
Pro sklad. Když řemeslníci dokončí výrobní příkaz, sám o sobě se ještě nestává skladovou zásobou - někdo ho musí odnést na místo a to místo zadat. Tento seznam je <b>Dispatching → From production</b> (Z výroby). Každý řádek říká, pro koho je zboží určeno, a navrhne místo: partnerovo shromažďovací místo, pokud je celý příkaz pro jednoho partnera, jinak skladové místo, které zadáte sami. Stiskněte <b>Put away</b> (Uložit) a zboží se naskladní na dané místo s dávkovým kódem.
</aside>

## Odkud se řádky berou

Výrobní příkaz se tu objeví ve chvíli, kdy jsou všechny jeho úkoly na dílně označené jako DONE, a zůstává tu, dokud není uložen. Nikdo vám ho nemusí posílat ručně.

Výrobní příkazy, na kterých se ještě pracuje, tu nejsou. Pokud potřebujete vidět, co se blíží, nástěnka **To produce** (K výrobě) v továrně má sloupec **Done** (Hotovo) se stejnými výrobními příkazy - viz [Práce se seznamem To produce](/docs/fulfilling-partner-orders-cs).

## Čtení řádku

| Sloupec | Co říká |
| --- | --- |
| Job order | odkaz na výrobní příkaz, JOxxx-0001 |
| Artisan | kdo to vyrobil |
| Made | kolik čeho - 20 × SKO-01, jeden řádek na výrobek |
| For | kód partnerské organizace, nebo *Stock* (Sklad) |
| To location | místo, kam by to mělo jít |

**For** se určuje podle požadavků, ze kterých výrobní příkaz vznikl. Pokud všechny řádky požadovala stejná partnerská organizace, zboží je její a řádek to uvádí, s jejím shromažďovacím místem už předvyplněným v poli **To location**. Je to stejné místo, jaké používá [pre-pick seznam](/docs/gathering-a-partners-goods-cs): cokoli v něm leží, je zamluvené a přestává se počítat jako dostupné pro kohokoli jiného.

Pokud byl výrobní příkaz vyroben pro sklad, pro vlastního zákazníka, nebo pro víc než jednoho partnera, řádek uvádí *Stock* a pole s místem je prázdné. Zadejte kód místa, kam zboží ukládáte.

## Uložení

1. Odneste zboží na zobrazené místo, nebo na to, které zvolíte.
2. Zkontrolujte kód v **To location**. Pokud jste zboží uložili jinam, změňte ho.
3. Stiskněte **Put away**.

Tím se zboží naskladní na dané místo, dostane dávkový kód sestavený z odkazu na výrobní příkaz a kódu výrobku, odečtou se suroviny, které recept udává jako spotřebované, a výrobní příkaz se označí jako přijatý. Řádek zmizí a na nástěnce továrny opustí sloupec **Done**.

Naskladní se množství, které řemeslníci skutečně vyrobili, ne to, co bylo požadováno. Výrobní příkaz, který žádal 25 a dostal 19, naskladní 19.

## Co se stane dál

- **Partnerovo zboží** čeká v jeho shromažďovacím místě, dokud někdo na stránce **To produce** neodešle vychystanou objednávku do skladu. Vychystávání je pak jen chůze k jednomu místu. Partner vidí řádek jako *Staged for you* (Připraveno pro vás) na svém nákupním seznamu.
- **Zboží vlastního zákazníka** jde do běžného skladu a čekající dodací list se uvolní k vychystání, protože nedostatek, který ho zdržoval, je pryč.
- **Sklad** se prostě stane dostupným.

## Co je dobré vědět

- **Záložka se zobrazuje jen** organizacím, které mají továrnu.
- **Jeden výrobní příkaz, jedno místo.** Pokud opravdu potřebujete rozdělit výrobní příkaz na dvě místa, uložte ho do skladu a nechte pre-pick seznam přenést partnerův podíl.
- **Špatné místo se opravuje jako každá jiná chyba na skladě** - přesuňte zásobu mezi místy. Výrobní příkaz samotný se znovu neotevírá.
- **Nic tu není rezervováno, dokud to není v shromažďovacím místě.** Zboží uložené do běžného skladu lze vychystat pro kohokoli.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Seznam:</b> vaše organizace → <b>Warehouse</b> → <b>Dispatching</b> → záložka <b>From production</b>.</li>
<li><b>Naskladnit:</b> zkontrolujte <b>To location</b> → <b>Put away</b>.</li>
<li><b>Zjistit, co je na místě:</b> <b>Warehouse</b> → <b>Locations</b> → dané místo → záložka <b>SKOs</b>.</li>
<li><b>Co továrna ještě dluží:</b> <b>Factory</b> → <b>To produce</b> → <b>Board</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Zobrazení seznamu a ukládání: dispatching pozice pro daný sklad, nebo supervizor organizace.</li>
</ul>
</aside>
