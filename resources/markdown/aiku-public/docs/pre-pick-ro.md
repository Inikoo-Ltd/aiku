---
title: Pre-pick
summary: Ghidul fabricii pentru Factory → Pre-pick - ce linii de la parteneri ajung acolo, ce înseamnă fiecare status și ce faci când stocul e complet, când e doar o parte, când mai vine marfă și când nu va mai veni deloc.
date: 2026-11-25
source_date: 2026-11-25
tags: production, procurement, intercompany, warehouse
category: production
series: Ordering from partners
order: 8
---

<aside class="tldr">
Liniile care trebuie produse merg la <b>To produce</b> (de produs); liniile pe care le aveți deja pe raft ajung la <b>Pre-pick</b>. Pentru fiecare linie din Pre-pick:
<ul>
<li><b>Aveți tot stocul:</b> nu faceți nimic. aiku o trimite singur la depozit în zece minute. Dacă stocul o acoperă exact, fără nimic în plus, ea așteaptă: apăsați <b>Pre-pick</b>.</li>
<li><b>Aveți o parte, iar de la un furnizor mai vine marfă:</b> așteptați. Imediat ce livrarea este înregistrată în stoc, aiku trimite singur toată linia. Sau apăsați <b>Pre-pick</b> ca să trimiteți acum ce aveți; restul merge în <b>To produce</b>.</li>
<li><b>Aveți o parte, iar produsul este unul pe care îl faceți voi:</b> așteptarea nu ajută, pentru că nimeni nu e rugat să facă restul. Apăsați <b>Pre-pick</b> ca să trimiteți ce aveți; restul merge în <b>To produce</b>, ca să fie produs.</li>
<li><b>Nu va mai veni (scos din gamă, indisponibil):</b> apăsați <b>Close short</b> (închide cu cantitate mai mică) ca să trimiteți ce e și să anulați restul, sau <b>Can't be done</b> ca să nu trimiteți nimic; partenerul este anunțat. Dacă nu aveți nimic în stoc, linia e în <b>To produce</b>: vedeți Cazul 3 mai jos.</li>
</ul>
</aside>

<figure><img src="/art/docs/draw-pre-pick-fork.svg" alt="Schiță în acuarelă: o linie din lista de cumpărături a unui partener ajunge la un indicator care arată spre To produce, un banc de lucru unde se produc lucruri, sau spre Pre-pick, un raft cu stoc. De pe raft, stocul complet pleacă singur în chesonul partenerului în zece minute, stocul parțial merge în cheson în timp ce restul așteaptă un camion cu livrare, iar stocul care nu va mai veni primește o ștampilă roșie Close short, cu o notă care zboară înapoi la partener." width="1200" height="750" loading="eager"><figcaption>Pe raft: Pre-pick. Nu e pe raft: To produce.</figcaption></figure>

## De ce o linie ajunge aici și nu în To produce

Comanda unui partener sosește ca linii pe o listă de cumpărături. Pentru fiecare linie aiku se uită la **stocul liber**: ce e pe raft minus ce e deja promis altor linii de la parteneri.

- **Stocul liber nu acoperă nimic din ea** - linia merge în **To produce**, ca să fie produsă. Vezi [Lucrul cu lista To produce](/docs/fulfilling-partner-orders-ro).
- **Stocul liber acoperă o parte sau tot** - linia vine în **Factory → Pre-pick**.

Așadar aceeași comandă poate fi împărțită între cele două pagini: ceaiul pe care îl amestecați merge în To produce, iar sticlele, pungile și cutiile pe care le aveți deja vin aici. O linie al cărei stoc se termină mai târziu trece singură în To produce.

Liniile sunt servite la coadă, pe produs: întâi **urgent**, apoi **high**, **normal** și **low**; la aceeași prioritate, cea necesară mai devreme, apoi cea mai veche. O linie numără doar stocul rămas după toate liniile din fața ei.

## Cum citești lista

| Coloană | Ce îți spune |
| --- | --- |
| For | ce organizație parteneră a cerut |
| Artefact | codul și numele produsului |
| To | chesonul de adunare al acelui partener, unde va ajunge marfa |
| Asked | cât a cerut partenerul |
| In stock | ce e acum pe rafturile voastre |
| Can pick | cât poți da acestei linii acum, după liniile din fața ei la coadă |
| Shortfall | cât lipsește ca să fie acoperită toată linia |
| Status | ce are de gând aiku să facă cu ea, vezi mai jos |
| Priority, Added | locul liniei la coadă |

Categoria, solicitantul și urgența filtrează lista; numerele de pe filtre sunt numere reale.

## Ce înseamnă statusurile

| Status | Ce înseamnă | Ce faci |
| --- | --- | --- |
| **Going to the warehouse** (merge la depozit) | stocul acoperă toată linia, și mai rămâne ceva | nimic - în zece minute aiku o pre-preia singur și dispare din listă |
| **Held, one more needed on the shelf** (ținută, mai trebuie una pe raft) | stocul acoperă linia exact, fără nimic în plus | nimic, sau apasă **Pre-pick** dacă trebuie să plece acum; aiku trimite singur o linie doar când cel puțin o unitate rămâne pe raft |
| **Awaiting full stock, production manager to decide** (așteaptă stocul complet, decide șeful de producție) | doar o parte din linie e în stoc | decizi tu, vezi cazurile de mai jos |
| **In To produce** | există stoc, dar liniile din fața acesteia l-au luat | nimic aici; linia așteaptă pe pagina To produce |

Pre-preluarea nu e expediere. Ea promite stocul acelui partener și pune un drum pe lista depozitului: ei îl iau de pe raft și îl pun în chesonul partenerului. Marfa pleacă la următoarea expediere a partenerului, după un program sau când cineva apasă **Send to warehouse** (trimite la depozit). Vezi [Adunarea mărfii unui partener](/docs/gathering-a-partners-goods-ro).

## Cazul 1: ai tot stocul

Nu faci nimic. Linia arată **Going to the warehouse**, iar aiku o pre-preia în zece minute. Dacă arată **Held, one more needed on the shelf** și nu trebuie să aștepte, apasă **Pre-pick** pe rând.

Ca să trimiți mai multe linii deodată, bifează-le și apasă **Pre-pick selected**, sau **Pre-pick all** pentru tot ce arată filtrele curente.

## Cazul 2: ai o parte din stoc, sau mai vine marfă

Linia arată **Awaiting full stock, production manager to decide**. Ai trei variante:

- **Aștepți restul.** Nu faci nimic. Când marfa comandată de la un furnizor sosește și e băgată în stoc, stocul liber crește; imediat ce acoperă toată linia, cu cel puțin una rămasă pe raft, aiku o pre-preia singur. Alege asta când livrarea e aproape și partenerul preferă să primească totul odată. Așteptarea merge doar când stocul chiar vine: la un produs pe care îl faceți voi, nimeni nu e rugat să facă restul cât timp linia așteaptă aici, deci ar aștepta la nesfârșit.
- **Trimiți acum ce ai, restul mai târziu.** Apasă **Pre-pick** (cantitatea apare ca *partial pick*, pre-preluare parțială). Partea din stoc merge în cheson; restul rămâne pe lista partenerului ca linie separată, încă deschisă, și trece în **To produce**. Un produs pe care îl faceți voi apare acolo pe tablă, ca să fie produs; orice altceva apare în tab-ul **All** și revine aici când sosește stoc nou.
- **Trimiți ce ai și te oprești aici.** Apasă **Close short**. aiku îți cere confirmarea, trimite ce e în stoc și anulează restul. Lista partenerului scade la cantitatea trimisă, iar cel care a adăugat linia și echipa de achiziții a partenerului sunt anunțați că a fost închisă mai scurt, ca să poată cumpăra în altă parte.

## Cazul 3: stocul nu va mai veni

Produsul e scos din gamă, furnizorul nu-l mai are sau nu va fi disponibil mult timp.

- **O parte e în stoc** - apasă **Close short**: partenerul primește ce există și e anunțat că restul nu va veni. Dacă nu poți trimite nimic nici așa, apasă în schimb **Can't be done** pe rând și scrie motivul.
- **Nimic în stoc, iar voi îl produceți** - linia e un card pe tabla **To produce**. Trage-l din **Backlog** (în **Preparing**, sau în **Producing** dacă fabrica voastră folosește tabla simplă) și, în fereastra care se deschide, apasă **Can't be done** (nu se poate face) și scrie motivul. Linia iese de pe tablă, iar cel care a adăugat-o și echipa de achiziții a partenerului sunt anunțați, cu motivul tău, ca să cumpere din altă parte.
- **Nimic în stoc, iar voi nu îl produceți** - linia e în **To produce → All**. Apasă **Can't be done** pe rândul ei și scrie motivul; sunt anunțate aceleași persoane.

Nu lăsa asemenea linii să aștepte fără nicio veste: partenerul le vede ca **Requested** pe lista lui și continuă să conteze pe ele.

## Ce vede partenerul

Pe lista lui de cumpărături fiecare linie arată până unde a ajuns: **Requested**, **Being made**, **Pre-picked**, **Staged for you**, **Being picked**, **On its way**.

Când o linie e închisă mai scurt sau nu se poate face, oamenii de partea partenerului sunt anunțați, cu motivul.

## Lucruri bune de știut

- **Nimic nu rezervă stoc până nu e pre-preluat.** O linie care spune *Awaiting full stock* nu a pus nimic deoparte: stocul ei poate fi încă vândut sau dat altei linii. Dacă nu trebuie luat, pre-preia ce e acolo.
- **Dacă raftul se dovedește mai mic** - un audit găsește mai puțin, sau s-a vândut în altă parte - aiku retrage promisiunea începând cu cele mai noi pre-preluări, iar liniile acelea se întorc în listă sau în To produce.
- **Lista se calculează din nou de fiecare dată când o deschizi.** Liniile pleacă singure odată pre-preluate; nu ai nimic de curățat.
- **Numărul de lângă Pre-pick** din bara laterală a fabricii arată câte linii așteaptă, și se actualizează singur.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Lista:</b> organizația ta → <b>Factory</b> → <b>Pre-pick</b>.</li>
<li><b>Trimite o linie acum:</b> <b>Pre-pick</b> pe rând, sau bifează rânduri și apasă <b>Pre-pick selected</b>, sau <b>Pre-pick all</b>.</li>
<li><b>Trimite ce există și anulează restul:</b> <b>Close short</b> pe rând.</li>
<li><b>O linie care nu se poate face:</b> <b>Can't be done</b> pe rândul ei în <b>Pre-pick</b> sau în <b>Factory</b> → <b>To produce</b> → <b>All</b>, sau trage cardul ei din <b>Backlog</b> pe tablă.</li>
<li><b>Drumurile către cheson:</b> <b>Warehouse</b> → <b>Dispatching</b> → tab-ul <b>Pre-pick</b>, vezi <a href="/docs/gathering-a-partners-goods-ro">Adunarea mărfii unui partener</a>.</li>
<li><b>Trimite marfa adunată:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>, sau lasă programul de expediere al partenerului să o facă.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li>Pozițiile se setează pe fișa angajatului sub Human Resources și aduc cu ele drepturile.</li>
<li>Vizualizarea listei: orice poziție de fabrică care poate vedea producția, sau supervizor de organizație.</li>
<li>Pre-preluarea și închiderea cu cantitate mai mică: o poziție de fabrică care planifică sau pregătește producția, un editor de achiziții, sau supervizor de organizație.</li>
<li><b>Can't be done</b>: o poziție de fabrică care planifică sau pregătește producția, sau supervizor de organizație. Vezi <a href="/docs/factory-positions-ro">Factory positions</a>.</li>
</ul>
</aside>
