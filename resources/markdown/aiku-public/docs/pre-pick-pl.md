---
title: Pre-pick
summary: Przewodnik dla fabryki po Factory → Pre-pick - które pozycje partnera tu trafiają, co znaczy każdy status i co robić, gdy towar jest, gdy jest tylko część, gdy więcej jest w drodze i gdy już nie przyjdzie.
date: 2026-11-25
source_date: 2026-11-25
tags: production, procurement, intercompany, warehouse
category: production
series: Ordering from partners
order: 8
---

<aside class="tldr">
Pozycje, które trzeba wyprodukować, idą do <b>To produce</b> (do wyprodukowania); pozycje, które już masz na półce, trafiają do <b>Pre-pick</b>. Dla każdej pozycji w Pre-pick:
<ul>
<li><b>Masz cały towar:</b> nic nie rób. aiku samo wyśle ją do magazynu w ciągu dziesięciu minut. Jeśli towaru jest dokładnie tyle, co w pozycji, i nic nie zostaje, pozycja czeka: naciśnij <b>Pre-pick</b>.</li>
<li><b>Masz tylko część, a więcej jest w drodze od dostawcy:</b> poczekaj. Gdy dostawa zostanie przyjęta na stan, aiku samo wyśle całą pozycję. Albo naciśnij <b>Pre-pick</b>, żeby wysłać od razu to, co masz; reszta idzie do <b>To produce</b>.</li>
<li><b>Masz tylko część, a to produkt, który sam wytwarzasz:</b> czekanie nic nie da, bo nikt nie dostaje polecenia wyprodukowania reszty. Naciśnij <b>Pre-pick</b>, żeby wysłać to, co masz; reszta idzie do <b>To produce</b>, żeby ją wyprodukować.</li>
<li><b>Towar nie przyjdzie (wycofany, niedostępny):</b> naciśnij <b>Close short</b> (zamknij z niedoborem), żeby wysłać to, co jest, i anulować resztę, albo <b>Can't be done</b>, żeby nie wysyłać nic; partner dostaje powiadomienie. Jeśli nic nie ma na stanie, pozycja jest w <b>To produce</b>: zobacz Przypadek 3 niżej.</li>
</ul>
</aside>

<figure><img src="/art/docs/draw-pre-pick-fork.svg" alt="Szkic akwarelą: pozycja z listy zakupów partnera dochodzi do drogowskazu, który wskazuje To produce, czyli stół warsztatowy, na którym coś się wytwarza, albo Pre-pick, czyli półkę z towarem. Z półki: cały towar na stanie idzie sam do skrzyni partnera w ciągu dziesięciu minut; część towaru na stanie idzie do skrzyni, a reszta czeka na ciężarówkę z dostawą; towar, który nie przyjdzie, dostaje czerwoną pieczątkę Close short, a do partnera leci wiadomość." width="1200" height="750" loading="eager"><figcaption>Na półce: Pre-pick. Nie ma na półce: To produce.</figcaption></figure>

## Dlaczego pozycja trafia tutaj, a nie do To produce

Zamówienie partnera przychodzi jako pozycje na liście zakupów. Dla każdej pozycji aiku sprawdza Twój **free stock** (stan wolny): to, co leży na półce, minus to, co jest już obiecane innym pozycjom partnerów.

- **Stan wolny nie pokrywa nic** - pozycja idzie do **To produce**, żeby ją wyprodukować. Zobacz [Praca z listą To produce](/docs/fulfilling-partner-orders-pl).
- **Stan wolny pokrywa część lub całość** - pozycja trafia do **Factory → Pre-pick**.

To samo zamówienie może więc być podzielone między obie strony: herbata, którą mieszasz, idzie do To produce, a butelki, torby i pudełka, które już masz, trafiają tutaj. Pozycja, której towar skończy się później, sama przechodzi do To produce.

Pozycje są obsługiwane w kolejce, osobno dla każdego produktu: najpierw **urgent** (pilne), potem **high** (wysoki), **normal** (normalny) i **low** (niski); przy tym samym priorytecie - ta, która jest potrzebna najwcześniej, potem najstarsza. Pozycja liczy tylko towar, który zostaje po wszystkich pozycjach przed nią.

## Jak czytać listę

| Kolumna | Co mówi |
| --- | --- |
| For | która organizacja partnerska prosiła |
| Artefact | kod i nazwa produktu |
| To | skrzynia zbiorcza tego partnera, dokąd pójdzie towar |
| Asked | ile partner zamówił |
| In stock | ile jest teraz na Twoich półkach |
| Can pick | ile możesz teraz dać tej pozycji, po pozycjach przed nią w kolejce |
| Shortfall | ile brakuje, żeby pokryć całą pozycję |
| Status | co aiku z nią zrobi, patrz niżej |
| Priority, Added | miejsce pozycji w kolejce |

Kategoria, zamawiający i pilność filtrują listę; liczby przy filtrach to prawdziwe ilości.

## Co znaczą statusy

| Status | Co znaczy | Co robisz |
| --- | --- | --- |
| **Going to the warehouse** (idzie do magazynu) | towar pokrywa całą pozycję i jeszcze zostaje | nic - w ciągu dziesięciu minut aiku samo zrobi pre-pick i pozycja zniknie z listy |
| **Held, one more needed on the shelf** (wstrzymana, na półce musi zostać jeszcze jedna sztuka) | towar pokrywa pozycję dokładnie, nic nie zostaje | nic, albo naciśnij **Pre-pick**, jeśli ma iść teraz; aiku wysyła pozycję samo tylko wtedy, gdy na półce zostaje przynajmniej jedna sztuka |
| **Awaiting full stock, production manager to decide** (czeka na pełny stan, decyduje kierownik produkcji) | na stanie jest tylko część pozycji | zdecyduj, patrz przypadki niżej |
| **In To produce** | towar jest, ale pozycje przed tą już go zabrały | nic tutaj; pozycja czeka na stronie To produce |

Pre-pick to nie wysyłka. Obiecuje towar temu partnerowi i dodaje spacer na listę magazynu: ktoś zdejmuje towar z półki i wkłada go do skrzyni partnera. Towar wyjeżdża przy najbliższej wysyłce partnera, według harmonogramu albo gdy ktoś naciśnie **Send to warehouse** (wyślij do magazynu). Zobacz [Zbieranie towaru partnera](/docs/gathering-a-partners-goods-pl).

## Przypadek 1: masz cały towar

Nie rób nic. Pozycja pokazuje **Going to the warehouse** i aiku zrobi pre-pick w ciągu dziesięciu minut. Jeśli pokazuje **Held, one more needed on the shelf** i nie powinna czekać, naciśnij **Pre-pick** w tym wierszu.

Żeby wysłać kilka pozycji naraz, zaznacz je i naciśnij **Pre-pick selected**, albo **Pre-pick all** dla wszystkiego, co pokazują bieżące filtry.

## Przypadek 2: masz część towaru albo więcej jest w drodze

Pozycja pokazuje **Awaiting full stock, production manager to decide**. Masz trzy wyjścia:

- **Poczekać na resztę.** Nie rób nic. Gdy towar zamówiony u dostawcy dotrze i zostanie przyjęty na stan, stan wolny rośnie; gdy tylko pokryje całą pozycję, a na półce zostanie przynajmniej jedna sztuka, aiku samo zrobi pre-pick. Wybierz to, gdy dostawa jest blisko, a partner woli dostać wszystko naraz. Czekanie działa tylko wtedy, gdy towar naprawdę jest w drodze: przy produkcie, który wytwarzasz, nikt nie dostaje polecenia wyprodukowania reszty, dopóki pozycja tu czeka, więc czekałaby w nieskończoność.
- **Wysłać to, co masz teraz, resztę później.** Naciśnij **Pre-pick** (ilość pokaże się jako *partial pick*, częściowa kompletacja). Część będąca na stanie idzie do skrzyni; reszta zostaje na liście partnera jako osobna otwarta pozycja i przechodzi do **To produce**. Produkt, który wytwarzasz, pojawia się tam na tablicy, żeby go wyprodukować; cokolwiek innego pojawia się w zakładce **All** (wszystkie) i wraca tutaj, gdy przyjdzie nowy towar.
- **Wysłać to, co masz, i na tym skończyć.** Naciśnij **Close short**. aiku prosi o potwierdzenie, wysyła to, co jest na stanie, i anuluje resztę. Lista partnera spada do wysłanej ilości, a osoba, która dodała pozycję, i zespół zakupów partnera dostają informację, że pozycję zamknięto z niedoborem, żeby mogli kupić ją gdzie indziej.

## Przypadek 3: towar nie przyjdzie

Produkt jest wycofany, dostawca już go nie ma albo nie będzie dostępny przez długi czas.

- **Część jest na stanie** - naciśnij **Close short**: partner dostaje to, co jest, i dowiaduje się, że reszta nie przyjdzie. Jeśli nie możesz wysłać także nic z tego, naciśnij zamiast tego **Can't be done** w wierszu i podaj powód.
- **Nic nie ma na stanie i produkt wytwarzasz** - pozycja jest kartą na tablicy **To produce**. Przeciągnij ją z **Backlog** (zaległe) do **Preparing** (w przygotowaniu), albo do **Producing** (w produkcji), jeśli Twoja fabryka używa prostej tablicy, a w oknie, które się otworzy, naciśnij **Can't be done** (nie da się zrobić) i podaj powód. Pozycja znika z tablicy, a osoba, która ją dodała, i zespół zakupów partnera dostają informację z Twoim powodem, żeby mogli kupić towar gdzie indziej.
- **Nic nie ma na stanie i produktu nie wytwarzasz** - pozycja jest w **To produce → All**. Naciśnij **Can't be done** w jej wierszu i podaj powód; te same osoby dostają informację.

Nie zostawiaj takich pozycji bez słowa: partner widzi je jako **Requested** (zamówione) na swojej liście i dalej na nie liczy.

## Co widzi partner

Na własnej liście zakupów każda pozycja pokazuje, dokąd doszła: **Requested**, **Being made**, **Pre-picked**, **Staged for you**, **Being picked**, **On its way**.

Gdy pozycję zamknięto z niedoborem albo nie da się jej zrobić, osoby po stronie partnera dostają informację wraz z powodem.

## Warto wiedzieć

- **Nic nie rezerwuje towaru, dopóki nie zrobiono pre-picku.** Pozycja ze statusem *Awaiting full stock* niczego nie odłożyła: jej towar można jeszcze sprzedać albo dać innej pozycji. Jeśli nie wolno go zabrać, zrób pre-pick tego, co jest.
- **Jeśli na półce okaże się za mało** - inwentaryzacja pokaże mniej albo towar sprzedano gdzie indziej - aiku cofa obietnicę, zaczynając od najnowszych pre-picków, a te pozycje wracają na listę albo do To produce.
- **Lista jest liczona od nowa przy każdym otwarciu.** Pozycje znikają same po pre-picku; nie ma nic do sprzątania.
- **Liczba przy Pre-pick** w menu bocznym fabryki to liczba czekających pozycji i odświeża się sama.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Lista:</b> Twoja organizacja → <b>Factory</b> → <b>Pre-pick</b>.</li>
<li><b>Wyślij pozycję teraz:</b> <b>Pre-pick</b> w wierszu, albo zaznacz wiersze i naciśnij <b>Pre-pick selected</b>, albo <b>Pre-pick all</b>.</li>
<li><b>Wyślij to, co jest, i anuluj resztę:</b> <b>Close short</b> w wierszu.</li>
<li><b>Pozycja, której nie da się zrobić:</b> <b>Can't be done</b> w jej wierszu w <b>Pre-pick</b> albo w <b>Factory</b> → <b>To produce</b> → <b>All</b>, albo przeciągnij jej kartę z <b>Backlog</b> na tablicy.</li>
<li><b>Spacery do skrzyni:</b> <b>Warehouse</b> → <b>Dispatching</b> → zakładka <b>Pre-pick</b>, zobacz <a href="/docs/gathering-a-partners-goods-pl">Zbieranie towaru partnera</a>.</li>
<li><b>Wyślij zebrany towar:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>, albo pozwól działać harmonogramowi wysyłek partnera.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Potrzebne uprawnienia</strong>
<ul>
<li>Stanowiska ustawia się w karcie pracownika w Human Resources i niosą one ze sobą uprawnienia.</li>
<li>Podgląd listy: dowolne stanowisko w fabryce, które może przeglądać produkcję, albo supervisor organizacji.</li>
<li>Pre-pick i Close short: stanowisko w fabryce, które planuje lub przygotowuje produkcję, edytor zakupów albo supervisor organizacji.</li>
<li><b>Can't be done</b>: stanowisko w fabryce, które planuje lub przygotowuje produkcję, albo supervisor organizacji. Zobacz <a href="/docs/factory-positions-pl">Factory positions</a>.</li>
</ul>
</aside>
