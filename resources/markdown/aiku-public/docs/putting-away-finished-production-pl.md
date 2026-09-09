---
title: Odkładanie gotowej produkcji
summary: Przewodnik dla magazynu - gdzie pojawiają się zakończone zlecenia produkcyjne, jak aiku wylicza, czy trafiają do skrzyni partnera czy do zwykłego magazynu, i jak zaksięgować je jednym przyciskiem.
date: 2026-09-08
source_date: 2026-09-08
tags: dispatch, production, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 10
---

<aside class="tldr">
Dla magazynu. Gdy rzemieślnicy kończą zlecenie produkcyjne, towar sam z siebie nie staje się zapasem - ktoś musi go gdzieś zanieść i powiedzieć gdzie. Ta lista to <b>Dispatching → From production</b> (Z produkcji). Każdy wiersz mówi, dla kogo jest towar, i podpowiada lokalizację: skrzynię zbiorczą partnera, gdy całe zlecenie jest dla jednego partnera, albo lokalizację magazynową, którą wpisujesz sam. Naciśnij <b>Put away</b> (Odłóż), a towar zostanie zaksięgowany do tej lokalizacji z kodem partii.
</aside>

## Skąd biorą się wiersze

Zlecenie produkcyjne pojawia się tutaj w momencie, gdy każde zadanie w nim jest oznaczone jako DONE na hali, i zostaje, dopóki nie zostanie odłożone. Nikt nie musi go do Ciebie wysyłać.

Zleceń wciąż w produkcji tu nie ma. Jeśli chcesz zobaczyć, co nadchodzi, tablica fabryki **To produce** (Do produkcji) ma kolumnę **Done** (Gotowe) z tymi samymi zleceniami - zobacz [Praca z listą To produce](/docs/fulfilling-partner-orders-pl).

## Czytanie wiersza

| Kolumna | Co mówi |
| --- | --- |
| Job order | numer zlecenia, JOxxx-0001 |
| Artisan | kto to zrobił |
| Made | ile czego - 20 × SKO-01, jedna linia na produkt |
| For | kod organizacji partnerskiej, albo *Stock* (Magazyn) |
| To location | lokalizacja, do której powinno trafić |

**For** jest wyliczane z zapytań stojących za zleceniem. Jeśli każda linia była zamówiona przez tę samą organizację partnerską, towar jest jej, a wiersz to pokazuje, ze skrzynią zbiorczą tego partnera już wpisaną w **To location**. To ta sama skrzynia, której używa [lista pre-pick](/docs/gathering-a-partners-goods-pl): wszystko, co w niej leży, jest już przypisane i przestaje liczyć się jako dostępne dla kogokolwiek innego.

Jeśli zlecenie było zrobione na magazyn, dla własnego klienta, albo dla więcej niż jednego partnera, wiersz pokazuje *Stock*, a pole lokalizacji jest puste. Wpisz kod lokalizacji, do której odkładasz towar.

## Odkładanie towaru

1. Zanieś towar do wskazanej lokalizacji albo do tej, którą wybrałeś.
2. Sprawdź kod w **To location**. Zmień go, jeśli położyłeś towar gdzie indziej.
3. Naciśnij **Put away**.

To księguje towar do lokalizacji, nadaje mu kod partii złożony z numeru zlecenia i kodu produktu, odejmuje surowce, które wg receptury zostały zużyte, i oznacza zlecenie jako przyjęte. Wiersz znika, a na tablicy fabryki linia opuszcza kolumnę **Done**.

Zaksięgowana ilość to tyle, ile rzemieślnicy faktycznie zrobili, a nie ile było zamówione. Zlecenie, które prosiło o 25, a dostało 19, księguje 19.

## Co dzieje się dalej

- **Towar partnera** leży w jego skrzyni, dopóki ktoś na stronie **To produce** nie wyśle skompletowanego zamówienia do magazynu. Kompletacja jest wtedy spacerem do jednej skrzyni. Partner widzi tę linię jako *Staged for you* (Przygotowane dla Ciebie) na swojej liście zakupowej.
- **Towar własnego klienta** trafia do zwykłego magazynu, a czekający dokument wydania jest zwalniany do kompletacji, bo brak, który go wstrzymywał, już nie istnieje.
- **Stock** po prostu staje się dostępny.

## Warto wiedzieć

- **Zakładka pojawia się tylko** dla organizacji, które mają fabrykę.
- **Jedno zlecenie, jedna lokalizacja.** Jeśli zlecenie naprawdę trzeba podzielić między dwa miejsca, odłóż je do magazynu i pozwól liście pre-pick przenieść udział partnera.
- **Zła lokalizacja naprawia się jak każdy inny błąd magazynowy** - przenieś towar między lokalizacjami. Samo zlecenie nie jest otwierane ponownie.
- **Nic tutaj nie jest zarezerwowane, dopóki nie trafi do skrzyni.** Towar odłożony do zwykłego magazynu może zostać skompletowany dla kogokolwiek.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Lista:</b> Twoja organizacja → <b>Warehouse</b> → <b>Dispatching</b> → zakładka <b>From production</b>.</li>
<li><b>Zaksięguj:</b> sprawdź <b>To location</b> → <b>Put away</b>.</li>
<li><b>Sprawdź, co jest w skrzyni:</b> <b>Warehouse</b> → <b>Locations</b> → lokalizacja → zakładka <b>SKOs</b>.</li>
<li><b>Co fabryka jeszcze jest winna:</b> <b>Factory</b> → <b>To produce</b> → <b>Board</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Stanowiska są ustawiane na karcie pracownika w module Human Resources i niosą ze sobą uprawnienia.</li>
<li>Podgląd listy i odkładanie towaru: stanowisko dispatching dla magazynu albo przełożony organizacji.</li>
</ul>
</aside>
