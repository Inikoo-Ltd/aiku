---
title: Lucrul cu lista To produce
summary: Ghidul fabricii - o singură coadă cu tot ce datorează fabrica, organizațiilor partenere și propriilor clienți, grupată așa cum gândește un planificator de producție.
date: 2026-09-02
source_date: 2026-09-02
tags: production, procurement, intercompany, dispatch
category: production
series: Ordering from partners
order: 4
---

<aside class="tldr">
Pentru persoanele care <em>fac lucruri</em> și pentru persoana care planifică ziua fabricii. <b>To produce</b> (de produs) e coada fabricii: fiecare linie cerută de o organizație parteneră, plus fiecare linie comandată de un client propriu pe care fabrica nu o are în stoc. O grupezi pe artizan, pe categorie sau pe cumpărător, bifezi ce poți trimite partenerilor, iar restul actelor curge singur. Ești nou în fluxul cu partenerii? Începe cu <a href="/docs/ordering-from-a-partner-organisation-ro">prezentarea generală</a>. Vrei ca lista să știe cine face ce? Citește mai întâi <a href="/docs/who-makes-what-ro">Cine face ce</a>.
</aside>

## De unde vin liniile

**Factory → To produce** (Fabrică → De produs) e alimentată din două locuri. Nu tastezi niciodată o linie aici tu însuți.

- **Cererile partenerilor.** Organizațiile surori pun ce au nevoie pe [lista lor de cumpărături](/docs/buying-from-a-partner-ro). Fiecare linie deschisă adresată fabricii tale apare aici cu cumpărătorul, cantitatea și prioritatea pe care au setat-o.
- **Clienții proprii.** Când o comandă e trimisă în propriul tău magazin, aiku se uită la fiecare produs. Dacă stocul din spatele lui e insuficient și acel stoc e făcut de fabrică, deficitul ajunge aici ca o linie, marcată cu clientul și referința comenzii. Când acea comandă e expediată, linia se închide singură.

Comenzile care ajung prin sistemul vechi nu alimentează lista. Doar comenzile trimise în aiku o fac.

Filtrul **Source** (sursă) din partea de sus a tab-ului *All* îți permite să vezi doar liniile de la parteneri sau doar cele de la clienții proprii.

## Cele patru vederi

Bara de tab-uri de deasupra titlului e tot rostul paginii. Aceleași linii, patru moduri de a le privi.

- **All** (toate). Tabelul plat, sortabil și căutabil, cu numărul de linii deschise. Îl folosești când cauți un singur lucru.
- **By artisan** (pe artizan). Un bloc per persoană, folosind artizanul atașat artefactului sau, dacă lipsește, categoriei lui. Liniile fără nimeni atașat stau sub *Unassigned* (neatribuit). Aceasta e vederea pentru distribuirea muncii zilei.
- **By category** (pe categorie). Un bloc per categorie de artefact, ca cel care face bombe de baie să vadă bombele de baie, iar cel care face săpun să vadă săpunul.
- **By buyer** (pe cumpărător). Un bloc per organizație parteneră sau client propriu, pentru când construiești o expediere.

În vederile grupate, fiecare bloc are o capsulă deasupra listei care arată numele lui și numărul de linii. Click pe o capsulă ca să ascunzi acel bloc, click din nou ca să-l aduci înapoi. aiku își amintește alegerea ta în acest browser, așa că un planificator căruia îi pasă doar de două categorii vede mereu doar două.

## Trimiterea liniilor partenerilor

Liniile partenerilor se expediază de aici; liniile clienților proprii nu, ele călătoresc cu propria lor comandă.

- Bifează liniile de partener pe care le poți trimite. Ajustează cantitatea pentru un **partial pick** (picking parțial), restul rămâne deschis pentru o expediere ulterioară.
- **Pick into order** (adună în comandă) strânge bifele tale într-o expediere în așteptare, per organizație cumpărătoare. Rămâne deschisă în căsuța *Picked orders* (comenzi preluate) până o trimiți.
- **Send to warehouse** (trimite la depozit) predă expedierea depozitului tău ca pe o comandă normală: preluată, ambalată, expediată și facturată ca orice altceva. Livrarea de stoc care intră pentru organizația cumpărătoare e creată pentru ea și urmărește progresul depozitului tău. Nimeni nu actualizează manual partea cumpărătorului.

Bifarea unei linii de client propriu nu face nimic util. E sărită când apeși Pick into order, pentru că acel produs aparține deja unei comenzi de client.

## Lucruri bune de știut

- Lista deschisă a unui cumpărător e plafonată la aproximativ un ciclu de comandă din ce le livrezi istoric, așa că ce ajunge la tine e o cerere filtrată, nu un catalog întreg. Dacă o linie pare ciudată, întreabă; cumpărătorul a renunțat la ceva ca să o pună acolo.
- Primul picking pentru un partener nou creează un cont de client numit după organizația cumpărătoare în magazinul tău. E normal. Avertizează serviciul clienți ca nimeni să nu-l "curețe".
- Până apeși Send to warehouse, comanda preluată e invizibilă pe ecranele obișnuite de comenzi; pagina To produce e locul ei de bază.
- Ce expediezi e ce spune livrarea de stoc a cumpărătorului. Nu umfla niciodată cantitățile ca să "se potrivească cu lista".

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Vezi coada:</b> organizația ta → <b>Factory</b> → <b>To produce</b>. Schimbă vederea cu tab-urile <b>All · By artisan · By category · By buyer</b>.</li>
<li><b>Ascunde un bloc:</b> într-o vedere grupată, click pe capsula lui deasupra listei. Click din nou ca să-l arăți.</li>
<li><b>Doar parteneri sau doar clienți:</b> tab-ul <i>All</i> → filtrul <b>Source</b>.</li>
<li><b>Expediază la un partener:</b> bifează liniile → <b>Pick into order</b> → <b>Send to warehouse</b> în căsuța <i>Picked orders</i>.</li>
<li><b>Decide cine face ce:</b> vezi <a href="/docs/who-makes-what-ro">Cine face ce</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li>Pozițiile se setează pe fișa angajatului sub Human Resources și aduc cu ele drepturile.</li>
<li>Vizualizarea listei: poziția <b>Production operative</b> (operator) pentru fabrică, sau mai sus.</li>
<li>Preluarea, trimiterea și crearea ordinelor de lucru: poziția <b>Production floor supervisor</b> (supervizor de hală) pentru fabrică, sau supervizor de organizație.</li>
</ul>
</aside>
