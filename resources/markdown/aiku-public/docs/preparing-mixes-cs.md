---
title: Příprava směsí
summary: Pro přípraváře a plánovače - jak se ze směsi nebo základu stane něco, co továrna sleduje, jak záložka Mixes zjistí, co je třeba připravit, a jak plynou přípravářovy výrobní příkazy.
date: 2026-09-08
source_date: 2026-09-08
tags: production, crafts
category: production
help_routes: grp.org.productions.show.to_produce.mixes, grp.org.productions.show.crafts.raw_materials
series: Ordering from partners
order: 6
---

<aside class="tldr">
Pro člověka, který připravuje směsi a základy dřív, než řemeslníci mohou začít, a pro plánovače, který mu tuto práci posílá. Směs se vyrábí ve vlastní továrně, takže ji aiku vede jako <b>raw material</b> (surovinu, kterou řemeslníci spotřebovávají) i jako <b>artefakt</b> (který vyrábí přípravář). Jakmile jsou propojené, záložka <b>Mixes</b> (Směsi) na <a href="/docs/fulfilling-partner-orders-cs">To produce</a> spočítá, kolik dané směsi z otevřených výrobních příkazů chybí, a přetažení karty na přípraváře z toho udělá výrobní příkaz. Nastavení kategorií a řemeslníků je v <a href="/docs/who-makes-what-cs">Kdo co vyrábí</a>.
</aside>

## Proč je směs dvě věci

Recept na koupelovou bombu říká "0,5 kg základové směsi na kus". Tato základová směs se nekupuje, připravuje se v továrně z vlastních surovin. Žije tedy dvakrát:

- Jako **surovina**, aby ji recepty mohly spotřebovávat a sklad se odečetl, když je hotový produkt přijatý na sklad.
- Jako **artefakt**, s vlastním receptem a vlastními výrobními příkazy, aby měl přípravář co dělat a dávku, kterou přijme na sklad.

Propojení obou je jedno pole na surovině: **Made in-house as** (Vyrábí se ve vlastní továrně jako). Nastavte ho na artefakt dané směsi. To je celé nastavení.

## Nastavení směsi

1. **Vytvořte artefakt** pro směs pod **Factory → Crafts → Artefacts** (Továrna → Řemesla → Artefakty), s recepturou a vlastními surovinami, jako u kteréhokoli jiného artefaktu. Dejte mu sklad (SKU), aby přijaté dávky měly kam jít.
2. **Vytvořte nebo otevřete surovinu** pro směs pod **Factory → Crafts → Raw materials** (Továrna → Řemesla → Suroviny). Upravte ji, nastavte **Made in-house as** (Vyrábí se ve vlastní továrně jako) na artefakt z kroku 1 a dejte jí stejný sklad (SKU).
3. **Použijte surovinu v receptech.** U každého produktu, který danou směs potřebuje, přidejte směs do správného kroku receptu s množstvím na kus.
4. **Připojte přípraváře** k artefaktu směsi, nebo ke kategorii obsahující všechny směsi, pod *Usually made by* (Obvykle vyrábí). Výrobní příkazy pro směsi pak jdou k této osobě.

## Záložka Mixes

**Factory → To produce → Mixes** (Továrna → K výrobě → Směsi) je malá nástěnka se čtyřmi pruhy: **Needed** (Potřeba), **Assigned** (Přiřazeno), **Mixing** (Míchá se) a **Done** (Hotovo). Zobrazuje jen suroviny vyráběné ve vlastní továrně, které potřebuje nějaký otevřený výrobní příkaz. Výrobní příkaz je otevřený od okamžiku vytvoření až do přijetí na sklad.

Karta v **Needed** je směs, které se továrně nedostává. Červené číslo je nedostatek: co potřebují otevřené výrobní příkazy, z jejich množství a množství na jednotku podle receptu, minus co je na skladě, minus co se už míchá. Pod ním *for* vypíše kódy produktů, které čekají, aby přípravář věděl, co je zablokované, a jméno obvyklého přípraváře, je-li připojený.

Přetáhněte kartu z **Needed** do **Assigned**. aiku se zeptá na množství, navrhne nedostatek, abyste ho mohli zaokrouhlit na rozumnou dávku, a *Kdo to smíchá?*. Vyberte přípraváře a výrobní příkaz se vytvoří jako koncept, adresovaný jemu, s odkazem na kartě. Otevřete ho a stiskněte **Release to floor** (Uvolnit do dílny), až má začít.

**Mixing** (Míchá se) a **Done** (Hotovo) se posouvají samy: karta jde do Mixing, jakmile přípravář stiskne START, a do Done, jakmile je hotový poslední úkol. Z nástěnky zmizí, jakmile je dávka uložena na sklad.

## Co dělá přípravář

Přípravář vede svou vlastní linku, takže má pro danou továrnu pozici **Mix preparer** (přípravář směsí). To mu umožňuje otevřít záložku Mixes, vytvářet a uvolňovat vlastní výrobní příkazy a přijímat je na sklad, aniž by na kohokoli čekal. Nemůže sahat na výrobní příkazy adresované jiným lidem; to zůstává na plánovači. Na dílně pracuje jako kterýkoli řemeslník: jeho úkoly se objeví na [obrazovce dílny](/docs/working-the-floor-screen-cs), mačká START a DONE, a když je hotový poslední krok, dávka se uloží na sklad s kódem dávky, buď skladem z [Dispatching → From production](/docs/putting-away-finished-production-cs), nebo přípravářem ze stránky výrobního příkazu. Od té chvíle se směs vykazuje jako na skladě a řemeslníci mohou vyrábět své produkty.

Pokud přípravář není placený úkolovou mzdou, je to nastavení mezd, ne důvod přeskočit dílnu. Záznam o tom, kdo připravil kterou dávku a kdy, je to, co dává hotovému produktu zpětnou vysledovatelnost k jeho surovinám.

## Co je dobré vědět

- Směs nemůže potřebovat sama sebe. Pokud vlastní recept artefaktu směsi obsahuje tutéž surovinu, tento řádek se ignoruje.
- Záložka Mixes čte jen výrobní příkazy v této továrně. Produkt vyráběný v jiné továrně tu poptávku nevytváří.
- Výrobní příkaz na směs se počítá jako vyráběný, dokud není uložen na sklad, i když jsou hotové všechny kroky. Ukládejte dávky na sklad včas a nedostatek zůstane pravdivý.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Propojit směs:</b> <b>Factory → Crafts → Raw materials</b> (Továrna → Řemesla → Suroviny) → otevřít směs → <b>Edit</b> (Upravit) → <b>Made in-house as</b> (Vyrábí se ve vlastní továrně jako).</li>
<li><b>Vidět, co připravit:</b> <b>Factory → To produce → Mixes</b> (Továrna → K výrobě → Směsi).</li>
<li><b>Poslat práci:</b> přetáhnout kartu z <b>Needed</b> do <b>Assigned</b> → množství a přípravář → otevřít výrobní příkaz → <b>Release to floor</b> (Uvolnit do dílny).</li>
<li><b>Provést práci:</b> <b>Factory → Jobs</b> (Továrna → Úkoly) → <b>START</b> / <b>DONE</b>; pak se dávka uloží na sklad z <b>Warehouse → Dispatching → From production</b> nebo ze stránky výrobního příkazu.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Zobrazení záložky Mixes a práce na dílně: pozice <b>Production operative</b> (dělník) pro danou továrnu, nebo výše.</li>
<li>Vytváření výrobních příkazů na směsi a uvolňování i přijímání vlastních: pozice <b>Mix preparer</b> (přípravář směsí) pro danou továrnu. Tuto pozici přípravář potřebuje.</li>
<li>Vše ostatní, včetně výrobních příkazů jiných lidí a propojení suroviny s jejím artefaktem: pozice <b>Production floor supervisor</b> (vedoucí výroby) pro danou továrnu, nebo supervizor organizace.</li>
</ul>
</aside>
