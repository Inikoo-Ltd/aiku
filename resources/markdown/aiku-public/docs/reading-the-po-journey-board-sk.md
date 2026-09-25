---
title: Čítanie tabule postupu objednávok (PO journey board)
summary: Pre nákupcov a manažment — ako sa každá otvorená objednávka (PO) stáva jednou stužkou, čo znamenajú jednotlivé farby a dátumy, odkiaľ pochádzajú termíny, ako označiť fázy, ktoré nikto iný nezaznamenáva, a ako nájsť objednávky, ktoré potrebujú vašu pozornosť.
date: 2026-09-25
source_date: 2026-09-25
tags: procurement, supply-chain, agents
category: procurement
series: PO journey
order: 1
---

<aside class="tldr">
Pre nákupcov, nákupných manažérov a riaditeľov. Prvá stránka <b>Supply Chain</b> zobrazuje každú otvorenú objednávku (PO) ako jednu stužku, zľava doprava, odo dňa jej vytvorenia po deň, keď sú jej produkty v predaji. Zelená znamená hotovo, modrá je fáza, v ktorej sa objednávka práve nachádza, červená je fáza, ktorá mešká, s počtom dní meškania. Zaškrtnite <b>Problems only</b> (len problémy), ak chcete vidieť iba objednávky, ktoré niekoho potrebujú. Agenti zaznamenávajú svoju stranu práce podľa popisu v <a href="/docs/recording-order-progress-for-agents-sk">zaznamenávaní postupu objednávky ako agent</a>.
</aside>

<figure><img src="/art/docs/draw-po-journey.svg" alt="Akvarelová skica troch stužiek nákupných objednávok: prvá je celá zelená až do konca, druhá je zelená a potom modrá pri fáze In transit, tretia je zelená a potom červená pri fáze Production s plus dvanástimi dňami, a za ňou sivé plánované dátumy" width="1200" height="750" loading="lazy"><figcaption>Jedna objednávka, jedna stužka. Červená bunka je miesto, kde sa zasekla.</figcaption></figure>

## Čo je na tabuli

Na tabuli je každá objednávka, ktorá je ešte na ceste:

- **Objednávky priamym dodávateľom.**
- **Objednávky medzi spoločnosťami AW**, napríklad keď jedna spoločnosť nakupuje od továrne.
- **Objednávky cez agentov.** Tie sa zobrazujú ako agentova objednávka pre každého dodávateľa, pretože práve tam sa naozaj odohráva výroba, kontrola kvality a meškania. Prepnutím <b>Orders to suppliers</b> na <b>Agent POs</b> vľavo hore zobrazíte namiesto toho každú agentovu objednávku ako jeden riadok. Agentova objednávka, ktorú agent ešte nerozdelil podľa dodávateľa, sa zobrazuje ako jeden riadok s malým označením *nerozdelené*.

Objednávka z tabule zmizne, len čo je jej tovar uložený v sklade a každý produkt z nej je v predaji. Objednávky dokončené za posledných 60 dní zostávajú viditeľné ako <b>Completed</b>, aby ste videli, čo prišlo. Objednávky staršie ako rok, ktoré sa nikdy nedokončili, sa odložia bokom a nezobrazujú sa.

## Fázy

| Fáza | Splnené keď |
|---|---|
| PO created (vytvorenie objednávky) | Objednávka je odoslaná dodávateľovi. Koncept sa nepočíta. |
| Spec / Sample (špecifikácia / vzorka) | Vzorka je schválená. Len pri nových produktoch. |
| Deposit paid (zaplatená záloha) | Je zaplatená záloha dodávateľovi. |
| Production (výroba) | Tovar je vyrobený. |
| QC (kontrola kvality) | Tovar prejde kontrolou kvality. |
| Clean handover (čisté odovzdanie) | Tovar je odovzdaný kompletný, skontrolovaný a s dokladmi. |
| Dispatched (expedícia) | Tovar opúšťa dodávateľa. |
| In transit (na ceste) | Tovar dorazí do nášho skladu. |
| Warehouse received (prijaté na sklade) | Tovar je skontrolovaný a uložený na svoje miesto. |
| Products online (produkty online) | Každý produkt z objednávky je vytvorený a v predaji na webe. |

Nie každá objednávka prechádza všetkými fázami. Objednávky medzi spoločnosťami AW idú priamo z vytvorenia do expedície. Opakované objednávky preskakujú špecifikáciu a vzorku. Stĺpec, ktorý objednávka nevyužíva, sa zobrazuje ako tenká čiara.

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

Každá fáza dostane počet dní od chvíle, keď skutočne skončila fáza pred ňou. Ak teda fáza Production (výroba) skončila o dva týždne neskôr, fáza QC (kontrola kvality) sa počíta odo dňa, keď výroba naozaj skončila, a červená farba padne na fázu, ktorá sa oneskorila, nie na všetko po nej.

Počet dní pochádza, v tomto poradí, z:

1. **Dátumov dohodnutých na objednávke.** Odhadovaný dátum výroby, dátum príchodu na objednávke alebo na jej zásielke, alebo agentom schválený dátum pripravenosti. Keď je známy dátum príchodu, expedícia má termín o dobu prepravy skôr. Keď je dohodnutý dátum pripravenosti, kontrola kvality má termín v ten deň a čisté odovzdanie do siedmich dní od neho, ako je uvedené v agentskej zmluve.
2. **Vlastných dní fáz agenta**, ak sú nastavené na stránke agenta.
3. **Dodacej doby agenta alebo dodávateľa**, rozloženej medzi jednotlivé fázy. Dodacia doba každého agenta je nastavená podľa toho, ako rýchlo dorazilo 80 % jeho doterajších objednávok, takže červená znamená pre daného agenta pomalšie ako zvyčajne.

## Fázy, ktoré nikto iný nezaznamenáva

Systém sám vidí, kedy je objednávka odoslaná, kedy tovar odchádza, prichádza a je uložený, a kedy sú produkty online. Nevidí však, kedy je schválená vzorka, zaplatená záloha, dokončená výroba, tovar skontrolovaný alebo odovzdaný. Toto musí niekto označiť.

- **Agenti** označujú <b>Deposit paid</b>, <b>Sample approved</b> a <b>Production done</b> na svojich vlastných objednávkach.
- **Nákupcovia** potvrdzujú kontrolu kvality (QC) a <b>Clean handover</b>. Agenti tieto dve položky nemôžu nastaviť.

Kým nie je na objednávke označená ani jedna z týchto vecí, jej bunky zostávajú prázdne a objednávka sa sleduje len podľa expedície a príchodu. Len čo je jedna označená, objednávka čaká na ďalšej neoznačenej fáze, takže začnite od prvej a pokračujte ďalej.

Ak chcete označiť fázu, kliknite na jej bunku, skontrolujte dátum a kliknite na <b>Mark done</b>. <b>Clear</b> odstráni dátum zadaný omylom. Každá zmena sa zaznamenáva spolu s tým, kto ju vykonal.

## Filtre a čísla

Hore: <b>AW company</b>, <b>Journey</b> (agent, priamy dodávateľ, medzi spoločnosťami), <b>PO type</b> (*NPO* je objednávka s aspoň jedným produktom, ktorý sme ešte nikdy nedostali; všetko ostatné je opakovaná objednávka) a <b>Status</b>. Pod nimi: <b>Agent</b>, <b>PO creator / buyer</b>, <b>Supplier</b>, <b>Country</b> a <b>Current stage</b>, plus <b>Problems only</b> a vyhľadávacie pole pre PO, dodávateľa, agenta alebo nákupcu.

Karty počítajú objednávky v aktuálnom filtri: otvorené objednávky, ich hodnotu v librách podľa dnešného výmenného kurzu, v poriadku, rizikové, po termíne a dokončené za posledných 60 dní. Kliknutím na kartu podľa nej filtrujete. <b>Urgent blockages</b> vpravo zoskupuje meškajúce objednávky podľa toho, na čo čakajú; kliknutím na jednu z nich ich zobrazíte.

<aside class="wayfinder">
<b>Kde kliknúť v aiku</b><br>
<b>Supply Chain</b> v ľavom menu otvorí tabuľu. <b>PO journey</b> v hornom menu vás na ňu vráti; <b>Overview</b> vedľa neho obsahuje karty dodávateľského reťazca a nákupné zoznamy. Kliknutím na bunku označíte fázu; kliknutím na referenciu objednávky ju otvoríte. Dni fáz podľa agenta: <b>Supply Chain → Agents →</b> agent <b>→ Edit</b>, sekcia <b>PO journey</b>.
</aside>

<aside class="wayfinder">
<b>Aké práva potrebujete</b><br>
Právo supply chain view na zobrazenie tabule. Právo supply chain edit, alebo právo purchasing edit pre spoločnosť, ktorá objednávku vytvorila, na označovanie fáz. QC a clean handover na objednávkach agentov označuje len personál AW.
</aside>
