---
title: Príprava zmesí
summary: Pre prípravára a plánovača - ako sa zo zmesi alebo základu stane niečo, čo továreň sleduje, ako záložka Mixes vypočíta, čo pripraviť, a ako prebiehajú prípravárove pracovné príkazy.
date: 2026-09-02
source_date: 2026-09-02
tags: production, crafts
category: production
series: Ordering from partners
order: 6
---

<aside class="tldr">
Pre osobu, ktorá pripravuje zmesi a základy skôr, než môžu remeselníci začať, a pre plánovača, ktorý im túto prácu posiela. Zmes (mix) sa vyrába priamo v továrni, takže ju aiku vedie ako <b>surovinu</b> (raw material) — remeselníci ju spotrebúvajú — aj ako <b>artefakt</b> — prípravár ju vyrába. Po prepojení záložka <b>Mixes</b> na stránke <a href="/docs/fulfilling-partner-orders-sk">To produce</a> vypočíta, koľko z každej zmesi treba na základe otvorených pracovných príkazov, a jedno tlačidlo z toho urobí pracovné príkazy pre prípravára. Nastavenie kategórií a remeselníkov je v <a href="/docs/who-makes-what-sk">Kto čo vyrába</a>.
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

**Factory → To produce → Mixes** zobrazuje každú surovinu vyrábanú priamo v továrni, ktorú potrebuje niektorý otvorený pracovný príkaz. Pracovný príkaz je otvorený od okamihu vytvorenia až kým nie je prijatý na sklad.

Pri každej zmesi vidíte:

- **Needed** (potrebné): množstvá z otvorených pracovných príkazov vynásobené množstvom na kus z receptu, spočítané naprieč produktmi.
- **On hand** (na sklade): aktuálny stav zmesi na sklade.
- **Being made** (vo výrobe): množstvo v otvorených pracovných príkazoch na samotnú zmes.
- **Short** (chýba): potrebné mínus na sklade mínus vo výrobe. Riadky, kde niečo chýba, sú hore a zobrazené červeno.
- **Needed for** (potrebné pre): kódy produktov, ktoré ju spotrebúvajú, aby prípravár vedel, na čo sa čaká.

Odškrtnite zmesi na prípravu, upravte množstvo, ak dávka nesedí presne s nedostatkom, a stlačte **Create job orders**. Vytvorí sa jeden pracovný príkaz na prípravára, adresovaný jemu, v stave návrhu (draft). Otvorte ho a stlačte *Release to floor* (uvoľniť na dielňu), keď má začať.

## Čo robí prípravár

Prípravár pracuje ako každý remeselník: jeho úlohy sa zobrazia na obrazovke dielne, stláča START a DONE, a keď je dokončený posledný krok, pracovný príkaz sa prijme na sklad s kódom dávky. Od tej chvíle je zmes na sklade a remeselníci môžu vyrábať svoje produkty.

Ak prípravár nie je platený úkolovo, je to nastavenie mzdy, nie dôvod preskočiť dielňu. Záznam o tom, kto akú dávku pripravil a kedy, je to, čo dáva vysledovateľnosť od hotového produktu späť k jeho surovinám.

## Čo je dobré vedieť

- Zmes nemôže potrebovať samu seba. Ak recept artefaktu zmesi uvádza tú istú surovinu, tento riadok sa ignoruje.
- Záložka Mixes číta iba pracovné príkazy tejto továrne. Produkt vyrábaný v inej továrni tu dopyt nevytvára.
- "Being made" počíta pracovný príkaz, kým nie je prijatý na sklad, aj keď sú všetky úlohy hotové. Prijímajte pracovné príkazy včas a čísla zostanú pravdivé.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Prepojiť zmes:</b> <b>Factory → Crafts → Raw materials</b> → otvorte zmes → <b>Edit</b> → <b>Made in-house as</b>.</li>
<li><b>Vidieť, čo pripraviť:</b> <b>Factory → To produce → Mixes</b>.</li>
<li><b>Poslať prácu:</b> odškrtnite zmesi → <b>Create job orders</b> → otvorte pracovný príkaz → <b>Release to floor</b>.</li>
<li><b>Vykonať prácu:</b> <b>Factory → Floor</b> (My tasks) → <b>START</b> / <b>DONE</b>; potom sa pracovný príkaz prijme na sklad z jeho stránky.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Prepojenie suroviny s artefaktom: úpravové práva na crafts továrne, alebo organisation supervisor.</li>
<li>Vidieť záložku Mixes: práva na zobrazenie prevádzky alebo nákupu továrne.</li>
<li>Vytváranie a uvoľňovanie pracovných príkazov: orchestračné práva na prevádzku továrne, alebo organisation supervisor.</li>
</ul>
</aside>
