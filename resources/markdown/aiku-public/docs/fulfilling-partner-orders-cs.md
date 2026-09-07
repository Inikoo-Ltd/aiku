---
title: Práce se seznamem To produce
summary: Průvodce pro továrnu - jedna fronta všeho, co továrna dluží, partnerským organizacím i vlastním zákazníkům, seskupená tak, jak přemýšlí plánovač výroby.
date: 2026-09-02
source_date: 2026-09-02
tags: production, procurement, intercompany, dispatch
category: production
series: Ordering from partners
order: 4
---

<aside class="tldr">
Pro lidi, kteří <em>vyrábějí věci</em>, a pro osobu, která plánuje den v továrně. <b>To produce</b> (K výrobě) je fronta továrny: každý řádek, který si vyžádala partnerská organizace, plus každý řádek, který objednal vlastní zákazník a který továrna nemá skladem. Seskupíte to podle řemeslníka, kategorie nebo odběratele, zaškrtnete, co lze poslat partnerům, a zbytek papírování se odehraje samo. Jste v partnerském toku noví? Začněte <a href="/docs/ordering-from-a-partner-organisation-cs">přehledem</a>. Chcete, aby seznam věděl, kdo co vyrábí? Přečtěte si nejdřív <a href="/docs/who-makes-what-cs">Kdo co vyrábí</a>.
</aside>

## Odkud řádky pocházejí

**Factory → To produce** (Továrna → K výrobě) se plní ze dvou zdrojů. Řádek sem sami nikdy nepíšete.

- **Partnerské požadavky.** Sesterské organizace dají to, co potřebují, na svůj [nákupní seznam](/docs/buying-from-a-partner-cs). Každý otevřený řádek adresovaný vaší továrně se tu objeví s kupujícím, množstvím a prioritou, kterou nastavili.
- **Vlastní zákazníci.** Když je v jejich obchodě odeslaná objednávka, aiku se podívá na každý produkt. Pokud je za ním sklad nedostatečný a tento sklad vyrábí továrna, nedostatek se sem dostane jako řádek, označený zákazníkem a číslem objednávky. Jakmile je daná objednávka expedovaná, řádek se sám zavře.

Objednávky, které přicházejí přes starý systém, seznam nekrmí. Jen objednávky odeslané v aiku.

Filtr **Source** (Zdroj) nahoře na záložce *All* (Vše) vám umožní vidět jen partnerské řádky nebo jen řádky vlastních zákazníků.

## Čtyři pohledy

Lišta záložek nad nadpisem je celý smysl stránky. Stejné řádky, čtyři způsoby pohledu.

- **All** (Vše). Plochá tabulka, řaditelná a prohledávatelná, s počtem otevřených řádků. Použijte, když hledáte jednu konkrétní věc.
- **By artisan** (Podle řemeslníka). Jeden blok na osobu, podle řemeslníka připojeného k artefaktu, nebo, když ten chybí, k jeho kategorii. Řádky, ke kterým nikdo připojený není, spadají pod *Unassigned* (Nepřiřazeno). Toto je pohled pro rozdělování denní práce.
- **By category** (Podle kategorie). Jeden blok na kategorii artefaktu, takže výrobce koupelových bomb vidí koupelové bomby a mydlář vidí mýdlo.
- **By buyer** (Podle odběratele). Jeden blok na partnerskou organizaci nebo vlastního zákazníka, pro chvíle, kdy sestavujete zásilku.

V seskupených pohledech má každý blok nad seznamem kapsli se svým názvem a počtem řádků. Kliknutím na kapsli blok skryjete, dalším kliknutím ho zase zobrazíte. aiku si vaši volbu pamatuje v tomto prohlížeči, takže plánovač, kterému záleží jen na dvou kategoriích, vidí vždy jen dvě.

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
<li><b>Zobrazit frontu:</b> vaše organizace → <b>Factory</b> (Továrna) → <b>To produce</b> (K výrobě). Přepínejte pohledy záložkami <b>All · By artisan · By category · By buyer</b> (Vše · Podle řemeslníka · Podle kategorie · Podle odběratele).</li>
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
<li>Vychystávání, odesílání a vytváření výrobních příkazů: pozice <b>Production floor supervisor</b> (vedoucí výroby) pro danou továrnu, nebo supervizor organizace.</li>
</ul>
</aside>
