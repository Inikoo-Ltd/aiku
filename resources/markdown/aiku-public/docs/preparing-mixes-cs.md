---
title: Příprava směsí
summary: Pro přípraváře a plánovače - jak se ze směsi nebo základu stane něco, co továrna sleduje, jak záložka Mixes zjistí, co je třeba připravit, a jak plynou přípravářovy výrobní příkazy.
date: 2026-09-02
source_date: 2026-09-02
tags: production, crafts
category: production
series: Ordering from partners
order: 6
---

<aside class="tldr">
Pro člověka, který připravuje směsi a základy dřív, než řemeslníci mohou začít, a pro plánovače, který mu tuto práci posílá. Směs se vyrábí ve vlastní továrně, takže ji aiku vede jako <b>raw material</b> (surovinu, kterou řemeslníci spotřebovávají) i jako <b>artefakt</b> (který vyrábí přípravář). Jakmile jsou propojené, záložka <b>Mixes</b> (Směsi) na <a href="/docs/fulfilling-partner-orders-cs">To produce</a> spočítá, kolik dané směsi je třeba z otevřených výrobních příkazů, a jedno tlačítko z toho udělá výrobní příkazy pro přípraváře. Nastavení kategorií a řemeslníků je v <a href="/docs/who-makes-what-cs">Kdo co vyrábí</a>.
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

**Factory → To produce → Mixes** (Továrna → K výrobě → Směsi) vypíše každou surovinu vyráběnou ve vlastní továrně, kterou potřebuje nějaký otevřený výrobní příkaz. Výrobní příkaz je otevřený od okamžiku vytvoření až do přijetí na sklad.

U každé směsi vidíte:

- **Needed** (Potřeba): množství z otevřených výrobních příkazů vynásobené množstvím na kus podle receptu, sečtené napříč produkty.
- **On hand** (Na skladě): aktuální sklad dané směsi.
- **Being made** (Vyrábí se): množství v otevřených výrobních příkazech na samotnou směs.
- **Short** (Chybí): potřeba mínus sklad mínus vyráběné množství. Řádky s nedostatkem jsou první a zobrazují se červeně.
- **Needed for** (Potřeba pro): kódy produktů, které danou směs spotřebovávají, aby přípravář věděl, co čeká.

Zaškrtněte směsi k přípravě, upravte množství, pokud nedostatek neodpovídá správné dávce, a stiskněte **Create job orders** (Vytvořit výrobní příkazy). Vytvoří se jeden výrobní příkaz na přípraváře, adresovaný jemu, jako koncept. Otevřete ho a stiskněte *Release to floor* (Uvolnit do dílny), až má začít.

## Co dělá přípravář

Přípravář vede svou vlastní linku, takže má pro danou továrnu pozici **Mix preparer** (přípravář směsí). To mu umožňuje otevřít záložku Mixes, vytvářet a uvolňovat vlastní výrobní příkazy a přijímat je na sklad, aniž by na kohokoli čekal. Nemůže sahat na výrobní příkazy adresované jiným lidem; to zůstává na plánovači. Na dílně pracuje jako kterýkoli řemeslník: jeho úkoly se objeví na obrazovce dílny, mačká START a DONE, a když je hotový poslední krok, výrobní příkaz se přijme na sklad s kódem dávky. Od té chvíle se směs vykazuje jako na skladě a řemeslníci mohou vyrábět své produkty.

Pokud přípravář není placený úkolovou mzdou, je to nastavení mezd, ne důvod přeskočit dílnu. Záznam o tom, kdo připravil kterou dávku a kdy, je to, co dává hotovému produktu zpětnou vysledovatelnost k jeho surovinám.

## Co je dobré vědět

- Směs nemůže potřebovat sama sebe. Pokud vlastní recept artefaktu směsi obsahuje tutéž surovinu, tento řádek se ignoruje.
- Záložka Mixes čte jen výrobní příkazy v této továrně. Produkt vyráběný v jiné továrně tu poptávku nevytváří.
- "Being made" (Vyrábí se) počítá výrobní příkaz, dokud není přijatý na sklad, i když jsou hotové všechny kroky. Přijímejte výrobní příkazy na sklad včas a čísla zůstanou pravdivá.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Propojit směs:</b> <b>Factory → Crafts → Raw materials</b> (Továrna → Řemesla → Suroviny) → otevřít směs → <b>Edit</b> (Upravit) → <b>Made in-house as</b> (Vyrábí se ve vlastní továrně jako).</li>
<li><b>Vidět, co připravit:</b> <b>Factory → To produce → Mixes</b> (Továrna → K výrobě → Směsi).</li>
<li><b>Poslat práci:</b> zaškrtnout směsi → <b>Create job orders</b> (Vytvořit výrobní příkazy) → otevřít výrobní příkaz → <b>Release to floor</b> (Uvolnit do dílny).</li>
<li><b>Provést práci:</b> <b>Factory → Floor</b> (Továrna → Dílna) (My tasks / Moje úkoly) → <b>START</b> / <b>DONE</b>; pak se výrobní příkaz přijme na sklad ze své vlastní stránky.</li>
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
