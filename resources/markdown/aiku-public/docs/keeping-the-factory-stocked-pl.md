---
title: Utrzymywanie zapasów fabryki
summary: Strona To restock - które wyroby kończą się najpierw, ile czasu fabryka potrzebuje na wykonanie czegokolwiek, i jak umieścić własną pracę uzupełniającą na tablicy To produce.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, planning
category: production
series: Ordering from partners
order: 11
---

<aside class="tldr">
Dla osoby, która planuje tydzień fabryki. <a href="/docs/fulfilling-partner-orders-pl">To produce</a> (Do produkcji) odpowiada na pytanie <i>o co ktoś poprosił</i>. <b>To restock</b> (Do uzupełnienia) odpowiada na inne pytanie: <i>czego nam zabraknie, niezależnie od tego, czy ktoś już poprosił</i>. Sortuje wszystko, co wytwarza fabryka, wg tego, ile dni zapasu zostało, mierzonych względem tego, ile czasu ta fabryka faktycznie potrzebuje, żeby coś wykonać, i pozwala przenieść to, co warto wykonać, na tablicę To produce.
</aside>

## Miarą jest lead time

Każdy kubełek na tej stronie jest mierzony w **lead times** (czasach realizacji), nie w dniach. Lead time to średnia liczba dni od skierowania pracy na halę do jej powrotu do magazynu, wyliczona z własnych zleceń produkcyjnych tej fabryki z ostatniego roku. Przy mniej niż pięciu zakończonych zleceniach nie ma czego mierzyć, więc aiku używa szacunku - siedmiu dni, chyba że ktoś ustawi inną liczbę na fabryce - i pokazuje *estimate* (szacunek) obok liczby.

Dlatego kubełki czytają się tak, jak się czytają. Wyrób z czterema dniami zapasu nie jest w tarapatach w fabryce, która realizuje pracę w dwa dni; jest już stracony w takiej, która potrzebuje tygodnia.

## Kubełki

| Kubełek | Co oznacza |
| --- | --- |
| Out of stock (Brak w magazynie) | nic na półce |
| Doomed (Skazane) | skończy się, zanim dotarłoby cokolwiek rozpoczętego dziś |
| Critical (Krytyczne) | skończy się w ciągu dwóch lead times |
| Danger (Niebezpieczne) | skończy się w ciągu trzech lead times |
| Watch (Do obserwacji) | skończy się w ciągu czterech lead times |
| Covered (Pokryte) | więcej niż cztery lead times zapasu |
| Dead stock (Martwy zapas) | wartość na półce i zupełny brak sprzedaży |
| Never made yet (Nigdy jeszcze niewytworzone) | wyrób bez żadnego zapisu stanu za nim |

Każdy kubełek niesie trzy liczby: ile wyrobów w nim jest, ile jest **już podjętych** (in hand) i ile jest **nietkniętych** (untouched). Podjęte oznacza, że ktoś już się tym zajął - otwarta pozycja na tablicy To produce albo zlecenie produkcyjne na hali. Nietknięte to liczba do przepracowania.

Kliknij kubełki, żeby wybrać, co pokazuje lista poniżej. Otwiera się na **Out of stock, Doomed and Critical** (Brak w magazynie, Skazane i Krytyczne), co jest uczciwą listą na rano.

## Pasy

Pod kubełkami ta sama praca jest rozłożona na cztery pasy:

- **To do** (Do zrobienia) - wyroby z wybranych kubełków, którymi nic jeszcze nie zajęto. Najpierw pilne. Każdy wiersz niesie kod towaru, co jest na półce, dni zapasu, rodzinę wyrobu, kto zwykle go wykonuje oraz liczbę **units** (sztuk), na jaką zostałoby wystawione zlecenie: zalecaną ilość zamówienia przeliczoną na sztuki i zaokrągloną w górę do najbliższej pełnej partii. Wszystko, o co już poprosił partner, jest pominięte - to zadanie To produce, nie tej strony.
- **Queued** (W kolejce) - pozycje już czekające na tablicy To produce, niezależnie czy przyszły od partnera, czy stąd.
- **Producing** (W produkcji) - pozycje ze zleceniem produkcyjnym na hali, z jego numerem i rzemieślnikiem.
- **Restocked** (Uzupełnione) - co wróciło z hali w ostatnich dwóch tygodniach, żebyś widział, że strona działa.

## Umieszczanie pracy na tablicy

Zaznacz wiersze w **To do** i naciśnij przycisk, żeby je skolejkować. Każdy staje się pozycją na tablicy To produce bez partnera i bez klienta za sobą: po prostu praca, którą fabryka jest winna sama sobie. Stamtąd jest planowana, przypisywana i wykonywana dokładnie jak pozycja partnerska, i opuszcza tablicę, gdy gotowy towar zostaje odłożony.

Pozycja jest pomijana, i mówi o tym, jeśli ten sam towar jest już otwarty na tablicy. Nie da się skolejkować tego samego dwa razy, naciskając przycisk dwa razy.

## Warto wiedzieć

- **Pozycji na zamówienie tu nie ma.** Wyrób, którego SKO jest oznaczone jako *On Demand* (Na zamówienie), jest wykonywany, gdy ktoś o niego poprosi, i nie ma zapasu, który mógłby się skończyć.
- **Martwy zapas to pytanie, nie zadanie.** Wartość leżąca bez ruchu i bez sprzedaży zwykle chce rozmowy ze sklepem, nie zlecenia produkcyjnego.
- **Strona jest liczona na nowo za każdym razem.** Nic nie jest przechowywane, nic nie trzeba sprzątać, a kubełek opróżnia się sam, gdy towar dotrze.
- **Dni zapasu pochodzą z tej samej prognozy**, której używa reszta aiku, patrz [Jak aiku przewiduje, czego Ci zabraknie](/docs/how-aiku-predicts-what-you-run-out-of-pl).

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Strona:</b> Twoja organizacja → <b>Factory</b> (Fabryka) → <b>To restock</b> (Do uzupełnienia).</li>
<li><b>Zmień, co pokazują pasy:</b> kliknij kafelki kubełków u góry.</li>
<li><b>Skolejkuj własną pracę:</b> zaznacz wiersze w <b>To do</b> → przycisk kolejkowania → pojawiają się na <b>To produce</b>.</li>
<li><b>Ustaw szacowany lead time</b>, gdy historia jest jeszcze skąpa: we własnych ustawieniach fabryki; gdy tylko zakończy się pięć zleceń produkcyjnych, liczba pomiarowa przejmuje sama.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Stanowiska są ustawiane na karcie pracownika w module Human Resources (Kadry) i niosą ze sobą uprawnienia.</li>
<li>Podgląd strony: <b>Production operative</b> (pracownik produkcyjny) dla fabryki albo wyżej.</li>
<li>Kolejkowanie pracy do To produce: <b>Production floor supervisor</b> (kierownik hali) dla fabryki albo przełożony organizacji.</li>
</ul>
</aside>
