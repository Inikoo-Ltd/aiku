---
title: Shromažďování partnerova zboží
summary: Průvodce pro sklad - co znamená pre-pick, proč zboží v partnerově shromažďovacím místě přestává počítat jako dostupné, a jak pracovat se seznamem Pre-pick v sekci Dispatching.
date: 2026-09-09
source_date: 2026-09-09
tags: dispatch, procurement, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 8
---

<aside class="tldr">
Pro sklad. Část toho, co partnerská organizace požaduje, se tady vůbec nevyrábí - lahvičky, sáčky, krabice, tyčinky. Není co vyrábět: někdo to prostě musí sundat z regálu a dát do místa daného partnera. Tomuto přenesení se říká <b>pre-pick</b>, místo je <b>goods out gathering location</b> (shromažďovací místo pro odchozí zboží), a jakmile je zboží v něm, přestává počítat jako dostupné pro kohokoli jiného. Váš seznam přenosů k vykonání je <b>Dispatching → Pre-pick</b>.
</aside>

## Proč pre-pick existuje

Požadavek partnerské organizace se okamžitě nestává objednávkou. Zůstává na seznamu, dokud s ním na této straně někdo nezačne pracovat, a objednávkou, dodacím listem a stock delivery se stává až v okamžiku, kdy je odeslán do skladu.

To vytváří mezeru. Partner dnes požádá o 490 sad lahviček s víčky, expedovány budou až za týden, a mezitím nic nebrání tomu, aby se tyto lahvičky prodaly nebo použily jinde. Nic v aiku samo o sobě zboží nedrží stranou - ani požadavek, ani objednávka, ani dodací list. Jediné, co skutečně zboží rezervuje, je jeho přesun tam, odkud si ho nikdo jiný nemůže vzít.

Přesně k tomu slouží **goods out gathering location**: obyčejné místo ve skladu, označené jako shromažďovací bod pro jednoho partnera. Co je v něm, patří jemu.

## Co ta dvě slova znamenají

- **Pre-pick** - sundání zboží z regálu předem a jeho uložení do místa daného partnera, ještě předtím, než se objednávka kamkoli vydá. Na straně továrny to znamená také "tohle nevyrábíme, vezmeme to ze skladu".
- **Goods out gathering location** - místo označené tak, že cokoliv v něm přestává počítat jako dostupné. Zboží je pořád naše, pořád se počítá, oceňuje a auditovalo. Je jen předem přislíbené.

## Seznam přenosů k vykonání

**Warehouse → Dispatching → Pre-pick.**

Každý řádek je jeden přenos:

| Sloupec | Co říká |
| --- | --- |
| For | pro kterou partnerskou organizaci je zboží určeno |
| SKO | kód a název toho, co přinést |
| From | místo, které navrhuje systém - to, kde je ho nejvíc |
| To | shromažďovací místo daného partnera |
| Staged | kolik už je v místě uloženo |
| To move | kolik ještě zbývá přenést |

Zboží přineste, uložte do místa a pak stiskněte **Moved** (Přesunuto). Tím se přesun zapíše do aiku, řádek sám zmizí a množství vypadne z dostupnosti.

Seznam se při každém otevření počítá znovu - není to sada úkolů, které by měl někdo odškrtávat nebo uklízet. Pokud je zboží už v místě, řádek tam prostě není. Pokud někdo k požadavku přidá víc, řádek se zase objeví.

Záložka se zobrazuje jen organizacím, které mají pro partnera zřízené shromažďovací místo. Pokud ji nevidíte, ještě to nastavené není.

## Druhá polovina téže práce v továrně

Přenosy odněkud přicházejí: někdo v továrně musí rozhodnout, že se řádek vezme ze skladu, místo aby se vyráběl. Toto rozhodnutí má vlastní stránku, **Factory → Pre-pick** (Továrna → Předvychystat), a je dvojčetem seznamu výše.

Uvádí každý otevřený partnerský řádek, za kterým je sklad, ať už tento artefakt tato továrna vyrábí nebo ne, a nikdy nezobrazuje řádek, který je už předvychystaný. Každý řádek nese žadatele, artefakt, kolik bylo **asked** (požadováno), kolik je **in stock** (na skladě) a kolik **can pick** (lze vychystat) - obojí navzájem omezené, takže se řádku nikdy neslíbí víc, než existuje. Kategorie, žadatel a naléhavost seznam filtrují, a počty na filtrech jsou skutečné počty, ne jen to, co se vejde na stránku.

**Pre-pick** na řádku, **Pre-pick selected** (Předvychystat vybrané) pro zaškrtnuté, nebo **Pre-pick all** (Předvychystat vše) pro všechno, co ukazují aktuální filtry. Předvychystání přislíbí sklad danému partnerovi a dá přenos na seznam skladu; tam, kde je dostupná jen část požadovaného, se řádek rozdělí, přislíbená část odejde a zbytek zůstane otevřený. Nic se neprodává a žádná objednávka nevzniká - sklad prostě přestane být dostupný pro kohokoli jiného.

Číslo vedle **Pre-pick** (Předvychystat) v postranním panelu továrny udává, kolik řádků na toto rozhodnutí čeká, a mění se samo.

## Co vidí partner

Není potřeba nic hlásit ručně. Ve svém vlastním nákupním seznamu má každý řádek uveden svůj stav: **Requested**, **Being made**, **Pre-picked**, **Staged for you**, **Being picked**, **On its way** - vedle s odkazem na výrobní příkaz, objednávku nebo dodací list.

**Staged for you** znamená přesně to, co jste udělali: jejich zboží je v jejich shromažďovacím místě a čeká na příští expedici.

## Kdy se to skutečně odešle

Shromažďování není expedice. Zboží odjíždí, až někdo odešle vychystanou objednávku do skladu na stránce **To produce** (K výrobě), která ze shromážděných požadavků udělá objednávku, dodací list a stock delivery na straně partnera. Viz [Práce se seznamem To produce](/docs/fulfilling-partner-orders-cs).

Protože je zboží už na jednom místě, vychystávání je v tu chvíli jen přenos na jedno místo, ne obchůzka po celém skladu.

## Co je dobré vědět

- **Shromažďovací místo není sklad na uskladnění.** Cokoli v něm zůstane, je pro všechny ostatní neviditelné - nenabídne se vychystávači ani se nezobrazí jako dostupné k prodeji. Ukládejte tam zboží jen tehdy, když jde k danému partnerovi.
- **Označení už existujícího místa okamžitě mění čísla.** Pokud místo v okamžiku označení za shromažďovací bod už obsahuje zboží, toto zboží hned vypadne z dostupnosti. Před označením zkontrolujte, co v místě je.
- **Nic jiného zboží nerezervuje.** Dvěma lidem lze říct, že tytéž kusy jsou volné, dokud je někdo fyzicky nepřenese do shromažďovacího místa. Pokud se něco nesmí prodat partnerovi zpod ruky, přesuňte to.
- **Přesun zpět zboží uvolní.** Vezměte zboží z místa pryč, nebo místu odeberte shromažďovací označení, a množství se vrátí do dostupnosti.
- **Jedno místo na partnera** je obvyklé uspořádání, pojmenované po partnerovi, aby ho vychystávač poznal na první pohled.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Rozhodnout, že se řádek vezme ze skladu:</b> vaše organizace → <b>Factory</b> (Továrna) → <b>Pre-pick</b> (Předvychystat) → <b>Pre-pick</b> na řádku, nebo zaškrtnout a použít <b>Pre-pick selected</b> / <b>Pre-pick all</b>.</li>
<li><b>Seznam přenosů:</b> vaše organizace → <b>Warehouse</b> → <b>Dispatching</b> → záložka <b>Pre-pick</b>.</li>
<li><b>Zapsat přenos:</b> stiskněte <b>Moved</b> na řádku, jakmile je zboží fyzicky v místě.</li>
<li><b>Zkontrolovat obsah místa:</b> <b>Warehouse</b> → <b>Locations</b> → dané místo → záložka <b>SKOs</b>.</li>
<li><b>Označit místo jako shromažďovací bod:</b> <b>Warehouse</b> → <b>Locations</b> → dané místo → <b>Overview</b> → <b>Goods out gathering</b>.</li>
<li><b>Nasměrovat partnera na jeho místo:</b> ne přes obrazovku - zeptejte se administrátora, nastavuje se schválně z konzole, aby se to nedalo změnit omylem.</li>
<li><b>Odeslat shromážděné zboží:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Zobrazení seznamu a zápis přenosu: dispatching pozice pro daný sklad, nebo supervizor organizace.</li>
<li>Označení místa jako shromažďovacího bodu: skladová pozice s právem upravovat místa.</li>
<li>Nasměrování partnera na místo: administrátor, z konzole.</li>
</ul>
</aside>
