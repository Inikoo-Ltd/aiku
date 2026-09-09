---
title: Stanowiska w fabryce
summary: Cztery stanowiska w fabryce - kierownik hali, przygotowujący mieszanki, brygadzista i pracownik produkcyjny - co każde z nich może robić w aiku i jak przypisuje się stanowisko danej osobie.
date: 2026-09-08
source_date: 2026-09-08
tags: production, hr
category: production
series: Ordering from partners
order: 7
---

<aside class="tldr">
Dla kierowników i dla każdego, kto chce wiedzieć, na co pozwala jego login. Fabryka w aiku ma cztery stanowiska, od osoby planującej całą halę aż po osobę, która wykonuje produkty. Stanowiska nadaje się na karcie pracownika w module <b>Human Resources</b> (Kadry), jednym zaznaczeniem na fabrykę, a uprawnienia przypisują się automatycznie. Nikt nie edytuje uprawnień ręcznie. Ekrany używane na co dzień są opisane w artykułach <a href="/docs/fulfilling-partner-orders-pl">Praca z listą To produce (Do produkcji)</a>, <a href="/docs/preparing-mixes-pl">Przygotowywanie mieszanek</a> i <a href="/docs/working-the-floor-screen-pl">Praca na ekranie hali</a>.
</aside>

## Cztery stanowiska

Od najwyższego do najniższego. Każde kolejne obejmuje wszystko, co ma stanowisko poniżej na ekranie hali, ale nie ma uprawnień planistycznych stanowiska powyżej.

**Floor supervisor** (kierownik hali). Prowadzi fabrykę. Widzi listę **To produce** (Do produkcji) ze wszystkimi zakładkami, przesuwa karty po Board, żeby tworzyć zlecenia produkcyjne, kieruje je na halę, może przyjmować je na magazyn, gdy magazyn ich nie odłożył, decyduje, kto zwykle co wykonuje, i prowadzi listę rzemieślników. Zwykle jedna lub dwie osoby na fabrykę.

**Mix preparer** (przygotowujący mieszanki). Prowadzi mieszanki i bazy, na których pracują rzemieślnicy. Widzi **To produce** oraz zakładkę **Mixes** (Mieszanki), tworzy zlecenia na mieszanki, przeciągając kartę do siebie, oraz kieruje na halę i przyjmuje wyłącznie te zlecenia, które są przypisane jemu samemu. Nie może dotknąć cudzego zlecenia. Pracuje na hali jak rzemieślnik.

**Foreman** (brygadzista). Pilnuje porządku wśród pracowników produkcyjnych. Widzi wszystko na hali i wszystkie zlecenia, może zmieniać pozycje i ilości w zleceniu oraz może rozpocząć zadanie w imieniu innej osoby. Nie może tworzyć zleceń z listy **To produce**, kierować ich na halę ani zmieniać, kto co wykonuje. To osoba, do której idzie się, gdy zadanie utknęło.

**Operative** (pracownik produkcyjny). Wykonuje produkty. Jego ekran to **Factory → Jobs** (Fabryka → Zlecenia): zlecenia przypisane jemu, a przy każdym START i DONE. Może zajrzeć do To produce i Board, żeby zobaczyć, co nadchodzi, ale nic tam nie może przesuwać. Odrzuty zapisuje brygadzista lub ktoś wyżej, nie sam pracownik produkcyjny.

## Jak nadaje się stanowisko

1. **Human Resources** (Kadry) → **Employees** (Pracownicy), otwórz kartę osoby, **Edit** (Edytuj), a następnie **Job Positions (permissions)** (Stanowiska - uprawnienia).
2. Rozwiń organizację. Znajdź wiersz **Production** (Produkcja).
3. Zaznacz jedno stanowisko. Jeśli organizacja ma więcej niż jedną fabrykę, wybierz, których fabryk to dotyczy.
4. Zapisz. Uprawnienia, a wraz z nimi sama fabryka, pojawiają się na koncie danej osoby od razu. Jeśli sekcja Factory (Fabryka) mimo to nie widnieje w jej menu bocznym, poproś ją o wylogowanie i ponowne zalogowanie się.

Jedna osoba może mieć kilka stanowisk, zarówno w fabryce, jak i w innych obszarach. Kierownik hali, który dodatkowo kompletuje zamówienia w magazynie, po prostu ma zaznaczone oba wiersze.

## Warto wiedzieć

- **Ekran hali jest taki sam dla wszystkich.** Zmienia się to, które zlecenia się pojawiają i jakie przyciski są dostępne, a nie sam układ ekranu. Brygadzista i wyżej widzą też otwartą pulę zleceń, na których nikt nie jest wskazany, i mogą jedno wziąć.
- **Gotowe wyroby to sprawa magazynu.** Gdy ostatnie zadanie jest gotowe, zlecenie produkcyjne trafia pod <a href="/docs/putting-away-finished-production-pl">Dispatching → From production</a>, żeby dyspozytor je odłożył. Żadne stanowisko w fabryce nie jest do tego potrzebne.
- **Stanowiska są przypisywane per fabryka.** Ta sama osoba może być kierownikiem hali w jednej fabryce, a pracownikiem produkcyjnym w innej.
- **Bez stanowiska nie ma fabryki.** Osoba bez żadnego stanowiska w Production w ogóle nie widzi sekcji Factory, nawet jeśli w innej fabryce jest kierownikiem.
- **Administratorzy organizacji** mają to wszystko bez potrzeby nadawania stanowiska w fabryce.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Nadanie stanowiska:</b> Twoja organizacja → <b>Human Resources</b> → <b>Employees</b> → otwórz kartę osoby → <b>Edit</b> → <b>Job Positions (permissions)</b> → rozwiń organizację → wiersz <b>Production</b>.</li>
<li><b>Sprawdzenie, co ktoś ma:</b> na tej samej stronie widać zaznaczenie przy każdym stanowisku, które dana osoba posiada.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Nadawanie lub zmiana stanowisk: przełożony ds. kadr (Human Resources) dla danej organizacji lub administrator organizacji.</li>
</ul>
</aside>
