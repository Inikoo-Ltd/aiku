---
title: Odczytywanie pulpitu zakupów partnerskich
summary: Ekran, który pokazuje, co kupić od partnera i ile masz na to miejsca - trzy karty pojemności, pączek pokrycia zapasu z ośmioma kubełkami i potok zamówień.
date: 2026-09-02
source_date: 2026-09-02
tags: procurement, intercompany, shopping-list, stock
category: procurement
series: Ordering from partners
order: 2
---

<aside class="tldr">
Ten pulpit to miejsce, od którego zaczyna się każda sesja zakupowa. Górny rząd pokazuje, ile masz miejsca - pieniędzy i przestrzeni magazynowej. Środek pokazuje, które produkty partnera trzeba zamówić, od najgorszych. Dół pokazuje, co już jest w drodze. Nie musisz niczego pamiętać - ekran mówi, co wymaga uwagi. Samo składanie zamówienia jest opisane w <a href="/docs/buying-from-a-partner-pl">Kupowaniu od partnera</a>.
</aside>

Otwórz go w **Procurement → Partners → {partner} → Shopping** (Zakupy → Partnerzy → {partner} → Zakupy). Używaj go zamiast otwierać listę zakupową i próbować przypomnieć sobie, czego brakowało.

## Trzy karty u góry: ile masz miejsca

Te karty to ograniczenia, nie ozdoba. Istnieją, ponieważ lista zakupowa, do której każdy może wrzucić cokolwiek, przestaje cokolwiek znaczyć - partner, który dostaje tysiąc pozycji, nie jest w stanie rozpoznać, które dwie są pilne.

- **Order budget used** (Wykorzystany budżet zamówień). Wartość Twojej otwartej listy w porównaniu z tym, co ten partner faktycznie dostarcza Ci w jednym cyklu zamówień, pokazana w walucie Twojej organizacji - każda kwota na tych ekranach jest dla Ciebie przeliczona, więc nigdy nie musisz myśleć w walucie partnera. Jeśli jest wystarczająco dużo historii dostaw, budżet jest wyliczany z rzeczywistych dostaw; jeśli nie, to jeden cykl zamówień tego, co faktycznie sprzedajesz z jego produktów. Nikt nie wpisuje tej liczby ręcznie - ani Ty, ani Twój przełożony. Gdy pasek jest pełny, karta pokazuje **at capacity** (na granicy limitu).
- **Warehouse space** (Miejsce w magazynie). Ile lokalizacji jest wolnych z ogólnej liczby, z paskiem podzielonym na to, co *in use* (w użyciu), co *inbound* (przychodzące) na otwartych zamówieniach zakupu i dostawach, oraz co zajęłaby *ta lista zakupowa*. Poniżej sprawiedliwy udział partnera: ile z wolnych slotów mogą zająć jego zupełnie nowe produkty. Lokalizacje liczone są jako sloty - nie mamy danych o objętości, więc nie udajemy, że mierzymy metry sześcienne.
- **Lead time** (Czas realizacji). Karta zatytułowana nazwą partnera pokazuje jego zmierzony czas realizacji **order → booked in** (od zamówienia do zaksięgowania), z ilu dostaw został wyliczony (albo informację, że to nadal szacunek), ile zamówień zakupu jest opóźnionych i o ile, oraz jak duży jest jego katalog.

## Pokrycie zapasu: pączek i osiem kubełków

Ta sekcja obejmuje cały katalog partnera, podzielony na osiem kubełków wg tego, jak długo wystarczy Twój własny stan. Ryzykowne kubełki są wymierzone zmierzonym czasem realizacji, nie tygodniami kalendarzowymi - o to właśnie chodzi.

Zaczyna się od **wykresu pączkowego (donut chart)**: każdy produkt w katalogu, jedna wycinka na kubełek, z sumą pośrodku. Najedź na wycinkę, aby zobaczyć liczbę i procent; kliknij wycinkę - albo wiersz w legendzie obok niej - aby przejrzeć ten kubełek w katalogu partnera. Jedno spojrzenie mówi, czy dziś jest spokojne uzupełnienie, czy alarm: dużo czerwieni oznacza kłopoty, głównie zieleń oznacza, że wszystko gra.

Poniżej wykresu kubełki są ułożone w dwóch grupach. **Needs ordering** (Wymaga zamówienia) zawiera pięć, które potrzebują Twojej uwagi:

- **Out of stock** (Brak w magazynie) - nic nie zostało na półce.
- **Doomed** (Skazane) - masz jeszcze stan, ale skończy się, zanim mogłaby dotrzeć dostawa, nawet gdybyś zamówił właśnie teraz.
- **Critical / Danger / Watch** (Krytyczne / Niebezpieczne / Do obserwacji) - skończy się w ciągu dwóch, trzech albo czterech czasów realizacji.

**Not for ordering** (Nie do zamawiania) zawiera pozostałe trzy:

- **Covered** (Pokryte) - na razie w porządku.
- **Dead stock** (Martwy zapas) - nic się nie sprzedaje, pieniądze leżą na półce; wiersz pokazuje, ile są warte.
- **We never stocked** (Nigdy nie mieliśmy) - partner to sprzedaje, ale Ty nigdy tego nie prowadziłeś.

Jeden rodzaj pozycji nigdy się tu nie pojawia: SKO oznaczone przez Ciebie jako **On Demand** (Na zamówienie) we własnym magazynie. Ich stan nie jest śledzony, więc "brak w magazynie" nic by nie znaczyło - pulpit, tabele kubełków i Auto-fill wszystkie je pomijają.

Każdy kafelek odpowiada na jedno pytanie: **ile jeszcze mnie potrzebuje?** Licznik "*N* need action" (*N* wymaga działania) pomija wszystko, co jest już na liście albo już w drodze, więc maleje w miarę pracy nad nim. Pod nim ten sam licznik rozbity wg **rank** (rangi) - najpierw produkty A, D i Z wyblakłe na końcu. Dwa produkty A bez stanu magazynowego liczą się bardziej niż pięćset produktów D, więc w takiej kolejności warto pracować.

Trzy rzeczy, które można zrobić z poziomu kafelka:

- **Kliknij liczbę**, aby otworzyć kubełek jako tabelę: każda pozycja, z rangą, ich stanem, Twoim stanem, kiedy Ci się skończy, i polem ilości, które zapisuje się prosto do listy zakupowej.
- **Kliknij nazwę kubełka albo literę rangi**, aby przejrzeć te produkty w katalogu partnera.
- **Fill** (Uzupełnij), aby otworzyć Auto-fill już zawężone do tego kubełka i z gotową propozycją - wystarczy dostosować i zatwierdzić. Trochę więcej pracy niż magiczny przycisk, ale dużo więcej kontroli. Liczniki na kafelku - *N on the way · N on list* (N w drodze · N na liście) - pokazują, ile z kubełka już obsłużyłeś.

Na **Covered** i **Dead stock** zamiast tego pojawia się czerwone ostrzeżenie, gdy coś z tego kubełka leży na Twojej liście zakupowej: to zapas, którego nie potrzebujesz. **remove** (usuń) czyści te pozycje jednym kliknięciem.

## Potok zamówień

Dolny pasek śledzi wszystko od potrzeby aż po półkę: **on shopping list → being prepared → ready to ship → in transit → arrived, booking in** (na liście zakupowej → w przygotowaniu → gotowe do wysyłki → w transporcie → dotarło, księgowanie). Każda kolumna pokazuje swoje dostawy i ile jest w nich pozycji; każda karta otwiera dostawę tylko do odczytu - to magazyn sprzedającego nią zarządza, dopóki towar do Ciebie nie dotrze.

Karty widocznie się starzeją. Po przekroczeniu trzykrotności czasu realizacji stają się bursztynowe; po dziesięciokrotności - czerwone. Stara karta to pytanie do zadania partnerowi, nie liczba do wpatrywania się w nią. Wszystko, co naprawdę jest opóźnione, pojawia się też na liście **Late from this partner** (Opóźnione od tego partnera) poniżej, od największego opóźnienia, z oznaczeniem "no delivery date given" (brak podanej daty dostawy).

## Dlaczego ekran czasem mówi nie

Dodanie do listy może zostać odrzucone. To celowe i są tylko trzy powody:

- **At the budget cap** (Na granicy budżetu) - najpierw usuń coś albo obniż priorytet. Prawdziwa awaria zawsze się zmieści: **pozycje rangi A i bez stanu magazynowego są zwolnione**, więc awaria nigdy nie czeka za limitem.
- **The warehouse floor** (Próg magazynowy) - przy mniej niż 5% wolnych lokalizacji żaden *nowy* produkt od nikogo nie zostaje dodany. Pozycje, które już magazynujesz, uzupełniają własne sloty i przechodzą swobodnie.
- **This partner's fair share** (Sprawiedliwy udział tego partnera) - jeden partner może zająć około jednej piątej wolnych slotów produktami, których nigdy nie magazynowałeś. Inni dostawcy też potrzebują miejsca.

Ta sama zasada obowiązuje wszędzie, gdzie dodajesz - ręcznie, hurtowo albo z Auto-fill - więc propozycja nigdy nie zawiera pozycji, których nie możesz zatwierdzić.

## Zmierzone, albo uczciwie oznaczone jako szacunek

Dwie liczby napędzają większość tego ekranu: czas realizacji i budżet. Zasada jest taka sama dla obu. **Jeśli mamy historię, liczba jest zmierzona i nie da się jej edytować.** Jeśli nie mamy, karta o tym informuje, a szacunek można edytować - ale w ustawieniach, nigdy bezpośrednio na pulpicie: **estimated delivery time** (szacowany czas dostawy) dla danego produktu, na produkcie dostawcy albo we własnych ustawieniach SKO. Gdy pojawi się wystarczająco dużo rzeczywistych dostaw, pomiar przejmuje kontrolę, a pole szacunku znika. Nikt nie może nadpisać tego, co faktycznie się wydarzyło.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Pulpit:</b> Twoja organizacja → <b>Procurement → Partners</b> (Zakupy → Partnerzy) → otwórz partnera → <b>Shopping</b> (Zakupy).</li>
<li><b>Przejdź z pączka:</b> kliknij wycinkę albo wiersz w legendzie, aby przejrzeć ten kubełek w katalogu.</li>
<li><b>Pracuj nad kubełkiem:</b> kliknij liczbę na kafelku, aby zobaczyć tabelę pozycji, literę rangi, aby przejrzeć te produkty, albo <b>Fill</b> (Uzupełnij) po zawężoną propozycję Auto-fill.</li>
<li><b>Wyczyść listę:</b> <b>remove</b> (usuń) na kafelku Covered albo Dead stock.</li>
<li><b>Popraw szacunek czasu realizacji:</b> ustawienia SKO albo ustawienia produktu dostawcy - tylko dopóki nadal widnieje <i>estimate</i> (szacunek).</li>
</ul>
</aside>
