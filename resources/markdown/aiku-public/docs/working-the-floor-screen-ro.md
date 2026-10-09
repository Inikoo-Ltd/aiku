---
title: Lucrul cu ecranul de fabrică
summary: Ghidul meșteșugarului - ecranul Sarcinile mele, lista de sarcini care se actualizează singură, START și DONE, și ce apeși dacă ai făcut mai puțin decât ți s-a cerut.
date: 2026-10-09
source_date: 2026-10-09
tags: production, floor, artisan
category: production
series: Ordering from partners
order: 9
---

<aside class="tldr">
Pentru cei care fac lucrurile. <b>Factory → Jobs</b> (Fabrică → Sarcini) e toată ziua ta pe un singur ecran: sarcinile adresate ție în stânga, cea la care lucrezi în mijloc. Apasă <b>START</b>, fă-o, scrie câte ai făcut, apasă <b>DONE</b> (gata). Dacă ai făcut mai puțin decât ți s-a cerut, ecranul îți pune o singură întrebare: închizi sarcina aici, sau duci restul mai departe într-o sarcină nouă. Nimic altceva de completat.
</aside>

## Ecranul

Pagina e organizată ca o căsuță de mesaje.

**Stânga, mereu vizibilă:** lista. Doi indicatori sus - unități făcute azi și sarcini terminate azi. Sub ei **Your jobs** (sarcinile tale), munca adresată ție. Dacă poziția ta îți permite să alegi din grupul deschis tuturor, urmează o secțiune **Open jobs** (sarcini deschise). Jos de tot, **Finished today** (terminate azi), ce ai închis deja.

**Dreapta:** ce ai selectat. Înainte să începi, sunt detaliile sarcinii cu un buton mare <b>START</b>. Odată începută, e cardul de lucru cu ceasul.

Lista nu trebuie reîmprospătată. Când un planificator îți repartizează ceva, când un coleg începe una dintre sarcinile deschise, când tu închizi una, lista se schimbă singură, pe orice ecran care arată acea fabrică.

## Un rând din listă

Fiecare rând e o sarcină dintr-un ordin de lucru:

| Rând | Ce înseamnă |
| --- | --- |
| Cod | produsul de făcut - SKO-01 |
| Nume | numele lui |
| Task · job order | pasul (Production, Labelling...) și referința ordinului de lucru |
| 0/25 | făcut până acum / cerut |

Un rând cu ▶ verde și o oră e cel la care lucrezi, și ora la care l-ai început. O notă chihlimbarie sub un rând înseamnă că așteaptă un mix, sau că altcineva l-a deschis deja.

## Cum faci o sarcină

1. Apasă pe rând. Detaliile apar în dreapta.
2. Apasă <b>START</b>. Ceasul pornește și rândul primește marcajul verde.
3. Fă-o.
4. Scrie numărul făcut în <b>Quantity made</b> (cantitate făcută). Dacă ești maistru sau mai sus, mai există și o căsuță <b>Rejected</b> (respins) pentru piesele care nu au trecut.
5. Apasă <b>DONE</b>.

Ai făcut tot ce ți s-a cerut? Gata. Sarcina se închide, ordinul de lucru e terminat și depozitul e anunțat că are ceva de pus la locul lui - vezi [Punerea la loc a producției terminate](/docs/putting-away-finished-production-ro).

## Când ai făcut mai puțin decât ți s-a cerut

Scrie numărul real și apasă <b>DONE</b>. Rândul de completat e înlocuit cu un panou scurt: *19 făcute · 6 de făcut* și trei butoane.

- <b>Continue later</b> (continuă mai târziu) - sarcina se închide la 19, și un nou ordin de lucru pentru cele 6 apare în lista ta, adresat ție. Îl preiei mâine, sau oricând ajunge materialul.
- <b>Job finished</b> (sarcină terminată) - sarcina se închide la 19 și asta e tot. Planificatorul vede un ordin de lucru care a cerut 25 și a primit 19.
- <b>Back</b> (înapoi) - schimbă numărul.

Oricum ar fi, sarcina la care lucrai se închide și e plătită la ce ai făcut. Nimic nu rămâne pe jumătate deschis în lista ta.

## Când ai făcut mai mult decât ți s-a cerut

Scrie numărul real și apasă **DONE**. Ecranul avertizează *18 above target, a manager must authorise it* (18 peste țintă, un manager trebuie să aprobe) și deschide **Manager authorisation required for overproduction**.

- **Scan the badge** - un manager sau supervizor ține codul QR personal (același cu care se pontează) în fața camerei. Este acceptat imediat ce e citit. **Switch camera** comută între camera din față și cea din spate.
- **Use manager PIN** - dacă camera nu îl citește, managerul își tastează în schimb PIN-ul de pontaj.

Doar cineva care conduce acest atelier de fabrică poate aproba, și niciodată pentru propria muncă. După cinci coduri sau PIN-uri greșite, fereastra refuză timp de 15 minute.

Odată aprobată, sarcina crește la cât s-a făcut de fapt: depozitul pune totul la loc, ce nu a cerut nicio comandă intră în stoc, iar materiile prime se scad pentru tot lotul. Pașii următori ai aceleiași sarcini cresc și ei, ca următorul om să poată termina tot. Pagina comenzii de lucru arată cine a făcut surplusul, cine l-a aprobat, cum și când.

## Un singur lot pentru mai multe linii

Uneori un lot servește mai multe sarcini. De exemplu, 100 de batoane HCS-48 și 100 de batoane feliate SLHCS-48 cu același parfum se amestecă, se toarnă și se modelează împreună, ca 200 de batoane.

**Combinarea (managerii).** Apasă pe una dintre linii pe ecranul de fabrică. Sub detaliile ei, **Combine Production with other lines into one batch** listează celelalte linii deschise care așteaptă același pas. Bifează-le pe cele care intră în același lot și apasă **Combine**. O linie deja combinată, terminată sau la care se lucrează nu este oferită.

**Lucrul la el (meșteșugarii).** Liniile apar acum ca un singur rând cu 🔗 și codurile împreună, *HCS-48 + SLHCS-48*, și totalul, *0/200*. Detaliile listează fiecare linie cu comanda ei de lucru. Apasă **START** o dată, fă lotul, scrie totalul făcut - 200 - și apasă **DONE** o dată.

**Ce face Aiku cu el.** Totalul se împarte între linii după cât mai avea fiecare de făcut, în unități întregi: 100 la HCS-48, 100 la SLHCS-48. Timpul se împarte la fel. Fiecare linie trece apoi la propriii pași următori - ambalare în folie pentru una, feliere și apoi ambalare pentru cealaltă. Pagina comenzii de lucru arată pasul ca *One batch with* (un lot cu) cealaltă linie.

**Plata și țintele** se calculează pe lot ca întreg: 200 de batoane în orele cât a durat, față de ținta pasului. Combinarea plătește exact cât ar plăti loturi separate la aceeași viteză. Când liniile au ținte diferite pentru pas, ținta lotului este cea care ia aceleași ore ca loturile separate.

Dacă faci mai mult decât au cerut toate liniile la un loc, e nevoie de codul sau PIN-ul unui manager, ca mai sus; surplusul se împarte și el între linii. Un manager poate **Separate** (separa) liniile din nou oricând nu lucrează nimeni la ele. Ce s-a făcut deja împreună rămâne la fiecare linie.

## Lucruri bune de știut

- **O sarcină pe rând.** Cât timp ai o sarcină deschisă, butoanele START sunt dezactivate. Închide-o mai întâi.
- **Plata ta pentru sarcină** urmează numărul din Quantity made, așa cum e explicat în [Poziții de fabrică](/docs/factory-positions-ro). Piesele respinse nu se plătesc.
- **Open jobs** (sarcini deschise) sunt sarcini fără meșteșugar numit, sau cu altcineva numit. E în regulă să o iei, dacă poziția ta permite; devine a ta până se închide.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Ecranul tău:</b> organizația ta → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Începe:</b> apasă pe rând → <b>START</b>.</li>
<li><b>Termină:</b> scrie <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Mai puțin decât cerut:</b> <b>DONE</b> → <b>Continue later</b> sau <b>Job finished</b>.</li>
<li><b>Un lot pentru mai multe linii:</b> apasă pe o linie → <b>Combine … with other lines into one batch</b> → bifează liniile → <b>Combine</b>. Pentru a anula: apasă pe rândul combinat → <b>Separate</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li>Pozițiile se setează pe fișa angajatului sub Human Resources și aduc cu ele drepturile.</li>
<li>Vizualizarea sarcinilor proprii, START și DONE: poziția <b>Operative</b> pentru fabrică.</li>
<li>Vizualizarea și preluarea <b>Open jobs</b>, înregistrarea respingerilor: <b>Foreman</b>, <b>Mix preparer</b> sau <b>Floor supervisor</b>.</li>
<li>Combinarea și separarea liniilor: managerii care conduc atelierul de fabrică, aceiași oameni care pot aproba supraproducția.</li>
</ul>
</aside>
