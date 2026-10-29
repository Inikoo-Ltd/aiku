---
title: Praca z ekranem hali
summary: Przewodnik dla rzemieślnika - ekran Moje zadania, lista zadań, która sama się aktualizuje, START i DONE, co nacisnąć, gdy zrobiłeś mniej niż było trzeba, oraz robienie przerwy.
date: 2026-10-23
source_date: 2026-10-23
tags: production, floor, artisan
category: production
series: Ordering from partners
order: 9
---

<aside class="tldr">
Dla osób, które wytwarzają towar. <b>Factory</b> (Fabryka) → <b>Jobs</b> (Zadania) to cały Twój dzień na jednym ekranie: zadania przypisane do Ciebie po lewej, to nad którym pracujesz - na środku. Naciśnij <b>START</b>, zrób zadanie, wpisz ile zrobiłeś, naciśnij <b>DONE</b> (Gotowe). Jeśli zrobiłeś mniej niż było zamówione, ekran zadaje jedno pytanie: zatrzymać się i kontynuować później, zamknąć zadanie i przenieść resztę do nowego, czy zakończyć je tutaj. Nic więcej do wypełnienia.
</aside>

## Ekran

Strona wygląda jak skrzynka odbiorcza.

**Po lewej, zawsze widoczne:** lista. Na górze dwa liczniki - ile sztuk zrobiono dziś i ile zadań zamknięto dziś. Poniżej **Your jobs** (Twoje zadania) - praca przypisana do Ciebie: całe zadania z Twoim nazwiskiem oraz pojedyncze kroki, które kierownik hali przydzielił Ci w planie dnia (zobacz [Planowanie dnia produkcji](/docs/planning-the-production-day-pl)). Zadanie może mieć wykonanie u jednej osoby, a pakowanie u innej; każda widzi tylko swój krok. Jeśli Twoje stanowisko pozwala brać zadania z puli otwartej, pojawia się sekcja **Open jobs** (Zadania otwarte). Na dole **Finished today** (Zakończone dzisiaj) - to, co już zamknąłeś.

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

## Kroki liczone w opakowaniach

Niektóre kroki, przede wszystkim pakowanie, liczy się w opakowaniach, a nie w pojedynczych sztukach: tacka z 8 kulami do kąpieli liczy się jako 1. Ekran pokazuje wtedy na bursztynowo **Counted in PACKS of 8** (Liczone w OPAKOWANIACH po 8), a pole nazywa się **Packs of 8 made** (Wykonane opakowania po 8). Wpisz, ile pełnych opakowań napełniłeś: dla 56 kul na 7 tackach wpisz 7, nie 56. Pod polem ekran pokazuje, co oznacza twoja liczba, *7 packs of 8 = 56 × ABB-06a*, a potwierdzenie pokazuje to jeszcze raz. Przyjmowane są tylko całe opakowania; jedynym wyjątkiem jest dokładna liczba pozostała do zrobienia, którą wpisujesz, dotykając dużej liczby. Gdy kierownik musi zatwierdzić nadwyżkę, okno pokazuje ją w opakowaniach i w sztukach przed zatwierdzeniem.

## Gdy zrobiłeś mniej niż było zamówione

Wpisz prawdziwą liczbę i naciśnij **DONE**. Pole z liczbą zamienia się w krótki panel: *19 zrobione · 6 do zrobienia* i cztery przyciski.

- **Pause** (Wstrzymaj) - ta sama mieszanka, kontynuujesz później. Zadanie zostaje otwarte z tym samym numerem i tym samym kodem partii, a liczenie jest kontynuowane od 19. Użyj tego, gdy dzień kończy się przed mieszanką.
- **Close at 19 and move remaining 6 to new job …** (Zamknij na 19 i przenieś pozostałe 6 do nowego zadania …) - zadanie zamyka się na 19, a 6 sztuk trafia do nowego zlecenia produkcyjnego z kolejną literą (0127 staje się 0127a) i własnym kodem partii. Użyj tego, gdy reszta będzie nową mieszanką. Ekran prosi o potwierdzenie przyciskiem **Yes, close and move** (Tak, zamknij i przenieś), bo zamkniętego zadania nie można kontynuować.
- **Job finished** (Zadanie zakończone) - zadanie zamyka się na 19 i to koniec sprawy. Planista widzi zlecenie, które prosiło o 25, a dostało 19.
- **Back** (Wstecz) - zmień liczbę.

Zadanie przeniesione do nowego pokazuje na swojej karcie **Continued from 0127** (Kontynuacja z 0127), a to, z którego pochodzi, pokazuje **Continued in 0127a** (Kontynuowane w 0127a). Nowe zadanie zostaje z tymi samymi osobami i w tym samym planie dnia.

## Gdy zrobiłeś więcej niż było zamówione

Wpisz prawdziwą liczbę i naciśnij **DONE**. Ekran ostrzega *18 above target, a manager must authorise it* (18 ponad cel, kierownik musi to zatwierdzić) i otwiera **Manager authorisation required for overproduction**.

- **Scan the badge** - kierownik lub brygadzista przykłada do kamery swój osobisty kod QR (ten sam, którym się odbija przy wejściu). Jest przyjęty, gdy tylko zostanie odczytany. **Switch camera** przełącza między przednią a tylną kamerą.
- **Use manager PIN** - jeśli kamera go nie odczyta, kierownik wpisuje zamiast tego swój PIN do odbijania.

Zatwierdzić może tylko ktoś, kto prowadzi tę halę produkcyjną, i nigdy dla własnej pracy. Po pięciu błędnych kodach lub PIN-ach okno odmawia przez 15 minut.

Po zatwierdzeniu zadanie rośnie do tego, co naprawdę zrobiono: magazyn odkłada całość, to, czego nie zamówiło żadne zamówienie, trafia na stan, a surowce są odejmowane za całą partię. Kolejne kroki tego samego zadania też rosną, żeby następna osoba mogła dokończyć całość. Strona zlecenia produkcyjnego pokazuje, kto zrobił nadwyżkę, kto ją zatwierdził, jak i kiedy.

Wyjątkiem jest praca przeniesiona na inny dzień. Tam żaden kierownik nie może zatwierdzić, by krok przekroczył to, co zrobił poprzedni krok na tej części: jeśli Making skończył tam 138, Labelling zatrzymuje się na 138, a ekran podaje drugą część pracy (na przykład JOaroma-0152a), gdzie zrobiono resztę i gdzie trzeba ją wpisać.

Wpisano złą liczbę, na przykład sztuki tam, gdzie chodziło o opakowania? Kierownik otwiera kartę **Performance** (Wydajność), znajduje wpis pod pracownikiem i naciska **Correct quantity** (Popraw ilość). Wpis można tylko zmniejszyć. Zadanie wraca do tego, co naprawdę zrobiono, zatwierdzona nadwyżka zmniejsza się razem z nim, a wynagrodzenie za ten wpis jest liczone od nowa. Działa to, dopóki magazyn nie zacznie przyjmować zadania.

## Jedna partia dla kilku pozycji

Czasem jedna partia służy kilku zadaniom. Na przykład 100 bochenków HCS-48 i 100 krojonych bochenków SLHCS-48 o tym samym zapachu miesza się, wylewa i formuje razem jako 200 bochenków.

**Łączenie (kierownicy).** Dotknij jednej z pozycji na ekranie hali. Pod jej szczegółami **Combine Production with other lines into one batch** wymienia pozostałe otwarte pozycje czekające na ten sam krok. Zaznacz te, które idą do tej samej partii, i naciśnij **Combine**. Pozycja już połączona, zakończona albo w trakcie pracy nie jest proponowana.

**Praca nad nią (rzemieślnicy).** Pozycje są teraz widoczne jako jeden wiersz z 🔗 i kodami razem, *HCS-48 + SLHCS-48*, oraz sumą, *0/200*. Szczegóły wymieniają każdą pozycję z jej zleceniem. Naciśnij **START** raz, zrób partię, wpisz łączną zrobioną ilość - 200 - i naciśnij **DONE** raz.

**Co Aiku z tym robi.** Suma jest dzielona między pozycje według tego, ile każda miała jeszcze do zrobienia, w całych sztukach: 100 dla HCS-48, 100 dla SLHCS-48. Czas jest dzielony tak samo. Każda pozycja przechodzi potem do własnych kolejnych kroków - foliowanie dla jednej, krojenie, a potem foliowanie dla drugiej. Strona zlecenia pokazuje krok jako *One batch with* (jedna partia z) drugą pozycją.

**Wynagrodzenie i cele** są liczone dla partii jako całości: 200 bochenków w godzinach, jakie to zajęło, wobec celu kroku. Łączenie płaci dokładnie tyle, co osobne partie przy tej samej szybkości. Gdy pozycje mają różne cele dla kroku, cel partii to ten, który zajmuje tyle samo godzin, co osobne partie.

Zrobienie więcej, niż chciały wszystkie pozycje razem, wymaga kodu lub PIN-u kierownika, jak wyżej; nadwyżka też jest dzielona między pozycje. Kierownik może pozycje znów rozdzielić (**Separate**), kiedy nikt nad nimi nie pracuje. To, co już zrobiono razem, zostaje przy każdej pozycji.

## Mieszanie i inne kroki ważone w kg

Mieszanie to osobny krok na początku receptury i liczy się je w kg, a nie w sztukach. Ekran pokazuje przy liczbach **kg**, a pole nazywa się **Kg made** (Wykonane kg): dla mieszanki 50 kg wpisz 50. Pod polem ekran pokazuje, co z tego wyjdzie, *50 kg of mix = 500 × ABB-06a*. Mieszanka, która wyjdzie trochę ponad to, czego wymagało zadanie, jest przyjmowana bez kierownika.

Krok widzą i mogą go rozpocząć tylko rzemieślnicy wskazani przy tym kroku; nie ma go na liście nikogo innego. Jest płatny za godzinę według stawki podstawowej: bez celu, bez progu płacowego i bez uwagi o niewykonaniu celu, a czas nadal liczy się do kosztu produktu.

Następny krok pozostaje zablokowany z napisem **Waiting for** (Oczekiwanie na) i nazwą kroku mieszania, dopóki nie zostanie zapisana jakaś mieszanka, i otwiera się zaraz po wpisaniu pierwszej. Jedna mieszanka dla kilku pozycji działa jak w części powyżej: kierownik łączy pozycje mieszania, mieszający wpisuje kg raz, a każda pozycja dostaje swoją część.

**Mieszanki w pełnych partiach.** Gdy produkt ma rozmiar partii mieszanki (25 kg lub 50 kg), wymagane kg zaokrągla się w górę do pełnych partii i pokazuje jako partie: 94,1 kg w partiach po 50 kg pokazuje się jako *2 × 50 kg batches = 100 kg* (2 partie po 50 kg = 100 kg). Pole nazywa się **Batches of 50 kg made** (Zrobione partie po 50 kg) i wpisuje się liczbę partii, 2, a nie kg. Przy połączonej mieszance zaokrągla się sumę wszystkich linii. Nadwyżka ponad zlecenie nie zmienia zlecenia, ilości do formowania ani wypłaty. Kierownicy ustawiają to w kroku receptury produktu, w **Mix batch size** (Rozmiar partii mieszanki).

**Ustawienie (kierownicy).** Na stronie zadania produkcyjnego **Only these artisans can do it** (Mogą je wykonywać tylko ci rzemieślnicy) pokazuje, kto może je wykonywać; tam dodaje się lub usuwa osoby. Formularz edycji ma **Paid by piece rate** (Płatne od sztuki; wyłączone dla pracy godzinowej) oraz **Recorded in kg** (Zapisywane w kg). Ile kg mieszanki przypada na jedną sztukę, wpisuje się w kroku receptury każdego produktu.

## Robienie przerwy

Pod obszarem roboczym jest rząd **Break:** (Przerwa) z przyciskami **5m**, **20m** i **30m**. Dotknij długości przerwy, którą bierzesz, i potwierdź przyciskiem **Yes, start break** (Tak, zacznij przerwę).

- Jeśli jesteś odbity na zegarze, przerwa Cię wyloguje z zegara.
- Jeśli masz otwarte zadanie, jego zegar zatrzymuje się na czas przerwy: czas przerwy nie liczy się jako czas pracy nad zadaniem.
- Ekran zamienia się w duży panel **On break** (Na przerwie): czas od początku przerwy, **Planned:** (Planowana) z wybraną długością i duży przycisk **Finish break** (Zakończ przerwę).

**Przerwa nie kończy się sama.** Gdy wrócisz do stanowiska, naciśnij **Finish break**. To ponownie Cię odbija na zegarze i zapisuje, ile przerwa naprawdę trwała. Odbicie się kodem QR przy kiosku też kończy przerwę.

Gdy przerwa przekroczy zaplanowany czas, panel pokazuje, o ile. Gdy przekroczenie jest większe niż kilka minut (5, chyba że kierownik produkcji zmienił tę wartość), panel robi się czerwony, a przerwa pokazuje się jako przekroczona brygadzistom i kierownikowi na karcie **Performance** (Wydajność).

Rzemieślnicy płatni godzinowo nie mają przycisków przerwy. Robią przerwy, odbijając się kodem QR, a zadanie trwa dalej, gdy odbiją się ponownie.

**Dla brygadzistów i kierowników.** Karta **Performance** ma tabelę **Breaks** (Przerwy): dla każdego rzemieślnika i dnia liczbę przerw, minuty zaplanowane, minuty faktycznie wykorzystane i minuty ponad plan. Zaznacz **Breaks over plan only** (Tylko przerwy ponad plan) i naciśnij **Apply** (Zastosuj), aby zobaczyć tylko przerwy, które się przeciągnęły. Czerwone pole na górze wymienia przerwy, które właśnie trwają za długo, oraz przerwy wciąż otwarte po zakończeniu zmiany. Dla przerwy pozostawionej otwartej po zmianie naciśnij **Set end** (Ustaw koniec), wpisz godzinę, o której rzemieślnik naprawdę wrócił, i zapisz; godziny i wynagrodzenie zadań, które przerwa obejmuje, są liczone od nowa, a pierwotnie zapisany koniec zostaje w historii. Odbicia nie są tam zmieniane: poprawia się je w Human Resources. Kierownik produkcji ustawia dopuszczalne przekroczenie kołem zębatym obok nagłówka **Breaks**.

## Warto wiedzieć

- **Jedno zadanie naraz.** Gdy masz otwarte zadanie, przyciski START są nieaktywne. Najpierw je zamknij.
- **Twoja zapłata za zadanie** zależy od liczby w polu Quantity made, jak wyjaśniono w [Stanowiska w fabryce](/docs/factory-positions-pl). Odrzucone sztuki nie są płacone.
- **Open jobs** to zadania i kroki bez przypisanej osoby, albo z przypisaną inną osobą. Wzięcie takiego zadania jest w porządku, jeśli pozwala na to Twoje stanowisko - staje się wtedy Twoje aż do zamknięcia.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Twój ekran:</b> Twoja organizacja → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Start:</b> dotknij wiersza → <b>START</b>.</li>
<li><b>Zakończ:</b> wpisz <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Mniej niż zamówiono:</b> <b>DONE</b> → <b>Pause</b>, <b>Close at … and move remaining …</b> lub <b>Job finished</b>.</li>
<li><b>Robienie przerwy:</b> <b>Break:</b> <b>5m</b>, <b>20m</b> lub <b>30m</b> → <b>Yes, start break</b>. Po powrocie: <b>Finish break</b>.</li>
<li><b>Przerwa pozostawiona otwarta po zmianie (brygadziści):</b> <b>Factory</b> → <b>Artisans</b> → <b>Performance</b> → <b>Set end</b>.</li>
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
