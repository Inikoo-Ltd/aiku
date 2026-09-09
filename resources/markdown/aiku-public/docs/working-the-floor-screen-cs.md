---
title: Práce s obrazovkou dílny
summary: Průvodce pro řemeslníka - obrazovka Moje úkoly, seznam úkolů, který se sám aktualizuje, START a DONE, a co dělat, když vyrobíte míň, než bylo požadováno.
date: 2026-09-08
source_date: 2026-09-08
tags: production, floor, artisan
category: production
series: Ordering from partners
order: 9
---

<aside class="tldr">
Pro ty, kdo vyrábějí. <b>Factory → Jobs</b> (Továrna → Úkoly) je celý váš den na jedné obrazovce: vlevo úkoly určené vám, uprostřed ten, na kterém právě pracujete. Stiskněte <b>START</b>, vyrobte to, zadejte kolik kusů, stiskněte <b>DONE</b> (Hotovo). Pokud jste vyrobili míň, než bylo požadováno, obrazovka se zeptá jen na jednu věc: dokončit úkol takto, nebo zbytek přenést do nového úkolu. Nic dalšího se nevyplňuje.
</aside>

## Obrazovka

Stránka je uspořádaná jako doručená pošta.

**Vlevo, vždy vidět:** seznam. Nahoře dva počítadla - kolik kusů jste dnes vyrobili a kolik úkolů jste dnes dokončili. Pod nimi **Your jobs** (Vaše úkoly), práce určená vám. Pokud vám to vaše pozice umožňuje vybírat i z volných úkolů, následuje sekce **Open jobs** (Volné úkoly). Dole **Finished today** (Dnes dokončeno), co jste už uzavřeli.

**Vpravo:** to, co máte právě vybráno. Než začnete, jsou to detaily úkolu s velkým tlačítkem **START**. Jakmile začnete, je to pracovní karta s hodinami.

Seznam se nemusí obnovovat ručně. Když vám plánovač přiřadí úkol, když kolega začne pracovat na volném úkolu, když vy sami nějaký uzavřete, seznam se změní sám, na všech obrazovkách zobrazujících danou továrnu.

## Jeden řádek v seznamu

Každý řádek je jeden úkol na jednom výrobním příkazu:

| Řádek | Význam |
| --- | --- |
| Kód | výrobek, který se má vyrobit - SKO-01 |
| Název | jeho název |
| Úkol · výrobní příkaz | daný krok (Production, Labelling...) a odkaz na výrobní příkaz |
| 0/25 | vyrobeno zatím / požadováno |

Řádek se zelenou šipkou ▶ a časem je ten, na kterém právě pracujete, a čas ukazuje, kdy jste začali. Oranžová poznámka pod řádkem znamená, že úkol čeká na směs, nebo že ho už má otevřený někdo jiný.

## Práce na úkolu

1. Klepněte na řádek. Vpravo se zobrazí detaily.
2. Stiskněte **START**. Spustí se hodiny a řádek dostane zelenou značku.
3. Vyrobte to.
4. Zadejte počet kusů, které jste vyrobili, do pole **Quantity made** (Vyrobené množství). Pokud jste mistr nebo výše, je tam i pole **Rejected** (Zmetky) pro kusy, které neprošly kontrolou.
5. Stiskněte **DONE**.

Vyrobili jste přesně tolik, kolik bylo požadováno? Tím je hotovo. Úkol se uzavře, výrobní příkaz je dokončen a sklad dostane informaci, že má co uložit - viz [Ukládání hotové výroby](/docs/putting-away-finished-production-cs).

## Když vyrobíte míň, než bylo požadováno

Zadejte skutečný počet a stiskněte **DONE**. Vstupní řádek se nahradí krátkým panelem: *19 hotovo · 6 zbývá* a třemi tlačítky.

- **Continue later** (Pokračovat později) - úkol se uzavře na 19 a v seznamu se vám objeví nový výrobní příkaz na zbývajících 6, určený vám. Vyzvedněte si ho zítra, nebo kdykoli dorazí materiál.
- **Job finished** (Úkol dokončen) - úkol se uzavře na 19 a tím to končí. Plánovač uvidí výrobní příkaz, který žádal 25 a dostal 19.
- **Back** (Zpět) - změnit číslo.

V obou případech se úkol, na kterém jste pracovali, uzavře a proplatí podle toho, co jste skutečně vyrobili. Nic vám v seznamu nezůstane napůl otevřené.

## Co je dobré vědět

- **Jeden úkol najednou.** Dokud máte otevřený úkol, tlačítka START jsou vypnutá. Nejdřív ho uzavřete.
- **Vaše odměna za úkol** se řídí číslem v Quantity made, jak je vysvětleno v [Pozice v továrně](/docs/factory-positions-cs). Zmetky se neplatí.
- **Open jobs** jsou úkoly bez přiřazeného řemeslníka, nebo přiřazené někomu jinému. Vzít si takový úkol je v pořádku, pokud to vaše pozice dovoluje; pak je váš, dokud ho neuzavřete.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Vaše obrazovka:</b> vaše organizace → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Začít:</b> klepněte na řádek → <b>START</b>.</li>
<li><b>Dokončit:</b> zadejte <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Míň, než bylo požadováno:</b> <b>DONE</b> → <b>Continue later</b> nebo <b>Job finished</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Zobrazení vlastních úkolů, START a DONE: pozice <b>Operative</b> pro danou továrnu.</li>
<li>Zobrazení a přebírání <b>Open jobs</b>, zaznamenávání zmetků: <b>Foreman</b>, <b>Mix preparer</b> nebo <b>Floor supervisor</b>.</li>
</ul>
</aside>
