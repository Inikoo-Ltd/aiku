---
title: Zaznamenávanie postupu objednávky ako agent
summary: Pre agentov — dátumy, ktoré zaznamenávate na každej objednávke dodávateľovi (zaplatená záloha, začatie výroby, dokončená výroba, navrhovaný dátum pripravenosti, odovzdanie tovaru) a vaše vlastné číslo objednávky, kedy a kde ich zaznamenať, čo namiesto toho zaznamenáva nakupujúca spoločnosť, a prečo sa chýbajúci dátum zobrazuje ako meškanie.
date: 2026-10-23
source_date: 2026-10-23
tags: procurement, agents, supply-chain
category: procurement
series: PO journey
order: 2
---

<aside class="tldr">
Pre koordinačných agentov. Nakupujúca spoločnosť sleduje každú objednávku v siedmich fázach: odoslaná, záloha zaplatená, vo výrobe, pripravené a kontrola kvality prešla, expedované, dorazilo a preclené, prijaté. Sama vidí, kedy je tovar expedovaný a kedy dorazí, ale nevidí, čo sa deje u dodávateľa. Túto časť dopĺňate vy: na každej objednávke dodávateľovi zaznamenávate, kedy bola zaplatená záloha, kedy sa začala výroba, kedy bola výroba dokončená, dátum, kedy očakávate, že bude tovar pripravený, a deň, keď tovar odovzdáte prepravnému agentovi. Zaznamenajte každý z nich v ten deň, keď sa to stane. Strana nákupcu je vysvetlená v <a href="/docs/reading-the-po-journey-board-sk">čítaní tabule PO journey</a>.
</aside>

## Prečo záležia vaše dátumy

Každá objednávka, ktorú cez vás nakupujúca spoločnosť zadá, zobrazuje svoje fázy v hornej časti stránky objednávky a znova ako riadok na tabuli <b>PO journey</b>. Každá fáza ukazuje svoj dátum a kto ho zaznamenal. Keď fáza prekročí svoj cieľ, na tabuli sa zmení na červenú a zobrazí, o koľko dní mešká.

Bez vašich dátumov nakupujúca spoločnosť na vašej strane vidí len dve veci: deň, keď vám bola objednávka odoslaná, a deň, keď tovar odišiel. Všetko medzi tým je neviditeľné a meškanie vyzerá rovnako bez ohľadu na to, či nastalo u dodávateľa, pri kontrole kvality alebo pri odovzdaní. S vašimi dátumami fázy presne ukážu, kde sa objednávka nachádza, a meškajúci dodávateľ je viditeľný celé týždne predtým, než mal tovar odísť.

Len čo na objednávke zaznamenáte prvý dátum, ďalšie sa očakávajú. Ak cieľ fázy prejde a vy ju nezaznamenáte, táto fáza sa zobrazí ako meškajúca, aj keď bola práca hotová. Chýbajúci dátum vyzerá presne ako meškanie.

## Dátumy, ktoré zaznamenávate

| Dátum | Kedy ho zaznamenať | Kde |
|---|---|---|
| Deposit paid (zaplatená záloha) | Keď dodávateľ dostane zálohu. | <b>Pay deposit</b> v paneli platieb objednávky, so sumou a bankovým výpisom. Prvá zaznamenaná platba je záloha; to, čo sa zaplatí po nej, je doplatok. |
| Production started (začatie výroby) | Keď dodávateľ začne tovar vyrábať. | Tlačidlo <b>Production started</b> na fázach v hornej časti objednávky. |
| Production done (dokončená výroba) | Keď je tovar u dodávateľa hotový. Hotový znamená celá objednávka, nie prvá dávka. | Tlačidlo <b>Production done</b> na fázach v hornej časti objednávky. |
| Proposed ready date (navrhovaný dátum pripravenosti) | Keď viete, kedy bude tovar pripravený na odovzdanie. Dátum, ktorému veríte. Nákupca ho schvaľuje. | Sekcia <b>Clean handover</b> na stránke <b>Edit</b> objednávky. |
| Handed over (odovzdané) | Keď tovar dorazí k prepravnému agentovi. Celá objednávka, nie jej prvá časť. | Tlačidlo <b>Handed over</b> na fázach v hornej časti objednávky. |
| Vaše číslo objednávky (your order number) | Keď máte pre objednávku vlastné číslo. | <b>Agent's order number</b> na stránke <b>Edit</b> objednávky. Zobrazuje sa pod PO submitted. |

Fázy idú v poradí. Každý dátum zaznamenajte v deň, keď nastane. Dátum starší ako jeden deň, fáza zaznamenaná pred predchádzajúcou fázou alebo vymazanie dátumu si od vás vyžiada dôvod. Dôvod sa uchová v histórii objednávky spolu s vaším menom, aby nákupca videl, prečo.

## Čo zaznamenáva nakupujúca spoločnosť

Niektoré dátumy nemôžete zadať vy — je to zámer:

- **Sample approved (schválená vzorka).** Vzorku schvaľuje nákupca. Len pri nových produktoch; opakované objednávky vzorku nemajú.
- **Schválený dátum pripravenosti (Approved ready date).** Nákupca schváli váš navrhovaný dátum. Odvtedy je uzamknutý a určuje termíny: kontrola kvality má termín v ten deň a <b>Clean handover</b> do siedmich dní od neho.
- **Kontrola kvality (QC passed).** Potvrdzuje ju nákupca, zvyčajne na základe vášho dôkazu o kvalite. Schválenie QC uvoľní doplatok: kým nie je schválené, platba doplatku si vyžiada dôvod.
- **Kompletný compliance balík.**

Vaše <a href="/docs/your-clean-handover-score-sk">Clean Handover Score</a> sa počíta z týchto dátumov a z dátumu odovzdania, ktorý zaznamenávate. Dátum odovzdania zaznamenávate vy, preto zaznamenajte deň, keď tovar skutočne dorazil k prepravnému agentovi. Nákupca vidí, kto ho zaznamenal a kedy, môže ho porovnať s potvrdením prepravného agenta a môže ho opraviť; oprava sa uchová v histórii objednávky spolu s dôvodom.

## Keď dodávateľ zmení cenu

Ak dodávateľ požiada o novú cenu skôr, než objednávku potvrdíte, nepotvrdzujte ju za starú cenu. Otvorte objednávku, nájdite produkt v <b>Items</b> a na jeho riadku kliknite na <b>Supplier changed the price</b>. Zadajte cenu, ktorú dodávateľ žiada, za kartón tak, ako je zobrazená, prípadne krátku poznámku, ak pomôže, a potom <b>Ask the organisation</b>.

Nakupujúca spoločnosť dostane upozornenie a odpovie na riadku: prijme novú cenu, ponechá starú, alebo určí inú cenu. O odpovedi dostanete upozornenie vy. Kým nie je zodpovedaná každá otázka k cene na objednávke, <b>Confirm</b> zostane sivé. Ak nakupujúca spoločnosť ponechá starú cenu a dodávateľ za ňu nepredá, zrušte tento riadok pred potvrdením.

## Keď dodávateľ zmení množstvo

Ak dodávateľ nedokáže dodať objednané množstvo alebo predáva iba vo väčších baleniach, opýtajte sa nakupujúcej spoločnosti skôr, než potvrdíte. Otvorte objednávku, nájdite produkt v <b>Items</b> a pod jeho množstvom kliknite na <b>Ask for another quantity</b>. Zadajte množstvo v úrovni, ktorú zobrazuje karta Items (kartóny, SKO alebo kusy), prípadne krátku poznámku, ak pomôže, a potom <b>Ask the organisation</b>.

Nakupujúca spoločnosť dostane upozornenie a odpovie na riadku: prijme nové množstvo, alebo ponechá staré. O odpovedi dostanete upozornenie vy. Kým nie je zodpovedaná každá otázka k množstvu na objednávke, objednávku nemožno potvrdiť.

## Dobré návyky

- **Zaznamenajte v ten istý deň.** Päť sekúnd v ten deň vám neskôr ušetrí spor o červenej fáze aj písanie dôvodu.
- **Navrhujte dátumy pripravenosti, ktorým veríte.** Realistický dátum, ktorý dodržíte, je lepší než optimistický dátum, ktorý zmeškáte.
- **Informujte nákupcu včas**, keď sa dátum posunie. Nový schválený dátum dohodnutý včas udrží objednávku v termíne; posunutý dátum, na ktorom sa nikto nedohodol, sa zobrazí ako meškanie.

<aside class="wayfinder">
<b>Kde kliknúť v aiku</b><br>
Prihláste sa → <b>Purchase Orders</b> → otvorte objednávku. Každá objednávka je pre jedného z vašich dodávateľov. Sedem fáz je v hornej časti stránky: použite tam tlačidlá <b>Production started</b>, <b>Production done</b> a <b>Handed over</b>. Záloha sa zaznamenáva cez <b>Pay deposit</b> v paneli platieb. Potom otvorte <b>Edit</b>: <b>Proposed ready date</b> a <b>Agent's order number</b> sú v sekcii <b>Clean handover</b>.
</aside>

<aside class="wayfinder">
<b>Aké práva potrebujete</b><br>
Prihlásenie pre vašu agentskú organizáciu. Môžete označovať len svoje vlastné fázy na svojich vlastných objednávkach: začatie výroby, dokončenú výrobu a odovzdanie na fázach, zálohu ako platbu a na stránke Edit svoj navrhovaný dátum pripravenosti a číslo objednávky. Schválenie vzorky, schválený dátum pripravenosti, QC a compliance pre vás otvorené nie sú, pretože ich zaznamenáva nakupujúca spoločnosť.
</aside>
