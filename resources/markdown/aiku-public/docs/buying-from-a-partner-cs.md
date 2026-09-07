---
title: Nákup od partnera
summary: Průvodce pro kupujícího - začněte u nákupního dashboardu, naplňte seznam ručně, z partnerova katalogu nebo pomocí automatického doplnění, a přijměte zboží, když dorazí.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, intercompany, shopping-list
category: procurement
series: Ordering from partners
order: 3
---

<aside class="tldr">
Pro lidi, kteří partnerské objednávky <em>zadávají</em>. Vedete jeden otevřený seznam toho, co vaše organizace potřebuje; partner podle něj odesílá vlastním tempem. Začněte na <a href="/docs/reading-the-partner-shopping-dashboard-cs">nákupním dashboardu</a>, kde uvidíte, co je ohrožené a kolik máte prostoru, poté přidejte řádky ručně, z jejich katalogu, nebo nechte automatické doplnění navrhnout doplnění zásob v rámci rozpočtu. Jste v tomto toku noví? Začněte <a href="/docs/ordering-from-a-partner-organisation-cs">přehledem</a>.
</aside>

## Začněte na dashboardu

**Procurement → Partners → {partner} → Shopping** (Nákup → Partneři → {partner} → Nákup) otevře [nákupní dashboard](/docs/reading-the-partner-shopping-dashboard-cs): co brzy dojde, co je už na cestě, a dva limity, ve kterých se váš seznam pohybuje — **order budget** (objednávkový rozpočet) pro tohoto partnera a **warehouse space** (skladový prostor), který máte k dispozici. Pracujte s riziky odtud a většina seznamu se napíše sama; vše níže popisuje, jak se seznam chová, jakmile v něm jste.

## Nákupní seznam

Vedle dashboardu drží záložka **Shopping list** (Nákupní seznam) všechny otevřené řádky.

- **Add stocks** (Přidat skladové položky) otevře partnerův seznam skladových zásob s jejich dostupností, způsobem balení, vaším aktuálním skladem a tím, kolik jste toho spotřebovali za poslední čtyři čtvrtletí. Množství jsou v prodejních jednotkách prodávajícího (SKO).
- Každý řádek na první pohled ukazuje příběh skladu — *jejich sklad*, *náš sklad* a kdy *nám dojde* — plus částku ve vaší nákupní ceně, s celkovým součtem otevřených položek v patě tabulky.
- Otevřené řádky jsou plně vaše: zvolte **priority** (prioritu, od nízké po naléhavou) přímo z rozevíracího seznamu v tabulce, nebo řádek odstraňte tlačítkem koše. Pro změnu množství použijte **Browse** (Procházet) — stejný stepper u dané položky tam upraví otevřený řádek přímo. Jakmile partner řádek vychystá, uzamkne se a jeho stav vám ukáže, kde se nachází.

## Procházení partnerova katalogu

Vedle nákupního seznamu je záložka **Browse** (Procházet): celý partnerův katalog jako obchod, s aktuálním skladem a cenami. Procházejte podle **Departments** (Oddělení) nebo **Collections** (Kolekce), sestupte k rodinám produktů, nebo prostě napište do vyhledávacího pole. Každá karta produktu ukazuje aktuální cenu, štítek **Their stock** (Jejich sklad) s tím, co má partner k dispozici, a — u položek, které používáte — vaše vlastní čísla: *náš sklad*, *náš prodej / čtvrtletí* a *dojde nám za* tolik a tolik dní (červeně, když je to dva týdny nebo méně).

Dvě věci o tomto katalogu stojí za zapamatování. Ceny jsou **vaše, ne jejich obchodu**: partnerova ceníková cena s již odečtenou vaší intercompany slevou, přepočtená do měny vaší organizace, takže co vidíte, to bude i na faktuře. A katalog obsahuje produkty, které partner vyrobil **výhradně pro vás** — řádky, které se nikdy neobjeví v jejich veřejném obchodě, ale existují pro vaši organizaci. Pokud něco, co jste čekali, nenajdete, stojí za to se zeptat; pokud najdete něco, co jste nečekali, je to nejspíš vaše na základě dohody.

Objednávání probíhá přímo na kartě: pole s množstvím **je** váš nákupní seznam. Napište nebo nastavte číslo a řádek se přidá nebo aktualizuje na otevřeném seznamu; nastavte ho zpět na 0 a řádek se odstraní. Vedle něj čip **suggested** (navrženo, přerušovaně orámovaný) ukazuje množství, které by aiku objednalo — jedno kliknutí vyplní pole tímto číslem.

Zatímco procházíte, váš nákupní seznam se veze s vámi jako účtenka připnutá vpravo — každý řádek seskupený podle rodiny produktů, s průběžným součtem — takže vždy víte, kde objednávka stojí. **Go to Shopping list** (Přejít na nákupní seznam) vás vrátí zpět na celý editovatelný seznam.

<figure><img src="/art/docs/draw-partner-browse.svg" alt="Watercolor sketch of the partner catalogue browser: a search box, Departments and Collections tabs, product cards with plus buttons, and the shopping list receipt pinned on the right with its running total" width="1200" height="750" loading="lazy"><figcaption>Partnerův obchod, s vaším seznamem po ruce.</figcaption></figure>

## Automatické doplnění: rozpočet a volitelně instrukce

**Auto-fill** (Automatické doplnění) existuje proto, aby doplňování zásob nezáviselo na tom, že si někdo vzpomene na každou položku. Zadáte jedno číslo — **budget** (rozpočet), ve stejné měně jako ceny, za které nakupujete — a nástroj sestaví návrh, který se do něj vejde:

- Podívá se na každou položku, kterou partner dokáže dodat a kterou skutečně používáte, seřadí je podle toho, **jak brzy vám dojdou** (stejná prognóza *dojde nám za*, kterou vidíte při procházení), a doplní nejdřív ty, které dojdou nejdřív, každou v jejím doporučeném objednacím množství.
- Každý navržený řádek ukazuje svůj **důvod** ("Náš prodej/čtvrtletí ~48 · náš sklad 0 · dochází nám teď"), množství a cenu, takže vidíte, proč tam je. Množství se řídí stejnou prognózou jako čipy *suggested* v Browse.
- **Instruction box** (pole pro instrukci) je volitelné a přijímá běžný jazyk: *"upřednostni éterické oleje, přeskoč cokoliv, čeho máme na skladě na víc než 8 týdnů"*, *"zaměř se na svíčky, nic sezónního"*. AI přečte vaši instrukci spolu se stejnými daty o spotřebě a podle toho návrh přetvoří — ale výstup je před zobrazením ověřen proti realitě: množství jsou omezena tím, co partner skutečně má, a celková částka je vrácena zpět do vašeho rozpočtu. Pokud instrukci nelze splnit, dostanete standardní návrh.
- **Nic se nepřidá samo od sebe.** Návrh je sada zaškrtnutých řádků, které můžete odškrtnout, přepočítat nebo znovu vygenerovat s jiným rozpočtem či instrukcí; potvrdí to jen **Add items to shopping list** (Přidat položky do nákupního seznamu).
- **Některé položky se vylučují.** SKO se zapnutým **Do not auto order** (Neobjednávat automaticky) (v editační obrazovce SKO, pod Stock Data) se v návrhu nikdy neobjeví — pro položky, které chce procurement držet pod ruční kontrolou. Stále je můžete objednat ručně z Browse nebo ze seznamu zásob; přeskočí je jen automatická cesta. SKO označené **On Demand** (Na vyžádání) jsou z partnerského nákupu vyloučené úplně.

Automatické doplnění lze otevřít i už zaměřené na konkrétní oblast: **+ fill** (+ doplnit) na dlaždici rizika na dashboardu ho otevře jen pro daný segment, s návrhem už vygenerovaným. Platí stejná pravidla — upravíte, odškrtnete a potvrdíte; nic se nepřidá samo.

Dobrý zvyk: projděte dlaždice dashboardu od nejhoršího, pak jednou za doplňovací cyklus spusťte automatické doplnění, přečtěte si důvody, odškrtněte, s čím nesouhlasíte, a zbytek přidejte.

## Když seznam řekne ne

Přidání je odmítnuto ve třech případech, záměrně: seznam dosáhl **budget** (rozpočtu) pro tohoto partnera (položky ranku A a bez skladu jsou z limitu vyňaty — nouzová situace se vejde vždy), sklad má pod 5 % volných míst, nebo tento partner už vyčerpal svůj spravedlivý podíl na volných místech u produktů, které jste nikdy neskladovali. Řešte tu zprávu, nehledejte jinou cestu — stejná pojistka platí pro ruční přidání, hromadné přidání i automatické doplnění. [Článek o dashboardu](/docs/reading-the-partner-shopping-dashboard-cs) vysvětluje, odkud tyto limity pocházejí.

## Když je zboží na cestě

Jakmile partner [odešle zásilku do svého skladu](/docs/fulfilling-partner-orders-cs), objeví se u vašeho partnera pod **Stock deliveries** (Skladové dodávky) příchozí **skladová dodávka**. Nechte ji být, dokud říká confirmed (potvrzeno) nebo dispatched (expedováno) — sama zrcadlí sklad prodávajícího a aktualizuje se. Když krabice fyzicky dorazí: **receive** (přijmout), zkontrolujte a uložte na místa přesně jako u kterékoli dodavatelské dodávky. Cokoli chybí nebo je poškozené, se řeší po přijetí, proti navázané faktuře — jak peníze fungují, viz [přehled](/docs/ordering-from-a-partner-organisation-cs).

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Zjistit, co je třeba koupit:</b> vaše organizace → <b>Procurement → Partners</b> (Nákup → Partneři) → otevřít partnera → <b>Shopping</b> (dashboard) → projít dlaždice rizik.</li>
<li><b>Přidat na seznam:</b> <b>Shopping list</b> (Nákupní seznam) → <b>Add stocks</b> (Přidat skladové položky), nebo <b>Browse</b> (Procházet) a nastavit množství na kartách produktů, nebo <b>Auto-fill</b> (Automatické doplnění) (nebo <b>+ fill</b> na dlaždici dashboardu) pro návrh.</li>
<li><b>Upravit otevřené řádky:</b> změnit prioritu nebo smazat řádky v tabulce nákupního seznamu; změnit množství na kartách produktů v <b>Browse</b>.</li>
<li><b>Vyloučit položku z automatického doplnění:</b> vaše organizace → <b>Warehouse → Inventory</b> (Sklad → Zásoby) → otevřít SKO → <b>Edit SKO</b> (Upravit SKO) → zapnout <b>Do not auto order</b> (Neobjednávat automaticky).</li>
<li><b>Sledovat a přijmout zásilku:</b> stejná stránka partnera → <b>Stock deliveries</b> (Skladové dodávky) → až zboží dorazí, <b>Receive</b> (Přijmout) → zkontrolovat → uložit na místa.</li>
</ul>
</aside>
