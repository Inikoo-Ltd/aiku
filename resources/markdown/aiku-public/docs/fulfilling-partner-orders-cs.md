---
title: Práce se seznamem To produce
summary: Průvodce pro továrnu - jedna fronta všeho, co továrna dluží, partnerským organizacím i vlastním zákazníkům, seskupená tak, jak přemýšlí plánovač výroby.
date: 2026-09-09
source_date: 2026-09-09
tags: production, procurement, intercompany, dispatch
category: production
series: Ordering from partners
order: 4
---

<aside class="tldr">
Pro lidi, kteří <em>vyrábějí věci</em>, a pro osobu, která plánuje den v továrně. <b>To produce</b> (K výrobě) je fronta továrny: každý řádek, který si vyžádala partnerská organizace, plus každý řádek, který objednal vlastní zákazník a který továrna nemá skladem. <b>Board</b> (Nástěnka) je místo, kde plánujete: přetáhnete řádek napříč pruhy a rozhodnete, kolik se má vyrobit a kdo to udělá, a řemeslníkovi se vytvoří výrobní příkaz. Pohledy typu seznam seskupují tytéž řádky podle řemeslníka, kategorie nebo odběratele, a odtud zaškrtnete, co lze poslat partnerům; zbytek papírování se odehraje samo. Jste v partnerském toku noví? Začněte <a href="/docs/ordering-from-a-partner-organisation-cs">přehledem</a>. Chcete, aby seznam věděl, kdo co vyrábí? Přečtěte si nejdřív <a href="/docs/who-makes-what-cs">Kdo co vyrábí</a>.
</aside>

## Odkud řádky pocházejí

**Factory → To produce** (Továrna → K výrobě) se plní ze dvou zdrojů. Řádek sem sami nikdy nepíšete.

- **Partnerské požadavky.** Sesterské organizace dají to, co potřebují, na svůj [nákupní seznam](/docs/buying-from-a-partner-cs). Každý otevřený řádek adresovaný vaší továrně se tu objeví s kupujícím, množstvím a prioritou, kterou nastavili.
- **Vlastní zákazníci.** Když je v jejich obchodě odeslaná objednávka, aiku se podívá na každý produkt. Pokud je za ním sklad nedostatečný a tento sklad vyrábí továrna, nedostatek se sem dostane jako řádek, označený zákazníkem a číslem objednávky. Jakmile je daná objednávka expedovaná, řádek se sám zavře.

Objednávky, které přicházejí přes starý systém, seznam nekrmí. Jen objednávky odeslané v aiku.

Filtr **Source** (Zdroj) nahoře na záložce *All* (Vše) vám umožní vidět jen partnerské řádky nebo jen řádky vlastních zákazníků.

## Pohledy

Lišta záložek nad nadpisem je celý smysl stránky. Stejné řádky, šest způsobů pohledu.

- **Board** (Nástěnka). Plánovací pohled, ten, na kterém se stránka otevírá. Každý řádek je karta, která postupuje pruhy od *Backlog* (Fronta) k *Done* (Hotovo). Vysvětleno v další části.
- **All** (Vše). Plochá tabulka, řaditelná a prohledávatelná, s počtem otevřených řádků. Použijte, když hledáte jednu konkrétní věc.
- **By artisan** (Podle řemeslníka). Jeden blok na osobu, podle řemeslníka připojeného k artefaktu, nebo, když ten chybí, k jeho kategorii. Řádky, ke kterým nikdo připojený není, spadají pod *Unassigned* (Nepřiřazeno). Toto je pohled pro rozdělování denní práce.
- **By category** (Podle kategorie). Jeden blok na kategorii artefaktu, takže výrobce koupelových bomb vidí koupelové bomby a mydlář vidí mýdlo.
- **By buyer** (Podle odběratele). Jeden blok na partnerskou organizaci nebo vlastního zákazníka, pro chvíle, kdy sestavujete zásilku.
- **Mixes** (Směsi). Základy a směsi, které potřebují otevřené výrobní příkazy, pro přípraváře. Vysvětleno v [Příprava směsí](/docs/preparing-mixes-cs).

V seskupených pohledech má každý blok nad seznamem kapsli se svým názvem a počtem řádků. Kliknutím na kapsli blok skryjete, dalším kliknutím ho zase zobrazíte. aiku si vaši volbu pamatuje v tomto prohlížeči, takže plánovač, kterému záleží jen na dvou kategoriích, vidí vždy jen dvě.

## Board (Nástěnka)

Šest pruhů zleva doprava. Karta se posouvá doprava, jak práce postupuje, a většina posunů je přetažení.

| Pruh | Co v něm sedí |
| --- | --- |
| Backlog (Fronta) | řádky s artefaktem, na který se ještě nikdo nepodíval |
| Preparing (Připravuje se) | řádky, u kterých jste rozhodli vyrábět, s ustáleným množstvím |
| Assigned (Přiřazeno) | výrobní příkaz existuje a je adresovaný řemeslníkovi, ale nikdo ho ještě nezačal |
| Producing (Vyrábí se) | řemeslník stiskl START u jednoho z jeho úkolů |
| Done (Hotovo) | všechny úkoly na výrobním příkazu jsou hotové; čeká, až ho sklad uloží na místo |

Každá karta ukazuje produkt, požadované množství, kdo ho žádal, a **In stock** (Na skladě), abyste viděli, jestli se vůbec vyplatí vyrábět. Na nástěnku se dostanou jen řádky s artefaktem v této továrně; řádky, u kterých lze sklad prostě sundat z regálu, žijí na vlastní stránce, **Factory → Pre-pick** (Továrna → Předvychystat), viz [Sbírání zboží pro partnera](/docs/gathering-a-partners-goods-cs).

Pod pruhy sedí ještě jeden řádek: **N řádků příliš malých na dávku čeká na společnost · zobrazit**. Partner může požádat o méně než jednu celou dávku, a takový řádek nelze rozumně vyrobit samostatně, takže čeká u kraje cesty místo toho, aby zaplňoval Backlog. Vyzvedne se, jakmile otevřená poptávka po stejném skladu napříč všemi partnerskými seznamy dosáhne dávky, jakmile objednávka vlastního zákazníka u brány stejně spustí zakázku, nebo když stisknete *zobrazit* a vyrobíte to bez ohledu na to. Řádek může čekat dlouho; to je pravdivější popis jeho situace než jakýkoli stav, který bychom pro něj mohli vymyslet. Viz [Dávky, balení a částečné dávky](/docs/batches-packs-and-part-batches-cs).

**Backlog → Preparing.** Přetáhněte kartu a aiku se zeptá *Kolik vyrobit?*. Navrhne požadované množství; zadáte-li víc, přebytek se označí jako *pro sklad*. Pokud má artefakt doporučenou velikost dávky, malé tlačítko **↑** zaokrouhlí množství nahoru na celé dávky. To, o co je řemeslník nakonec požádán, je v **units** (kusech), zaokrouhlené nahoru na nejbližší celou dávku, a to, co se vrátí, se na cestě na regál dělí velikostí balení: 16 kusů desetikusového balení přistane jako 1,6 SKO. Číslo zůstává na kartě editovatelné, dokud je v Preparing.

**Preparing → Assigned.** Přetáhněte kartu a aiku se zeptá *Kdo to vyrobí?*. Navrhne řemeslníka připojeného k artefaktu nebo jeho kategorii, viz [Kdo co vyrábí](/docs/who-makes-what-cs). Vyberte jméno a výrobní příkaz se vytvoří jako koncept, adresovaný této osobě. Otevřete výrobní příkaz a stiskněte **Release to floor** (Uvolnit do dílny), až má začít; do té doby ho řemeslník nevidí. Řemeslníka později změníte kliknutím na jméno na kartě.

**Producing** (Vyrábí se) a **Done** (Hotovo) se posouvají samy podle toho, co se děje na obrazovce dílny. Karta z nástěnky zmizí, jakmile sklad uloží hotové zboží na místo, viz [Uložení hotové výroby](/docs/putting-away-finished-production-cs), nebo když je výrobní příkaz přijatý na sklad ze své vlastní stránky.

Několik karet najednou: kliknutím karty vyberte, pak přetáhněte kteroukoli z nich a přesune se celý výběr. Menu **Everybody** (Všichni) nad nástěnkou ji zúží na jednoho nebo dva řemeslníky, a filtry na rodinu, odběratele a prioritu dělají totéž pro karty.

Postranní panel továrny nese živé počty pro **To produce** (K výrobě) a **Pre-pick** (Předvychystat) vedle svých názvů a mění se sám, jak se nákupní seznamy mění; stránku není třeba znovu načítat, abyste viděli, jestli něco nového přibylo.

Pod nástěnkou a pohledem By artisan sedí **Open job orders per artisan** (Otevřené výrobní příkazy na řemeslníka): jeden čip na osobu s počtem otevřených výrobních příkazů. Červená znamená žádný, oranžová jeden; každý by měl mít aspoň dva, aby nikomu nedošla práce. Křížek na čipu označí danou osobu jako ne-řemeslníka a skryje ji z počtu.

## Odesílání partnerských řádků

Partnerské řádky se odesílají odsud; řádky vlastních zákazníků ne, ty cestují se svou vlastní objednávkou.

- Zaškrtněte partnerské řádky, které můžete odeslat. Upravte množství pro **partial pick** (částečné vychystání), zbytek zůstane otevřený pro pozdější zásilku.
- **Pick into order** (Vychystat do objednávky) shromáždí vaše zaškrtnutí do čekající zásilky na kupující organizaci. Zůstane otevřená v poli *Picked orders* (Vychystané objednávky), dokud ji neodešlete.
- **Send to warehouse** (Odeslat do skladu) předá zásilku vašemu skladu jako běžnou objednávku: vychystá se, zabalí, expeduje a vyfakturuje jako cokoli jiného. Příchozí skladová dodávka kupující organizace se pro ně vytvoří a sleduje postup vašeho skladu. Nikdo stranu kupujícího neaktualizuje ručně.

Zaškrtnutí řádku vlastního zákazníka nedělá nic užitečného. Přeskočí se při stisknutí Pick into order, protože daný produkt už patří k zákaznické objednávce.

## Co je dobré vědět

- Kupujícího otevřený seznam je omezen zhruba na jeden objednávkový cyklus toho, co mu historicky dodáváte, takže to, co k vám dorazí, je filtrovaný požadavek, ne výpis celého katalogu. Pokud vám řádek přijde divný, zeptejte se; kupující se kvůli němu něčeho vzdal.
- První vychystání pro nového partnera vytvoří ve vašem obchodě zákaznický účet pojmenovaný podle kupující organizace. Očekávané. Upozorněte zákaznický servis, ať to nikdo "neuklidí".
- Dokud nestisknete Send to warehouse, vychystaná objednávka je na běžných obrazovkách objednávek neviditelná; jejím domovem je stránka To produce.
- Co expedujete, je to, co říká kupujícího skladová dodávka. Množství nikdy nenafukujte, abyste "odpovídali seznamu".

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Zobrazit frontu:</b> vaše organizace → <b>Factory</b> (Továrna) → <b>To produce</b> (K výrobě). Přepínejte pohledy záložkami <b>Board · All · By artisan · By category · By buyer · Mixes</b> (Nástěnka · Vše · Podle řemeslníka · Podle kategorie · Podle odběratele · Směsi).</li>
<li><b>Rozhodnout množství:</b> <i>Board</i> → přetáhnout kartu z <b>Backlog</b> do <b>Preparing</b> → zadat číslo, nebo stisknout <b>↑</b> pro celé dávky.</li>
<li><b>Vytvořit výrobní příkaz:</b> přetáhnout kartu z <b>Preparing</b> do <b>Assigned</b> → vybrat řemeslníka → otevřít výrobní příkaz → <b>Release to floor</b> (Uvolnit do dílny).</li>
<li><b>Řádky, které stačí jen vychystat:</b> <b>Factory</b> (Továrna) → <b>Pre-pick</b> (Předvychystat), vlastní stránka.</li>
<li><b>Malé řádky čekající na dávku:</b> stiskněte <b>zobrazit</b> na řádku pod nástěnkou.</li>
<li><b>Co stejně dochází:</b> <b>Factory</b> (Továrna) → <b>To restock</b> (K doplnění), viz <a href="/docs/keeping-the-factory-stocked-cs">Udržování zásob v továrně</a>.</li>
<li><b>Skrýt blok:</b> v seskupeném pohledu kliknout na jeho kapsli nad seznamem. Dalším kliknutím zase zobrazit.</li>
<li><b>Jen partneři nebo jen zákazníci:</b> záložka <i>All</i> → filtr <b>Source</b> (Zdroj).</li>
<li><b>Odeslat partnerovi:</b> zaškrtnout řádky → <b>Pick into order</b> (Vychystat do objednávky) → <b>Send to warehouse</b> (Odeslat do skladu) v poli <i>Picked orders</i>.</li>
<li><b>Rozhodnout, kdo co vyrábí:</b> viz <a href="/docs/who-makes-what-cs">Kdo co vyrábí</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Zobrazení seznamu: pozice <b>Production operative</b> (dělník) pro danou továrnu, nebo výše.</li>
<li>Posouvání karet na nástěnce, vytváření a uvolňování výrobních příkazů, vychystávání a odesílání: pozice <b>Production floor supervisor</b> (vedoucí výroby) pro danou továrnu, nebo supervizor organizace. <b>Mix preparer</b> (přípravář směsí) může totéž jen pro směsi.</li>
</ul>
</aside>
