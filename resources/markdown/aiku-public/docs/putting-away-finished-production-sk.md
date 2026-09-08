---
title: Ukladanie hotovej výroby
summary: Sprievodca pre sklad - kde sa objavujú dokončené pracovné príkazy, ako aiku určí, či idú do zberného miesta partnera alebo do bežného skladu, a ako ich naskladniť jedným kódom.
date: 2026-09-08
source_date: 2026-09-08
tags: dispatch, production, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 10
---

<aside class="tldr">
Pre sklad. Keď remeselníci dokončia pracovný príkaz, sám sa skladom nestane - niekto ho musí odniesť niekam a povedať kam. Tento zoznam je <b>Dispatching → From production</b> (Z výroby). Každý riadok hovorí, pre koho tovar je, a navrhne lokáciu: zberné miesto partnera, ak je celý pracovný príkaz pre jedného partnera, inak skladovú lokáciu, ktorú zadáte sami. Stlačte <b>Put away</b> (Uložiť) a tovar sa naskladní do danej lokácie s dávkovým kódom.
</aside>

## Odkiaľ sa riadky berú

Pracovný príkaz sa tu objaví vo chvíli, keď sú všetky jeho úlohy na dielni označené ako DONE, a zostáva tu, kým ho niekto neuloží. Nikto vám ho nemusí posielať ručne.

Pracovné príkazy, na ktorých sa ešte pracuje, tu nie sú. Ak potrebujete vidieť, čo sa blíži, tabuľa výroby <b>To produce</b> má stĺpec <b>Done</b> s tými istými pracovnými príkazmi - pozrite [Práca so zoznamom To produce](/docs/fulfilling-partner-orders-sk).

## Čítanie riadka

| Stĺpec | Čo hovorí |
| --- | --- |
| Job order | referencia, JOxxx-0001 |
| Artisan | kto to vyrobil |
| Made | koľko čoho - 20 × SKO-01, jeden riadok na výrobok |
| For | kód partnerskej organizácie, alebo *Stock* |
| To location | lokácia, kam by to malo ísť |

**For** sa určí z požiadaviek za daným pracovným príkazom. Ak všetky riadky žiadala tá istá partnerská organizácia, tovar je jej a riadok to uvádza, s jej zberným miestom už predvyplneným v poli <b>To location</b>. Je to to isté miesto, ktoré používa [zoznam pre-pick](/docs/gathering-a-partners-goods-sk): čo v ňom leží, je zarezervované a prestáva sa počítať ako dostupné pre kohokoľvek iného.

Ak bol pracovný príkaz vyrobený na sklad, pre vlastného zákazníka, alebo pre viac ako jedného partnera, riadok uvádza *Stock* a pole s lokáciou je prázdne. Zadajte kód lokácie, do ktorej to ukladáte.

## Ukladanie

1. Odneste tovar na zobrazenú lokáciu, alebo na tú, ktorú ste si vybrali.
2. Skontrolujte kód v poli <b>To location</b>. Zmeňte ho, ak ste tovar uložili inde.
3. Stlačte <b>Put away</b>.

Tým sa tovar naskladní do danej lokácie, dostane dávkový kód zložený z referencie pracovného príkazu a kódu výrobku, odpočítajú sa suroviny, ktoré recept udáva ako spotrebované, a pracovný príkaz sa označí ako prijatý. Riadok zmizne a na tabuli výroby opustí stĺpec <b>Done</b>.

Naskladnené množstvo je to, čo remeselníci naozaj vyrobili, nie to, čo sa žiadalo. Pracovný príkaz, ktorý žiadal 25 a dostal 19, naskladní 19.

## Čo sa deje ďalej

- **Tovar partnera** zostáva v jeho zbernom mieste, kým niekto na stránke <b>To produce</b> nepošle vychystanú objednávku do skladu. Vychystávanie je potom len cesta na jedno miesto. Partner vidí riadok ako *Staged for you* (Pripravené pre vás) na svojom nákupnom zozname.
- **Tovar vlastného zákazníka** ide do bežného skladu a čakajúci dodací list sa uvoľní na vychystávanie sám, keďže nedostatok, ktorý ho zdržiaval, je preč.
- **Stock** (Sklad) sa jednoducho stane dostupným.

## Čo je dobré vedieť

- **Táto záložka sa zobrazí** iba organizáciám, ktoré majú dielňu.
- **Jeden pracovný príkaz, jedna lokácia.** Ak sa pracovný príkaz naozaj musí rozdeliť medzi dve miesta, uložte ho na sklad a nechajte zoznam pre-pick presunúť partnerov podiel.
- **Zlá lokácia sa opravuje ako každá iná skladová chyba** - presuňte tovar medzi lokáciami. Pracovný príkaz sa pritom znova neotvára.
- **Nič tu nie je zarezervované, kým to nie je v zbernom mieste.** Tovar uložený do bežného skladu môže byť vychystaný pre kohokoľvek.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zoznam:</b> vaša organizácia → <b>Warehouse</b> → <b>Dispatching</b> → záložka <b>From production</b>.</li>
<li><b>Naskladniť:</b> skontrolujte <b>To location</b> → <b>Put away</b>.</li>
<li><b>Zistiť, čo je v zbernom mieste:</b> <b>Warehouse</b> → <b>Locations</b> → lokácia → záložka <b>SKOs</b>.</li>
<li><b>Čo dielňa ešte dlhuje:</b> <b>Factory</b> → <b>To produce</b> → <b>Board</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú v karte zamestnanca v Human Resources a nesú so sebou oprávnenia.</li>
<li>Vidieť zoznam a ukladať tovar: pozícia dispatching pre sklad, alebo organisation supervisor.</li>
</ul>
</aside>
