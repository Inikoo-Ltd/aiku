---
title: Zamawianie od organizacji partnerskiej
summary: Dlaczego handel między organizacjami siostrzanymi opiera się na liście zakupowej zamiast na zamówieniach zakupu, i jak cała pętla działa - od zgłoszonej potrzeby do przyjętego na magazyn towaru.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, warehouse, intercompany
category: procurement
series: Ordering from partners
order: 1
---

<aside class="tldr">
Kiedy kupujesz od organizacji siostrzanej, nie wystawiasz zamówienia zakupu. Dodajesz to, czego potrzebujesz, do listy zakupowej; organizacja sprzedająca kompletuje ją, kiedy może ją wysłać. Od tego momentu wszystko toczy się samo: ich magazyn kompletuje i pakuje towar, a po Twojej stronie pojawia się przychodząca dostawa towaru, gotowa do przyjęcia, gdy towar dotrze. Jeśli Ty <em>składasz</em> te zamówienia, zacznij od <a href="/docs/reading-the-partner-shopping-dashboard-pl">pulpitu zakupów</a> i przeczytaj <a href="/docs/buying-from-a-partner-pl">Kupowanie od partnera</a>; jeśli Ty <em>realizujesz</em> te zamówienia, przeczytaj <a href="/docs/fulfilling-partner-orders-pl">Praca z listą To produce</a>.
</aside>

<figure><img src="/art/docs/draw-partner-shopping.svg" alt="Akwarelowy szkic: karta listy zakupowej kupującego (Procurement › Partners › Shopping list, z Auto-fill) i karta listy wysyłkowej sprzedającego z odhaczonymi pozycjami i przyciskiem Send to warehouse, przerywana strzałka między nimi, oraz ciężarówka wioząca towar do skrzynki oznaczonej jako przychodząca dostawa towaru" width="1200" height="750" loading="eager"><figcaption>Ty piszesz listę, oni ją kompletują i wysyłają, a po Twojej stronie wjeżdża dostawa towaru.</figcaption></figure>

## Dlaczego nie ma zamówienia zakupu

Zamówienie zakupu ma sens przy zewnętrznym dostawcy: zobowiązujesz się do ilości, dostawca je potwierdza, a obie strony śledzą ten sam dokument. Między naszymi własnymi organizacjami taka ceremonia tylko przeszkadza. Sprzedający zna swój stan magazynowy lepiej niż kupujący, a zmuszanie kupującego do zgadywania, co da się wysłać, prowadzi do niekończących się poprawek zamówień.

Dlatego cały przepływ jest odwrócony. **Kupujący mówi, czego potrzebuje**, **sprzedający decyduje, co i kiedy wysłać**. Nikt niczyjego zamówienia nie poprawia, bo nie ma zamówienia do poprawiania - jest tylko lista otwartych potrzeb i strumień wysyłek realizujących ją.

## Cała pętla, krok po kroku

1. Kupujący otwiera [pulpit zakupów](/docs/reading-the-partner-shopping-dashboard-pl), aby zobaczyć, co się kończy i ile jest miejsca, a następnie [dodaje to, czego potrzebuje, do listy zakupowej](/docs/buying-from-a-partner-pl) - ręcznie, z katalogu partnera, albo z propozycją auto-fill (automatycznego uzupełnienia).
2. Sprzedający [wybiera pozycje, które może wysłać, i kieruje przesyłkę do swojego magazynu](/docs/fulfilling-partner-orders-pl). Jest ona kompletowana, pakowana i wysyłana jak każde inne zamówienie.
3. W chwili, gdy przesyłka trafia do magazynu sprzedającego, po stronie kupującego pojawia się przychodząca **dostawa towaru** (stock delivery). Sama podąża za postępem sprzedającego - to sprzedający jest źródłem prawdy, dopóki towar nie dotrze.
4. Gdy towar fizycznie dotrze, kupujący przyjmuje go, sprawdza i rozmieszcza na lokalizacjach dokładnie tak, jak przy każdej dostawie od dostawcy.

## Lista jest celowo ograniczona

Lista kupującego to nie skrzynka życzeń. Jest ograniczona z grubsza do jednego cyklu zamówień tego, co partner faktycznie nam dostarcza, a nowe produkty są dodatkowo ograniczone wolnym miejscem w magazynie i sprawiedliwym udziałem w nim przypadającym na partnera. Lista, której nikt nie może zalać, to lista, którą sprzedający może odczytać: gdy jest na niej wszystko, nic nie jest pilne. Pozycje bez stanu magazynowego i rangi A są zwolnione z limitu, więc prawdziwy kryzys nigdy nie czeka w kolejce za ograniczeniem.

## Pieniądze, faktury i problemy

Między organizacjami nie ma osobnych faktur dostawcy. Faktura sprzedającego za przesyłkę **jest** tym dokumentem, a przychodząca dostawa towaru jest z nią powiązana. Jeśli coś dotrze w niepełnej ilości, uszkodzone lub niewłaściwe, zajmij się tym *po* przyjęciu dostawy - to właśnie w tym momencie odpowiedzialność przechodzi na Twoją stronę - a wszelki zwrot lub korekta rozliczane są względem tej powiązanej faktury.

## Warto wiedzieć

- Za pierwszym razem, gdy sprzedający kompletuje zamówienie dla partnera, w jego sklepie tworzone jest konto klienta nazwane od organizacji kupującej. To zamierzone - w ten sposób przesyłka przechodzi przez zwykłą maszynerię sprzedającego.
- Częściowe kompletacje to normalka. Pozycja skompletowana w części pozostawia resztę otwartą na kolejną przesyłkę; nic nie ginie.
- Ceny to bieżące ceny sklepowe sprzedającego z uwzględnieniem stałego rabatu międzyorganizacyjnego kupującej organizacji, pokazane w walucie kupującego. Nic nie jest negocjowane pozycja po pozycji; jeśli ustalenia się zmienią, zostanie to ogłoszone.

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li><b>Podgląd list zakupowej i wysyłkowej:</b> dostęp <i>view</i> (podgląd) do modułu procurement w Twojej organizacji.</li>
<li><b>Dodawanie pozycji, wybieranie do kompletacji, wysyłka do magazynu:</b> dostęp <i>edit</i> (edycja) do modułu procurement w organizacji wykonującej daną czynność (kupującego dla listy, sprzedającego dla kompletacji i wysyłki).</li>
<li><b>Przyjęcie i zaksięgowanie dostarczonego towaru:</b> dostęp do stanów magazynowych w magazynie kupującego, tak jak przy każdej dostawie od dostawcy.</li>
<li>Brakuje któregoś z tych uprawnień? Poproś administratora o nadanie roli w <b>Sysadmin → Users</b> (Administracja systemu → Użytkownicy) - uprawnienia są przypisywane per organizacja, więc posiadanie ich w jednej organizacji nie przenosi się na jej partnera.</li>
</ul>
</aside>
