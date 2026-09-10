---
title: Udržiavanie zásob továrne
summary: Stránka To restock - ktoré artefakty dôjdu ako prvé, ako dlho trvá továrni čokoľvek vyrobiť, a ako dostať vlastnú doplňovaciu prácu na board To produce.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, planning
category: production
series: Ordering from partners
order: 11
---

<aside class="tldr">
Pre toho, kto plánuje týždeň továrne. <a href="/docs/fulfilling-partner-orders-sk">To produce</a> odpovedá na otázku <i>čo si niekto vyžiadal</i>. <b>To restock</b> odpovedá na inú otázku: <i>čo nám dôjde, či už si to niekto vyžiadal, alebo nie</i>. Zoradí všetko, čo továreň vyrába, podľa toho, koľko dní pokrytia zostáva, meraného voči tomu, ako dlho tejto továrni skutočne trvá niečo vyrobiť, a umožní vám posunúť to, čo sa oplatí vyrobiť, na board To produce.
</aside>

## Meradlom je lead time

Každý bucket na tejto stránke sa meria v **lead times** (dodacích lehotách), nie v dňoch. Lead time je priemerný počet dní od začiatku práce na dielni po jej návrat do skladu, vypočítaný z vlastných pracovných príkazov tejto továrne za posledný rok. Ak je hotových pracovných príkazov menej ako päť, nie je čo merať, tak aiku namiesto toho použije odhad — sedem dní, ak niekto na továrni nenastaví iné číslo — a pri čísle napíše *estimate* (odhad).

Preto buckety čítajú tak, ako čítajú. Artefakt so štyrmi dňami pokrytia nie je v ohrození v továrni, ktorá obráti prácu za dva dni; v tej, ktorej to trvá týždeň, je už stratený.

## Buckety

| Bucket | Čo znamená |
| --- | --- |
| Out of stock | nič na regáli |
| Doomed | dôjde skôr, než by mohlo dorobiť čokoľvek začaté dnes |
| Critical | dôjde do dvoch lead times |
| Danger | dôjde do troch lead times |
| Watch | dôjde do štyroch lead times |
| Covered | viac ako štyri lead times pokrytia |
| Dead stock | hodnota na regáli a žiadne použitie |
| Never made yet | artefakt bez akéhokoľvek skladového záznamu za sebou |

Každý bucket nesie tri čísla: koľko artefaktov v ňom je, koľko z nich je **already in hand** (už riešené) a koľko je **untouched** (nedotknuté). In hand znamená, že sa tým už niekto zaoberá — otvorený riadok na boarde To produce, alebo pracovný príkaz na dielni. Untouched je číslo, ktoré treba riešiť.

Kliknutím na buckety zvolíte, čo ukazuje zoznam nižšie. Otvára sa na **Out of stock, Doomed a Critical**, čo je čestný ranný zoznam.

## Dráhy

Pod bucketmi je tá istá práca rozložená do štyroch dráh:

- **To do** — artefakty v bucketoch, ktoré ste zvolili, s ktorými sa ešte nič nerobilo. Najurgentnejšie prvé. Každý riadok nesie skladový kód, čo je na regáli, dni pokrytia, rodinu artefaktu, kto ho zvyčajne vyrába, a počet **units** (kusov), na ktoré by sa vystavila práca: odporúčané objednávacie množstvo prevedené na kusy a zaokrúhlené nahor na najbližšiu celú dávku. Čokoľvek, čo si už vyžiadal partner, je vynechané — to je práca pre To produce, nie pre túto stránku.
- **Queued** — riadky už čakajúce na boarde To produce, či už prišli od partnera, alebo odtiaľto.
- **Producing** — riadky s pracovným príkazom na dielni, s jeho referenciou a remeselníkom.
- **Restocked** — čo sa vrátilo z dielne za posledné dva týždne, aby ste videli, že stránka funguje.

## Umiestnenie práce na board

Odškrtnite riadky v **To do** a stlačte tlačidlo, aby ste ich zaradili do frontu. Každý sa stane riadkom na boarde To produce bez partnera a bez zákazníka za sebou: jednoducho práca, ktorú továreň dlhuje sama sebe. Odtiaľ sa plánuje, priraďuje a vyrába presne ako riadok partnera, a opúšťa board, keď sa hotový tovar uloží.

Riadok sa preskočí, a povie to, ak je ten istý tovar už otvorený na boarde. Nemôžete zaradiť tú istú vec dvakrát dvojitým stlačením tlačidla.

## Čo je dobré vedieť

- **Položky On demand tu nie sú.** Artefakt, ktorého SKO je označené *On Demand*, sa vyrába, keď je o neho požiadané, a nemá žiadne pokrytie, ktoré by mohlo dôjsť.
- **Dead stock je otázka, nie úloha.** Hodnota stojaca bez pohybu a bez použitia zvyčajne chce rozhovor s obchodom, nie pracovný príkaz.
- **Stránka sa počíta nanovo.** Nič sa neukladá, nič netreba upratovať, a bucket sa sám vyprázdni, keď tovar dorazí.
- **Dni pokrytia pochádzajú z tej istej predpovede**, akú používa zvyšok aiku, viď [Ako aiku predpovedá, čo vám dôjde](/docs/how-aiku-predicts-what-you-run-out-of-sk).

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Stránka:</b> vaša organizácia → <b>Factory</b> → <b>To restock</b>.</li>
<li><b>Zmeniť, čo dráhy ukazujú:</b> kliknite na dlaždice bucketov hore.</li>
<li><b>Zaradiť vlastnú prácu:</b> odškrtnite riadky v <b>To do</b> → tlačidlo zaradenia do frontu → objavia sa na <b>To produce</b>.</li>
<li><b>Nastaviť odhadovaný lead time</b>, kým je história tenká: vo vlastných nastaveniach továrne; keď je hotových päť pracovných príkazov, nameraná hodnota preberie kontrolu sama.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú v karte zamestnanca v Human Resources a nesú so sebou oprávnenia.</li>
<li>Vidieť stránku: pozícia <b>Production operative</b> pre továreň, alebo vyššia.</li>
<li>Zaraďovanie práce na To produce: pozícia <b>Production floor supervisor</b> pre továreň, alebo organisation supervisor.</li>
</ul>
</aside>
