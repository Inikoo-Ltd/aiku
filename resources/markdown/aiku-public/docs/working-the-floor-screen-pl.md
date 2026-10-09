---
title: Praca z ekranem hali
summary: Przewodnik dla rzemieślnika - ekran Moje zadania, lista zadań, która sama się aktualizuje, START i DONE, oraz co nacisnąć, gdy zrobiłeś mniej niż było trzeba.
date: 2026-10-09
source_date: 2026-10-09
tags: production, floor, artisan
category: production
series: Ordering from partners
order: 9
---

<aside class="tldr">
Dla osób, które wytwarzają towar. <b>Factory</b> (Fabryka) → <b>Jobs</b> (Zadania) to cały Twój dzień na jednym ekranie: zadania przypisane do Ciebie po lewej, to nad którym pracujesz - na środku. Naciśnij <b>START</b>, zrób zadanie, wpisz ile zrobiłeś, naciśnij <b>DONE</b> (Gotowe). Jeśli zrobiłeś mniej niż było zamówione, ekran zadaje jedno pytanie: zamknąć zadanie tutaj, czy przenieść resztę do nowego zadania. Nic więcej do wypełnienia.
</aside>

## Ekran

Strona wygląda jak skrzynka odbiorcza.

**Po lewej, zawsze widoczne:** lista. Na górze dwa liczniki - ile sztuk zrobiono dziś i ile zadań zamknięto dziś. Poniżej **Your jobs** (Twoje zadania) - praca przypisana do Ciebie. Jeśli Twoje stanowisko pozwala brać zadania z puli otwartej, pojawia się sekcja **Open jobs** (Zadania otwarte). Na dole **Finished today** (Zakończone dzisiaj) - to, co już zamknąłeś.

**Po prawej:** to, co masz zaznaczone. Zanim zaczniesz, są to szczegóły zadania z dużym przyciskiem **START**. Po rozpoczęciu jest to karta pracy z zegarem.

Listy nie trzeba odświeżać. Gdy planista przypisze Ci coś nowego, gdy kolega weźmie jedno z otwartych zadań, gdy zamkniesz swoje - lista zmienia się sama, na każdym ekranie pokazującym tę fabrykę.

## Jeden wiersz na liście

Każdy wiersz to jedno zadanie w jednym zleceniu produkcyjnym:

| Kolumna | Co znaczy |
| --- | --- |
| Code | produkt do zrobienia - SKO-01 |
| Name | jego nazwa |
| Task · job order | krok (Production, Labelling...) i numer zlecenia produkcyjnego |
| 0/25 | zrobiono dotąd / ile było zamówione |

Wiersz z zieloną strzałką ▶ i godziną to ten, nad którym pracujesz, oraz kiedy go zacząłeś. Żółta notatka pod wierszem oznacza, że zadanie czeka na mieszankę, albo że ktoś inny już je otworzył.

## Wykonywanie zadania

1. Dotknij wiersza. Szczegóły pojawią się po prawej.
2. Naciśnij **START**. Zegar startuje, a wiersz dostaje zielony znacznik.
3. Zrób zadanie.
4. Wpisz liczbę, którą zrobiłeś, w polu **Quantity made** (Wykonana ilość). Jeśli jesteś brygadzistą lub wyżej, jest też pole **Rejected** (Odrzucone) na sztuki, które nie przeszły kontroli.
5. Naciśnij **DONE**.

Zrobiłeś wszystko, co było zamówione? To tyle. Zadanie się zamyka, zlecenie produkcyjne jest zakończone, a magazyn dostaje informację, że jest coś do odłożenia - zobacz [Odkładanie gotowej produkcji](/docs/putting-away-finished-production-pl).

## Gdy zrobiłeś mniej niż było zamówione

Wpisz prawdziwą liczbę i naciśnij **DONE**. Pole z liczbą zamienia się w krótki panel: *19 zrobione · 6 do zrobienia* i trzy przyciski.

- **Continue later** (Kontynuuj później) - zadanie zamyka się na 19, a nowe zlecenie na 6 sztuk pojawia się na Twojej liście, przypisane do Ciebie. Weźmiesz je jutro, albo gdy przyjedzie materiał.
- **Job finished** (Zadanie zakończone) - zadanie zamyka się na 19 i to koniec sprawy. Planista widzi zlecenie, które prosiło o 25, a dostało 19.
- **Back** (Wstecz) - zmień liczbę.

Tak czy inaczej zadanie, nad którym pracowałeś, zostaje zamknięte i opłacone za tyle, ile zrobiłeś. Nic nie zostaje na wpół otwarte na Twojej liście.

## Gdy zrobiłeś więcej niż było zamówione

Wpisz prawdziwą liczbę i naciśnij **DONE**. Ekran ostrzega *18 above target, a manager must authorise it* (18 ponad cel, kierownik musi to zatwierdzić) i otwiera **Manager authorisation required for overproduction**.

- **Scan the badge** - kierownik lub brygadzista przykłada do kamery swój osobisty kod QR (ten sam, którym się odbija przy wejściu). Jest przyjęty, gdy tylko zostanie odczytany. **Switch camera** przełącza między przednią a tylną kamerą.
- **Use manager PIN** - jeśli kamera go nie odczyta, kierownik wpisuje zamiast tego swój PIN do odbijania.

Zatwierdzić może tylko ktoś, kto prowadzi tę halę produkcyjną, i nigdy dla własnej pracy. Po pięciu błędnych kodach lub PIN-ach okno odmawia przez 15 minut.

Po zatwierdzeniu zadanie rośnie do tego, co naprawdę zrobiono: magazyn odkłada całość, to, czego nie zamówiło żadne zamówienie, trafia na stan, a surowce są odejmowane za całą partię. Kolejne kroki tego samego zadania też rosną, żeby następna osoba mogła dokończyć całość. Strona zlecenia produkcyjnego pokazuje, kto zrobił nadwyżkę, kto ją zatwierdził, jak i kiedy.

## Jedna partia dla kilku pozycji

Czasem jedna partia służy kilku zadaniom. Na przykład 100 bochenków HCS-48 i 100 krojonych bochenków SLHCS-48 o tym samym zapachu miesza się, wylewa i formuje razem jako 200 bochenków.

**Łączenie (kierownicy).** Dotknij jednej z pozycji na ekranie hali. Pod jej szczegółami **Combine Production with other lines into one batch** wymienia pozostałe otwarte pozycje czekające na ten sam krok. Zaznacz te, które idą do tej samej partii, i naciśnij **Combine**. Pozycja już połączona, zakończona albo w trakcie pracy nie jest proponowana.

**Praca nad nią (rzemieślnicy).** Pozycje są teraz widoczne jako jeden wiersz z 🔗 i kodami razem, *HCS-48 + SLHCS-48*, oraz sumą, *0/200*. Szczegóły wymieniają każdą pozycję z jej zleceniem. Naciśnij **START** raz, zrób partię, wpisz łączną zrobioną ilość - 200 - i naciśnij **DONE** raz.

**Co Aiku z tym robi.** Suma jest dzielona między pozycje według tego, ile każda miała jeszcze do zrobienia, w całych sztukach: 100 dla HCS-48, 100 dla SLHCS-48. Czas jest dzielony tak samo. Każda pozycja przechodzi potem do własnych kolejnych kroków - foliowanie dla jednej, krojenie, a potem foliowanie dla drugiej. Strona zlecenia pokazuje krok jako *One batch with* (jedna partia z) drugą pozycją.

**Wynagrodzenie i cele** są liczone dla partii jako całości: 200 bochenków w godzinach, jakie to zajęło, wobec celu kroku. Łączenie płaci dokładnie tyle, co osobne partie przy tej samej szybkości. Gdy pozycje mają różne cele dla kroku, cel partii to ten, który zajmuje tyle samo godzin, co osobne partie.

Zrobienie więcej, niż chciały wszystkie pozycje razem, wymaga kodu lub PIN-u kierownika, jak wyżej; nadwyżka też jest dzielona między pozycje. Kierownik może pozycje znów rozdzielić (**Separate**), kiedy nikt nad nimi nie pracuje. To, co już zrobiono razem, zostaje przy każdej pozycji.

## Warto wiedzieć

- **Jedno zadanie naraz.** Gdy masz otwarte zadanie, przyciski START są nieaktywne. Najpierw je zamknij.
- **Twoja zapłata za zadanie** zależy od liczby w polu Quantity made, jak wyjaśniono w [Stanowiska w fabryce](/docs/factory-positions-pl). Odrzucone sztuki nie są płacone.
- **Open jobs** to zadania bez przypisanego rzemieślnika, albo z przypisaną inną osobą. Wzięcie takiego zadania jest w porządku, jeśli pozwala na to Twoje stanowisko - staje się wtedy Twoje aż do zamknięcia.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Twój ekran:</b> Twoja organizacja → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Start:</b> dotknij wiersza → <b>START</b>.</li>
<li><b>Zakończ:</b> wpisz <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Mniej niż zamówiono:</b> <b>DONE</b> → <b>Continue later</b> lub <b>Job finished</b>.</li>
<li><b>Jedna partia dla kilku pozycji:</b> dotknij pozycji → <b>Combine … with other lines into one batch</b> → zaznacz pozycje → <b>Combine</b>. Aby cofnąć: dotknij połączonego wiersza → <b>Separate</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Stanowiska są ustawiane na karcie pracownika w module Human Resources i niosą ze sobą uprawnienia.</li>
<li>Podgląd własnych zadań, START i DONE: stanowisko <b>Operative</b> dla fabryki.</li>
<li>Podgląd i branie <b>Open jobs</b>, zapisywanie braków: <b>Foreman</b>, <b>Mix preparer</b> albo <b>Floor supervisor</b>.</li>
<li>Łączenie i rozdzielanie pozycji: kierownicy prowadzący halę produkcyjną, ci sami, którzy mogą zatwierdzić nadprodukcję.</li>
</ul>
</aside>
