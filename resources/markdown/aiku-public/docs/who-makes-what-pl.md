---
title: Kto co wykonuje
summary: Naucz aiku, którzy rzemieślnicy zwykle wykonują daną kategorię lub wyrób, aby lista To produce sama się sortowała na stosy dla poszczególnych osób. Rekomendacja, nigdy blokada.
date: 2026-09-08
source_date: 2026-09-08
tags: production, crafts, hr
category: production
series: Ordering from partners
order: 5
---

<aside class="tldr">
Dla kierownika fabryki albo planisty. Dwa drobne elementy konfiguracji sprawiają, że lista <a href="/docs/fulfilling-partner-orders-pl">To produce</a> staje się użyteczna: umieść wyroby w <b>categories</b> (kategoriach, na ekranie nazywanych działami) i przypisz <b>artisans</b> (rzemieślników), którzy zwykle wykonują daną kategorię lub wyrób. Wtedy widok <i>By artisan</i> sam buduje stos każdej osoby. Nic tutaj nie blokuje przekazania pracy komuś innemu - to tylko mówi, kto normalnie to robi.
</aside>

## Kategorie i rodziny

Wyrób (artefact) to jedna rzecz, którą wykonuje fabryka. Kategoria, na ekranie **dział** (department), to półka takich rzeczy: kule do kąpieli, mydło, olejki eteryczne, gama marki. Każdy wyrób należy najwyżej do jednego działu. Wewnątrz działu **rodziny** (families) to drobniejsze grupowanie, takie samo jak rodziny produktów w katalogu: lawendowe kule do kąpieli, te cytrusowe. Rodziny są opcjonalne i tylko pomagają coś odnaleźć; nic nie jest planowane wg rodziny.

- **Factory → Crafts → Artefacts** (Fabryka → Rzemiosło → Wyroby) ma trzy zakładki: **Departments** (Działy), **Families** (Rodziny) i **All artefacts** (Wszystkie wyroby). Otwórz dział albo rodzinę, aby zobaczyć jej wyroby, i użyj przycisku **new** (nowa) na dowolnej z tych dwóch zakładek, aby ją utworzyć.
- Aby przenieść wyroby, zaznacz je na dowolnej liście wyrobów i użyj **Move to department** (Przenieś do działu) albo **Move to family** (Przenieś do rodziny).
- Usunięcie rodziny pozostawia jej wyroby na miejscu, bez rodziny.
- Cały katalog wyrobów albo surowców można wczytać naraz przyciskiem **upload** (wczytaj) na liście.

Działy napędzają dwie rzeczy: widok *By category* listy To produce oraz zapasowy wybór rzemieślnika, opisany dalej.

## Rzemieślnicy

Na każdej stronie działu i każdej stronie wyrobu jest wiersz pod tytułem: **Usually made by** (Zwykle wykonuje). Rodziny go nie mają.

- Wybierz nazwisko z **Add artisan…** (Dodaj rzemieślnika…), aby kogoś przypisać. Proponowani są tylko aktualnie zatrudnieni pracownicy Twojej organizacji.
- Przypisz tyle osób, ile chcesz. Pierwsza jest wyróżniona; to domyślny właściciel.
- Kliknij mały krzyżyk na plakietce, aby odłączyć osobę. Kolejność ma znaczenie: pierwsza przypisana osoba pozostaje pierwsza, dopóki nie zostanie usunięta.

aiku czyta to tak. Dla pozycji w To produce najpierw patrzy na wyrób. Jeśli wyrób ma rzemieślników, pierwszy z nich jest właścicielem pozycji. Jeśli nie, patrzy na dział wyrobu i bierze pierwszego rzemieślnika stamtąd. Jeśli nigdzie nikogo nie ma, pozycja siedzi pod *Unassigned* (Nieprzypisane), a na Board pytanie *Kto to wykona?* pojawia się bez zaproponowanego imienia.

Więc najprostszy sposób skonfigurowania fabryki to: przypisać rzemieślników do działów i dotykać poszczególnych wyrobów tylko dla wyjątków. Jedna osoba robi całe mydło poza jedną kostką, która wymaga innej pary rąk.

## Czym to nie jest

- **Nie jest blokadą.** Zlecenia produkcyjne i sesje zadań tego nie sprawdzają. Każdy może zrobić wszystko, a planista może wybrać dowolne imię, gdy karta zostanie upuszczona w *Assigned*.
- **Nie jest rejestrem umiejętności.** Mówi, kto zwykle to robi, co jest niezłą wskazówką, kto jest w tym dobry, ale nikt nie jest za to oceniany.
- **Nie jest historią.** Kto faktycznie co wykonał, jest pod **Factory → Artisans → Performance** (Fabryka → Rzemieślnicy → Wyniki), zbudowanym z zamkniętych sesji zadań.
- **Nie jest listą osób.** Kto w ogóle liczy się jako rzemieślnik, pokazuje pasek *Open job orders per artisan* (Otwarte zlecenia na rzemieślnika) na To produce, gdzie krzyżyk ukrywa osobę, która nie wytwarza rzeczy.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Działy i rodziny:</b> Twoja organizacja → <b>Factory</b> (Fabryka) → <b>Crafts</b> (Rzemiosło) → <b>Artefacts</b> (Wyroby) → zakładka <b>Departments</b> (Działy) albo <b>Families</b> (Rodziny).</li>
<li><b>Przenieś wyroby:</b> zaznacz wyroby na dowolnej liście wyrobów → <b>Move to department</b> (Przenieś do działu) albo <b>Move to family</b> (Przenieś do rodziny).</li>
<li><b>Przypisz rzemieślnika:</b> otwórz dział albo wyrób → <b>Usually made by</b> (Zwykle wykonuje) → <b>Add artisan…</b> (Dodaj rzemieślnika…). Odłącz krzyżykiem na plakietce.</li>
<li><b>Zobacz efekt:</b> <b>Factory</b> (Fabryka) → <b>To produce</b> (Do produkcji) → <b>By artisan</b> (Wg rzemieślnika).</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Przypisywanie i odłączanie rzemieślników: stanowisko <b>Production floor supervisor</b> (kierownik hali) dla fabryki albo przełożony organizacji. Stanowiska są ustawiane na karcie pracownika w module Human Resources (Kadry). Każdy, kto widzi tę stronę, widzi nazwiska.</li>
<li>Tworzenie działów i rodzin, przenoszenie wyrobów, wczytywanie: przełożony organizacji. Żadne stanowisko w fabryce jeszcze tego nie obejmuje.</li>
</ul>
</aside>
