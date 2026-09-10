---
title: Lucrul cu lista To produce
summary: Ghidul fabricii - o singură coadă cu tot ce datorează fabrica, organizațiilor partenere și propriilor clienți, grupată așa cum gândește un planificator de producție.
date: 2026-09-09
source_date: 2026-09-09
tags: production, procurement, intercompany, dispatch
category: production
series: Ordering from partners
order: 4
---

<aside class="tldr">
Pentru persoanele care <em>fac lucruri</em> și pentru persoana care planifică ziua fabricii. <b>To produce</b> (de produs) e coada fabricii: fiecare linie cerută de o organizație parteneră, plus fiecare linie comandată de un client propriu pe care fabrica nu o are în stoc. <b>Board</b> (panoul) e locul unde planifici: tragi o linie de-a lungul culoarelor ca să decizi câte se fac și cine le face, iar un ordin de lucru e creat pentru artizan. Vederile de listă grupează aceleași linii pe artizan, categorie sau cumpărător, și de acolo bifezi ce poți trimite partenerilor; restul actelor curge singur. Ești nou în fluxul cu partenerii? Începe cu <a href="/docs/ordering-from-a-partner-organisation-ro">prezentarea generală</a>. Vrei ca lista să știe cine face ce? Citește mai întâi <a href="/docs/who-makes-what-ro">Cine face ce</a>.
</aside>

## De unde vin liniile

**Factory → To produce** (Fabrică → De produs) e alimentată din două locuri. Nu tastezi niciodată o linie aici tu însuți.

- **Cererile partenerilor.** Organizațiile surori pun ce au nevoie pe [lista lor de cumpărături](/docs/buying-from-a-partner-ro). Fiecare linie deschisă adresată fabricii tale apare aici cu cumpărătorul, cantitatea și prioritatea pe care au setat-o.
- **Clienții proprii.** Când o comandă e trimisă în propriul tău magazin, aiku se uită la fiecare produs. Dacă stocul din spatele lui e insuficient și acel stoc e făcut de fabrică, deficitul ajunge aici ca o linie, marcată cu clientul și referința comenzii. Când acea comandă e expediată, linia se închide singură.

Comenzile care ajung prin sistemul vechi nu alimentează lista. Doar comenzile trimise în aiku o fac.

Filtrul **Source** (sursă) din partea de sus a tab-ului *All* îți permite să vezi doar liniile de la parteneri sau doar cele de la clienții proprii.

## Vederile

Bara de tab-uri de deasupra titlului e tot rostul paginii. Aceleași linii, șase moduri de a le privi.

- **Board** (panou). Vederea de planificare, cea cu care se deschide pagina. Fiecare linie e o cartelă care se mișcă prin culoare de la *Backlog* la *Done*. Explicat în secțiunea următoare.
- **All** (toate). Tabelul plat, sortabil și căutabil, cu numărul de linii deschise. Îl folosești când cauți un singur lucru.
- **By artisan** (pe artizan). Un bloc per persoană, folosind artizanul atașat artefactului sau, dacă lipsește, categoriei lui. Liniile fără nimeni atașat stau sub *Unassigned* (neatribuit). Aceasta e vederea pentru distribuirea muncii zilei.
- **By category** (pe categorie). Un bloc per categorie de artefact, ca cel care face bombe de baie să vadă bombele de baie, iar cel care face săpun să vadă săpunul.
- **By buyer** (pe cumpărător). Un bloc per organizație parteneră sau client propriu, pentru când construiești o expediere.
- **Mixes** (amestecuri). Bazele și amestecurile de care au nevoie ordinele de lucru deschise, pentru preparator. Explicat în [Pregătirea amestecurilor](/docs/preparing-mixes-ro).

În vederile grupate, fiecare bloc are o capsulă deasupra listei care arată numele lui și numărul de linii. Click pe o capsulă ca să ascunzi acel bloc, click din nou ca să-l aduci înapoi. aiku își amintește alegerea ta în acest browser, așa că un planificator căruia îi pasă doar de două categorii vede mereu doar două.

## Board (panoul)

Șase culoare, de la stânga la dreapta. O cartelă se mișcă spre dreapta pe măsură ce lucrarea avansează, iar cele mai multe mutări sunt o tragere.

| Culoar | Ce stă acolo |
| --- | --- |
| Backlog | linii cu un artefact la care nimeni nu s-a uitat încă |
| Preparing | linii pe care ai decis să le faci, cu cantitatea stabilită |
| Assigned | există un ordin de lucru și e adresat unui artizan, dar nimeni nu l-a început |
| Producing | un artizan a apăsat START pe una din sarcinile lui |
| Done | toate sarcinile ordinului de lucru sunt gata; așteaptă ca depozitul să îl pună la loc |

Fiecare cartelă arată produsul, cantitatea cerută, cine a cerut și **In stock** (în stoc), ca să vezi dacă merită produs deloc. Doar liniile cu un artefact în această fabrică ajung pe panou; liniile pentru care stocul poate fi pur și simplu luat de pe raft trăiesc pe propria lor pagină, **Factory → Pre-pick**, vezi [Adunarea mărfii unui partener](/docs/gathering-a-partners-goods-ro).

Sub culoare mai stă o linie: **N lines too small for a batch are waiting for company · show** (N linii prea mici pentru un lot așteaptă companie · arată). Un partener poate cere mai puțin decât un lot întreg, iar acea linie nu poate fi făcută rezonabil de una singură, așa că așteaptă la marginea drumului în loc să aglomereze Backlog-ul. E preluată când cererea deschisă pentru același stoc, adunată de pe toate listele partenerilor, ajunge la un lot, când o comandă de client propriu la poartă face lucrarea să pornească oricum, sau când apeși *show* și o faci oricum. O linie poate aștepta mult; asta e o descriere mai adevărată a situației ei decât orice stare am putea inventa. Vezi [Loturi, pachete și loturi parțiale](/docs/batches-packs-and-part-batches-ro).

**Backlog → Preparing.** Lasă cartela și aiku întreabă *Câte se fac?*. Propune cantitatea cerută; scrie mai mult și diferența e marcată *for stock* (pentru stoc). Dacă artefactul are o dimensiune de lot recomandată, un mic buton **↑** rotunjește cantitatea la loturi întregi. Ce i se cere în cele din urmă artizanului e în **units** (unități), rotunjit la lotul întreg următor, iar ce se întoarce e împărțit la dimensiunea pachetului pe drumul spre raft: 16 unități dintr-un pachet de zece ajung ca 1,6 SKO-uri. Numărul rămâne editabil pe cartelă cât timp e în Preparing.

**Preparing → Assigned.** Lasă cartela și aiku întreabă *Cine o face?*. Propune artizanul atașat artefactului sau categoriei lui, vezi [Cine face ce](/docs/who-makes-what-ro). Alege un nume și un ordin de lucru e creat ca ciornă, adresat persoanei respective. Deschide ordinul de lucru și apasă **Release to floor** (trimite pe hală) când trebuie să înceapă; până atunci artizanul nu îl vede. Ca să schimbi artizanul mai târziu, apasă pe nume pe cartelă.

**Producing** și **Done** se mișcă singure, în funcție de ce se întâmplă pe ecranul halei. O cartelă părăsește panoul când depozitul pune la loc marfa finită, vezi [Punerea la loc a producției finite](/docs/putting-away-finished-production-ro), sau când ordinul de lucru e recepționat în stoc din propria lui pagină.

Mai multe cartele odată: apasă pe cartele ca să le selectezi, apoi trage oricare din ele și toată selecția se mișcă. Meniul **Everybody** (toată lumea) deasupra panoului îl restrânge la unul sau doi artizani, iar filtrele de familie, cumpărător și prioritate fac același lucru pentru cartele.

Bara laterală a fabricii poartă numărătorile live pentru **To produce** și **Pre-pick** lângă numele lor, și se mișcă singure pe măsură ce listele de cumpărături se schimbă; nu trebuie reîncărcată pagina ca să vezi dacă a intrat ceva nou.

Sub Board și vederea By artisan stă **Open job orders per artisan** (ordine de lucru deschise per artizan): câte o etichetă per persoană cu câte ordine de lucru deschise are. Roșu înseamnă niciunul, chihlimbariu înseamnă unul; toată lumea ar trebui să aibă cel puțin două ca nimeni să nu rămână fără. Cruciulița de pe o etichetă marchează persoana ca nefiind artizan și o ascunde din numărătoare.

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
<li><b>Vezi coada:</b> organizația ta → <b>Factory</b> → <b>To produce</b>. Schimbă vederea cu tab-urile <b>Board · All · By artisan · By category · By buyer · Mixes</b>.</li>
<li><b>Decide cantitatea:</b> <i>Board</i> → trage cartela din <b>Backlog</b> în <b>Preparing</b> → scrie numărul, sau apasă <b>↑</b> pentru loturi întregi.</li>
<li><b>Creează ordinul de lucru:</b> trage cartela din <b>Preparing</b> în <b>Assigned</b> → alege artizanul → deschide ordinul de lucru → <b>Release to floor</b>.</li>
<li><b>Linii care au nevoie doar de picking:</b> <b>Factory</b> → <b>Pre-pick</b>, pagina ei proprie.</li>
<li><b>Linii mici care așteaptă un lot:</b> apasă <b>show</b> pe linia de sub panou.</li>
<li><b>Ce se termină oricum:</b> <b>Factory</b> → <b>To restock</b>, vezi <a href="/docs/keeping-the-factory-stocked-ro">Menținerea stocului fabricii</a>.</li>
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
<li>Mutarea cartelelor pe Board, crearea și lansarea ordinelor de lucru, picking-ul și trimiterea: poziția <b>Production floor supervisor</b> (supervizor de hală) pentru fabrică, sau supervizor de organizație. <b>Mix preparer</b> (preparatorul de amestecuri) poate face la fel doar pentru amestecuri.</li>
</ul>
</aside>
