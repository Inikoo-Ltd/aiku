---
title: Vyskladnenie a balenie dodacieho listu
summary: Sledujte dodací list od chvíle, keď dorazí do skladu, cez vyskladnenie, balenie a expedíciu, a zistite, čo presne robí každé tlačidlo na dodacom liste.
date: 2026-09-01
source_date: 2026-09-01
tags: dispatch, picking, packing
category: dispatch
---

<aside class="tldr">
<em>Dodací list</em> je skladová kópia objednávky: zoznam toho, čo má ísť von, a záznam toho, čo skutočne odišlo. Prechádza pevnou sadou stavov - to do, picking, picked, packing, packed, finalised, dispatched - a každá zmena stavu je tlačidlo, ktoré stlačíte, keď je práca za ním hotová. Tento článok sleduje dodací list celou cestou a ukazuje, kde ho v každom stave nájdete.
</aside>

## Kde nájdete dodacie listy

Vo vašom sklade **Dispatching → Delivery notes** zobrazuje všetky dodacie listy, spolu s kartami **Stats** a **History** vedľa hlavného zoznamu. Samotný zoznam je rozdelený na karty podľa stavu po strane: **Dispatched**, **All**, **To do**, **Queued**, **Handling**, **Waiting**, **Picked**, **Packing**, **Packed** a **Finalised**. Každá karta zobrazuje len dodacie listy práve v danom stave, s počtom vedľa názvu, takže na prvý pohľad vidíte, koľko čoho kde leží.

Fáza dodacieho listu sa nazýva jeho stav. Postupne dodací list prechádza:

- **To do** - ešte sa nič nezačalo.
- **Queued** - čaká v picking session na jej spustenie.
- **Handling** - práve sa vyskladňuje.
- **Waiting** - vyskladňovanie sa zastavilo, pretože niečo na dodacom liste potrebuje rozhodnutie.
- **Picked** - všetky riadky sú vyskladnené.
- **Packing** - práve sa balí.
- **Packed** - všetky riadky sú zabalené.
- **Finalised** - pripravené na odoslanie, údaje o zásielke sú nastavené.
- **Dispatched** - odoslané.
- **Cancelled** - dodací list bol zrušený.

## Vyskladňovanie

Dodací list sa stane vyskladniteľným buď samostatne, z karty **To do**, alebo ako súčasť **picking session** - dávky dodacích listov vyskladňovaných spolu. Picking sessions majú vlastnú obrazovku, dostupnú z **Dispatching → Picking sessions**, s rovnakým typom kariet podľa stavu: **In Process**, **Picking**, **Waiting**, **Picked**, **Packed** a **All**.

Stlačenie **Start picking** na dodacom liste ho presunie do stavu **Handling** a zaznamená, kto ho vyskladňuje. Ak bol dodací list zaradený v rámci session, spustenie vyskladňovania pre celú session presunie do **Handling** rovnako všetky dodacie listy v nej, a ten, kto session spustil, sa stane vyskladňovačom na každom z nich. Dodací list už priradený niekomu inému zobrazuje uzamknutý zámok namiesto tlačidla na vyskladnenie - stlačte **Unlock to pick**, aby ste ho prevzali.

Počas vyskladňovania sa môže ukázať, že riadok potrebuje rozhodnutie, ktoré vyskladňovač nemôže urobiť sám - napríklad náhradu alebo uvoľnenie zo skladu. Vtedy sa celý dodací list presunie do **Waiting** namiesto toho, aby vyskladňovanie pokračovalo okolo problému. Akonáhle už naozaj nič nečaká, objaví sa tlačidlo **Auto Finish Waiting**, ktoré po stlačení skontroluje dodací list a ak sú naozaj všetky riadky vyriešené, posunie ho do **Picked**.

## Od vyskladneného k baleniu

Akonáhle sú všetky riadky na dodacom liste vyskladnené, leží v **Picked** s tlačidlom **Start packing**. Vo väčšine shopov je toto samostatný krok: stlačením sa dodací list presunie do **Packing**, zaznamená sa, kto balí, a uvoľní sa akékoľvek pickovacie miesto, ktoré ho držalo. Pre dropshippingové shopy sa balenie preskočí - z **Picked** namiesto toho tlačidlo znie **Set as packed** a posunie dodací list rovno do **Packed** v jednom kroku.

Počas balenia nie je možné dodací list označiť ako **Set as packed**, ak ešte má riadky čakajúce na rozhodnutie o náhrade alebo uvoľnení zo skladu - tento blok sa musí najprv vyriešiť.

Stlačenie **Set as packed** zaznamená, kto dodací list zabalil, dotiahne riadky, ktoré neboli potvrdené jednotlivo pri balení, a nastaví predvolenú zásielku, ak ešte žiadna nie je zaznamenaná.

Ak sa dodací list potrebuje vrátiť o krok späť, upraviteľné dodacie listy majú tlačidlá na vrátenie: **Undo set as picked** vráti dodací list z **Picked** späť do vyskladňovania, **Undo packing** vráti **Packing** späť do picked, a **Unpack** vráti **Packed** alebo **Finalised** dodací list späť do **Packing**.

## Finalizácia a expedícia so zasielateľom

Akonáhle má **Packed** dodací list zaznamenané zásielky, nesie jediné tlačidlo **Finalise and Dispatch** (popisok sa mení na **Dispatch** alebo **Finalise and set as Collected** pri náhradnom dodacom liste alebo pri takom, ktorý ide osobným odberom namiesto zasielateľa). Jeho stlačenie dodací list finalizuje - čo sa odmietne, ak nie je zaznamenaná žiadna zásielka - a v tom istom kroku ho expeduje: označí dodací list ako odoslaný, zaznamená čas expedície na každom riadku a pri dodacích listoch naviazaných na zákaznícku objednávku posunie do stavu odoslané aj samotnú objednávku.

Odoslaný dodací list je možné vrátiť späť pomocou **Undispatch**, ktoré ho vráti do stavu packed.

## Zrušenie dodacieho listu

Dodací list je možné zrušiť v ktoromkoľvek stave pred finalizáciou alebo expedíciou - zrušenie finalizovaného alebo odoslaného dodacieho listu sa odmietne. Zrušenie vráti späť na sklad všetko, čo už bolo vyskladnené alebo zabalené, označí každý riadok dodacieho listu ako zrušený a odpojí ho od akéhokoľvek vozíka alebo pickovacieho miesta, ktoré používal. Ak dodací list patrí k zákazníckej objednávke, aj samotná objednávka sa vráti späť, pokiaľ už nebola zrušená, finalizovaná alebo odoslaná.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Vidieť dodacie listy podľa stavu:</b> váš sklad → <b>Dispatching → Delivery notes</b>, potom vyberte kartu stavu - <b>To do</b>, <b>Queued</b>, <b>Handling</b>, <b>Waiting</b>, <b>Picked</b>, <b>Packing</b>, <b>Packed</b>, <b>Finalised</b>, <b>Dispatched</b> alebo <b>All</b>.</li>
<li><b>Pracovať s picking sessions:</b> váš sklad → <b>Dispatching → Picking sessions</b> → karty stavu <b>In Process</b>, <b>Picking</b>, <b>Waiting</b>, <b>Picked</b>, <b>Packed</b>.</li>
<li><b>Posunúť dodací list ďalej:</b> otvorte dodací list a použite tlačidlo daného stavu - <b>Start picking</b>, <b>Auto Finish Waiting</b>, <b>Start packing</b> / <b>Set as packed</b>, <b>Finalise and Dispatch</b>, <b>Dispatch</b>. Tlačidlá na vrátenie (<b>Undo set as picked</b>, <b>Undo packing</b>, <b>Unpack</b>, <b>Undispatch</b>) ho posunú späť.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Povolenia, ktoré potrebujete</strong>
Na zobrazenie dodacích listov skladu potrebujete prístup na zobrazenie dispatchingu alebo fulfilmentu pre daný sklad. Zrušenie dodacieho listu navyše vyžaduje rolu dispatching supervisor, admina organizácie, alebo prístup na úpravu objednávok či CRM daného shopu.
</aside>
