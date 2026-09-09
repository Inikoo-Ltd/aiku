---
title: Zbieranie towaru partnera
summary: Przewodnik dla magazynu - co znaczy pre-pick, dlaczego towar w skrzyni zbiorczej partnera przestaje liczyć się jako dostępny, i jak pracować z listą Pre-pick w Dispatching.
date: 2026-09-08
source_date: 2026-09-08
tags: dispatch, procurement, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 8
---

<aside class="tldr">
Dla magazynu. Część tego, o co prosi organizacja partnerska, w ogóle nie jest tu wytwarzana - butelki, torby, pudełka, patyczki. Nic do produkcji: ktoś musi po prostu zdjąć to z półki i włożyć do skrzyni tego partnera. Ten spacer nazywa się <b>pre-pick</b> (wstępna kompletacja), skrzynia to <b>goods out gathering location</b> (lokalizacja zbiorcza wysyłkowa), a od momentu, gdy towar w niej jest, przestaje liczyć się jako dostępny dla kogokolwiek innego. Twoja lista spacerów do wykonania to <b>Dispatching → Pre-pick</b>.
</aside>

## Po co istnieje pre-pick

Zapytanie organizacji partnerskiej nie staje się zamówieniem od razu. Leży na liście, dopóki ktoś tutaj go nie obsłuży, i dopiero wtedy staje się zamówieniem, dokumentem wydania i dostawą towaru, gdy zostanie wysłane do magazynu.

To zostawia lukę. Partner prosi dziś o 490 zestawów butelka-nakrętka, wysyłka dopiero za tydzień, a w międzyczasie nic nie stoi na przeszkodzie, żeby te butelki zostały sprzedane albo zużyte gdzie indziej. Nic w aiku samo z siebie nie wstrzymuje towaru - ani zapytanie, ani zamówienie, ani nawet dokument wydania. Jedyne, co naprawdę rezerwuje towar, to przeniesienie go w miejsce, z którego nie można go zabrać.

Do tego właśnie służy **goods out gathering location**: zwykła lokalizacja w magazynie, oznaczona jako punkt zbiorczy dla jednego partnera. To, co w niej leży, należy do niego.

## Co znaczą te dwa pojęcia

- **Pre-pick** - zdjęcie towaru z półki wcześniej i włożenie go do skrzyni tego partnera, zanim zamówienie gdziekolwiek trafi. Po stronie fabryki oznacza też "tego nie robimy, bierzemy z magazynu".
- **Goods out gathering location** - lokalizacja oznaczona tak, że wszystko, co w niej jest, przestaje liczyć się jako dostępne. Towar nadal jest nasz, nadal policzony, wyceniony i audytowany. Po prostu jest już przypisany.

## Lista spacerów do wykonania

**Warehouse → Dispatching → Pre-pick.**

Każdy wiersz to jeden spacer:

| Kolumna | Co mówi |
| --- | --- |
| For | dla jakiej organizacji partnerskiej jest towar |
| SKO | kod i nazwa tego, co przynieść |
| From | lokalizacja sugerowana przez system, ta z największą ilością |
| To | skrzynia zbiorcza tego partnera |
| Staged | ile już jest w skrzyni |
| To move | ile jeszcze trzeba przenieść |

Weź towar, włóż go do skrzyni, potem naciśnij **Moved** (przeniesiono). To zapisuje przeniesienie w aiku, wiersz sam znika, a ilość znika z dostępności.

Lista jest liczona na nowo za każdym razem, gdy ją otwierasz - to nie jest zestaw zadań do odhaczenia czy posprzątania. Jeśli towar jest już w skrzyni, wiersza po prostu nie ma. Jeśli ktoś doda więcej do zapytania, wiersz wraca.

Zakładka pojawia się tylko dla organizacji, które mają skonfigurowaną skrzynię zbiorczą dla partnera. Jeśli jej nie widzisz, jeszcze tego nie zrobiono.

## Co widzi partner

Nic do przekazania ręcznie. Na swojej liście zakupowej każda pozycja niesie informację, na jakim jest etapie: **Requested** (zamówione), **Being made** (w produkcji), **Pre-picked** (wstępnie skompletowane), **Staged for you** (przygotowane dla Ciebie), **Being picked** (w kompletacji), **On its way** (w drodze) - obok numer zlecenia produkcyjnego, zamówienia albo dokumentu wydania.

**Staged for you** znaczy dokładnie to, co zrobiłeś: ich towar jest w ich skrzyni, czeka na najbliższą wysyłkę.

## Kiedy naprawdę wychodzi

Zbieranie to nie wysyłka. Towar wyjeżdża, gdy ktoś wyśle skompletowane zamówienie do magazynu, na stronie **To produce** (Do produkcji), która zamienia zebrane zapytania w zamówienie, dokument wydania i dostawę towaru po stronie partnera. Zobacz [Praca z listą To produce](/docs/fulfilling-partner-orders-pl).

Ponieważ towar jest już w jednej skrzyni, kompletacja na tym etapie to spacer do jednej lokalizacji, a nie obchód całego magazynu.

## Warto wiedzieć

- **Skrzynia zbiorcza to nie magazynowanie.** Wszystko, co w niej zostanie, jest niewidoczne dla reszty - nie zostanie zaproponowane osobie kompletującej i nie pokaże się jako dostępne do sprzedaży. Odkładaj tam towar tylko wtedy, gdy naprawdę jedzie do tego partnera.
- **Oznaczenie już zapełnionej skrzyni zmienia liczby od razu.** Jeśli lokalizacja ma już towar w momencie oznaczenia jej jako punktu zbiorczego, ten towar od razu znika z dostępności. Sprawdź, co jest w lokalizacji, zanim ją oznaczysz.
- **Nic innego nie rezerwuje.** Dwie osoby mogą usłyszeć, że te same sztuki są wolne, dopóki ktoś fizycznie nie zaniesie ich do skrzyni. Jeśli coś nie może zostać sprzedane spod partnera, przenieś to.
- **Przeniesienie z powrotem zwalnia towar.** Wyjmij towar ze skrzyni albo zdejmij oznaczenie zbiorcze z lokalizacji, a ilość wraca do dostępności.
- **Jedna skrzynia na partnera** to zwykłe rozwiązanie, nazwana od partnera, żeby osoba kompletująca rozpoznała ją na pierwszy rzut oka.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Lista spacerów:</b> Twoja organizacja → <b>Warehouse</b> → <b>Dispatching</b> → zakładka <b>Pre-pick</b>.</li>
<li><b>Zapisz spacer:</b> naciśnij <b>Moved</b> przy wierszu, gdy towar jest już fizycznie w skrzyni.</li>
<li><b>Sprawdź, co jest w skrzyni:</b> <b>Warehouse</b> → <b>Locations</b> → lokalizacja → zakładka <b>SKOs</b>.</li>
<li><b>Oznacz lokalizację jako punkt zbiorczy:</b> <b>Warehouse</b> → <b>Locations</b> → lokalizacja → <b>Overview</b> → <b>Goods out gathering</b>.</li>
<li><b>Wskaż partnerowi jego skrzynię:</b> to nie ekran - poproś administratora, celowo ustawia się to z konsoli, żeby nie dało się zmienić przez przypadek.</li>
<li><b>Wyślij zebrany towar:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Stanowiska są ustawiane na karcie pracownika w module Human Resources i niosą ze sobą uprawnienia.</li>
<li>Podgląd listy i zapisanie przeniesienia: stanowisko dispatching dla magazynu albo przełożony organizacji.</li>
<li>Oznaczanie lokalizacji jako punktu zbiorczego: stanowisko magazynowe z uprawnieniem do edycji lokalizacji.</li>
<li>Wskazanie partnerowi skrzyni: administrator, z konsoli.</li>
</ul>
</aside>
