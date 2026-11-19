---
title: Planowanie dnia produkcji
summary: Dla kierownika produkcji i kierownika hali - wstawianie zadań do planu dnia bez wskazywania osoby, przydzielanie każdego kroku pracownikowi oraz co dzieje się z pracą niezakończoną w swoim dniu.
date: 2026-10-15
source_date: 2026-11-19
tags: production, planning, floor
category: production
series: Ordering from partners
order: 13
---

<aside class="tldr">
Dwie osoby, dwa zadania. <b>Kierownik produkcji</b> decyduje, co powstaje danego dnia: na tablicy <b>To produce</b> (Do produkcji) wybiera karty i naciska <b>Plan for this day</b> (Zaplanuj na ten dzień), nie wskazując nikogo. <b>Kierownik hali</b> (Floor supervisor) otwiera <b>Operations → Day plan</b> (Operacje → Plan dnia) i przydziela pracownikowi każdy krok każdego zaplanowanego zadania. Każdy pracownik widzi potem swoje kroki na ekranie hali. Wszystko, co nie zostało skończone w swoim dniu, zostaje w planie na bursztynowo, dopóki ktoś nie potwierdzi tego na nowy dzień.
</aside>

## Kierownik produkcji planuje dzień

Na **Factory → To produce** (Fabryka → Do produkcji) tablica działa jak dotąd: karty przechodzą z **Backlog** (Zaległe) do **Preparing** (W przygotowaniu), gdzie ustawia się ilość, a z **Preparing** do **Assigned** (Przypisane), gdzie powstaje zlecenie produkcyjne.

Gdy karta zostaje upuszczona na **Assigned**, małe okno z pytaniem *Kto to robi?* zaczyna się teraz od daty i przycisku:

1. Wybierz dzień. Okno otwiera się na dzisiaj.
2. Naciśnij **Plan for this day**.

Powstaje zlecenie produkcyjne na ten dzień, bez wskazanej osoby. Każda karta staje się osobnym zleceniem, więc każde można ustawiać w kolejności i przesuwać osobno. Kilka kart zaznaczonych i upuszczonych razem jest planowanych razem, po jednym zleceniu na kartę.

Wybranie nazwiska z listy poniżej działa tak jak zawsze: całe zadanie trafia do tej osoby i nie ma go w planie żadnego dnia.

Zadanie, które ma już wskazaną osobę, też można wstawić do planu: naciśnij ołówek obok nazwiska na jego karcie, a potem **Plan for this day**. Nazwisko zostaje zdjęte z zadania, a jego kroki przydziela kierownik hali.

## Kierownik hali przydziela kroki

**Factory → Operations → Day plan** wyświetla zadania zaplanowane na dany dzień, jeden wiersz na produkt w zadaniu.

| Kolumna | Znaczenie |
| --- | --- |
| Order (Kolejność) | miejsce zadania w dniu - wpisz numer, żeby je przesunąć |
| Job (Zadanie) | zlecenie produkcyjne i jego stan |
| Product (Produkt) | co jest wytwarzane |
| Qty (Ilość) | ile |
| Steps and operators (Kroki i pracownicy) | każdy krok receptury: zrobiono dotąd / zamówiono, oraz komu jest przydzielony |
| Planned (Zaplanowano) | dzień, na który zaplanowano |

Każdy krok ma własną listę nazwisk. Wybierz jedno, a krok pojawi się na ekranie hali tej osoby i niczyim innym. Krok z bursztynową ramką nie ma jeszcze nikogo; pasek u góry je zlicza, *3 kroki bez pracownika*, więc nic nie zostanie pominięte przez pomyłkę.

Aby zmienić, kto wykonuje krok w ciągu dnia, wybierz inne nazwisko. Aby odebrać krok komuś, naciśnij mały krzyżyk na liście. Jeśli ta osoba już zaczęła, jej praca zostaje zapisana na jej nazwisko: lista decyduje tylko o tym, kto widzi krok od teraz.

Data u góry przenosi na inny dzień. Plan na jutro można przydzielić już dzisiaj.

## Śledzenie dnia

Ta sama strona pokazuje, jak idzie dzień, i odświeża się sama:

- pasek u góry: ile zadań, *12 z 18 kroków zrobionych*, kroki bez pracownika, zadania niezakończone w swoim dniu;
- obok każdego kroku: szare kółko (niezaczęty), bursztynowy znacznik ładowania (zaczęty) lub zielony ptaszek (zrobiony), z dotychczasową liczbą wykonanych sztuk;
- *working now* (teraz pracuje) i nazwisko, gdy ktoś ma ten krok otwarty na hali.

Osoby, które widzą stronę, ale nie prowadzą hali, widzą nazwiska, nie listy.

## Gdy kierownika hali nie ma

Kierownik produkcji otwiera tę samą stronę i sam przydziela kroki. Planowanie i przydzielanie kroków wymagają tego samego stanowiska.

## Co nie zostało skończone

Zaplanowane zadanie z otwartymi krokami po upływie swojego dnia nie przepada. Od następnego dnia pokazuje się w Day plan ze starą datą na bursztynowo, a pasek liczy je jako *niezakończone w swoim dniu*. Dla każdego z nich wybierz:

- **Confirm for** (Potwierdź na) dzień, który właśnie oglądasz - zadanie przechodzi do planu tego dnia, na koniec kolejności;
- **Take off the plan** (Zdejmij z planu) - zadanie zostaje jako zlecenie produkcyjne, ale nie ma go w planie żadnego dnia.

Dopóki nie zostanie naciśnięte jedno z dwóch, zadanie zostaje przed Tobą każdego dnia. Jego kroki zostają też na ekranach hali pracowników, którym je przydzielono, więc ktoś, kto wczoraj zatrzymał mieszankę, może ją dokończyć dziś rano.

Gdy pracownik zamyka zadanie z mniejszą ilością i przenosi resztę do nowego zadania (zobacz [Praca z ekranem hali](/docs/working-the-floor-screen-pl)), nowe zadanie zachowuje zaplanowany dzień, miejsce w kolejności i pracownika każdego kroku.

## Gdy produkt powstaje z innego produktu

Niektóre produkty powstają z innego produktu fabryki: jumbo kula do kąpieli Puckator (PuckJBB-04) to kula JBB-04 ze stanu magazynowego, zafoliowana i oznaczona etykietą. Gdy JBB-04 nie ma dość na stanie, karta na tablicy **To produce** i jej zadanie w **Day plan** mówią o tym na bursztynowo, na przykład *Needs 3 SKO JBB-04, not in stock: make JBB-04 first* (Potrzeba 3 SKO JBB-04, brak na stanie: najpierw wykonaj JBB-04). Zadania już w planie biorą stan jako pierwsze, potem karty z zaległości, od najstarszej.

Naciśnij **Create JBB-04 job** (Utwórz zadanie JBB-04) obok komunikatu: otworzy się okno **Create job order** (Utwórz zlecenie produkcyjne) z już wpisanym JBB-04 i brakującymi SKO oraz z notatką, dla jakiego produktu jest to zadanie. Sprawdź je, wybierz pracownika i zapisz. Gdy zadanie JBB-04 jest wykonywane na stan, komunikat zmienia się na niebieski, *Waits for 3 SKO JBB-04 from* (Czeka na 3 SKO JBB-04 z) nowego zadania, aż kule do kąpieli powstaną. Zadanie JBB-04 wykonywane na zamówienie klienta lub partnera się nie liczy, bo te kule są już obiecane.

Komunikat znika, gdy krok używający JBB-04 jest zrobiony.

## Prośba do magazynu o materiały

Kiedy fabryka czegoś potrzebuje z magazynu, każdy pracujący w produkcji prosi o to u góry **Factory → Operations → Materials check** (Fabryka → Operacje → Kontrola materiałów), pod **Ask the warehouse** (Poproś magazyn). Każdy wiersz to:

| Pole | Co wpisać |
| --- | --- |
| SKO | wpisz kod lub nazwę; lista pokazuje, ile SKO ma magazyn i fabryka |
| Quantity and unit (Ilość i jednostka) | tak, jak powiedziałbyś to magazynowi: *3 Boxes*, *1 Pallet*, *80 Bags*, *2 SKOs* albo *All there is* |
| Drop point (Miejsce rozładunku) | gdzie w fabryce to zostawić (*Storage room*, *Clean room*, *Bath bomb area*...) |
| Urgent (Pilne) | magazyn widzi pilne wiersze jako pierwsze |
| Note (Notatka) | wszystko, co magazyn powinien wiedzieć (*250ml*, *proszę na zewnątrz na paletach*) |

**Add line** (Dodaj wiersz) prosi o kilka rzeczy naraz, a **Send to warehouse** (Wyślij do magazynu) je wysyła. **Asked this week** (Zamówione w tym tygodniu) poniżej pokazuje każdą prośbę z tym, kto ją zgłosił i kiedy, a dla każdego wiersza, czy jest *To bring* (Do przywiezienia), *Delivered* (Dostarczono; ile SKO, kto, kiedy) czy *Can't bring* (Nie można przywieźć) z powodem podanym przez magazyn.

Kierownik produkcji i kierownik hali prowadzą listę miejsc rozładunku przyciskiem **Drop points** (Miejsca rozładunku): dodają, zmieniają nazwy, przesuwają w górę i w dół albo usuwają. Usunięte miejsce rozładunku zachowuje swoją nazwę przy prośbach, które już tam wysłano.

## Poranna kontrola materiałów

Magazyn dowozi materiały do fabryki w całych kartonach, a nie w dokładnych ilościach na dany dzień. **Factory → Operations → Materials check** (Fabryka → Operacje → Kontrola materiałów) pokazuje, czego plan dnia jeszcze potrzebuje, żeby fabryka sprawdziła, co ma, i poprosiła magazyn o resztę jedną czytelną listą.

Każdy wiersz to jeden materiał, zsumowany po wszystkich zadaniach dnia, razem z zadaniami, które go potrzebują (*JOaroma-0141, JOaroma-0145*). Liczone są tylko kroki jeszcze niezrobione: gdy wytwarzanie jest skończone i zostało samo pakowanie, wiersz pokazuje kartony i etykiety, a nie już butelki. Mieszanki powstają w fabryce i nie ma ich na liście. Ilości pochodzą z receptur, więc zadanie, którego receptura nie wymienia materiałów, niczego nie potrzebuje: bursztynowy pasek nad listą wymienia takie zadania, żeby luka w recepturze była widoczna.

| Kolumna | Znaczenie |
| --- | --- |
| Material (Materiał) | materiał i kod towaru w magazynie |
| For jobs (Dla zadań) | zadania dnia, które go potrzebują |
| Needed (Potrzeba) | ile zużyją kroki, które zostały do zrobienia |
| At the factory (W fabryce) | ile jest teraz w lokalizacji fabryki i ile już zamówiono dzisiaj |
| Check (Kontrola) | zaznacz **Have enough** (Wystarczy) albo wpisz, mniej więcej ile brakuje |

Gdy wpiszesz brakującą ilość, wiersz pokazuje, ile przywiezie magazyn, zaokrąglone w górę do jego kartonów: brakuje 1 200 przy 500 w kartonie to *bring 3 boxes (1 500)* (przywieź 3 kartony). Czerwone *Warehouse short* (Brak w magazynie) oznacza, że magazyn nie ma tyle na stanie, więc wiadomo to od razu.

Naciśnij **Send to warehouse** (Wyślij do magazynu). Brakujące wiersze trafiają do magazynu jako jedno zamówienie, a ono pojawia się pod **Asked this week**, z każdym materiałem oznaczonym jako *To bring* (Do przywiezienia), *Delivered* (Dostarczono) lub *Can't bring*. Zaznaczenie wszystkiego jako wystarczające i wysłanie zapisuje, że kontrola została wykonana. Kolejna kontrola później w ciągu dnia dodaje tylko to, czego nadal brakuje.

Kontrolę wykonuje kierownik produkcji albo kierownik hali, albo przekazuje ją pracownikowi przez **Checked by** (Sprawdza) u góry. Ten pracownik widzi wtedy na ekranie hali przypomnienie, które otwiera kontrolę.

## Magazyn dowozi materiały

W magazynie karta **For production** (Dla produkcji) pod **Action needed** (Wymaga działania) w **Inventory** (Zapasy) pokazuje, na ile wierszy czeka fabryka, a pilne na małej plakietce. Otwiera **Inventory → For production**: każdy wiersz, który jeszcze trzeba przywieźć, najpierw pilne, potem najstarsze, z tym, o co poproszono, miejscem rozładunku, kto prosił i kiedy, notatką i regałem, z którego trzeba wziąć. Kompletujący wybiera regał, wpisuje przywiezione SKO i naciska **Delivered**. Gdy magazyn nie może dowieźć, **Can't bring** pyta o powód (*out of stock*, *ordered from supplier*) i informuje fabrykę. **Done** (Zrobione) pokazuje to, co przywieziono lub odmówiono, wraz z czasem realizacji.

Jeśli Aiku pokazuje na regale mniej SKO, niż przywieziono, dostawa i tak przechodzi: to, co Aiku miało, trafia do fabryki, a reszta jest oznaczona jako *not in Aiku, count it* (nie ma w Aiku, policz), żeby SKO zostało przeliczone.

Dostarczony towar przechodzi z regału do lokalizacji fabryki. Nadal jest zapasem: jest liczony i widoczny jak dotąd, a zamówienie klienta może go tam jeszcze skompletować, gdy regały w magazynie są puste, przy czym regały zawsze mają pierwszeństwo. Niewykorzystane materiały można przenieść z powrotem na regał ze strony lokalizacji fabryki, jak każde inne przeniesienie między lokalizacjami.

Gdy gotowe zadanie jest przyjmowane na stan, jego materiały są zużywane najpierw z lokalizacji fabryki, według ilości z receptury, i dopiero potem z innych lokalizacji. Od czasu do czasu przelicz lokalizację fabryki, żeby skorygować stłuczki i straty.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Zaplanuj zadanie na dzień:</b> <b>Factory</b> → <b>To produce</b> → upuść kartę na <b>Assigned</b> → wybierz dzień → <b>Plan for this day</b>.</li>
<li><b>Przydziel krok pracownikowi:</b> <b>Factory</b> → <b>Operations</b> → <b>Day plan</b> → lista obok kroku.</li>
<li><b>Zmień kolejność dnia:</b> wpisz numer w <b>Order</b>.</li>
<li><b>Wczorajsze niezakończone zadanie:</b> <b>Confirm for</b> dzień, albo <b>Take off the plan</b>.</li>
<li><b>Z tablicy do planu:</b> link <b>Day plan</b> nad tablicą.</li>
<li><b>Poproś magazyn o coś:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Ask the warehouse</b> → <b>Send to warehouse</b>.</li>
<li><b>Zmień miejsca rozładunku:</b> <b>Materials check</b> → <b>Drop points</b>.</li>
<li><b>Sprawdź materiały na dzień:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Have enough</b> albo brakująca ilość → <b>Send to warehouse</b>.</li>
<li><b>Przekaż kontrolę pracownikowi:</b> <b>Materials check</b> → <b>Checked by</b>.</li>
<li><b>Dowiezienie materiałów (magazyn):</b> <b>Inventory</b> → <b>For production</b> → <b>Delivered</b> albo <b>Can't bring</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Stanowiska są ustawiane na karcie pracownika w module Human Resources i niosą ze sobą uprawnienia.</li>
<li>Planowanie dnia, przydzielanie kroków, zmiana kolejności: <b>Floor supervisor</b> dla fabryki.</li>
<li>Podgląd Day plan bez zmian: <b>Foreman</b>.</li>
<li>Praca nad przydzielonym krokiem: <b>Operative</b>, na ekranie hali.</li>
<li>Prośba do magazynu o materiały: każdy, kto widzi Operations fabryki.</li>
<li>Kontrola materiałów i miejsca rozładunku: <b>Floor supervisor</b>; kontrolę także pracownik, któremu ją przekazano.</li>
<li>Dowożenie materiałów do fabryki: stanowisko w magazynie, które pracuje w magazynie.</li>
</ul>
</aside>
