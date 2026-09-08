---
title: Przygotowywanie mieszanek
summary: Dla przygotowującego i dla planisty - jak mieszanka lub baza staje się czymś, co śledzi fabryka, jak zakładka Mixes wylicza, co przygotować, i jak płyną zlecenia przygotowującego.
date: 2026-09-02
source_date: 2026-09-02
tags: production, crafts
category: production
series: Ordering from partners
order: 6
---

<aside class="tldr">
Dla osoby, która przygotowuje mieszanki i bazy, zanim rzemieślnicy mogą zacząć, oraz dla planisty, który wysyła jej pracę. Mieszanka jest wytwarzana u nas, więc aiku traktuje ją jednocześnie jako <b>surowiec</b> (rzemieślnicy go zużywają) i jako <b>wyrób</b> (przygotowujący go wykonuje). Po połączeniu obu, zakładka <b>Mixes</b> (Mieszanki) na liście <a href="/docs/fulfilling-partner-orders-pl">To produce</a> wylicza, ile każdej mieszanki potrzeba na podstawie otwartych zleceń produkcyjnych, a jeden przycisk zamienia to w zlecenia dla przygotowującego. Konfiguracja kategorii i rzemieślników jest opisana w <a href="/docs/who-makes-what-pl">Kto co wykonuje</a>.
</aside>

## Dlaczego mieszanka jest dwiema rzeczami

Receptura kuli do kąpieli mówi "0,5 kg bazy na sztukę". Ta baza nie jest kupowana, tylko przygotowywana w fabryce z własnych surowców. Więc istnieje podwójnie:

- Jako **surowiec**, żeby receptury mogły ją zużywać i żeby zapas był odejmowany, gdy gotowy wyrób trafia na magazyn.
- Jako **wyrób**, z własną recepturą i własnymi zleceniami produkcyjnymi, żeby przygotowujący miał pracę i partię do przyjęcia na magazyn.

Połączenie między nimi to jedno pole na surowcu: **Made in-house as** (Wytwarzane u nas jako). Ustaw je na wyrób odpowiadający mieszance. To cała konfiguracja.

## Konfigurowanie mieszanki

1. **Utwórz wyrób** dla mieszanki pod **Factory → Crafts → Artefacts** (Fabryka → Rzemiosło → Wyroby), z jego krokami receptury i własnymi surowcami, tak jak każdy inny wyrób. Nadaj mu magazyn (SKU), żeby przyjęte partie miały gdzie trafić.
2. **Utwórz lub otwórz surowiec** dla mieszanki pod **Factory → Crafts → Raw materials** (Fabryka → Rzemiosło → Surowce). Edytuj go, ustaw **Made in-house as** (Wytwarzane u nas jako) na wyrób z kroku 1 i nadaj mu ten sam magazyn (SKU).
3. **Użyj surowca w recepturach.** Na każdym produkcie, który potrzebuje mieszanki, dodaj ją do właściwego kroku receptury z ilością na sztukę.
4. **Przypisz przygotowującego** do wyrobu-mieszanki, albo do kategorii obejmującej wszystkie mieszanki, w polu *Usually made by* (Zwykle wykonuje). Zlecenia na mieszanki trafiają wtedy do tej osoby.

## Zakładka Mixes

**Factory → To produce → Mixes** (Fabryka → Do produkcji → Mieszanki) pokazuje każdy wytwarzany u nas surowiec, którego potrzebuje otwarte zlecenie produkcyjne. Zlecenie jest otwarte od chwili utworzenia aż do przyjęcia na magazyn.

Dla każdej mieszanki widać:

- **Needed** (Potrzeba): ilości z otwartych zleceń pomnożone przez ilość na sztukę z receptury, zsumowane po wszystkich produktach.
- **On hand** (Na magazynie): aktualny zapas mieszanki.
- **Being made** (W przygotowaniu): ilość w otwartych zleceniach na samą mieszankę.
- **Short** (Brakuje): potrzeba minus na magazynie minus w przygotowaniu. Braki są na górze listy i pokazane na czerwono.
- **Needed for** (Potrzebne do): kody produktów, które ją zużywają, żeby przygotowujący wiedział, na co czekają.

Zaznacz mieszanki do przygotowania, popraw ilość, jeśli brak nie odpowiada właściwej wielkości partii, i naciśnij **Create job orders** (Utwórz zlecenia). Powstaje jedno zlecenie na przygotowującego, zaadresowane do niego, w wersji roboczej. Otwórz je i naciśnij *Release to floor* (Skieruj na halę), gdy ma się zacząć.

## Co robi przygotowujący

Przygotowujący prowadzi własną linię, więc dla fabryki ma stanowisko <b>Mix preparer</b> (przygotowujący mieszanki). To daje mu dostęp do zakładki Mixes, możliwość tworzenia i kierowania na halę własnych zleceń oraz przyjmowania ich na magazyn, bez czekania na nikogo. Nie może dotknąć zleceń zaadresowanych do innych osób - to zostaje przy planiście. Na hali pracuje jak każdy rzemieślnik: jego zadania pojawiają się na ekranie hali, naciska START i DONE (Zakończono), a gdy ostatni krok jest gotowy, zlecenie jest przyjmowane na magazyn z kodem partii. Od tej chwili mieszanka jest widoczna jako dostępna na magazynie i rzemieślnicy mogą wykonywać swoje produkty.

Jeśli przygotowujący nie jest rozliczany stawką akordową, to ustawienie w kadrach, a nie powód, żeby pomijać halę. Zapis, kto przygotował którą partię i kiedy, daje możliwość prześledzenia od gotowego produktu z powrotem do jego składników.

## Warto wiedzieć

- Mieszanka nie może potrzebować samej siebie. Jeśli receptura wyrobu-mieszanki wymienia ten sam surowiec, ta pozycja jest pomijana.
- Zakładka Mixes czyta tylko zlecenia z tej fabryki. Produkt wykonywany w innej fabryce nie tworzy tu zapotrzebowania.
- "Being made" (W przygotowaniu) liczy zlecenie aż do przyjęcia na magazyn, nawet jeśli wszystkie kroki są zrobione. Przyjmuj zlecenia na bieżąco, a liczby pozostaną wiarygodne.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Połącz mieszankę:</b> <b>Factory → Crafts → Raw materials</b> (Fabryka → Rzemiosło → Surowce) → otwórz mieszankę → <b>Edit</b> (Edytuj) → <b>Made in-house as</b> (Wytwarzane u nas jako).</li>
<li><b>Zobacz, co przygotować:</b> <b>Factory → To produce → Mixes</b> (Fabryka → Do produkcji → Mieszanki).</li>
<li><b>Wyślij pracę:</b> zaznacz mieszanki → <b>Create job orders</b> (Utwórz zlecenia) → otwórz zlecenie → <b>Release to floor</b> (Skieruj na halę).</li>
<li><b>Wykonaj pracę:</b> <b>Factory → Floor</b> (Fabryka → Hala) (My tasks / Moje zadania) → <b>START</b> / <b>DONE</b> (Zakończono); potem zlecenie jest przyjmowane na magazyn ze swojej strony.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Stanowiska są ustawiane na karcie pracownika w module Human Resources (Kadry) i niosą ze sobą uprawnienia.</li>
<li>Widok zakładki Mixes i praca na hali: <b>Production operative</b> (pracownik produkcyjny) dla fabryki lub wyższe.</li>
<li>Tworzenie zleceń na mieszanki oraz kierowanie na halę i przyjmowanie własnych: <b>Mix preparer</b> (przygotowujący mieszanki) dla fabryki. Tego stanowiska potrzebuje przygotowujący.</li>
<li>Wszystko inne, w tym zlecenia innych osób i łączenie surowca z jego wyrobem: <b>Production floor supervisor</b> (kierownik hali) dla fabryki lub przełożony organizacji.</li>
</ul>
</aside>
