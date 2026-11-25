---
title: Pre-pick
summary: Sprievodca pre výrobu k stránke Factory → Pre-pick - ktoré partnerské položky sa tu objavia, čo znamená každý stav a čo robiť, keď tovar na sklade je, keď je iba jeho časť, keď je ďalší na ceste a keď už neprinesie.
date: 2026-11-25
source_date: 2026-11-25
tags: production, procurement, intercompany, warehouse
category: production
series: Ordering from partners
order: 8
---

<aside class="tldr">
Položky, ktoré treba vyrobiť, idú do <b>To produce</b>; položky, ktoré už máte na regáli, prídu do <b>Pre-pick</b>. Pri každej položke v Pre-pick:
<ul>
<li><b>Máte všetok tovar:</b> nerobte nič. aiku ju samo pošle do skladu do desiatich minút. Ak zásoba pokrýva položku presne a nič nezostane, položka čaká: stlačte <b>Pre-pick</b>.</li>
<li><b>Máte iba časť a ďalší tovar je na ceste od dodávateľa:</b> počkajte. Keď sa dodávka zapíše na sklad, aiku pošle celú položku samo. Alebo stlačte <b>Pre-pick</b> a pošlite hneď to, čo máte; zvyšok ide do <b>To produce</b>.</li>
<li><b>Máte iba časť a je to niečo, čo vyrábate:</b> čakanie nepomôže, lebo nikto nedostane pokyn vyrobiť zvyšok. Stlačte <b>Pre-pick</b> a pošlite, čo máte; zvyšok ide do <b>To produce</b>, aby sa vyrobil.</li>
<li><b>Neprinesie sa (vyradené, nedostupné):</b> stlačte <b>Close short</b>, pošle sa, čo je, a zvyšok sa zruší, alebo <b>Can't be done</b>, aby sa neposlalo nič; partner dostane správu. Ak nie je nič na sklade, položka je v <b>To produce</b>: pozrite Prípad 3 nižšie.</li>
</ul>
</aside>

<figure><img src="/art/docs/draw-pre-pick-fork.svg" alt="Akvarelová skica: položka z partnerovho nákupného zoznamu príde k rázcestníku, ktorý ukazuje buď do To produce, na pracovný stôl, kde sa veci vyrábajú, alebo do Pre-pick, na regál so zásobou. Z regálu ide tovar, ktorý je celý na sklade, sám do partnerovho zberného miesta do desiatich minút; tovar, ktorý je na sklade len sčasti, ide na zberné miesto a zvyšok čaká na nákladné auto s dodávkou; zásoba, ktorá sa neprinesie, dostane červenú pečiatku Close short a poznámka letí späť k partnerovi." width="1200" height="750" loading="eager"><figcaption>Na regáli: Pre-pick. Nie na regáli: To produce.</figcaption></figure>

## Prečo položka skončí tu a nie v To produce

Objednávka partnera prichádza ako položky na nákupnom zozname. Pri každej položke aiku pozrie vašu **free stock** (voľnú zásobu): čo leží na regáli mínus to, čo je už sľúbené iným partnerským položkám.

- **Voľná zásoba nepokrýva nič** - položka ide do **To produce**, aby sa vyrobila. Pozrite [Práca so zoznamom To produce](/docs/fulfilling-partner-orders-sk).
- **Voľná zásoba pokrýva časť alebo celú položku** - položka príde do **Factory → Pre-pick**.

Tá istá objednávka sa teda môže rozdeliť medzi obe stránky: čaj, ktorý miešate, ide do To produce, fľaše, vrecká a škatule, ktoré už máte, prídu sem. Položka, ktorej zásoba sa neskôr minie, sa presunie do To produce sama.

Položky sa vybavujú v poradí, pre každý produkt zvlášť: najprv **urgent**, potom **high**, **normal** a **low**; pri rovnakej priorite tá, ktorá je potrebná skôr, a potom najstaršia. Položka počíta iba so zásobou, ktorá zostane po všetkých položkách pred ňou.

## Ako čítať zoznam

| Stĺpec | Čo hovorí |
| --- | --- |
| For | ktorá partnerská organizácia o tovar požiadala |
| Artefact | kód a názov produktu |
| To | zberné miesto daného partnera, kam tovar pôjde |
| Asked | koľko partner požiadal |
| In stock | čo je teraz na vašich regáloch |
| Can pick | čo môžete tejto položke dať teraz, po položkách pred ňou v poradí |
| Shortfall | čo chýba na pokrytie celej položky |
| Status | čo s ňou aiku urobí, pozrite nižšie |
| Priority, Added | kde sa položka nachádza v poradí |

Zoznam filtrujú kategória, žiadateľ a naliehavosť; čísla pri filtroch sú skutočné počty.

## Čo znamenajú stavy

| Stav | Čo to znamená | Čo robíte |
| --- | --- | --- |
| **Going to the warehouse** (Ide do skladu) | zásoba pokrýva celú položku a ešte nejaká zostane | nič - do desiatich minút ju aiku samo pre-vychystá a zmizne zo zoznamu |
| **Held, one more needed on the shelf** (Pozastavené, na regáli treba ešte jeden kus) | zásoba pokrýva položku presne, nič nezostane | nič, alebo stlačte **Pre-pick**, ak má ísť hneď; aiku posiela položku samo, len keď na regáli zostane aspoň jeden kus |
| **Awaiting full stock, production manager to decide** (Čaká na plnú zásobu, rozhodne vedúci výroby) | na sklade je iba časť položky | rozhodnite, pozrite prípady nižšie |
| **In To produce** (V To produce) | zásoba je, ale položky pred touto ju už zobrali | tu nič; položka čaká na stránke To produce |

Pre-pick nie je expedícia. Sľúbi zásobu danému partnerovi a dá do zoznamu skladu cestu: zložia ju z regálu a dajú do zberného miesta partnera. Tovar odíde, keď pôjde najbližšia zásielka partnera, podľa rozvrhu alebo keď niekto stlačí **Send to warehouse**. Pozrite [Zhromažďovanie tovaru pre partnera](/docs/gathering-a-partners-goods-sk).

## Prípad 1: máte celú zásobu

Nerobte nič. Položka ukazuje **Going to the warehouse** a aiku ju do desiatich minút pre-vychystá. Ak ukazuje **Held, one more needed on the shelf** a nemá čakať, stlačte **Pre-pick** na riadku.

Ak chcete poslať viac položiek naraz, označte ich a stlačte **Pre-pick selected**, alebo **Pre-pick all** pre všetko, čo aktuálne filtre zobrazujú.

## Prípad 2: máte časť zásoby, alebo je ďalšia na ceste

Položka ukazuje **Awaiting full stock, production manager to decide**. Máte tri možnosti:

- **Počkať na zvyšok.** Nerobte nič. Keď tovar objednaný od dodávateľa príde a zapíše sa na sklad, voľná zásoba narastie; hneď ako pokryje celú položku a na regáli zostane aspoň jeden kus, aiku ju pre-vychystá samo. Zvoľte to, keď je dodávka blízko a partner by radšej dostal všetko naraz. Čakanie funguje, len keď sa zásoba naozaj chystá prísť: pri produkte, ktorý vyrábate, nikto nedostane pokyn vyrobiť zvyšok, kým položka čaká tu, a čakala by donekonečna.
- **Poslať, čo máte teraz, zvyšok neskôr.** Stlačte **Pre-pick** (množstvo sa zobrazí ako *partial pick*, čiastočné vychystanie). Časť, ktorá je na sklade, ide do zberného miesta; zvyšok zostane na zozname partnera ako samostatná otvorená položka a presunie sa do **To produce**. Produkt, ktorý vyrábate, sa tam zobrazí na tabuli, aby sa vyrobil; čokoľvek iné sa zobrazí na záložke **All** a vráti sa sem, keď príde nová zásoba.
- **Poslať, čo máte, a tým skončiť.** Stlačte **Close short** (uzavrieť s menším množstvom). aiku vás požiada o potvrdenie, pošle, čo je na sklade, a zvyšok zruší. Zoznam partnera klesne na odoslané množstvo a ten, kto položku pridal, a nákupný tím partnera dostanú správu, že bola uzavretá s menším množstvom, aby si mohli zvyšok kúpiť inde.

## Prípad 3: zásoba neprinesie

Produkt je vyradený, dodávateľ ho už nemá, alebo nebude dlho dostupný.

- **Časť je na sklade** - stlačte **Close short**: partner dostane, čo je, a dozvie sa, že zvyšok nepríde. Ak nemôžete poslať ani nič z toho, stlačte namiesto toho **Can't be done** na riadku a uveďte dôvod.
- **Nič nie je na sklade a vy to vyrábate** - položka je kartou na tabuli **To produce**. Presuňte ju z **Backlog** (do **Preparing**, alebo do **Producing**, ak vaša výroba používa jednoduchú tabuľu) a v okne, ktoré sa otvorí, stlačte **Can't be done** (Nedá sa urobiť) a uveďte dôvod. Položka z tabule zmizne a ten, kto ju pridal, a nákupný tím partnera dostanú správu s vaším dôvodom, aby to mohli zohnať inde.
- **Nič nie je na sklade a vy to nevyrábate** - položka je v **To produce → All**. Stlačte **Can't be done** na jej riadku a uveďte dôvod; správu dostanú tí istí ľudia.

Takéto položky nenechávajte čakať bez slova: partner ich na svojom zozname vidí ako **Requested** a ďalej s nimi počíta.

## Čo vidí partner

Na vlastnom nákupnom zozname má každá položka stav, kam sa dostala: **Requested** (Vyžiadané), **Being made** (Vo výrobe), **Pre-picked** (Pre-vychystané), **Staged for you** (Pripravené pre vás), **Being picked** (Vo vychystávaní), **On its way** (Na ceste).

Keď sa položka uzavrie s menším množstvom alebo sa nedá urobiť, ľudia na strane partnera dostanú správu, s dôvodom.

## Čo je dobré vedieť

- **Nič nerezervuje zásobu, kým nie je pre-vychystaná.** Položka so stavom *Awaiting full stock* nič neodložila: jej zásobu možno stále predať alebo dať inej položke. Ak sa nesmie vziať, pre-vychystajte, čo tam je.
- **Ak sa ukáže, že na regáli je menej** - audit nájde menej, alebo sa predalo inde - aiku vezme sľub späť od najnovších pre-vychystaných položiek, a tie sa vrátia na zoznam alebo do To produce.
- **Zoznam sa počíta nanovo zakaždým, keď ho otvoríte.** Položky z neho zmiznú samy po pre-vychystaní; nie je čo upratovať.
- **Číslo vedľa Pre-pick** v bočnom paneli výroby je počet čakajúcich položiek a aktualizuje sa samo.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zoznam:</b> vaša organizácia → <b>Factory</b> → <b>Pre-pick</b>.</li>
<li><b>Poslať položku hneď:</b> <b>Pre-pick</b> na riadku, alebo označte riadky a stlačte <b>Pre-pick selected</b>, alebo <b>Pre-pick all</b>.</li>
<li><b>Poslať, čo je, a zvyšok zrušiť:</b> <b>Close short</b> na riadku.</li>
<li><b>Položka, ktorú sa nedá urobiť:</b> <b>Can't be done</b> na jej riadku v <b>Pre-pick</b> alebo vo <b>Factory</b> → <b>To produce</b> → <b>All</b>, alebo presuňte jej kartu z <b>Backlog</b> na tabuli.</li>
<li><b>Cesty do zberného miesta:</b> <b>Warehouse</b> → <b>Dispatching</b> → záložka <b>Pre-pick</b>, pozrite <a href="/docs/gathering-a-partners-goods-sk">Zhromažďovanie tovaru pre partnera</a>.</li>
<li><b>Odoslať zhromaždený tovar:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>, alebo to nechajte na rozvrh zásielok partnera.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú v karte zamestnanca v Human Resources a nesú so sebou oprávnenia.</li>
<li>Vidieť zoznam: akákoľvek pozícia vo výrobe, ktorá môže zobraziť výrobu, alebo organisation supervisor.</li>
<li>Pre-vychystať a uzavrieť s menším množstvom: pozícia vo výrobe, ktorá plánuje alebo pripravuje výrobu, editor nákupu, alebo organisation supervisor.</li>
<li><b>Can't be done</b>: pozícia vo výrobe, ktorá plánuje alebo pripravuje výrobu, alebo organisation supervisor. Pozrite <a href="/docs/factory-positions-sk">Pozície vo výrobe</a>.</li>
</ul>
</aside>
