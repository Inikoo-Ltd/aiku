---
title: Partie, opakowania i częściowe partie
summary: Fabryka wytwarza sztuki w pełnych partiach, magazyn liczy opakowania. Co packed_in i wielkość partii robią ze zleceniem produkcyjnym, z tym, co trafia na półkę, i z tym, co powinien zamówić partner.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, procurement, crafts
category: production
series: Ordering from partners
order: 12
---

<aside class="tldr">
Przy każdym wyrobie spotykają się dwie liczby i nie są tą samą liczbą. Hala pracuje w <b>units</b> (sztukach) - jedna kula do kąpieli, jedna kostka mydła - i sensownie może wykonać tylko całą <b>batch</b> (partię), bo tyle mieści mikser. Magazyn i sklep pracują w <b>SKO</b>: opakowaniu, w którym towar jest sprzedawany i przechowywany, dziesięć na skrzynkę. <b>Packed in</b> (zapakowane w) to jedyny most między nimi, a aiku teraz przechodzi po nim z obu stron, zamiast udawać, że te liczby są wymienne.
</aside>

## Trzy liczby

- **Batch size** (wielkość partii) - ile sztuk fabryka wytwarza naraz. Należy do wyrobu, ustawiana na jego stronie albo hurtowo z listy wyrobów, patrz [Zmiana wielu wyrobów naraz](/docs/changing-many-artefacts-at-once-pl).
- **Packed in** (zapakowane w) - ile sztuk mieści się w jednym SKO. Należy do SKO w magazynie.
- **Units i SKO** - halę pyta się o sztuki, wszystko inne liczy się w SKO.

## Co dzieje się po każdej stronie

**Wystawianie zlecenia.** Cokolwiek zostało zamówione - pozycja partnerska, brak u własnego klienta, pozycja uzupełnienia - jest ilością w SKO. aiku mnoży ją przez *packed in*, żeby dostać sztuki, a potem zaokrągla **w górę** do najbliższej pełnej partii. Poproś o 1 SKO opakowania po dziesięć wytwarzanego w partiach po 16, a rzemieślnika poprosi się o 16 sztuk, nie o 1 i nie o 10.

**Przyjmowanie gotowego zlecenia.** To, co wykonał rzemieślnik, jest w sztukach, i jest dzielone przez *packed in* w drodze na półkę. Te 16 sztuk opakowania po dziesięć trafia na półkę jako 1,6 SKO: jedna zaplombowana skrzynka i sześć luzem. Liczba na półce jest uczciwa co do reszty, zamiast ją zaokrąglać do zera.

## Gdy partia nie wypełnia pełnych opakowań

Partia po 16 i skrzynka po 10 nigdy się nie zgadzają dokładnie. To nie jest błąd i aiku nie traktuje tego jak błędu: fabryka dobiera wielkość partii do miksera, sklep sprzedaje w opakowaniach, i obie strony mają rację. Warto po prostu wiedzieć, gdzie to się zdarza, więc jest to mierzone:

- kolumna **Batch in SKOs** (Partia w SKO) i filtr **Batch not whole SKOs** (Partia nie w pełnych SKO) na liście wyrobów;
- wiersz na stronie wyrobu pokazujący partię w SKO i najbliższą wielkość partii, która wyszłaby w całości;
- statystyka na pulpicie crafts licząca wyroby, u których to się zdarza.

Nic nie zmusza Cię do zmiany wielkości partii. Jeśli sugestię łatwo przyjąć, przyjmij ją, a arytmetyka przestanie zostawiać reszty. Jeśli to mikser decyduje o partii, zostaw jak jest.

## Co powinien zamówić partner

Ta sama arytmetyka decyduje o najzgrabniejszej ilości zamówienia, którą aiku nazywa **order step** (krokiem zamówienia): najmniejszej liczbie SKO, którą pełne partie wypełniają dokładnie. Dla partii 16 sztuk w opakowaniach po 10 to 8 SKO - osiemdziesiąt sztuk, pięć partii, bez reszty.

Po stronie partnera, na [liście zakupowej](/docs/buying-from-a-partner-pl) i na liście stanów magazynowych:

- pozycja pokazuje *made in batches of N units* (wytwarzane w partiach po N sztuk) i, gdy się różnią, *full batches every N SKO* (pełne partie co N SKO);
- mały przycisk zaokrągla ilość w górę do najbliższego kroku;
- ilości **suggested** (sugerowane) i wszystko, co proponuje Auto-fill, są już na kroku;
- zamówienie poza krokiem nadal jest przyjmowane, z uwagą, że pełna partia i tak zostanie wykonana, więc zamówienie może zostać opóźnione albo ilość dostosowana.

Zamówienie poniżej jednego pełnego kroku w ogóle nie może zostać wykonane samodzielnie. Na tablicy [To produce](/docs/fulfilling-partner-orders-pl) czeka pod hasłem *lines too small for a batch are waiting for company* (pozycje za małe na partię czekają na towarzystwo), dopóki nie zbierze się wystarczający popyt, zamówienie własnego klienta i tak nie uruchomi zlecenia, albo planista nie zdecyduje wykonać jej mimo wszystko.

<aside class="wayfinder"><strong>Gdzie kliknąć w aiku</strong>
<ul>
<li><b>Ustaw wielkość partii:</b> Twoja organizacja → <b>Factory</b> (Fabryka) → <b>Crafts</b> (Rzemiosło) → <b>Artefacts</b> (Wyroby) → otwórz wyrób albo zaznacz kilka i ustaw z paska zaznaczenia.</li>
<li><b>Znajdź te niewygodne:</b> lista wyrobów → filtr <b>Batch not whole SKOs</b> (Partia nie w pełnych SKO) albo kolumna <b>Batch in SKOs</b> (Partia w SKO).</li>
<li><b>Zobacz sugestię:</b> strona wyrobu, pod wielkością partii.</li>
<li><b>Ustaw packed in:</b> <b>Warehouse → Inventory</b> (Magazyn → Zapasy) → otwórz SKO → <b>Edit SKO</b> (Edytuj SKO).</li>
<li><b>Zamów na kroku:</b> lista zakupowa partnera → przycisk obok <i>full batches every N SKO</i>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Wymagane uprawnienia</strong>
<ul>
<li>Stanowiska są ustawiane na karcie pracownika w module Human Resources (Kadry) i niosą ze sobą uprawnienia.</li>
<li>Wielkość partii i trwałość na wyrobach: uprawnienie fabryki <b>research and development</b> (badania i rozwój) albo przełożony organizacji.</li>
<li>Packed in na SKO: stanowisko magazynowe mogące edytować zapasy.</li>
</ul>
</aside>
