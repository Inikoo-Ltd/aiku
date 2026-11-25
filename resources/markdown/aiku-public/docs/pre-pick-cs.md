---
title: Pre-pick
summary: Průvodce pro továrnu k sekci Factory → Pre-pick - které partnerské řádky se tu objeví, co znamená každý stav a co dělat, když zboží na skladě je, když je jen část, když je další na cestě a když už nedorazí.
date: 2026-11-25
source_date: 2026-11-25
tags: production, procurement, intercompany, warehouse
category: production
series: Ordering from partners
order: 8
---

<aside class="tldr">
Řádky, které je třeba vyrobit, jdou do <b>To produce</b>; řádky, které už máte na regálu, přijdou do <b>Pre-pick</b>. U každého řádku v Pre-pick:
<ul>
<li><b>Máte všechno zboží:</b> nedělejte nic. aiku ho samo pošle do skladu do deseti minut. Pokud zásoba pokrývá řádek přesně a nic nezbude, řádek čeká: stiskněte <b>Pre-pick</b>.</li>
<li><b>Máte jen část a další zboží je na cestě od dodavatele:</b> počkejte. Jakmile je dodávka zapsaná na sklad, aiku pošle celý řádek samo. Nebo stiskněte <b>Pre-pick</b> a pošlete hned to, co máte; zbytek jde do <b>To produce</b>.</li>
<li><b>Máte jen část a vyrábíte to:</b> čekání nepomůže, protože nikdo nedostane za úkol zbytek vyrobit. Stiskněte <b>Pre-pick</b> a pošlete, co máte; zbytek jde do <b>To produce</b>, aby se vyrobil.</li>
<li><b>Nedorazí (vyřazeno, nedostupné):</b> stiskněte <b>Close short</b> (uzavřít s menším množstvím): pošle se, co je, a zbytek se zruší, nebo <b>Can't be done</b>, aby se neposlalo nic; partner dostane zprávu. Pokud na skladě nic není, řádek je v <b>To produce</b>: viz případ 3 níže.</li>
</ul>
</aside>

<figure><img src="/art/docs/draw-pre-pick-fork.svg" alt="Akvarelová skica: řádek z partnerova nákupního seznamu dojde k rozcestníku, který ukazuje buď do To produce, na pracovní stůl, kde se věci vyrábějí, nebo do Pre-pick, k regálu se zásobou. Z regálu jde zboží, které je celé na skladě, samo do místa partnera do deseti minut; zboží, které je na skladě jen zčásti, jde do místa a zbytek čeká na nákladní auto s dodávkou; zboží, které nedorazí, dostane červené razítko Close short a zpráva letí zpět k partnerovi." width="1200" height="750" loading="eager"><figcaption>Na regálu: Pre-pick. Ne na regálu: To produce.</figcaption></figure>

## Proč řádek skončí tady a ne v To produce

Objednávka partnera přichází jako řádky na nákupním seznamu. U každého řádku se aiku podívá na vaši **free stock** (volnou zásobu): co je na regálu minus to, co je už přislíbeno jiným partnerským řádkům.

- **Volná zásoba nepokrývá nic** - řádek jde do **To produce**, aby se vyrobil. Viz [Práce se seznamem To produce](/docs/fulfilling-partner-orders-cs).
- **Volná zásoba pokrývá část nebo celek** - řádek přijde do **Factory → Pre-pick**.

Stejná objednávka tedy může být rozdělená mezi obě stránky: čaj, který míchíte, jde do To produce, lahvičky, sáčky a krabice, které už máte, přijdou sem. Řádek, kterému později dojde zásoba, se do To produce přesune sám.

Řádky se obsluhují ve frontě, pro každý produkt zvlášť: nejdřív **urgent**, pak **high**, **normal** a **low**; při stejné prioritě ten, který je potřeba dřív, a pak ten starší. Řádek počítá jen se zásobou, která zbyde po všech řádcích před ním.

## Čtení seznamu

| Sloupec | Co říká |
| --- | --- |
| For | která partnerská organizace o zboží požádala |
| Artefact | kód a název produktu |
| To | shromažďovací místo daného partnera, kam zboží půjde |
| Asked | kolik partner požadoval |
| In stock | kolik je teď ve vašich regálech |
| Can pick | kolik můžete tomuto řádku dát hned, po řádcích před ním ve frontě |
| Shortfall | kolik chybí k pokrytí celého řádku |
| Status | co s ním aiku udělá, viz níže |
| Priority, Added | kde řádek ve frontě stojí |

Kategorie, žadatel a naléhavost seznam filtrují; čísla na filtrech jsou skutečné počty.

## Co znamenají stavy

| Status | Co znamená | Co děláte |
| --- | --- | --- |
| **Going to the warehouse** (Jde do skladu) | zásoba pokrývá celý řádek a ještě něco zbude | nic - do deseti minut ho aiku samo předvychystá a ze seznamu zmizí |
| **Held, one more needed on the shelf** (Zadrženo, na regálu musí zůstat ještě jeden) | zásoba pokrývá řádek přesně, nic nezbyde | nic, nebo stiskněte **Pre-pick**, má-li jít hned; aiku posílá řádek samo jen tehdy, když na regálu aspoň jeden zůstane |
| **Awaiting full stock, production manager to decide** (Čeká na plnou zásobu, rozhodne vedoucí výroby) | na skladě je jen část řádku | rozhodněte, viz případy níže |
| **In To produce** (V To produce) | zásoba je, ale řádky před tímto ji vyčerpaly | nic zde; řádek čeká na stránce To produce |

Pre-pick není expedice. Přislíbí zásobu danému partnerovi a dá skladu na seznam přenos: sundají zboží z regálu a uloží ho do místa partnera. Zboží odjede, až půjde další zásilka partnera, podle plánu nebo když někdo stiskne **Send to warehouse**. Viz [Shromažďování partnerova zboží](/docs/gathering-a-partners-goods-cs).

## Případ 1: máte všechnu zásobu

Nedělejte nic. Řádek ukazuje **Going to the warehouse** a aiku ho do deseti minut předvychystá. Pokud ukazuje **Held, one more needed on the shelf** a nemá čekat, stiskněte **Pre-pick** na řádku.

Chcete-li poslat víc řádků najednou, zaškrtněte je a stiskněte **Pre-pick selected**, nebo **Pre-pick all** pro všechno, co ukazují aktuální filtry.

## Případ 2: máte jen část zásoby, nebo je další na cestě

Řádek ukazuje **Awaiting full stock, production manager to decide**. Máte tři možnosti:

- **Počkat na zbytek.** Nedělejte nic. Když zboží objednané od dodavatele dorazí a zapíše se na sklad, volná zásoba vzroste; jakmile pokryje celý řádek a aspoň jeden kus zůstane na regálu, aiku ho samo předvychystá. Zvolte to, když je dodávka blízko a partner raději dostane všechno najednou. Čekání funguje jen tehdy, když zboží opravdu dorazí: u produktu, který vyrábíte, nikdo nedostane za úkol zbytek vyrobit, dokud řádek čeká tady, takže by čekal věčně.
- **Poslat, co máte teď, zbytek později.** Stiskněte **Pre-pick** (množství se ukáže jako *partial pick*, částečné vychystání). Část na skladě jde do místa partnera; zbytek zůstane na partnerově seznamu jako samostatný otevřený řádek a přesune se do **To produce**. Produkt, který vyrábíte, se tam objeví na tabuli, aby se vyrobil; cokoli jiného se objeví na záložce **All** a vrátí se sem, až dorazí nová zásoba.
- **Poslat, co máte, a skončit.** Stiskněte **Close short**. aiku vás požádá o potvrzení, pošle, co je na skladě, a zbytek zruší. Partnerův seznam klesne na odeslané množství a ten, kdo řádek přidal, i partnerův nákupní tým se dozvědí, že byl uzavřen s menším množstvím, aby to mohli koupit jinde.

## Případ 3: zásoba nedorazí

Produkt je vyřazen, dodavatel ho už nemá, nebo nebude dlouho dostupný.

- **Něco je na skladě** - stiskněte **Close short**: partner dostane, co je, a dozví se, že zbytek nedorazí. Pokud nemůžete poslat ani nic z toho, stiskněte místo toho **Can't be done** na řádku a uveďte důvod.
- **Nic není na skladě a vy to vyrábíte** - řádek je karta na tabuli **To produce**. Přetáhněte ji z **Backlog** (do **Preparing**, nebo do **Producing**, pokud vaše továrna používá jednoduchou tabuli) a v okně, které se otevře, stiskněte **Can't be done** (Nelze provést) a uveďte důvod. Řádek z tabule zmizí a ten, kdo ho přidal, i partnerův nákupní tým se s vaším důvodem dozvědí, že to mají sehnat jinde.
- **Nic není na skladě a vy to nevyrábíte** - řádek je v **To produce → All**. Stiskněte **Can't be done** na jeho řádku a uveďte důvod; dozvědí se to stejní lidé.

Takové řádky nenechávejte čekat bez zprávy: partner je vidí jako **Requested** a dál s nimi počítá.

## Co vidí partner

Ve svém nákupním seznamu má každý řádek uvedeno, kam až se dostal: **Requested**, **Being made**, **Pre-picked**, **Staged for you**, **Being picked**, **On its way**.

Když je řádek uzavřen s menším množstvím nebo ho nelze provést, lidé na partnerově straně se to s důvodem dozvědí.

## Co je dobré vědět

- **Nic nerezervuje zásobu, dokud není předvychystána.** Řádek, který říká *Awaiting full stock*, nic neodložil: jeho zásobu lze pořád prodat nebo dát jinému řádku. Pokud se jí nesmí nikdo dotknout, předvychystejte, co tam je.
- **Když se ukáže, že regál je poloprázdný** - audit najde méně, nebo se to prodalo jinde - aiku bere příslib zpět od nejnovějších pre-picků, a ty řádky se vrátí na seznam nebo do To produce.
- **Seznam se při každém otevření počítá znovu.** Řádky mizí samy, jakmile jsou předvychystány; není co uklízet.
- **Číslo vedle Pre-pick** v postranním panelu továrny udává, kolik řádků čeká, a mění se samo.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Seznam:</b> vaše organizace → <b>Factory</b> (Továrna) → <b>Pre-pick</b>.</li>
<li><b>Poslat řádek hned:</b> <b>Pre-pick</b> na řádku, nebo zaškrtnout řádky a stisknout <b>Pre-pick selected</b>, nebo <b>Pre-pick all</b>.</li>
<li><b>Poslat, co je, a zbytek zrušit:</b> <b>Close short</b> na řádku.</li>
<li><b>Řádek, který nejde udělat:</b> <b>Can't be done</b> na jeho řádku v <b>Pre-pick</b> nebo ve <b>Factory</b> → <b>To produce</b> → <b>All</b>, nebo přetáhněte jeho kartu z <b>Backlog</b> na tabuli.</li>
<li><b>Přenosy do místa:</b> <b>Warehouse</b> → <b>Dispatching</b> → záložka <b>Pre-pick</b>, viz <a href="/docs/gathering-a-partners-goods-cs">Shromažďování partnerova zboží</a>.</li>
<li><b>Odeslat shromážděné zboží:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>, nebo to nechat na plánu zásilek partnera.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Zobrazení seznamu: jakákoli továrenní pozice, která smí prohlížet výrobu, nebo supervizor organizace.</li>
<li>Pre-pick a Close short: továrenní pozice, která plánuje nebo připravuje výrobu, editor nákupu, nebo supervizor organizace.</li>
<li><b>Can't be done</b>: továrenní pozice, která plánuje nebo připravuje výrobu, nebo supervizor organizace. Viz <a href="/docs/factory-positions-cs">Továrenní pozice</a>.</li>
</ul>
</aside>
