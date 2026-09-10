---
title: Zhromažďovanie tovaru pre partnera
summary: Sprievodca pre sklad - čo znamená pre-pick, prečo sa tovar v zbernom mieste partnera prestáva počítať ako dostupný, a ako pracovať so zoznamom Pre-pick v Dispatching.
date: 2026-09-09
source_date: 2026-09-09
tags: dispatch, procurement, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 8
---

<aside class="tldr">
Pre sklad. Časť toho, čo partnerská organizácia žiada, sa tu vôbec nevyrába - fľaše, vrecká, škatule, tyčinky. Nič na výrobu: niekto to jednoducho musí zložiť z regálu a dať do zberného miesta daného partnera. Táto cesta sa volá <b>pre-pick</b>, zberné miesto je <b>goods out gathering location</b> (zberná lokácia pre výstup tovaru), a v momente, keď je tovar v nej, prestáva sa počítať ako dostupný pre kohokoľvek iného. Váš zoznam ciest, ktoré treba urobiť, je <b>Dispatching → Pre-pick</b>.
</aside>

## Prečo pre-pick existuje

Požiadavka partnerskej organizácie sa hneď nestáva objednávkou. Zostáva na zozname, kým na tejto strane niekto nezareaguje, a objednávkou, dodacím listom a skladovou dodávkou sa stáva až vtedy, keď sa pošle do skladu.

Tým vzniká medzera. Partner dnes požiada o 490 setov fľaša-viečko, expedujú sa až o týždeň, a medzitým nič nebráni tomu, aby sa tie fľaše predali alebo použili inde. Nič v aiku samo osebe tovar nedrží - ani požiadavka, ani objednávka, ani dodací list. Jediné, čo tovar naozaj rezervuje, je presunúť ho niekam, odkiaľ sa nedá zobrať.

Na to slúži **goods out gathering location**: bežná lokácia v sklade, označená ako zberné miesto pre jedného partnera. Čo v nej leží, je jeho.

## Čo tie dve slová znamenajú

- **Pre-pick** - vzatie tovaru z regálu vopred a jeho uloženie do zberného miesta daného partnera, ešte predtým, ako sa objednávka niekam pohne. Na strane výroby to tiež znamená "toto nevyrábame, vezmite to zo skladu".
- **Goods out gathering location** - lokácia označená tak, že čokoľvek v nej sa prestáva počítať ako dostupné. Tovar je stále náš, stále sa počíta, stále sa oceňuje, stále sa auditje. Je jednoducho zarezervovaný.

## Zoznam ciest, ktoré treba urobiť

**Warehouse → Dispatching → Pre-pick.**

Každý riadok je jedna cesta:

| Stĺpec | Čo hovorí |
| --- | --- |
| For | pre ktorú partnerskú organizáciu je tovar určený |
| SKO | kód a názov toho, čo treba doniesť |
| From | lokácia, ktorú navrhuje systém, tá s najväčším množstvom |
| To | zberné miesto daného partnera |
| Staged | koľko je už v zbernom mieste |
| To move | koľko ešte treba priniesť |

Doneste tovar, uložte ho do zberného miesta a stlačte **Moved**. Tým sa presun zaznamená v aiku, riadok sám zmizne a množstvo vypadne z dostupnosti.

Zoznam sa počíta nanovo zakaždým, keď ho otvoríte - nie je to súbor úloh, ktoré treba odškrtnúť alebo upratať. Ak je tovar už v zbernom mieste, riadok tam jednoducho nie je. Ak niekto pridá k požiadavke ďalšie množstvo, riadok sa vráti.

Táto záložka sa zobrazí iba organizáciám, ktoré majú pre partnera nastavené zberné miesto. Ak ju nevidíte, ešte to nebolo nastavené.

## Polovica tej istej práce na strane výroby

Cesty niekde vznikajú: niekto vo výrobe musí rozhodnúť, že sa položka vezme zo skladu namiesto toho, aby sa vyrobila. Toto rozhodnutie má vlastnú stránku, **Factory → Pre-pick**, a je dvojičkou zoznamu vyššie.

Zobrazuje každú otvorenú partnerskú položku, za ktorou stojí sklad, bez ohľadu na to, či ju táto výroba vyrába, a nikdy nezobrazí položku, ktorá už bola pre-vychystaná. Každý riadok nesie žiadateľa, artefakt, čo bolo **asked** (požadované), čo je **in stock** (na sklade) a čo **can pick** (možno vychystať) - tieto dve hodnoty sú navzájom obmedzené, takže položka nikdy nesľúbi viac, ako existuje. Zoznam sa dá filtrovať podľa kategórie, žiadateľa a naliehavosti, a počty vo filtroch sú skutočné počty, nielen to, čo sa zmestí na stránku.

**Pre-pick** na riadku, **Pre-pick selected** pre to, čo označíte, alebo **Pre-pick all** pre všetko, čo aktuálne filtre zobrazujú. Pre-pick sľúbi tovar danému partnerovi a dá cestu na zoznam skladu; ak je dostupná iba časť požadovaného, položka sa rozdelí, sľúbená časť odíde a zvyšok zostane otvorený. Nič sa nepredáva a nevzniká žiadna objednávka - tovar sa jednoducho prestáva počítať ako dostupný pre kohokoľvek iného.

Číslo vedľa **Pre-pick** v bočnom paneli výroby udáva, koľko položiek čaká na toto rozhodnutie, a aktualizuje sa samo.

## Čo vidí partner

Nič sa mu netreba oznamovať ručne. Na svojom vlastnom nákupnom zozname má každá položka pri sebe stav, kam sa dostala: **Requested** (Vyžiadané), **Being made** (Vo výrobe), **Pre-picked** (Pre-vychystané), **Staged for you** (Pripravené pre vás), **Being picked** (Vo vychystávaní), **On its way** (Na ceste) - spolu s číslom pracovného príkazu, objednávky alebo dodacieho listu.

**Staged for you** znamená presne to, čo ste urobili: ich tovar je v ich zbernom mieste a čaká na najbližšiu expedíciu.

## Kedy sa to naozaj expeduje

Zhromaždenie nie je expedícia. Tovar odchádza, keď niekto pošle vychystanú objednávku do skladu, na stránke **To produce**, ktorá zhromaždené požiadavky premení na objednávku, dodací list a skladovú dodávku na strane partnera. Pozrite [Práca so zoznamom To produce](/docs/fulfilling-partner-orders-sk).

Keďže tovar je už v jednom zbernom mieste, vychystávanie v tej chvíli je cesta na jedinú lokáciu namiesto obchádzky celého skladu.

## Čo je dobré vedieť

- **Zberné miesto nie je sklad na uskladnenie.** Čo v ňom zostane, je pre všetkých ostatných neviditeľné - nikdy sa neponúkne vychystávačovi a neukáže sa ako dostupné na predaj. Ukladajte tam tovar iba vtedy, keď má ísť k danému partnerovi.
- **Označenie už naplneného zberného miesta zmení čísla okamžite.** Ak lokácia už obsahuje tovar v momente, keď ju označíte ako zberné miesto, tento tovar okamžite vypadne z dostupnosti. Pred označením skontrolujte, čo v lokácii je.
- **Nič iné nerezervuje.** Dvom ľuďom sa môže zdať, že rovnaké kusy sú voľné, kým jeden z nich nejdú fyzicky do zberného miesta. Ak sa niečo nesmie predať spod partnera, presuňte to.
- **Presunutím späť sa tovar uvoľní.** Vezmite tovar zo zberného miesta, alebo lokácii zrušte označenie zberného miesta, a množstvo sa vráti do dostupnosti.
- **Jedno zberné miesto na partnera** je bežné usporiadanie, pomenované po partnerovi, aby ho vychystávač spoznal na prvý pohľad.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Rozhodnúť, že položka sa vezme zo skladu:</b> vaša organizácia → <b>Factory</b> → <b>Pre-pick</b> → <b>Pre-pick</b> na riadku, alebo označte a použite <b>Pre-pick selected</b> / <b>Pre-pick all</b>.</li>
<li><b>Zoznam ciest:</b> vaša organizácia → <b>Warehouse</b> → <b>Dispatching</b> → záložka <b>Pre-pick</b>.</li>
<li><b>Zaznamenať cestu:</b> stlačte <b>Moved</b> na riadku, keď je tovar fyzicky v zbernom mieste.</li>
<li><b>Skontrolovať, čo je v zbernom mieste:</b> <b>Warehouse</b> → <b>Locations</b> → lokácia → záložka <b>SKOs</b>.</li>
<li><b>Označiť lokáciu ako zberné miesto:</b> <b>Warehouse</b> → <b>Locations</b> → lokácia → <b>Overview</b> → <b>Goods out gathering</b>.</li>
<li><b>Ukázať partnerovi jeho zberné miesto:</b> nie je to obrazovka - opýtajte sa administrátora, nastavuje sa zámerne z konzoly, aby sa to nedalo omylom zmeniť.</li>
<li><b>Odoslať zhromaždený tovar:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú v karte zamestnanca v Human Resources a nesú so sebou oprávnenia.</li>
<li>Vidieť zoznam a zaznamenať presun: pozícia dispatching pre sklad, alebo organisation supervisor.</li>
<li>Označiť lokáciu ako zberné miesto: pozícia pre sklad, ktorá môže upravovať lokácie.</li>
<li>Ukázať partnerovi jeho zberné miesto: administrátor, z konzoly.</li>
</ul>
</aside>
