---
title: Príprava zmesí
summary: Pre prípravára a plánovača - ako sa zo zmesi alebo základu stane niečo, čo továreň sleduje, ako záložka Mixes vypočíta, čo pripraviť, a ako prebiehajú prípravárove pracovné príkazy.
date: 2026-09-08
source_date: 2026-09-08
tags: production, crafts
category: production
help_routes: grp.org.productions.show.to_produce.mixes, grp.org.productions.show.crafts.raw_materials
series: Ordering from partners
order: 6
---

<aside class="tldr">
Pre osobu, ktorá pripravuje zmesi a základy skôr, než môžu remeselníci začať, a pre plánovača, ktorý im túto prácu posiela. Zmes (mix) sa vyrába priamo v továrni, takže ju aiku vedie ako <b>surovinu</b> (raw material) — remeselníci ju spotrebúvajú — aj ako <b>artefakt</b> — prípravár ju vyrába. Po prepojení záložka <b>Mixes</b> na stránke <a href="/docs/fulfilling-partner-orders-sk">To produce</a> vypočíta, koľko z každej zmesi chýba na základe otvorených pracovných príkazov, a pretiahnutím karty k prípravárovi z toho vznikne pracovný príkaz. Nastavenie kategórií a remeselníkov je v <a href="/docs/who-makes-what-sk">Kto čo vyrába</a>.
</aside>

## Prečo je zmes dvomi vecami

Recept na kúpeľovú guľu hovorí "0,5 kg základnej zmesi na kus". Táto základná zmes sa nekupuje, pripravuje sa v továrni z vlastných surovín. Preto existuje dvakrát:

- Ako **surovina** (raw material), aby ju recepty mohli spotrebúvať a aby sa sklad odpočítal pri prijatí hotového produktu.
- Ako **artefakt**, s vlastným receptom a vlastnými pracovnými príkazmi, aby mal prípravár prácu a dávku (batch), ktorú príjme na sklad.

Prepojenie medzi oboma je jedno pole na surovine: **Made in-house as** (vyrobené priamo v továrni ako). Nastavte ho na artefakt danej zmesi. To je celé nastavenie.

## Nastavenie zmesi

1. **Vytvorte artefakt** pre zmes pod **Factory → Crafts → Artefacts**, s krokmi receptu a vlastnými surovinami, ako pri každom inom artefakte. Priraďte mu sklad (SKU), aby prijaté dávky mali kam ísť.
2. **Vytvorte alebo otvorte surovinu** pre zmes pod **Factory → Crafts → Raw materials**. Upravte ju, nastavte **Made in-house as** na artefakt z kroku 1 a priraďte jej ten istý sklad (SKU).
3. **Použite surovinu v receptoch.** Pri každom produkte, ktorý zmes potrebuje, ju pridajte do príslušného kroku receptu s množstvom na kus.
4. **Pripojte prípravára** k artefaktu zmesi, alebo ku kategórii, ktorá zoskupuje všetky zmesi, pod *Usually made by*. Pracovné príkazy na zmesi potom smerujú k tejto osobe.

## Záložka Mixes

**Factory → To produce → Mixes** je malý board so štyrmi dráhami: **Needed**, **Assigned**, **Mixing** a **Done**. Zobrazuje iba suroviny vyrábané priamo v továrni, ktoré potrebuje niektorý otvorený pracovný príkaz. Pracovný príkaz je otvorený od okamihu vytvorenia až kým nie je prijatý na sklad.

Karta v **Needed** je zmes, ktorej má továreň nedostatok. Červené číslo je nedostatok (shortfall): čo potrebujú otvorené pracovné príkazy, z ich množstiev a množstva na kus z receptu, mínus to, čo je na sklade, mínus to, čo sa už mieša. Pod ním *for* uvádza kódy produktov, ktoré čakajú, aby prípravár vedel, na čo je práca blokovaná, a meno obvyklého prípravára, ak je pripojený.

Pretiahnite kartu z **Needed** do **Assigned**. aiku sa opýta na množstvo a navrhne nedostatok, aby ste ho mohli zaokrúhliť na rozumnú dávku, a *Kto to zmieša?*. Vyberte prípravára a pracovný príkaz sa vytvorí v stave návrhu (draft), adresovaný jemu, s jeho referenciou na karte. Otvorte ho a stlačte **Release to floor** (uvoľniť na dielňu), keď má začať.

**Mixing** a **Done** sa posúvajú samy: karta prejde do Mixing, keď prípravár stlačí START, a do Done, keď je hotová posledná úloha. Board opustí, keď je dávka uložená na sklad.

## Čo robí prípravár

Prípravár vedie vlastnú linku, preto má pozíciu <b>Mix preparer</b> (prípravár zmesí) pre továreň. Vďaka tomu môže otvoriť záložku Mixes, vytvárať a uvoľňovať vlastné pracovné príkazy a prijímať ich na sklad bez čakania na kohokoľvek. Na pracovné príkazy adresované iným ľuďom nesiaha; tie ostávajú plánovačovi. V dielni pracuje ako každý remeselník: jeho úlohy sa zobrazia na [obrazovke dielne](/docs/working-the-floor-screen-sk), stláča START a DONE, a keď je dokončený posledný krok, dávka sa uloží na sklad s kódom dávky, buď skladom z [Dispatching → From production](/docs/putting-away-finished-production-sk), alebo prípravárom zo stránky pracovného príkazu. Od tej chvíle je zmes na sklade a remeselníci môžu vyrábať svoje produkty.

Ak prípravár nie je platený úkolovo, je to nastavenie mzdy, nie dôvod preskočiť dielňu. Záznam o tom, kto akú dávku pripravil a kedy, je to, čo dáva vysledovateľnosť od hotového produktu späť k jeho surovinám.

## Čo je dobré vedieť

- Zmes nemôže potrebovať samu seba. Ak recept artefaktu zmesi uvádza tú istú surovinu, tento riadok sa ignoruje.
- Záložka Mixes číta iba pracovné príkazy tejto továrne. Produkt vyrábaný v inej továrni tu dopyt nevytvára.
- Pracovný príkaz na zmes sa počíta ako vo výrobe, kým nie je uložený na sklad, aj keď sú všetky úlohy hotové. Ukladajte dávky včas a nedostatok zostane pravdivý.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Prepojiť zmes:</b> <b>Factory → Crafts → Raw materials</b> → otvorte zmes → <b>Edit</b> → <b>Made in-house as</b>.</li>
<li><b>Vidieť, čo pripraviť:</b> <b>Factory → To produce → Mixes</b>.</li>
<li><b>Poslať prácu:</b> potiahnite kartu z <b>Needed</b> do <b>Assigned</b> → množstvo a prípravár → otvorte pracovný príkaz → <b>Release to floor</b>.</li>
<li><b>Vykonať prácu:</b> <b>Factory → Jobs</b> → <b>START</b> / <b>DONE</b>; potom sa dávka uloží na sklad z <b>Warehouse → Dispatching → From production</b> alebo zo stránky pracovného príkazu.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú v karte zamestnanca v Human Resources a nesú so sebou oprávnenia.</li>
<li>Vidieť záložku Mixes a pracovať v dielni: pozícia <b>Production operative</b> (operátor) pre továreň, alebo vyššia.</li>
<li>Vytváranie pracovných príkazov na zmesi a uvoľňovanie a prijímanie vlastných: pozícia <b>Mix preparer</b> (prípravár zmesí) pre továreň. Prípravár potrebuje túto.</li>
<li>Všetko ostatné vrátane pracovných príkazov iných ľudí a prepojenia suroviny s artefaktom: pozícia <b>Production floor supervisor</b> (vedúci dielne) pre továreň, alebo organisation supervisor.</li>
</ul>
</aside>
