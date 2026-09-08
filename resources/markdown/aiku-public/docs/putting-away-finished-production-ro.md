---
title: Punerea la loc a producției terminate
summary: Ghidul depozitului - unde apar ordinele de lucru terminate, cum stabilește aiku dacă merg în chesonul unui partener sau în stocul obișnuit, și cum le înregistrezi cu un singur cod.
date: 2026-09-08
source_date: 2026-09-08
tags: dispatch, production, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 10
---

<aside class="tldr">
Pentru depozit. Când meșteșugarii termină un ordin de lucru, acesta nu devine stoc de la sine - cineva trebuie să-l care undeva și să spună unde. Acea listă e <b>Dispatching → From production</b> (din producție). Fiecare rând spune pentru cine e marfa și sugerează locația: chesonul de adunare al unui partener dacă tot ordinul e pentru un singur partener, altfel o locație de stoc pe care o scrii tu. Apasă <b>Put away</b> (pune la loc) și marfa e înregistrată în acea locație cu un cod de lot.
</aside>

## De unde vin rândurile

Un ordin de lucru apare aici în momentul în care fiecare sarcină din el e marcată DONE pe ecranul de fabrică, și rămâne până e pus la loc. Nimeni nu trebuie să ți-l trimită.

Ordinele de lucru încă în lucru nu sunt aici. Dacă vrei să vezi ce urmează, panoul <b>To produce</b> (de produs) al fabricii are o coloană <b>Done</b> cu aceleași ordine de lucru - vezi [Lucrul cu lista To produce](/docs/fulfilling-partner-orders-ro).

## Citirea unui rând

| Coloană | Ce îți spune |
| --- | --- |
| Job order | referința, JOxxx-0001 |
| Artisan | cine a făcut |
| Made | câte din ce - 20 × SKO-01, un rând per produs |
| For | codul organizației partenere, sau *Stock* |
| To location | locația unde ar trebui dus |

<b>For</b> se stabilește din cererile din spatele ordinului de lucru. Dacă toate liniile au fost cerute de aceeași organizație parteneră, marfa e a ei și rândul o arată, cu chesonul de adunare al acelui partener deja completat sub <b>To location</b>. E același cheson folosit de [lista de pre-preluare](/docs/gathering-a-partners-goods-ro): tot ce e în el e rezervat și nu mai contează ca disponibil pentru nimeni altcineva.

Dacă ordinul de lucru a fost făcut pentru stoc, pentru un client propriu, sau pentru mai mult de un partener, rândul spune *Stock* și căsuța de locație e goală. Scrie codul locației unde o pui.

## Punerea la loc

1. Cară marfa la locația arătată, sau la cea pe care ai ales-o tu.
2. Verifică codul din <b>To location</b>. Schimbă-l dacă ai pus marfa în altă parte.
3. Apasă <b>Put away</b>.

Asta înregistrează marfa în locație, îi dă un cod de lot făcut din referința ordinului de lucru și codul produsului, scade materiile prime pe care rețeta spune că le-a folosit, și marchează ordinul de lucru ca primit. Rândul dispare, iar pe panoul fabricii linia iese din coloana <b>Done</b>.

Cantitatea înregistrată e ce au făcut efectiv meșteșugarii, nu ce s-a cerut. Un ordin de lucru care a cerut 25 și a primit 19 înregistrează 19.

## Ce urmează

- **Marfa partenerului** stă în chesonul lui până cineva de pe pagina <b>To produce</b> trimite comanda preluată la depozit. Preluarea devine atunci un drum către un singur cheson. Partenerul vede linia ca *Staged for you* (pregătit pentru tine) pe lista lui de cumpărături.
- **Marfa clienților proprii** intră în stocul obișnuit și avizul de expediere care aștepta e eliberat pentru preluare, pentru că lipsa care îl ținea în loc a dispărut.
- **Stock** (stoc) devine pur și simplu disponibil.

## Lucruri bune de știut

- **Tab-ul apare doar** pentru organizațiile care au o fabrică.
- **Un ordin de lucru, o locație.** Dacă un ordin de lucru chiar trebuie împărțit între două locuri, pune-l la loc în stoc și lasă lista de pre-preluare să mute partea partenerului.
- **O locație greșită se repară ca orice altă eroare de stoc** - muți stocul între locații. Ordinul de lucru în sine nu se redeschide.
- **Nimic aici nu e rezervat până nu e într-un cheson.** Marfa pusă în stocul obișnuit poate fi preluată pentru oricine.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Lista:</b> organizația ta → <b>Warehouse</b> → <b>Dispatching</b> → tab-ul <b>From production</b>.</li>
<li><b>Înregistreaz-o:</b> verifică <b>To location</b> → <b>Put away</b>.</li>
<li><b>Vezi ce e într-un cheson:</b> <b>Warehouse</b> → <b>Locations</b> → locația → tab-ul <b>SKOs</b>.</li>
<li><b>Ce mai datorează fabrica:</b> <b>Factory</b> → <b>To produce</b> → <b>Board</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li>Pozițiile se setează pe fișa angajatului sub Human Resources și aduc cu ele drepturile.</li>
<li>Vizualizarea listei și punerea la loc: o poziție de dispatching pentru depozit, sau supervizor de organizație.</li>
</ul>
</aside>
