---
title: Dávky, balenia a čiastočné dávky
summary: Továreň vyrába kusy v celých dávkach, sklad počíta balenia. Čo packed_in a veľkosť dávky robia s pracovným príkazom, s tým, čo pristane na regáli, a s tým, čo by mal partner objednať.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, procurement, crafts
category: production
series: Ordering from partners
order: 12
---

<aside class="tldr">
Pri každom artefakte sa stretávajú dva počty a nie sú to isté počty. Dielňa pracuje v <b>units</b> (kusoch) - jedna kúpeľová guľa, jeden kus mydla - a rozumne dokáže vyrobiť len celú <b>batch</b> (dávku) naraz, lebo to je to, čo pojme miešačka. Sklad a obchod pracujú v <b>SKO</b>: balení, v ktorom sa tovar predáva a skladuje, desať na škatuľu. <b>Packed in</b> je jediný most medzi nimi dvoma, a aiku ho teraz prekračuje na oboch koncoch namiesto toho, aby predstieral, že sú tie čísla zameniteľné.
</aside>

## Tri čísla

- **Batch size** (veľkosť dávky) — koľko kusov továreň vyrobí naraz. Patrí artefaktu, nastavuje sa na jeho stránke alebo hromadne zo zoznamu artefaktov, viď [Zmena viacerých artefaktov naraz](/docs/changing-many-artefacts-at-once-sk).
- **Packed in** — koľko kusov ide do jedného SKO. Patrí SKO v sklade.
- **Units a SKO** — dielňa dostáva zadanie v kusoch, všetko ostatné sa počíta v SKO.

## Čo sa deje na každom konci

**Vystavenie práce.** Čokoľvek bolo vyžiadané — riadok partnera, nedostatok vlastného zákazníka, riadok doplnenia — je množstvo v SKO. aiku ho vynásobí *packed in*, aby dostal kusy, a potom zaokrúhli **nahor** na najbližšiu celú dávku. Vyžiadajte 1 SKO desaťbalenia vyrábaného v dávkach po 16 a remeselník dostane zadanie na 16 kusov, nie 1 a nie 10.

**Prijatie hotovej práce.** To, čo remeselník vyrobil, sú kusy, a na ceste na regál sa delia *packed in*. Tých 16 kusov desaťbalenia pristane ako 1,6 SKO: jedna uzavretá škatuľa a šesť voľných. Číslo na regáli je voči zvyšku čestné, namiesto toho, aby ho zaokrúhlilo preč.

## Keď dávka nevyplní celé balenia

Dávka 16 a škatuľa 10 nikdy nevyjdú presne. To nie je chyba a aiku to tak neberie: továreň nastavuje veľkosť dávky podľa miešačky, obchod predáva v baleniach, a obe majú pravdu. Jednoducho sa oplatí vedieť, kde sa to deje, takže sa to meria:

- stĺpec **Batch in SKOs** a filter **Batch not whole SKOs** v zozname artefaktov;
- riadok na stránke artefaktu ukazujúci dávku v SKO a najbližšiu veľkosť dávky, ktorá by vyšla presne;
- štatistika na crafts dashboarde počítajúca artefakty, kde sa to deje.

Nič vás nenúti meniť veľkosť dávky. Ak je návrh ľahké prijať, prijmite ho a aritmetika prestane nechávať zvyšky. Ak veľkosť dávky rozhoduje miešačka, nechajte to tak.

## Čo by mal partner objednať

Tá istá aritmetika rozhoduje o najúhľadnejšom objednávacom množstve, ktoré aiku nazýva **order step**: najmenší počet SKO, ktorý celé dávky vyplnia presne. Pre dávku 16 kusov v baleniach po 10 je to 8 SKO — osemdesiat kusov, päť dávok, žiadny zvyšok.

Na strane partnera, v [nákupnom zozname](/docs/buying-from-a-partner-sk) a v skladovom zozname:

- riadok hovorí *made in batches of N units* (vyrába sa v dávkach po N kusoch) a tam, kde sa líšia, aj *full batches every N SKO* (celé dávky každých N SKO);
- malé tlačidlo zaokrúhli množstvo nahor na najbližší step;
- **suggested** množstvá a všetko, čo navrhuje Auto-fill, sú už na stepe;
- objednávka mimo stepu sa stále prijíma, s poznámkou, že celá dávka sa vyrobí tak či tak, takže objednávka môže byť oneskorená alebo množstvo upravené.

Objednávku pod jeden celý step nemožno vyrobiť samostatne vôbec. Na boarde [To produce](/docs/fulfilling-partner-orders-sk) čaká za *riadkami príliš malými na dávku, ktoré čakajú na spoločnosť*, kým sa nenazbiera dosť dopytu, vlastná zákaznícka objednávka aj tak nerozbehne prácu, alebo kým sa plánovač nerozhodne vyrobiť ju bez ohľadu na to.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Nastaviť veľkosť dávky:</b> vaša organizácia → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → otvorte artefakt, alebo označte viacero a nastavte to z lišty výberu.</li>
<li><b>Nájsť tie nepohodlné:</b> zoznam artefaktov → filter <b>Batch not whole SKOs</b>, alebo stĺpec <b>Batch in SKOs</b>.</li>
<li><b>Vidieť návrh:</b> stránka artefaktu, pod veľkosťou dávky.</li>
<li><b>Nastaviť packed in:</b> <b>Warehouse → Inventory</b> → otvorte SKO → <b>Edit SKO</b>.</li>
<li><b>Objednať na stepe:</b> nákupný zoznam partnera → tlačidlo vedľa <i>full batches every N SKO</i>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú v karte zamestnanca v Human Resources a nesú so sebou oprávnenia.</li>
<li>Veľkosť dávky a trvanlivosť na artefaktoch: právo <b>research and development</b> továrne, alebo organisation supervisor.</li>
<li>Packed in na SKO: pozícia pre sklad, ktorá môže upravovať inventár.</li>
</ul>
</aside>
