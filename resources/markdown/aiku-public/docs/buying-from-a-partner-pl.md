---
title: Kupowanie od partnera
summary: Przewodnik dla kupującego - zacznij od pulpitu zakupów, uzupełnij listę ręcznie, z katalogu partnera albo za pomocą auto-fill, i odbierz towar, gdy dotrze.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, intercompany, shopping-list
category: procurement
series: Ordering from partners
order: 3
---

<aside class="tldr">
Dla osób, które <em>składają</em> zamówienia partnerskie. Prowadzisz jedną otwartą listę tego, czego potrzebuje Twoja organizacja; partner wysyła towar względem niej we własnym tempie. Zacznij od <a href="/docs/reading-the-partner-shopping-dashboard-pl">pulpitu zakupów</a>, aby zobaczyć, co jest zagrożone i ile masz miejsca, a następnie dodawaj pozycje ręcznie, z jego katalogu, albo pozwól, by auto-fill (automatyczne uzupełnienie) zaproponowało uzupełnienie w ramach budżetu. Nowy w tym procesie? Zacznij od <a href="/docs/ordering-from-a-partner-organisation-pl">przeglądu</a>.
</aside>

## Zacznij od pulpitu

**Procurement → Partners → {partner} → Shopping** (Zakupy → Partnerzy → {partner} → Zakupy) otwiera [pulpit zakupów](/docs/reading-the-partner-shopping-dashboard-pl): co zaraz się skończy, co jest już w drodze, i dwa ograniczenia, w których żyje Twoja lista - **order budget** (budżet zamówień) dla tego partnera i dostępne **warehouse space** (miejsce w magazynie). Popracuj stamtąd nad kafelkami ryzyka, a większość listy napisze się sama; wszystko poniżej opisuje, jak lista zachowuje się, gdy już w niej jesteś.

## Lista zakupowa

Obok pulpitu zakładka **Shopping list** (Lista zakupowa) przechowuje wszystkie otwarte pozycje.

- **Add stocks** (Dodaj towary) otwiera listę stanów magazynowych partnera z ich dostępnością, sposobem pakowania każdej pozycji, Twoim własnym bieżącym stanem i tym, ile zużyłeś w ostatnich czterech kwartałach. Ilości podane są w jednostkach wysyłkowych sprzedającego (SKO).
- Każda pozycja pokazuje historię stanu na pierwszy rzut oka - *their stock* (ich stan), *our stock* (nasz stan) i kiedy *we run out* (nam się skończy) - a do tego kwotę w Twojej cenie zakupu, z sumą otwartych pozycji u dołu tabeli.
- Otwarte pozycje są w pełni Twoje: wybierz **priority** (priorytet, low → urgent, niski → pilny) prosto z listy rozwijanej w tabeli, albo usuń pozycję przyciskiem kosza. Aby zmienić ilość, użyj **Browse** (Przeglądaj) - licznik przy tym samym produkcie edytuje tam otwartą pozycję bezpośrednio. Gdy partner skompletuje pozycję, blokuje się ona, a jej stan mówi, na jakim jest etapie.

## Przeglądanie katalogu partnera

Obok listy zakupowej znajduje się zakładka **Browse** (Przeglądaj): cały katalog partnera jako sklep, z aktualnymi stanami i cenami na żywo. Poruszaj się po nim przez **Departments** (Działy) lub **Collections** (Kolekcje), schodź do rodzin produktów, albo po prostu wpisz coś w polu wyszukiwania. Każda karta produktu pokazuje aktualną cenę, plakietkę **Their stock** (Ich stan) z tym, co partner ma dostępne, oraz - dla produktów, których używasz - Twoje własne liczby: *our stock* (nasz stan), *our sales / quarter* (nasza sprzedaż / kwartał) i *we run out in* (skończy nam się za) tyle a tyle dni (na czerwono, gdy to dwa tygodnie lub mniej).

Warto wiedzieć dwie rzeczy o tym katalogu. Ceny są **Twoje, nie z półki**: cena katalogowa sprzedającego z odjętym już Twoim rabatem międzyorganizacyjnym, przeliczona na walutę Twojej organizacji, więc to, co widzisz, jest tym, co powie faktura. I zawiera produkty, które partner uczynił **exclusive to you** (wyłącznymi dla Ciebie) - pozycje, które nigdy nie pojawiają się w jego publicznym sklepie, ale istnieją dla Twojej organizacji. Jeśli nie możesz znaleźć czegoś, czego się spodziewałeś, warto o to zapytać; jeśli znajdziesz coś, czego się nie spodziewałeś, prawdopodobnie jest Twoje na mocy ustaleń.

Zamawianie odbywa się bezpośrednio na karcie: pole ilości **jest** Twoją listą zakupową. Wpisz lub ustaw licznikiem liczbę, a pozycja zostanie dodana lub zaktualizowana na otwartej liście; ustaw z powrotem na 0, a pozycja zostanie usunięta. Obok niego przerywana plakietka **suggested** (sugerowane) pokazuje ilość, jaką zamówiłoby aiku - jedno kliknięcie wypełnia nią pole.

Podczas przeglądania Twoja lista zakupowa towarzyszy Ci jako paragon przypięty po prawej stronie - każda pozycja pogrupowana wg rodziny, z bieżącą sumą - więc zawsze wiesz, jak stoi zamówienie. **Go to Shopping list** (Przejdź do listy zakupowej) zabiera z powrotem do pełnej, edytowalnej listy.

<figure><img src="/art/docs/draw-partner-browse.svg" alt="Akwarelowy szkic przeglądarki katalogu partnera: pole wyszukiwania, zakładki Departments i Collections, karty produktów z przyciskami plus i paragon listy zakupowej przypięty po prawej z bieżącą sumą" width="1200" height="750" loading="lazy"><figcaption>Sklep partnera, z Twoją listą jadącą obok.</figcaption></figure>

## Auto-fill: budżet i, jeśli chcesz, instrukcja

Auto-fill (automatyczne uzupełnienie) istnieje po to, żeby uzupełnianie zapasów nie zależało od tego, czy ktoś pamięta o każdej pozycji. Podajesz mu jedną liczbę - **budget** (budżet), w tej samej walucie, w jakiej kupujesz - a ono buduje propozycję, która się w niej mieści:

- Przegląda każdą pozycję, którą partner może dostarczyć i której faktycznie używasz, szereguje je wg tego, **jak szybko Ci się skończy** (ta sama prognoza *we run out in*, którą widzisz podczas przeglądania), i uzupełnia najpierw te najbliższe wyczerpania, każdą w zalecanej ilości zamówienia.
- Każda proponowana pozycja pokazuje swój **reason** (powód, np. "Our sales/quarter ~48 · our stock 0 · we run out now"), ilość i koszt, więc widzisz, dlaczego się tam znalazła. Ilości wynikają z tej samej prognozy co plakietki *suggested* w Browse.
- **Instruction box** (pole instrukcji) jest opcjonalne i przyjmuje zwykły język: *"prioritise essential oils, skip anything we hold over 8 weeks of"* ("traktuj priorytetowo olejki eteryczne, pomiń wszystko, czego mamy zapas na ponad 8 tygodni"), *"focus on candles, nothing seasonal"* ("skup się na świecach, nic sezonowego"). AI czyta Twoją instrukcję razem z tymi samymi danymi o zużyciu i odpowiednio przekształca propozycję - ale jej wynik jest sprawdzany wobec rzeczywistości, zanim go zobaczysz: ilości są ograniczone do tego, co partner faktycznie ma, a suma jest wymuszona z powrotem w granicach Twojego budżetu. Jeśli instrukcji nie da się zrealizować, dostajesz standardową propozycję.
- **Nic nie dodaje się samo.** Propozycja to zestaw zaznaczonych pozycji, które możesz odznaczyć, zmienić ilość albo wygenerować ponownie z innym budżetem lub instrukcją; dopiero **Add items to shopping list** (Dodaj pozycje do listy zakupowej) coś zatwierdza.
- **Niektóre pozycje się wyłączają.** SKO z włączonym **Do not auto order** (Nie zamawiaj automatycznie) (na ekranie edycji SKO, w sekcji Stock Data) nigdy nie pojawia się w propozycji - dla pozycji, które dział zakupów chce mieć pod ręczną kontrolą. Nadal można ją zamówić ręcznie z Browse albo z listy stanów; tylko ścieżka automatyczna ją pomija. SKO oznaczone jako **On Demand** (Na zamówienie) są całkowicie pominięte w zakupach partnerskich.

Auto-fill można też otworzyć od razu zawężone: **+ fill** na kafelku ryzyka na pulpicie otwiera je tylko dla tego kubełka, z już wygenerowaną propozycją. Te same zasady - dostosowujesz, odznaczasz i zatwierdzasz; nic nie dodaje się samo.

Dobry nawyk: pracuj nad kafelkami pulpitu od najgorszych, potem raz na cykl uzupełnienia uruchom Auto-fill dla tego, co zostało, przeczytaj powody, odznacz to, z czym się nie zgadzasz, i dodaj resztę.

## Kiedy lista mówi nie

Dodawanie jest odrzucane celowo w trzech przypadkach: lista osiągnęła **budget** (budżet) dla tego partnera (pozycje rangi A i bez stanu magazynowego są zwolnione - awaria zawsze się zmieści), magazyn ma poniżej 5% wolnych lokalizacji, albo ten partner wykorzystał już swój sprawiedliwy udział w wolnych slotach produktami, których nigdy nie magazynowałeś. Zajmij się komunikatem zamiast szukać obejścia: ta sama blokada obejmuje dodawanie ręczne, hurtowe i Auto-fill. [Artykuł o pulpicie](/docs/reading-the-partner-shopping-dashboard-pl) wyjaśnia, skąd biorą się te limity.

## Kiedy towar jest w drodze

Gdy partner [wyśle przesyłkę do swojego magazynu](/docs/fulfilling-partner-orders-pl), po stronie Twojego partnera pod **Stock deliveries** (Dostawy towaru) pojawia się przychodząca **stock delivery** (dostawa towaru). Zostaw ją w spokoju, dopóki ma status confirmed (potwierdzona) lub dispatched (wysłana) - odzwierciedla magazyn sprzedającego i aktualizuje się sama. Gdy pudła fizycznie dotrą: **receive** (przyjmij), sprawdź i rozmieść na lokalizacjach dokładnie tak, jak przy każdej dostawie od dostawcy. Wszystko, co niepełne lub uszkodzone, załatwiasz po przyjęciu, względem powiązanej faktury - zobacz [przegląd](/docs/ordering-from-a-partner-organisation-pl), jak działają pieniądze.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Zobacz, co trzeba kupić:</b> Twoja organizacja → <b>Procurement → Partners</b> (Zakupy → Partnerzy) → otwórz partnera → <b>Shopping</b> (Zakupy, pulpit) → popracuj nad kafelkami ryzyka.</li>
<li><b>Dodaj do listy:</b> <b>Shopping list</b> (Lista zakupowa) → <b>Add stocks</b> (Dodaj towary), albo <b>Browse</b> (Przeglądaj) i ustaw ilości na kartach produktów, albo <b>Auto-fill</b> (lub <b>+ fill</b> na kafelku pulpitu) po propozycję.</li>
<li><b>Dostosuj otwarte pozycje:</b> zmień priorytet albo usuń pozycje w tabeli listy zakupowej; zmień ilości z kart produktów w <b>Browse</b> (Przeglądaj).</li>
<li><b>Wyłącz pozycję z auto-fill:</b> Twoja organizacja → <b>Warehouse → Inventory</b> (Magazyn → Zapasy) → otwórz SKO → <b>Edit SKO</b> (Edytuj SKO) → włącz <b>Do not auto order</b> (Nie zamawiaj automatycznie).</li>
<li><b>Śledź i przyjmij przesyłkę:</b> ta sama strona partnera → <b>Stock deliveries</b> (Dostawy towaru) → gdy towar dotrze, <b>Receive</b> (Przyjmij) → sprawdź → rozmieść na lokalizacjach.</li>
</ul>
</aside>
