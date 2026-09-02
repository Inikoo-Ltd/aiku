---
title: Kto co wykonuje
summary: Naucz aiku, którzy rzemieślnicy zwykle wykonują daną kategorię lub wyrób, aby lista To produce sama się sortowała na stosy dla poszczególnych osób. Rekomendacja, nigdy blokada.
date: 2026-09-02
source_date: 2026-09-02
tags: production, crafts, hr
category: production
series: Ordering from partners
order: 5
---

<aside class="tldr">
Dla kierownika fabryki albo planisty. Dwa drobne elementy konfiguracji sprawiają, że lista <a href="/docs/fulfilling-partner-orders-pl">To produce</a> staje się użyteczna: umieść wyroby w <b>categories</b> (kategoriach) i przypisz <b>artisans</b> (rzemieślników), którzy zwykle wykonują daną kategorię lub wyrób. Wtedy widok <i>By artisan</i> sam buduje stos każdej osoby. Nic tutaj nie blokuje przekazania pracy komuś innemu - to tylko mówi, kto normalnie to robi.
</aside>

## Kategorie

Wyrób (artefact) to jedna rzecz, którą wykonuje fabryka. Kategoria to półka takich rzeczy: kule do kąpieli, mydło, olejki eteryczne, gama marki. Każdy wyrób należy najwyżej do jednej kategorii.

- **Factory → Crafts → Artefact families** (Fabryka → Rzemiosło → Rodziny wyrobów) wyświetla kategorie z liczbą wyrobów w każdej z nich. Otwórz jedną, aby zobaczyć jej wyroby.
- Aby przenieść wyroby między kategoriami, zaznacz je na dowolnej liście wyrobów i użyj **Move to family** (Przenieś do rodziny). Aby utworzyć kategorię, użyj przycisku **new** (nowa) na liście.

Kategorie napędzają dwie rzeczy: widok *By category* listy To produce oraz zapasowy wybór rzemieślnika, opisany dalej.

## Rzemieślnicy

Na każdej stronie kategorii i każdej stronie wyrobu jest wiersz pod tytułem: **Usually made by** (Zwykle wykonuje).

- Wybierz nazwisko z **Add artisan…** (Dodaj rzemieślnika…), aby kogoś przypisać. Proponowani są tylko aktualnie zatrudnieni pracownicy Twojej organizacji.
- Przypisz tyle osób, ile chcesz. Pierwsza jest wyróżniona; to domyślny właściciel.
- Kliknij mały krzyżyk na plakietce, aby odłączyć osobę. Kolejność ma znaczenie: pierwsza przypisana osoba pozostaje pierwsza, dopóki nie zostanie usunięta.

aiku czyta to tak. Dla pozycji w To produce najpierw patrzy na wyrób. Jeśli wyrób ma rzemieślników, pierwszy z nich jest właścicielem pozycji. Jeśli nie, patrzy na kategorię wyrobu i bierze pierwszego rzemieślnika stamtąd. Jeśli nigdzie nikogo nie ma, pozycja siedzi pod *Unassigned* (Nieprzypisane).

Więc najprostszy sposób skonfigurowania fabryki to: przypisać rzemieślników do kategorii i dotykać poszczególnych wyrobów tylko dla wyjątków. Jedna osoba robi całe mydło poza jedną kostką, która wymaga innej pary rąk.

## Czym to nie jest

- **Nie jest blokadą.** Zlecenia produkcyjne i sesje zadań tego nie sprawdzają. Każdy może zrobić wszystko.
- **Nie jest rejestrem umiejętności.** Mówi, kto zwykle to robi, co jest niezłą wskazówką, kto jest w tym dobry, ale nikt nie jest za to oceniany.
- **Nie jest historią.** Kto faktycznie co wykonał, jest na ekranach rzemieślników pod **Factory → Operations → Artisans** (Fabryka → Operacje → Rzemieślnicy), zbudowanych z zamkniętych sesji zadań.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Kategorie:</b> Twoja organizacja → <b>Factory</b> (Fabryka) → <b>Crafts</b> (Rzemiosło) → <b>Artefact families</b> (Rodziny wyrobów).</li>
<li><b>Przenieś wyroby:</b> zaznacz wyroby na dowolnej liście wyrobów → <b>Move to family</b> (Przenieś do rodziny).</li>
<li><b>Przypisz rzemieślnika:</b> otwórz kategorię albo wyrób → <b>Usually made by</b> (Zwykle wykonuje) → <b>Add artisan…</b> (Dodaj rzemieślnika…). Odłącz krzyżykiem na plakietce.</li>
<li><b>Zobacz efekt:</b> <b>Factory</b> (Fabryka) → <b>To produce</b> (Do produkcji) → <b>By artisan</b> (Wg rzemieślnika).</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Przypisywanie i odłączanie rzemieślników: stanowisko <b>Production floor supervisor</b> (kierownik hali) dla fabryki albo przełożony organizacji. Stanowiska są ustawiane na karcie pracownika w module Human Resources (Kadry). Każdy, kto widzi tę stronę, widzi nazwiska.</li>
</ul>
</aside>
