---
title: Objednávanie u partnerskej organizácie
summary: Prečo obchod medzi sesterskými organizáciami používa nákupný zoznam namiesto objednávok, a ako celý cyklus funguje od zapísanej potreby po naskladnený tovar.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, warehouse, intercompany
category: procurement
series: Ordering from partners
order: 1
---

<aside class="tldr">
Keď nakupujete od sesterskej organizácie, nevystavujete objednávku. Pridáte, čo potrebujete, do nákupného zoznamu; predávajúca organizácia si vyberie, čo odošle, keď to zvládne. Odtiaľ všetko plynie samo: ich sklad to vyskladní a zabalí, a na vašej strane sa objaví prichádzajúca dodávka, pripravená na naskladnenie hneď, ako tovar dorazí. Ak tieto objednávky *zadávate*, začnite pri <a href="/docs/reading-the-partner-shopping-dashboard-sk">nákupnom paneli</a> a prečítajte si <a href="/docs/buying-from-a-partner-sk">Buying from a partner</a>; ak ich *vybavujete*, prečítajte si <a href="/docs/fulfilling-partner-orders-sk">Fulfilling partner orders</a>.
</aside>

<figure><img src="/art/docs/draw-partner-shopping.svg" alt="Akvarelová skica: karta nákupného zoznamu kupujúceho (Procurement › Partners › Shopping list, s Auto-fill) a karta odosielacieho zoznamu predávajúceho s odškrtnutými riadkami a tlačidlom Send to warehouse, prerušovaná šípka medzi nimi a nákladiak vezúci tovar ku škatuli označenej ako prichádzajúca dodávka" width="1200" height="750" loading="eager"><figcaption>Vy napíšete zoznam, oni ho vyskladnia a odošlú, na vašej strane sa objaví dodávka.</figcaption></figure>

## Prečo tu nie je objednávka

Objednávka dáva zmysel s externým dodávateľom: zaviažete sa k množstvám, oni to potvrdia a obe strany sledujú ten istý dokument. Medzi vlastnými organizáciami je táto ceremónia na obtiaž. Predávajúci pozná svoj vlastný sklad lepšie ako kupujúci, a nútiť kupujúceho hádať, čo sa dá odoslať, vedie k nekonečným upravovaným objednávkam.

Takže je tok obrátený. **Kupujúci povie, čo potrebuje**, **predávajúci rozhodne, čo a kedy odošle**. Nikto nikomu neupravuje objednávku, pretože žiadna objednávka na úpravu neexistuje - len zoznam otvorených potrieb a prúd zásielok proti nemu.

## Celý cyklus

1. Kupujúci otvorí [nákupný panel](/docs/reading-the-partner-shopping-dashboard-sk), aby videl, čo dochádza a koľko je miesta, a potom [pridá, čo potrebuje, do nákupného zoznamu](/docs/buying-from-a-partner-sk) - ručne, z partnerovho katalógu, alebo pomocou návrhu Auto-fill.
2. Predávajúci [vyberie riadky, ktoré vie odoslať, a odošle zásielku na svoj sklad](/docs/fulfilling-partner-orders-sk). Vyskladní sa, zabalí a expeduje ako každá iná objednávka.
3. V momente, keď zásielka vstúpi do skladu predávajúceho, sa na strane kupujúceho objaví prichádzajúca **dodávka**. Sama sleduje priebeh u predávajúceho - predávajúci je zdrojom pravdy, kým tovar nedorazí.
4. Keď tovar fyzicky dorazí, kupujúci ho prevezme, skontroluje a uloží na miesta presne ako pri akejkoľvek dodávke od dodávateľa.

## Zoznam je zámerne obmedzený

Zoznam kupujúceho nie je bezodná krabica prianí. Je obmedzený na približne jeden objednávkový cyklus toho, čo partner naozaj dodáva, a nové produkty sú obmedzené voľným miestom v sklade a spravodlivým podielom naň na partnera. Zoznam, ktorý nikto nemôže zaplaviť, je zoznam, ktorý predávajúci vie čítať: keď je na ňom všetko, nie je na ňom nič naliehavé. Položky bez skladu a s rankom A sú z limitu vyňaté, takže skutočná kríza nikdy nečaká za limitom.

## Peniaze, faktúry a problémy

Medzi organizáciami neexistujú samostatné faktúry od dodávateľa. Vlastná faktúra predávajúceho za zásielku **je** dokument, a prichádzajúca dodávka je s ňou prepojená. Ak niečo dorazí neúplné, poškodené alebo nesprávne, riešte to *po* tom, čo dodávku prevezmete - to je bod, kde zodpovednosť prechádza na vašu stranu - a akýkoľvek refund alebo dobropis sa rieši voči tejto prepojenej faktúre.

## Čo je dobré vedieť

- Keď predávajúci prvýkrát vyskladňuje pre partnera, v jeho shope sa vytvorí zákaznícky účet pomenovaný po kupujúcej organizácii. To je v poriadku - takto zásielka prechádza bežným mechanizmom predávajúceho.
- Čiastočné vyskladnenie je bežné. Riadok vyskladnený len sčasti necháva zvyšok otvorený pre neskoršiu zásielku; nič sa nestratí.
- Ceny sú aktuálne shopové ceny predávajúceho so štandardnou intercompany zľavou kupujúcej organizácie, zobrazené vo vlastnej mene kupujúceho. Nič sa nedojednáva riadok po riadku; ak sa dohoda zmení, bude to oznámené.

<aside class="wayfinder"><strong>Povolenia, ktoré potrebujete</strong>
<ul>
<li><b>Vidieť nákupný a odosielací zoznam:</b> procurement prístup <i>view</i> vo vašej organizácii.</li>
<li><b>Pridávať riadky, vyberať, odosielať na sklad:</b> procurement prístup <i>edit</i> v organizácii, ktorá danú akciu vykonáva (kupujúceho pri zozname, predávajúceho pri vyskladňovaní a odosielaní).</li>
<li><b>Prevziať a naskladniť tovar:</b> prístup k skladovým zásobám v sklade kupujúceho, rovnako ako pri akejkoľvek dodávke od dodávateľa.</li>
<li>Chýba vám niečo z toho? Požiadajte svojho admina o pridelenie roly v <b>Sysadmin → Users</b> - povolenia sú na organizáciu, takže mať ich v jednej organizácii sa neprenáša na jej partnera.</li>
</ul>
</aside>
