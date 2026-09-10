---
title: Adunarea mărfii unui partener
summary: Ghidul depozitului - ce înseamnă pre-pick, de ce stocul din chesonul de adunare al unui partener nu mai contează ca disponibil, și cum se lucrează lista Pre-pick din Dispatching.
date: 2026-09-09
source_date: 2026-09-09
tags: dispatch, procurement, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 8
---

<aside class="tldr">
Pentru depozit. O parte din ce cere o organizație parteneră nu se face aici deloc - sticle, pungi, cutii, bețe. Nimic de produs: cineva trebuie doar să le ia de pe raft și să le pună în chesonul acelui partener. Acel drum se numește <b>pre-pick</b> (pre-preluare), chesonul e o <b>goods out gathering location</b> (locație de adunare pentru expediere), iar din momentul în care stocul e în el, nu mai contează ca disponibil pentru nimeni altcineva. Lista ta de drumuri de făcut e <b>Dispatching → Pre-pick</b>.
</aside>

## De ce există pre-pick

Cererea unei organizații partenere nu devine comandă imediat. Stă pe o listă până cineva de aici acționează asupra ei, și devine comandă, aviz de expediere și livrare de stoc abia când e trimisă la depozit.

Asta lasă un gol. Un partener cere azi 490 de seturi sticlă-capac, nu se vor expedia decât peste o săptămână, iar între timp nimic nu împiedică acele sticle să fie vândute sau folosite în altă parte. Nimic în aiku nu reține stocul de la sine - nici cererea, nici comanda, nici măcar avizul de expediere. Singurul lucru care rezervă cu adevărat stocul e mutarea lui undeva de unde nu poate fi luat.

Exact la asta ajută o **goods out gathering location**: o locație obișnuită din depozit, marcată ca punct de adunare pentru un partener. Ce e în ea e al lui.

## Ce înseamnă cei doi termeni

- **Pre-pick** - scoaterea mărfii de pe raft mai devreme și punerea ei în chesonul acelui partener, înainte ca comanda să plece undeva. În partea de fabrică mai înseamnă și "nu facem asta, o luăm din stoc".
- **Goods out gathering location** - o locație marcată astfel încât tot ce e în ea nu mai contează ca disponibil. Stocul rămâne al nostru, tot numărat, tot evaluat, tot auditat. Pur și simplu e rezervat.

## Lista drumurilor de făcut

**Warehouse → Dispatching → Pre-pick.**

Fiecare rând e un drum:

| Coloană | Ce îți spune |
| --- | --- |
| For | pentru ce organizație parteneră e marfa |
| SKO | codul și numele lucrului de adus |
| From | locația pe care o sugerează sistemul, cea cu cel mai mult stoc |
| To | chesonul de adunare al acelui partener |
| Staged | cât e deja în cheson |
| To move | cât mai trebuie dus |

Ia marfa, pune-o în cheson, apoi apasă **Moved** (mutat). Asta înregistrează mutarea în aiku, rândul dispare singur, iar cantitatea iese din disponibil.

Lista se recalculează de fiecare dată când o deschizi - nu e un set de sarcini de bifat sau de curățat de cineva. Dacă stocul e deja în cheson, rândul pur și simplu nu e acolo. Dacă cineva adaugă mai mult la cerere, rândul revine.

Tab-ul apare doar pentru organizațiile care au un cheson de adunare configurat pentru un partener. Dacă nu-l vezi, încă nu a fost făcut.

## Jumătatea fabricii din aceeași muncă

Drumurile vin de undeva: cineva de la fabrică trebuie să decidă că o linie e luată din stoc, nu făcută. Acea decizie are propria ei pagină, **Factory → Pre-pick**, și e geamăna listei de mai sus.

Listează fiecare linie deschisă de partener cu stoc în spate, indiferent dacă fabrica face sau nu acel artefact, și nu arată niciodată o linie deja pre-preluată. Fiecare rând poartă solicitantul, artefactul, ce s-a **cerut**, ce e **în stoc**, și ce **se poate prelua** - cele două plafonate una față de cealaltă, așa că o linie nu e niciodată promisă mai mult decât există. Categoria, solicitantul și urgența filtrează lista, iar numerele de pe filtre sunt numere reale, nu doar ce încape pe pagină.

**Pre-pick** pe un rând, **Pre-pick selected** pentru ce ai bifat, sau **Pre-pick all** pentru tot ce arată filtrele curente. Pre-preluarea promite stocul acelui partener și pune drumul pe lista depozitului; unde e disponibilă doar o parte din ce s-a cerut, linia se împarte, partea promisă pleacă, iar restul rămâne deschis. Nimic nu e vândut și nicio comandă nu e creată - stocul pur și simplu nu mai e disponibil pentru nimeni altcineva.

Numărul de lângă **Pre-pick** din bara laterală a fabricii arată câte linii așteaptă acea decizie, și se actualizează singur.

## Ce vede partenerul

Nimic de anunțat manual. Pe lista lor de cumpărături fiecare linie poartă stadiul până unde a ajuns: **Requested** (cerut), **Being made** (în lucru), **Pre-picked** (pre-preluat), **Staged for you** (pregătit pentru tine), **Being picked** (în preluare), **On its way** (pe drum) - cu referința ordinului de lucru, a comenzii sau a avizului de expediere alături.

**Staged for you** înseamnă exact ce ai făcut: marfa lor e în chesonul lor, așteptând următoarea expediere.

## Când chiar pleacă

Adunarea nu e expediere. Marfa pleacă atunci când cineva trimite comanda preluată la depozit, din pagina **To produce** (de produs), care transformă cererile strânse într-o comandă, un aviz de expediere și o livrare de stoc de partea partenerului. Vezi [Lucrul cu lista To produce](/docs/fulfilling-partner-orders-ro).

Pentru că marfa e deja într-un singur cheson, preluarea de la acel moment e un drum către o singură locație, nu un tur al depozitului.

## Lucruri bune de știut

- **Un cheson de adunare nu e depozitare.** Tot ce rămâne într-unul e invizibil pentru toți ceilalți - nu va fi oferit unui preluator și nu va apărea ca disponibil la vânzare. Pune stoc acolo doar când e pentru acel partener.
- **Marcarea unui cheson deja folosit schimbă numerele imediat.** Dacă o locație are deja stoc când e marcată ca punct de adunare, acel stoc iese din disponibil pe loc. Verifică ce e într-o locație înainte de a o marca.
- **Nimic altceva nu rezervă.** Doi oameni pot fi anunțați că aceleași unități sunt libere până când unul dintre ei le duce efectiv în cheson. Dacă ceva nu trebuie vândut de sub un partener, mută-l.
- **Dacă îl muți înapoi, îl eliberezi.** Scoate stocul din cheson, sau șterge marcajul de adunare de pe locație, și cantitatea revine în disponibil.
- **Un cheson per partener** e aranjamentul obișnuit, numit după partener ca un preluator să-l recunoască dintr-o privire.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Decide că o linie e luată din stoc:</b> organizația ta → <b>Factory</b> → <b>Pre-pick</b> → <b>Pre-pick</b> pe rând, sau bifează și folosește <b>Pre-pick selected</b> / <b>Pre-pick all</b>.</li>
<li><b>Lista drumurilor:</b> organizația ta → <b>Warehouse</b> → <b>Dispatching</b> → tab-ul <b>Pre-pick</b>.</li>
<li><b>Înregistrează un drum:</b> apasă <b>Moved</b> pe rând după ce marfa e fizic în cheson.</li>
<li><b>Verifică ce e într-un cheson:</b> <b>Warehouse</b> → <b>Locations</b> → locația → tab-ul <b>SKOs</b>.</li>
<li><b>Marchează o locație ca punct de adunare:</b> <b>Warehouse</b> → <b>Locations</b> → locația → <b>Overview</b> → <b>Goods out gathering</b>.</li>
<li><b>Îndrumă un partener spre chesonul lui:</b> nu e un ecran - cere unui administrator, e setat din consolă intenționat ca să nu se schimbe din greșeală.</li>
<li><b>Trimite marfa adunată:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li>Pozițiile se setează pe fișa angajatului sub Human Resources și aduc cu ele drepturile.</li>
<li>Vizualizarea listei și înregistrarea unei mutări: o poziție de dispatching pentru depozit, sau supervizor de organizație.</li>
<li>Marcarea unei locații ca punct de adunare: o poziție de depozit care poate edita locații.</li>
<li>Îndrumarea unui partener spre un cheson: administrator, din consolă.</li>
</ul>
</aside>
