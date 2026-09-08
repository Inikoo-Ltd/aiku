---
title: Praca z listą To produce
summary: Przewodnik dla fabryki - jedna kolejka wszystkiego, co fabryka jest winna, zarówno organizacjom partnerskim, jak i własnym klientom, pogrupowana tak, jak myśli planista produkcji.
date: 2026-09-08
source_date: 2026-09-08
tags: production, procurement, intercompany, dispatch
category: production
series: Ordering from partners
order: 4
---

<aside class="tldr">
Dla osób, które <em>wytwarzają rzeczy</em>, i dla osoby planującej dzień fabryki. <b>To produce</b> (Do produkcji) to kolejka fabryki: każda pozycja, o którą poprosiła organizacja partnerska, plus każda pozycja zamówiona przez własnego klienta, której fabryka nie ma w magazynie. <b>Board</b> (Tablica) to miejsce planowania: przeciągasz pozycję między pasami, żeby zdecydować, ile wykonać i kto to zrobi, a dla rzemieślnika powstaje zlecenie produkcyjne. Widoki listy grupują te same pozycje wg rzemieślnika, kategorii albo kupującego, i stamtąd zaznaczasz to, co możesz wysłać do partnerów; reszta papierkowej roboty toczy się sama. Nowy w procesie partnerskim? Zacznij od <a href="/docs/ordering-from-a-partner-organisation-pl">przeglądu</a>. Chcesz, żeby lista wiedziała, kto co wykonuje? Przeczytaj najpierw <a href="/docs/who-makes-what-pl">Kto co wykonuje</a>.
</aside>

## Skąd biorą się pozycje

**Factory → To produce** (Fabryka → Do produkcji) jest zasilana z dwóch miejsc. Nigdy nie wpisujesz tu pozycji samodzielnie.

- **Partner requests** (Zapytania partnerów). Organizacje siostrzane umieszczają to, czego potrzebują, na swojej [liście zakupowej](/docs/buying-from-a-partner-pl). Każda otwarta pozycja adresowana do Twojej fabryki pojawia się tutaj wraz z kupującym, ilością i priorytetem, jaki ustawili.
- **Own customers** (Własni klienci). Gdy zamówienie zostaje złożone w Twoim własnym sklepie, aiku sprawdza każdy produkt. Jeśli stanu za nim brakuje, a ten stan jest wytwarzany przez fabrykę, brak trafia tutaj jako pozycja, oznaczona klientem i numerem zamówienia. Gdy to zamówienie zostanie wysłane, pozycja zamyka się sama.

Zamówienia, które przychodzą przez stary system, nie zasilają listy. Robią to tylko zamówienia złożone w aiku.

Filtr **Source** (Źródło) u góry zakładki *All* (Wszystkie) pozwala zobaczyć tylko pozycje partnerskie albo tylko pozycje własnych klientów.

## Widoki

Pasek zakładek nad tytułem to sedno tej strony. Te same pozycje, sześć sposobów patrzenia na nie.

- **Board** (Tablica). Widok planowania i ten, na którym strona się otwiera. Każda pozycja to karta, która przesuwa się przez pasy od *Backlog* do *Done*. Wyjaśnione w kolejnej sekcji.
- **All** (Wszystkie). Płaska tabela, sortowalna i przeszukiwalna, z licznikiem otwartych pozycji. Używaj jej, gdy szukasz jednej konkretnej rzeczy.
- **By artisan** (Wg rzemieślnika). Jeden blok na osobę, wg rzemieślnika przypisanego do wyrobu albo, w braku takiego, do jego kategorii. Pozycje bez nikogo przypisanego siedzą pod *Unassigned* (Nieprzypisane). To widok do rozdzielania dziennej pracy.
- **By category** (Wg kategorii). Jeden blok na kategorię wyrobów, więc osoba robiąca kule do kąpieli widzi kule do kąpieli, a osoba robiąca mydło widzi mydło.
- **By buyer** (Wg kupującego). Jeden blok na organizację partnerską albo własnego klienta, na potrzeby budowania przesyłki.
- **Mixes** (Mieszanki). Bazy i mieszanki, których potrzebują otwarte zlecenia produkcyjne, dla przygotowującego. Wyjaśnione w [Przygotowywanie mieszanek](/docs/preparing-mixes-pl).

W widokach grupowanych każdy blok ma kapsułę nad listą pokazującą jego nazwę i liczbę pozycji. Kliknij kapsułę, aby ukryć ten blok, kliknij ponownie, aby go przywrócić. aiku zapamiętuje Twój wybór w tej przeglądarce, więc planista, którego interesują tylko dwie kategorie, zawsze widzi tylko dwie.

## Tablica (Board)

Sześć pasów, od lewej do prawej. Karta przesuwa się w prawo w miarę postępu pracy, a większość przesunięć to przeciągnięcie.

| Pas | Co tam się znajduje |
| --- | --- |
| Pre-pick (Wstępna kompletacja) | pozycje, dla których nic nie trzeba wytwarzać, bo towar jest na półce. Ukryty, dopóki nie naciśniesz **Pre-pick** nad tablicą. Magazyn zbiera te pozycje, patrz [Kompletowanie towaru dla partnera](/docs/gathering-a-partners-goods-pl). |
| Backlog (Zaległości) | pozycje z wyrobem, na który jeszcze nikt nie spojrzał |
| Preparing (Przygotowywanie) | pozycje, które postanowiłeś wykonać, z ustaloną ilością |
| Assigned (Przypisane) | istnieje zlecenie produkcyjne skierowane do rzemieślnika, ale nikt jeszcze nie zaczął |
| Producing (W produkcji) | rzemieślnik nacisnął START na jednym z jego zadań |
| Done (Gotowe) | wszystkie zadania na zleceniu produkcyjnym są zrobione; czeka na odłożenie przez magazyn |

Każda karta pokazuje produkt, zamówioną ilość, kto zamówił oraz **In stock** (W magazynie), więc widzisz, czy w ogóle warto to wytwarzać.

**Backlog → Preparing.** Upuść kartę, a aiku zapyta *Ile wykonać?*. Proponuje zamówioną ilość; wpisz więcej, a nadwyżka zostanie oznaczona jako *na zapas*. Jeśli wyrób ma zalecaną wielkość partii, mały przycisk **↑** zaokrągla ilość do pełnych partii. Liczba pozostaje edytowalna na karcie, gdy jest w Preparing.

**Preparing → Assigned.** Upuść kartę, a aiku zapyta *Kto to wykona?*. Proponuje rzemieślnika przypisanego do wyrobu albo jego kategorii, patrz [Kto co wykonuje](/docs/who-makes-what-pl). Wybierz imię, a zlecenie produkcyjne powstaje w wersji roboczej, skierowane do tej osoby. Otwórz zlecenie i naciśnij **Release to floor** (Skieruj na halę), gdy ma się zacząć; do tego momentu rzemieślnik go nie widzi. Aby zmienić rzemieślnika później, kliknij imię na karcie.

**Producing** i **Done** przesuwają się same, w zależności od tego, co dzieje się na ekranie hali. Karta opuszcza tablicę, gdy magazyn odkłada gotowy towar, patrz [Odkładanie gotowej produkcji](/docs/putting-away-finished-production-pl), albo gdy zlecenie produkcyjne zostaje przyjęte na stan bezpośrednio ze swojej strony.

Kilka kart naraz: kliknij karty, żeby je zaznaczyć, a następnie przeciągnij dowolną z nich, a całe zaznaczenie się przesunie. Menu **Everybody** (Wszyscy) nad tablicą zawęża ją do jednego lub dwóch rzemieślników, a filtry rodziny, kupującego i priorytetu robią to samo dla kart.

Pod tablicą Board i widokiem By artisan znajduje się **Open job orders per artisan** (Otwarte zlecenia na rzemieślnika): jeden znacznik na osobę z liczbą otwartych zleceń. Czerwony oznacza brak, bursztynowy - jedno; każdy powinien mieć co najmniej dwa, żeby nikomu nie zabrakło pracy. Krzyżyk na znaczniku oznacza osobę jako nie-rzemieślnika i usuwa ją z licznika.

## Wysyłanie pozycji partnerskich

Pozycje partnerskie są wysyłane stąd; pozycje własnych klientów - nie, one podróżują ze swoim własnym zamówieniem.

- Zaznacz pozycje partnerskie, które możesz wysłać. Dostosuj ilość dla **partial pick** (częściowej kompletacji), reszta zostaje otwarta na kolejną przesyłkę.
- **Pick into order** (Skompletuj do zamówienia) zbiera Twoje zaznaczenia w oczekującą przesyłkę dla każdej organizacji kupującej osobno. Pozostaje otwarta w polu *Picked orders* (Skompletowane zamówienia), dopóki jej nie wyślesz.
- **Send to warehouse** (Wyślij do magazynu) przekazuje przesyłkę do Twojego magazynu jako zwykłe zamówienie: kompletowane, pakowane, wysyłane i fakturowane jak wszystko inne. Dla organizacji kupującej tworzona jest jej przychodząca dostawa towaru, która podąża za postępem Twojego magazynu. Nikt nie aktualizuje strony kupującego ręcznie.

Zaznaczenie pozycji własnego klienta nic nie daje. Jest pomijana, gdy naciśniesz Pick into order, ponieważ ten produkt już należy do zamówienia klienta.

## Warto wiedzieć

- Otwarta lista kupującego jest ograniczona z grubsza do jednego cyklu zamówień tego, co historycznie mu dostarczasz, więc to, co do Ciebie dociera, to przefiltrowane zapotrzebowanie, a nie zrzut całego katalogu. Jeśli pozycja wygląda dziwnie, zapytaj; kupujący z czegoś zrezygnował, żeby ją tam umieścić.
- Pierwsza kompletacja dla nowego partnera tworzy w Twoim sklepie konto klienta nazwane od organizacji kupującej. To zamierzone. Uprzedź obsługę klienta, żeby nikt tego "nie posprzątał".
- Dopóki nie naciśniesz Send to warehouse, skompletowane zamówienie jest niewidoczne na zwykłych ekranach zamówień; jego domem jest strona To produce.
- To, co wysyłasz, jest tym, co pokazuje dostawa towaru u kupującego. Nigdy nie zawyżaj ilości, żeby "pasowały do listy".

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Zobacz kolejkę:</b> Twoja organizacja → <b>Factory</b> (Fabryka) → <b>To produce</b> (Do produkcji). Przełączaj widoki zakładkami <b>Board · All · By artisan · By category · By buyer · Mixes</b> (Tablica · Wszystkie · Wg rzemieślnika · Wg kategorii · Wg kupującego · Mieszanki).</li>
<li><b>Zdecyduj o ilości:</b> <i>Board</i> → przeciągnij kartę z <b>Backlog</b> do <b>Preparing</b> → wpisz liczbę albo naciśnij <b>↑</b>, aby zaokrąglić do pełnych partii.</li>
<li><b>Utwórz zlecenie produkcyjne:</b> przeciągnij kartę z <b>Preparing</b> do <b>Assigned</b> → wybierz rzemieślnika → otwórz zlecenie → <b>Release to floor</b>.</li>
<li><b>Pozycje wymagające tylko kompletacji:</b> przycisk <b>Pre-pick</b> nad tablicą.</li>
<li><b>Ukryj blok:</b> w widoku grupowanym kliknij jego kapsułę nad listą. Kliknij ponownie, aby go pokazać.</li>
<li><b>Tylko partnerzy albo tylko klienci:</b> zakładka <i>All</i> (Wszystkie) → filtr <b>Source</b> (Źródło).</li>
<li><b>Wyślij do partnera:</b> zaznacz pozycje → <b>Pick into order</b> (Skompletuj do zamówienia) → <b>Send to warehouse</b> (Wyślij do magazynu) w polu <i>Picked orders</i> (Skompletowane zamówienia).</li>
<li><b>Zdecyduj, kto co wykonuje:</b> zobacz <a href="/docs/who-makes-what-pl">Kto co wykonuje</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Stanowiska są ustawiane na karcie pracownika w module Human Resources (Kadry) i niosą ze sobą uprawnienia.</li>
<li>Podgląd listy: <b>Production operative</b> (pracownik produkcyjny) dla fabryki albo wyżej.</li>
<li>Przesuwanie kart na Board, tworzenie i kierowanie zleceń na halę, kompletacja i wysyłka: <b>Production floor supervisor</b> (kierownik hali) dla fabryki albo przełożony organizacji. <b>Mix preparer</b> (przygotowujący mieszanki) może to samo, ale tylko dla mieszanek.</li>
</ul>
</aside>
