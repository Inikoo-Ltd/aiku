---
title: Menținerea stocului fabricii
summary: Pagina To restock - ce artefacte se termină primele, cât timp îi ia fabricii să facă orice, și cum pui propria muncă de reaprovizionare pe panoul To produce.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, planning
category: production
series: Ordering from partners
order: 11
---

<aside class="tldr">
Pentru persoana care planifică săptămâna fabricii. <a href="/docs/fulfilling-partner-orders-ro">To produce</a> (de produs) răspunde la <i>ce a cerut cineva</i>. <b>To restock</b> (de reaprovizionat) răspunde la cealaltă întrebare: <i>ce ni se va termina, indiferent dacă a cerut cineva sau nu</i>. Sortează tot ce face fabrica după câte zile de acoperire au rămas, măsurate față de cât timp îi ia efectiv acestei fabrici să facă ceva, și îți permite să împingi ce merită făcut pe panoul To produce.
</aside>

## Timpul de execuție e etalonul

Fiecare grupă de pe această pagină e măsurată în **timpi de execuție**, nu în zile. Timpul de execuție e numărul mediu de zile de la momentul în care munca ajunge pe hală până se întoarce în depozit, luat din ordinele de lucru ale acestei fabrici din ultimul an. Sub cinci ordine de lucru finalizate și nu e nimic de măsurat, așa că aiku folosește o estimare — șapte zile, dacă nimeni nu a setat altă cifră pe fabrică — și scrie *estimate* (estimat) lângă număr.

De aceea grupele se citesc așa cum se citesc. Un artefact cu patru zile de acoperire nu e în pericol într-o fabrică ce întoarce munca în două zile; e deja pierdut într-una care are nevoie de o săptămână.

## Grupele

| Grupă | Ce înseamnă |
| --- | --- |
| Out of stock | nimic pe raft |
| Doomed | se va termina înainte să poată ajunge ceva pornit azi |
| Critical | se termină în două timpi de execuție |
| Danger | se termină în trei timpi de execuție |
| Watch | se termină în patru timpi de execuție |
| Covered | mai mult de patru timpi de execuție de acoperire |
| Dead stock | valoare pe raft și nicio mișcare deloc |
| Never made yet | un artefact fără nicio evidență de stoc în spate |

Fiecare grupă poartă trei numere: câte artefacte sunt în ea, câte sunt **deja preluate**, și câte sunt **neatinse**. Deja preluate înseamnă că cineva s-a ocupat deja de el — o linie deschisă pe panoul To produce, sau un ordin de lucru pe hală. Neatinse e numărul de lucrat.

Click pe grupe ca să alegi ce arată lista de mai jos. Se deschide pe **Out of stock, Doomed and Critical**, care e lista sinceră de dimineață.

## Culoarele

Sub grupe, aceeași muncă e așezată în patru culoare:

- **To do** (de făcut) — artefacte din grupele selectate pentru care nu s-a făcut nimic. Urgentele primele. Fiecare rând poartă codul de stoc, ce e pe raft, zilele de acoperire, familia artefactului, cine îl face de obicei, și numărul de **units** (unități) pentru care s-ar ridica un ordin de lucru: cantitatea recomandată de comandă transformată în unități și rotunjită la lotul întreg următor. Tot ce a cerut deja un partener e lăsat deoparte — asta e treaba lui To produce, nu a acestei pagini.
- **Queued** (în așteptare) — linii deja în așteptare pe panoul To produce, indiferent dacă au venit de la un partener sau de aici.
- **Producing** (în producție) — linii cu un ordin de lucru pe hală, cu referința lui și artizanul.
- **Restocked** (reaprovizionat) — ce s-a întors de pe hală în ultimele două săptămâni, ca să vezi pagina la lucru.

## Punerea muncii pe panou

Bifează rânduri în **To do** și apasă butonul ca să le pui în așteptare. Fiecare devine o linie pe panoul To produce fără niciun partener și niciun client în spate: pur și simplu muncă pe care fabrica și-o datorează sieși. De acolo e planificată, atribuită și făcută exact ca o linie de partener, și părăsește panoul când marfa finită e pusă la loc.

O linie e sărită, și spune asta, dacă același stoc e deja deschis pe panou. Nu poți pune aceeași cerere în așteptare de două ori apăsând butonul de două ori.

## Lucruri bune de știut

- **Articolele On Demand nu sunt aici.** Un artefact al cărui SKO e marcat *On Demand* e făcut când e cerut și nu are acoperire care să se termine.
- **Dead stock e o întrebare, nu o sarcină.** Valoarea care stă pe loc fără nicio mișcare vrea de obicei o discuție cu magazinul, nu un ordin de lucru.
- **Pagina se recalculează de fiecare dată.** Nimic nu e stocat, nimic nu trebuie curățat, și o grupă se golește singură când marfa ajunge.
- **Zilele de acoperire vin din aceeași prognoză** pe care o folosește restul aiku, vezi [Cum prezice aiku ce ți se termină](/docs/how-aiku-predicts-what-you-run-out-of-ro).

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Pagina:</b> organizația ta → <b>Factory</b> → <b>To restock</b>.</li>
<li><b>Schimbă ce arată culoarele:</b> apasă plăcile grupelor de sus.</li>
<li><b>Pune-ți propria muncă în așteptare:</b> bifează rânduri în <b>To do</b> → butonul de așteptare → apar pe <b>To produce</b>.</li>
<li><b>Setează timpul de execuție estimat</b> cât timp istoricul e subțire: în setările proprii ale fabricii; odată ce cinci ordine de lucru au fost finalizate, cifra măsurată preia singură controlul.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li>Pozițiile se setează pe fișa angajatului sub Human Resources și aduc cu ele drepturile.</li>
<li>Vizualizarea paginii: poziția <b>Production operative</b> pentru fabrică, sau mai sus.</li>
<li>Punerea muncii în așteptare pe To produce: poziția <b>Production floor supervisor</b> pentru fabrică, sau supervizor de organizație.</li>
</ul>
</aside>
