---
title: Praca z listą To produce
summary: Przewodnik dla fabryki - jedna kolejka wszystkiego, co fabryka jest winna, zarówno organizacjom partnerskim, jak i własnym klientom, pogrupowana tak, jak myśli planista produkcji.
date: 2026-09-02
source_date: 2026-09-02
tags: production, procurement, intercompany, dispatch
category: production
series: Ordering from partners
order: 4
---

<aside class="tldr">
Dla osób, które <em>wytwarzają rzeczy</em>, i dla osoby planującej dzień fabryki. <b>To produce</b> (Do produkcji) to kolejka fabryki: każda pozycja, o którą poprosiła organizacja partnerska, plus każda pozycja zamówiona przez własnego klienta, której fabryka nie ma w magazynie. Grupujesz ją wg rzemieślnika, kategorii albo kupującego, zaznaczasz to, co możesz wysłać do partnerów, a reszta papierkowej roboty toczy się sama. Nowy w procesie partnerskim? Zacznij od <a href="/docs/ordering-from-a-partner-organisation-pl">przeglądu</a>. Chcesz, żeby lista wiedziała, kto co wykonuje? Przeczytaj najpierw <a href="/docs/who-makes-what-pl">Kto co wykonuje</a>.
</aside>

## Skąd biorą się pozycje

**Factory → To produce** (Fabryka → Do produkcji) jest zasilana z dwóch miejsc. Nigdy nie wpisujesz tu pozycji samodzielnie.

- **Partner requests** (Zapytania partnerów). Organizacje siostrzane umieszczają to, czego potrzebują, na swojej [liście zakupowej](/docs/buying-from-a-partner-pl). Każda otwarta pozycja adresowana do Twojej fabryki pojawia się tutaj wraz z kupującym, ilością i priorytetem, jaki ustawili.
- **Own customers** (Własni klienci). Gdy zamówienie zostaje złożone w Twoim własnym sklepie, aiku sprawdza każdy produkt. Jeśli stanu za nim brakuje, a ten stan jest wytwarzany przez fabrykę, brak trafia tutaj jako pozycja, oznaczona klientem i numerem zamówienia. Gdy to zamówienie zostanie wysłane, pozycja zamyka się sama.

Zamówienia, które przychodzą przez stary system, nie zasilają listy. Robią to tylko zamówienia złożone w aiku.

Filtr **Source** (Źródło) u góry zakładki *All* (Wszystkie) pozwala zobaczyć tylko pozycje partnerskie albo tylko pozycje własnych klientów.

## Cztery widoki

Pasek zakładek nad tytułem to sedno tej strony. Te same pozycje, cztery sposoby patrzenia na nie.

- **All** (Wszystkie). Płaska tabela, sortowalna i przeszukiwalna, z licznikiem otwartych pozycji. Używaj jej, gdy szukasz jednej konkretnej rzeczy.
- **By artisan** (Wg rzemieślnika). Jeden blok na osobę, wg rzemieślnika przypisanego do wyrobu albo, w braku takiego, do jego kategorii. Pozycje bez nikogo przypisanego siedzą pod *Unassigned* (Nieprzypisane). To widok do rozdzielania dziennej pracy.
- **By category** (Wg kategorii). Jeden blok na kategorię wyrobów, więc osoba robiąca kule do kąpieli widzi kule do kąpieli, a osoba robiąca mydło widzi mydło.
- **By buyer** (Wg kupującego). Jeden blok na organizację partnerską albo własnego klienta, na potrzeby budowania przesyłki.

W widokach grupowanych każdy blok ma kapsułę nad listą pokazującą jego nazwę i liczbę pozycji. Kliknij kapsułę, aby ukryć ten blok, kliknij ponownie, aby go przywrócić. aiku zapamiętuje Twój wybór w tej przeglądarce, więc planista, którego interesują tylko dwie kategorie, zawsze widzi tylko dwie.

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
<li><b>Zobacz kolejkę:</b> Twoja organizacja → <b>Factory</b> (Fabryka) → <b>To produce</b> (Do produkcji). Przełączaj widoki zakładkami <b>All · By artisan · By category · By buyer</b> (Wszystkie · Wg rzemieślnika · Wg kategorii · Wg kupującego).</li>
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
<li>Kompletacja, wysyłka i tworzenie zleceń produkcyjnych: <b>Production floor supervisor</b> (kierownik hali) dla fabryki albo przełożony organizacji.</li>
</ul>
</aside>
