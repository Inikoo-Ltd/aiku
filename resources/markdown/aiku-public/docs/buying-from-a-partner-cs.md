---
title: Nákup od partnera
summary: Průvodce pro kupujícího - začněte u nákupního dashboardu, naplňte seznam ručně, z partnerova katalogu nebo pomocí automatického doplnění, a přijměte zboží, když dorazí.
date: 2026-10-09
source_date: 2026-10-09
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
- Tam, kde si partner položku sám vyrábí, řádek navíc uvádí *made in batches of N units* (vyrobeno v dávkách po N kusech) a tam, kde se to s baleními nedělí rovnoměrně, *full batches every N SKO* (celé dávky po N SKO) — **order step** (objednávací krok), nejmenší objednávka, kterou celé dávky zaplní přesně. Tlačítko zaokrouhlí vaše množství nahoru na tento krok. Objednávka mimo krok je povolená a stránka to říká: stejně se vyrobí celá dávka, takže se objednávka může zpozdit nebo se upraví množství, a objednávka pod jeden celý krok čeká v továrně, dokud se k ní nepřipojí jiná poptávka. Viz [Dávky, balení a částečné dávky](/docs/batches-packs-and-part-batches-cs).
- Otevřené řádky jsou plně vaše: zvolte **priority** (prioritu, od nízké po naléhavou) přímo z rozevíracího seznamu v tabulce, nebo řádek odstraňte tlačítkem koše. Pro změnu množství použijte **Browse** (Procházet) — stejný stepper u dané položky tam upraví otevřený řádek přímo. Jakmile partner řádek vychystá, uzamkne se a jeho stav vám ukáže, kde se nachází.

## Procházení partnerova katalogu

Vedle nákupního seznamu je záložka **Browse** (Procházet): celý partnerův katalog jako obchod, s aktuálním skladem a cenami. Procházejte podle **Departments** (Oddělení) nebo **Collections** (Kolekce), sestupte k rodinám produktů, nebo prostě napište do vyhledávacího pole. Každá karta produktu ukazuje aktuální cenu, štítek **Their stock** (Jejich sklad) s tím, co má partner k dispozici, a — u položek, které používáte — vaše vlastní čísla: *náš sklad*, *náš prodej / čtvrtletí* a *dojde nám za* tolik a tolik dní (červeně, když je to dva týdny nebo méně).

Dvě věci o tomto katalogu stojí za zapamatování. Ceny jsou **vaše, ne jejich obchodu**: partnerova ceníková cena s již odečtenou vaší intercompany slevou, přepočtená do měny vaší organizace, takže co vidíte, to bude i na faktuře. A katalog obsahuje produkty, které partner vyrobil **výhradně pro vás** — řádky, které se nikdy neobjeví v jejich veřejném obchodě, ale existují pro vaši organizaci. Pokud něco, co jste čekali, nenajdete, stojí za to se zeptat; pokud najdete něco, co jste nečekali, je to nejspíš vaše na základě dohody.

Objednávání probíhá přímo na kartě: pole s množstvím **je** váš nákupní seznam. Napište nebo nastavte číslo a řádek se přidá nebo aktualizuje na otevřeném seznamu; nastavte ho zpět na 0 a řádek se odstraní. Vedle něj čip **suggested** (navrženo, přerušovaně orámovaný) ukazuje množství, které by aiku objednalo — jedno kliknutí vyplní pole tímto číslem.

Zatímco procházíte, váš nákupní seznam se veze s vámi jako účtenka připnutá vpravo — každý řádek seskupený podle rodiny produktů, s průběžným součtem — takže vždy víte, kde objednávka stojí. **Go to Shopping list** (Přejít na nákupní seznam) vás vrátí zpět na celý editovatelný seznam.

<figure><img src="/art/docs/draw-partner-browse.svg" alt="Watercolor sketch of the partner catalogue browser: a search box, Departments and Collections tabs, product cards with plus buttons, and the shopping list receipt pinned on the right with its running total" width="1200" height="750" loading="lazy"><figcaption>Partnerův obchod, s vaším seznamem po ruce.</figcaption></figure>

## Automatické doplnění: rozpočet a volitelně instrukce

**Auto-fill** (Automatické doplnění) existuje proto, aby doplňování zásob nezáviselo na tom, že si někdo vzpomene na každou položku. Zadáte jedno číslo — **budget** (rozpočet), ve stejné měně jako ceny, za které nakupujete — a nástroj sestaví návrh, který se do něj vejde:

- Podívá se na každou položku, kterou partner dokáže dodat a kterou skutečně používáte, seřadí je podle toho, **jak brzy vám dojdou** (stejná prognóza *dojde nám za*, kterou vidíte při procházení), a doplní nejdřív ty, které dojdou nejdřív, každou v jejím doporučeném objednacím množství, zaokrouhleném na objednávací krok dané položky.
- Každý navržený řádek ukazuje svůj **důvod** ("Náš prodej/čtvrtletí ~48 · náš sklad 0 · dochází nám teď"), množství a cenu, takže vidíte, proč tam je. Množství se řídí stejnou prognózou jako čipy *suggested* v Browse.
- **Instruction box** (pole pro instrukci) je volitelné a přijímá běžný jazyk: *"upřednostni éterické oleje, přeskoč cokoliv, čeho máme na skladě na víc než 8 týdnů"*, *"zaměř se na svíčky, nic sezónního"*. AI přečte vaši instrukci spolu se stejnými daty o spotřebě a podle toho návrh přetvoří — ale výstup je před zobrazením ověřen proti realitě: množství jsou omezena tím, co partner skutečně má, a celková částka je vrácena zpět do vašeho rozpočtu. Pokud instrukci nelze splnit, dostanete standardní návrh.
- **Nic se nepřidá samo od sebe.** Návrh je sada zaškrtnutých řádků, které můžete odškrtnout, přepočítat nebo znovu vygenerovat s jiným rozpočtem či instrukcí; potvrdí to jen **Add items to shopping list** (Přidat položky do nákupního seznamu).
- **Některé položky se vylučují.** SKO se zapnutým **Do not auto order** (Neobjednávat automaticky) (v editační obrazovce SKO, pod Stock Data) se v návrhu nikdy neobjeví — pro položky, které chce procurement držet pod ruční kontrolou. Stále je můžete objednat ručně z Browse nebo ze seznamu zásob; přeskočí je jen automatická cesta. SKO označené **On Demand** (Na vyžádání) jsou z partnerského nákupu vyloučené úplně.

Automatické doplnění lze otevřít i už zaměřené na konkrétní oblast: **+ fill** (+ doplnit) na dlaždici rizika na dashboardu ho otevře jen pro daný segment, s návrhem už vygenerovaným. Platí stejná pravidla — upravíte, odškrtnete a potvrdíte; nic se nepřidá samo.

Dobrý zvyk: projděte dlaždice dashboardu od nejhoršího, pak jednou za doplňovací cyklus spusťte automatické doplnění, přečtěte si důvody, odškrtněte, s čím nesouhlasíte, a zbytek přidejte.

## Požádejte svého AI asistenta o přidání řádků

Pokud je váš účet zapojen, může vám AI asistent, kterého k aiku připojíte, řádky na seznam přidat. Plánování udělejte ve svém vlastním asistentovi — zeptejte se ho, čeho se blíží nedostatek, a projděte čísla společně — a až budete spokojeni, řekněte mu: *„dobře, přidej to na nákupní seznam"*. Nejdřív vám ukáže kódy SKO a množství a přidá je teprve po vašem potvrzení.

- Při plánování asistent čte stejná čísla, která vidíte vy: sklad, prodeje, dny do vyprodání, prognózované množství, krok objednávky (order step) a dobu použitelnosti (shelf life). Nikdy nenavrhne víc, než se prodá před vypršením doby použitelnosti; produkt, u kterého zatím není doba použitelnosti zaznamenána, se plánuje jako by vydržel jeden rok.
- Množství jsou v SKO a řádek **nastavují**: SKO, které už na seznamu je, dostane nové množství, ne další navíc.
- Platí stejná pravidla jako při ručním přidání: kontroly rozpočtu, skladového prostoru a balení uvedené níže. Řádek, který seznam odmítne, se vrátí s důvodem a ostatní se přidají.
- Asistent pouze přidává a mění množství; priority a odstraňování řádků zůstávají v tabulce nákupního seznamu.
- Každá změna se zaznamená spolu s vaším požadavkem a lze ji vrátit: požádejte asistenta o vrácení, nebo to může udělat administrátor v logu AI změn (AI changes).
- Zapojení je přepínač na vašem uživatelském účtu, který zapíná administrátor, a navíc potřebujete oprávnění upravovat nákup (procurement) pro vaši organizaci.

### Zadání objednávky přes vašeho asistenta

S druhým přepínačem na vašem účtu může asistent objednávku nejen připravit, ale i zadat:

- **Odeslat koš** do výrobního hubu (manufacturing hub): *"odešli to"*. Hub začne vyrábět, co jste poslali, přesně tak, jako byste stiskli **Submit**.
- **Změnit řádek, který už byl hubu odeslán**, dokud ho hub nezačal zpracovávat (žádná zakázka, není předpřipraveno ani se nepřipravuje). Je to nebezpečné — hub už může kolem něj plánovat materiály a dávky — proto vás asistent nejdřív varuje a pokračuje, jen když na tom trváte.
- **Záchranné objednávky ostatním partnerům**: asistent ukáže, co může každý partner zachránit (SKO, náklady, celkem) a po potvrzení partnera a rozpočtu vytvoří záchrannou nákupní objednávku (purchase order) a odešle ji do partnerova skladu.

Zadané objednávky se zaznamenávají spolu s vaším požadavkem, ale z logu AI změn je **nelze vrátit**: stornujte je u partnera jako jakoukoli jinou objednávku.

## Když seznam řekne ne

Přidání je odmítnuto ve třech případech, záměrně: seznam dosáhl **budget** (rozpočtu) pro tohoto partnera (položky ranku A a bez skladu jsou z limitu vyňaty — nouzová situace se vejde vždy), sklad má pod 5 % volných míst, nebo tento partner už vyčerpal svůj spravedlivý podíl na volných místech u produktů, které jste nikdy neskladovali. Řešte tu zprávu, nehledejte jinou cestu — stejná pojistka platí pro ruční přidání, hromadné přidání i automatické doplnění. [Článek o dashboardu](/docs/reading-the-partner-shopping-dashboard-cs) vysvětluje, odkud tyto limity pocházejí.

## Když je zboží na cestě

Jakmile partner [odešle zásilku do svého skladu](/docs/fulfilling-partner-orders-cs), objeví se u vašeho partnera pod **Stock deliveries** (Skladové dodávky) příchozí **skladová dodávka**. Nechte ji být, dokud říká confirmed (potvrzeno) nebo dispatched (expedováno) — sama zrcadlí sklad prodávajícího a aktualizuje se. Když krabice fyzicky dorazí: **receive** (přijmout), zkontrolujte a uložte na místa přesně jako u kterékoli dodavatelské dodávky. Cokoli chybí nebo je poškozené, se řeší po přijetí, proti navázané faktuře — jak peníze fungují, viz [přehled](/docs/ordering-from-a-partner-organisation-cs).

Když partner expeduje, každý řádek přijde s již vyplněnými dávkami, které partner vyskladnil: stejný kód dávky a datum minimální trvanlivosti, přepočtené na vaše SKO. Při kontrole je jen potvrdíte, nebo opravíte, pokud zboží říká něco jiného, a naskladněním jdou tyto dávky na vaše regály.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Zjistit, co je třeba koupit:</b> vaše organizace → <b>Procurement → Partners</b> (Nákup → Partneři) → otevřít partnera → <b>Shopping</b> (dashboard) → projít dlaždice rizik.</li>
<li><b>Přidat na seznam:</b> <b>Shopping list</b> (Nákupní seznam) → <b>Add stocks</b> (Přidat skladové položky), nebo <b>Browse</b> (Procházet) a nastavit množství na kartách produktů, nebo <b>Auto-fill</b> (Automatické doplnění) (nebo <b>+ fill</b> na dlaždici dashboardu) pro návrh.</li>
<li><b>Objednat v celých dávkách:</b> tlačítko vedle <i>full batches every N SKO</i> na řádku nebo na kartě produktu.</li>
<li><b>Upravit otevřené řádky:</b> změnit prioritu nebo smazat řádky v tabulce nákupního seznamu; změnit množství na kartách produktů v <b>Browse</b>.</li>
<li><b>Nechat někoho plnit seznam přes jeho AI asistenta (administrátoři):</b> <b>Sysadmin → Users</b> (Správa systému → Uživatelé) → otevřít uživatele → <b>Edit</b> (Upravit) → <b>Access</b> (Přístup) → zapnout <b>Can connect AI assistant</b>, poté <b>Can add to the manufacturing hub shopping list through their AI assistant</b>.</li>
<li><b>Nechat někoho zadávat objednávky přes jeho AI asistenta (administrátoři):</b> stejná záložka <b>Access</b> → zapnout <b>Can place orders to the manufacturing hub and partners through their AI assistant</b>.</li>
<li><b>Vyloučit položku z automatického doplnění:</b> vaše organizace → <b>Warehouse → Inventory</b> (Sklad → Zásoby) → otevřít SKO → <b>Edit SKO</b> (Upravit SKO) → zapnout <b>Do not auto order</b> (Neobjednávat automaticky).</li>
<li><b>Sledovat a přijmout zásilku:</b> stejná stránka partnera → <b>Stock deliveries</b> (Skladové dodávky) → až zboží dorazí, <b>Receive</b> (Přijmout) → zkontrolovat → uložit na místa.</li>
</ul>
</aside>
