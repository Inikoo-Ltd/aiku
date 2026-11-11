---
title: Čítanie prehľadu objednávok (PO Overview)
summary: Pre nákupcov a manažment — ako sa každá otvorená objednávka (PO) stáva jednou stužkou, čo znamenajú jednotlivé farby a dátumy, odkiaľ pochádzajú termíny, ako označiť fázy, ktoré nikto iný nezaznamenáva, a ako nájsť objednávky, ktoré potrebujú vašu pozornosť.
date: 2026-11-11
source_date: 2026-11-11
tags: procurement, supply-chain, agents
category: procurement
series: PO journey
order: 1
---

<aside class="tldr">
Pre nákupcov, nákupných manažérov a riaditeľov. Prvá stránka <b>Supply Chain</b> zobrazuje každú otvorenú objednávku (PO) ako jednu stužku, zľava doprava, odo dňa jej vytvorenia po deň, keď sú jej produkty v predaji. Každá bunka ukazuje svoj deň počítaný od Day Zero, dňa vytvorenia objednávky (Z+14 je 14 dní potom). Zelená znamená hotovo, orámovaná bunka je fáza, v ktorej sa objednávka práve nachádza, červená je zmeškaný termín, s počtom dní meškania. Karta <b>Needs action</b> ukazuje iba objednávky, ktoré niekoho potrebujú. Agenti zaznamenávajú svoju stranu práce podľa popisu v <a href="/docs/recording-order-progress-for-agents-sk">zaznamenávaní postupu objednávky ako agent</a>.
</aside>

<figure><img src="/art/docs/draw-po-journey.svg" alt="Akvarelová skica troch stužiek nákupných objednávok: prvá je celá zelená až do konca, druhá je zelená a potom modrá v polovici, tretia je zelená a potom červená pri jednej fáze s plus dvanástimi dňami, a za ňou sivé plánované dátumy" width="1200" height="750" loading="lazy"><figcaption>Jedna objednávka, jedna stužka. Červená bunka je miesto, kde sa zasekla.</figcaption></figure>

## Čo je na tabuli

Na tabuli je každá objednávka, ktorá je ešte na ceste:

- **Objednávky priamym dodávateľom.**
- **Objednávky medzi spoločnosťami AW**, napríklad keď jedna spoločnosť nakupuje od továrne.
- **Objednávky cez agentov.** Objednávka cez agenta sa zadáva pre jedného dodávateľa za agentom, pretože práve tam sa naozaj odohráva výroba, kontrola kvality a meškania. Jej riadok zobrazuje agenta a dodávateľa.

Objednávka z tabule zmizne, len čo je jej tovar uložený v sklade a každý produkt z nej je v predaji. Objednávky dokončené za posledných 60 dní zostávajú viditeľné ako <b>Completed</b>, aby ste videli, čo prišlo. Objednávky staršie ako rok, ktoré sa nikdy nedokončili, sa odložia bokom a nezobrazujú sa.

## Fázy

Tabuľa používa rovnaké fázy ako stránka objednávky plus posledný stĺpec pre produkty, ktoré idú online. Fázy 1 až 5 sú strana práce agenta, na objednávkach cez agenta.

| Fáza | Splnené keď |
|---|---|
| 1 PO Launch (spustenie objednávky) | Objednávka je odoslaná dodávateľovi alebo agentovi. Toto je nultý deň. Koncept sa nepočíta. |
| 2 Agreement (dohoda) | Podmienky sú s dodávateľom dohodnuté: záloha je zaplatená, alebo je objednávka označená ako **No deposit needed** (záloha nie je potrebná). Objednávka sa potom zobrazí ako Agreed (dohodnuté). |
| 3 Making It Happen (výroba prebieha) | Dodávateľ začne tovar vyrábať. Vzorka sa pri nových produktoch schvaľuje pred tým. |
| 4 Ready to Roll (pripravené na odoslanie) | Tovar je vyrobený a kontrola kvality je schválená. Záverečné kontroly (doplatok, balenie, QC, compliance) sa zobrazujú pod fázou. |
| 5 Clean Hand Over (CHO) (čisté odovzdanie) | Agent tovar odovzdal ďalej. Čo to znamená, závisí od agenta, pozri nižšie. Odoslaná zásielka ju vždy dokončí. |
| 6 Homeward Bound (na ceste domov) | Každá zásielka objednávky odišla a dorazila do prístavu. |
| 7 Counted & Costed (spočítané a naceňené) | Každá zásielka objednávky je prijatá v našom sklade. |
| 8 Mission Complete (misia splnená) | Každý produkt z objednávky je vytvorený a v predaji na webe. |

**Clean Hand Over sa nastavuje pre každého agenta zvlášť.** Pri agentovi, ktorý posiela tovar ďalším kontajnerom (Čína, Indonézia), je hotové, keď je každá položka objednávky na zásielke. Pri agentovi, ktorý odovzdáva tovar prepravnému agentovi (India, Nepál), je hotové, keď agent zaznamená **Handed over**. Každý agent môže mať aj vlastný názov a popis ktorejkoľvek fázy, vlastné cieľové dni a záverečné kontroly, ktoré zobrazuje: pozri nižšie navigáciu.

Nie každá objednávka prechádza všetkými fázami. Objednávky medzi spoločnosťami AW idú priamo z odoslania objednávky do odovzdania. Objednávky priamym dodávateľom zobrazujú zálohu, len keď je nastavená, a výrobu, len keď dodávateľ udáva dni výroby. Stĺpec, ktorý objednávka nevyužíva, sa zobrazuje ako tenká čiara. Schválenie vzorky a čisté odovzdanie sú kroky vo vnútri svojej fázy: zobrazujú sa na stránke objednávky, nie ako stĺpce tu.

## Čítanie farieb

- **Zelená s dátumom:** hotovo v ten deň.
- **Bledozelená fajočka:** hotovo, ale nikto nezaznamenal dátum. Nasledujúca fáza sa už stala, takže táto musela tiež.
- **Modrá:** fáza, v ktorej sa objednávka práve nachádza, a je v termíne. Dátum je jej cieľ.
- **Jantárová:** aktuálna fáza má termín do troch dní.
- **Červená s +N dňami:** aktuálna fáza mešká o toľko dní. Na objednávku pripadá vždy len jedna červená bunka: fáza, v ktorej sa zasekla.
- **Sivý dátum:** plán pre neskoršiu fázu. **Prečiarknutý** znamená, že tento plánovaný dátum už uplynul, zatiaľ čo sa objednávka zasekla skôr, takže bude meškať aj tam.
- **Prázdne:** fáza, ktorú na tejto objednávke ešte nikto nezaznamenal. Pozri nižšie.

Stĺpec stavu vpravo hovorí to isté slovami: *V poriadku* s očakávaným dátumom dokončenia, alebo *Po termíne* s tým, na čo objednávka čaká, napríklad *Čaká sa na expedíciu* alebo *Ešte neodoslané*.

## Odkiaľ pochádzajú termíny

Každá fáza dostane počet dní od chvíle, keď skutočne skončila fáza pred ňou. Ak teda výroba skončila o dva týždne neskôr, QC (kontrola kvality) sa počíta odo dňa, keď výroba naozaj skončila, a červená farba padne na fázu, ktorá sa oneskorila, nie na všetko po nej.

Počet dní pochádza, v tomto poradí, z:

1. **Dátumov dohodnutých na objednávke.** Odhadovaný dátum výroby, dátum príchodu na objednávke alebo na jej zásielke, alebo agentom schválený dátum pripravenosti. Keď je známy dátum príchodu, odovzdanie má termín o dobu prepravy skôr. Keď je dohodnutý dátum pripravenosti, kontrola kvality má termín v ten deň a čisté odovzdanie do siedmich dní od neho, ako je uvedené v agentskej zmluve.
2. **Vlastných dní fáz agenta**, ak sú nastavené na stránke agenta.
3. **Dodacej doby agenta alebo dodávateľa**, rozloženej medzi jednotlivé fázy. Dodacia doba každého agenta je nastavená podľa toho, ako rýchlo dorazilo 80 % jeho doterajších objednávok, takže červená znamená pre daného agenta pomalšie ako zvyčajne.

## Fázy, ktoré nikto iný nezaznamenáva

Systém sám vidí, kedy je objednávka odoslaná, kedy tovar odchádza a je prijatý, kedy je uložený a kedy sú produkty online. Príchod do prístavu sa zadáva na paneli colného odbavenia zásielky. Nevidí však, kedy je zaplatená záloha, začatá výroba alebo kedy tovar prejde kontrolou kvality. Toto musí niekto označiť.

- **Záloha** je prvá platba zaznamenaná na objednávke, s jej bankovým výpisom.
- **Agenti** označujú začatie výroby, dokončenie výroby a odovzdanie na svojich vlastných objednávkach.
- **Nákupcovia** môžu označiť každú fázu a len oni schvaľujú vzorku, dátum pripravenosti a kontrolu kvality (QC). Schválenie QC uvoľní doplatok.

Kým nie je na objednávke označená ani jedna z týchto vecí, jej bunky zostávajú prázdne a objednávka sa sleduje len podľa odoslania a príchodu. Len čo je jedna označená, objednávka čaká na ďalšej neoznačenej fáze, takže začnite od prvej a pokračujte ďalej.

Ak chcete označiť fázu, kliknite na jej bunku, skontrolujte dátum a kliknite na <b>Mark done</b>. <b>Clear</b> odstráni dátum zadaný omylom. Dátum starší ako jeden deň alebo vymazanie dátumu si vyžiada dôvod, ktorý sa uchová v histórii objednávky spolu s tým, kto zmenu vykonal.

**Fázy idú prísne v poradí.** Kým je objednávka Submitted (odoslaná), dá sa urobiť len Agreement a nič neskoršie sa označiť nedá: nabehnutie kurzorom na neskoršiu bunku povie, že najprv treba dohodnúť podmienky. Od stavu Agreed sa dá označiť len fáza, v ktorej objednávka je; neskoršia bunka povie, ktorú fázu treba dokončiť najskôr. Automatické kroky (príjemky tovaru, zoznamy kontajnerov, platby, expedované a prijaté zásielky) sa nikdy nezdržiavajú.

**Preskočenie kroku.** Aby objednávka nikdy neuviazla, každá fáza má na stránke objednávky tlačidlo preskočenia: **Sample skipped** (vzorka preskočená), **Shipped direct** (odoslané priamo, mimo skladu agenta), **QC skipped** (kontrola kvality preskočená), **Compliance skipped** (compliance preskočené) a **Handed over without checks** (odovzdané bez kontrol). Preskočenie si vyžiada dôvod, zobrazí sa na fáze ako Skipped (preskočené) s tým, kto, kedy a prečo, ide do histórie objednávky a dá sa vrátiť späť. Agenti môžu preskočiť QC na svojich objednávkach; ostatné preskočenia sú pre zamestnancov AW.

## Filtre a čísla

Karty delia objednávky na <b>Open</b>, <b>Needs action</b> (rizikové alebo po termíne) a <b>Completed</b> za posledných 60 dní, každá s počtom. Pod nimi je vyhľadávacie pole pre PO, dodávateľa, agenta alebo nákupcu a rozbaľovacie zoznamy <b>Organisation</b>, <b>Buyer</b>, <b>Agent</b>, <b>Country</b>, <b>Current stage</b>, <b>Status</b>, <b>Supplier</b>, <b>Journey</b> (agent, priamy dodávateľ, medzi spoločnosťami) a <b>PO type</b> (*NPO* je objednávka s aspoň jedným produktom, ktorý sme ešte nikdy nedostali; všetko ostatné je opakovaná objednávka). <b>Reset filters</b> ich vymaže. <b>Sort</b> dá na začiatok objednávky, ktoré potrebujú akciu, najnovšie, najstaršie alebo s najvyššou hodnotou; <b>Rows per page</b> je dole.

Každý riadok ukazuje hodnotu objednávky v librách podľa dnešného výmenného kurzu a jej <b>Age</b>, dni od Day Zero. Šípka na začiatku riadku otvorí, na čo objednávka čaká, jej termín, záverečné kontroly na Ready to Roll a tlačidlo na označenie fázy; <b>Expand all</b> otvorí všetky riadky. <b>Urgent blockages</b> vpravo zoskupuje meškajúce objednávky podľa toho, na čo čakajú; kliknutím na jednu z nich ich zobrazíte.

<aside class="wayfinder">
<b>Kde kliknúť v aiku</b><br>
<b>Supply Chain</b> v ľavom menu otvorí tabuľu. <b>PO Overview</b> v hornom menu vás na ňu vráti; <b>Overview</b> vedľa neho obsahuje karty dodávateľského reťazca a nákupné zoznamy. Kliknutím na bunku označíte fázu; označiť sa dá len fáza, v ktorej objednávka je, a dátum starší ako jeden deň alebo vymazanie dátumu si vyžiada dôvod, ktorý sa uchová v histórii objednávky. Preskočenia sú na stránke objednávky. Kliknutím na referenciu objednávky ju otvoríte. Podľa agenta, ako sa robí Clean Hand Over, záverečné kontroly zobrazené na Ready to Roll a názov, popis a dni každej fázy: <b>Supply Chain → Agents →</b> agent <b>→ Edit</b>, sekcia <b>PO journey</b>. Pole ponechané prázdne použije štandardné znenie alebo dni.
</aside>

<aside class="wayfinder">
<b>Aké práva potrebujete</b><br>
Právo supply chain view na zobrazenie tabule. Právo supply chain edit, alebo právo purchasing edit pre spoločnosť, ktorá objednávku vytvorila, na označovanie fáz. Na objednávkach agentov schválenie vzorky, schválený dátum pripravenosti, QC a compliance označuje len personál AW.
</aside>
