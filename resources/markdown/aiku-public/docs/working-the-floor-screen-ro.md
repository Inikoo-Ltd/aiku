---
title: Lucrul cu ecranul de fabrică
summary: Ghidul meșteșugarului - ecranul Sarcinile mele, lista de sarcini care se actualizează singură, START și DONE, ce apeși dacă ai făcut mai puțin decât ți s-a cerut, și cum iei o pauză.
date: 2026-10-23
source_date: 2026-10-23
tags: production, floor, artisan
category: production
series: Ordering from partners
order: 9
---

<aside class="tldr">
Pentru cei care fac lucrurile. <b>Factory → Jobs</b> (Fabrică → Sarcini) e toată ziua ta pe un singur ecran: sarcinile adresate ție în stânga, cea la care lucrezi în mijloc. Apasă <b>START</b>, fă-o, scrie câte ai făcut, apasă <b>DONE</b> (gata). Dacă ai făcut mai puțin decât ți s-a cerut, ecranul îți pune o singură întrebare: faci pauză și continui mai târziu, închizi sarcina și muți restul într-una nouă, sau o termini aici. Nimic altceva de completat.
</aside>

## Ecranul

Pagina e organizată ca o căsuță de mesaje.

**Stânga, mereu vizibilă:** lista. Doi indicatori sus - unități făcute azi și sarcini terminate azi. Sub ei **Your jobs** (sarcinile tale), munca adresată ție: sarcini întregi cu numele tău pe ele și pași singuri pe care ți i-a dat supervizorul în planul zilei (vezi [Planificarea zilei de producție](/docs/planning-the-production-day-ro)). O sarcină poate avea fabricarea la o persoană și ambalarea la alta; fiecare vede doar pasul ei. Dacă poziția ta îți permite să alegi din grupul deschis tuturor, urmează o secțiune **Open jobs** (sarcini deschise). Jos de tot, **Finished today** (terminate azi), ce ai închis deja.

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

## Pași care se numără în pachete

Unii pași, mai ales ambalarea, se numără în pachete și nu în bucăți: o tavă cu 8 bile de baie se numără ca 1. Ecranul arată atunci <b>Counted in PACKS of 8</b> (se numără în PACHETE de 8) în chihlimbar, iar căsuța se numește <b>Packs of 8 made</b> (pachete de 8 făcute). Scrie câte pachete pline ai umplut: pentru 56 de bile de baie în 7 tăvi scrie 7, nu 56. Sub căsuță ecranul arată ce înseamnă numărul tău, <i>7 packs of 8 = 56 × ABB-06a</i>, iar confirmarea îl arată din nou. Se acceptă doar pachete întregi; singura excepție este numărul exact rămas de făcut, pe care îl completezi atingând numărul mare. Când un manager trebuie să autorizeze surplusul, fereastra arată surplusul în pachete și în bucăți înainte de aprobare.

## Când ai făcut mai puțin decât ți s-a cerut

Scrie numărul real și apasă <b>DONE</b>. Rândul de completat e înlocuit cu un panou scurt: *19 făcute · 6 de făcut* și patru butoane.

- <b>Pause</b> (pauză) - același mix, continui mai târziu. Sarcina rămâne deschisă, cu aceeași referință și același cod de lot, iar numărătoarea continuă de la 19. Folosește-l când se termină ziua înainte de mix.
- <b>Close at 19 and move remaining 6 to new job …</b> (închide la 19 și mută restul de 6 într-o sarcină nouă) - sarcina se închide la 19, iar cele 6 trec într-un nou ordin de lucru, cu litera următoare (0127 devine 0127a) și propriul cod de lot. Folosește-l când restul va fi un mix nou. Ecranul îți cere să confirmi cu <b>Yes, close and move</b> (da, închide și mută), pentru că o sarcină închisă nu mai poate fi continuată.
- <b>Job finished</b> (sarcină terminată) - sarcina se închide la 19 și asta e tot. Planificatorul vede un ordin de lucru care a cerut 25 și a primit 19.
- <b>Back</b> (înapoi) - schimbă numărul.

O sarcină mutată într-una nouă arată **Continued from 0127** (continuată din 0127) pe cardul ei, iar cea din care provine arată **Continued in 0127a** (continuată în 0127a). Sarcina nouă rămâne cu aceiași oameni și în același plan al zilei.

## Când ai făcut mai mult decât ți s-a cerut

Scrie numărul real și apasă **DONE**. Ecranul avertizează *18 above target, a manager must authorise it* (18 peste țintă, un manager trebuie să aprobe) și deschide **Manager authorisation required for overproduction**.

- **Scan the badge** - un manager sau supervizor ține codul QR personal (același cu care se pontează) în fața camerei. Este acceptat imediat ce e citit. **Switch camera** comută între camera din față și cea din spate.
- **Use manager PIN** - dacă camera nu îl citește, managerul își tastează în schimb PIN-ul de pontaj.

Doar cineva care conduce acest atelier de fabrică poate aproba, și niciodată pentru propria muncă. După cinci coduri sau PIN-uri greșite, fereastra refuză timp de 15 minute.

Odată aprobată, sarcina crește la cât s-a făcut de fapt: depozitul pune totul la loc, ce nu a cerut nicio comandă intră în stoc, iar materiile prime se scad pentru tot lotul. Pașii următori ai aceleiași sarcini cresc și ei, ca următorul om să poată termina tot. Pagina comenzii de lucru arată cine a făcut surplusul, cine l-a aprobat, cum și când.

Excepția e o lucrare mutată pe altă zi. Acolo niciun manager nu poate aproba ca un pas să depășească ce a făcut pasul dinainte pe acea parte: dacă Making a terminat 138 acolo, Labelling se oprește la 138, iar ecranul arată cealaltă parte a lucrării (de exemplu JOaroma-0152a), unde s-a făcut restul și unde trebuie trecut.

Ai scris un număr greșit, de exemplu bucăți acolo unde se cereau pachete? Un manager deschide fila <b>Performance</b> (performanță), găsește înregistrarea sub lucrător și apasă <b>Correct quantity</b> (corectează cantitatea). Înregistrarea poate fi doar micșorată. Sarcina revine la ce s-a făcut cu adevărat, surplusul autorizat se reduce odată cu ea, iar plata pentru acea înregistrare se calculează din nou. Funcționează până când depozitul începe să pună sarcina la raft.

## Un singur lot pentru mai multe linii

Uneori un lot servește mai multe sarcini. De exemplu, 100 de batoane HCS-48 și 100 de batoane feliate SLHCS-48 cu același parfum se amestecă, se toarnă și se modelează împreună, ca 200 de batoane.

**Combinarea (managerii).** Apasă pe una dintre linii pe ecranul de fabrică. Sub detaliile ei, **Combine Production with other lines into one batch** listează celelalte linii deschise care așteaptă același pas. Bifează-le pe cele care intră în același lot și apasă **Combine**. O linie deja combinată, terminată sau la care se lucrează nu este oferită.

**Lucrul la el (meșteșugarii).** Liniile apar acum ca un singur rând cu 🔗 și codurile împreună, *HCS-48 + SLHCS-48*, și totalul, *0/200*. Detaliile listează fiecare linie cu comanda ei de lucru. Apasă **START** o dată, fă lotul, scrie totalul făcut - 200 - și apasă **DONE** o dată.

**Ce face Aiku cu el.** Totalul se împarte între linii după cât mai avea fiecare de făcut, în unități întregi: 100 la HCS-48, 100 la SLHCS-48. Timpul se împarte la fel. Fiecare linie trece apoi la propriii pași următori - ambalare în folie pentru una, feliere și apoi ambalare pentru cealaltă. Pagina comenzii de lucru arată pasul ca *One batch with* (un lot cu) cealaltă linie.

**Plata și țintele** se calculează pe lot ca întreg: 200 de batoane în orele cât a durat, față de ținta pasului. Combinarea plătește exact cât ar plăti loturi separate la aceeași viteză. Când liniile au ținte diferite pentru pas, ținta lotului este cea care ia aceleași ore ca loturile separate.

Dacă faci mai mult decât au cerut toate liniile la un loc, e nevoie de codul sau PIN-ul unui manager, ca mai sus; surplusul se împarte și el între linii. Un manager poate **Separate** (separa) liniile din nou oricând nu lucrează nimeni la ele. Ce s-a făcut deja împreună rămâne la fiecare linie.

## Amestecarea și alți pași cântăriți în kg

Amestecarea este un pas separat la începutul rețetei și se numără în kg, nu în bucăți. Ecranul arată **kg** lângă numere, iar căsuța se numește **Kg made** (kg făcute): pentru un amestec de 50 kg scrie 50. Sub căsuță ecranul arată ce iese din el, *50 kg of mix = 500 × ABB-06a*. Un amestec care iese puțin peste ce a cerut sarcina este acceptat așa cum este, fără manager.

Doar meșteșugarii numiți la acest pas îl văd și îl pot începe; nu apare în lista nimănui altcuiva. Se plătește la oră, la tariful de bază: fără țintă, fără treaptă de plată și fără notă de țintă neatinsă, iar timpul se numără în continuare în costul produsului.

Pasul următor rămâne blocat cu **Waiting for** (se așteaptă) și numele pasului de amestecare până când se înregistrează ceva amestec, și se deschide imediat ce se introduce primul. Un singur amestec pentru mai multe linii funcționează ca în secțiunea de mai sus: un manager combină liniile de amestecare, cel care amestecă scrie kg o singură dată și fiecare linie își primește partea.

**Amestecuri în șarje întregi.** Când produsul are o mărime de șarjă (25 kg sau 50 kg), kg cerute se rotunjesc în sus la șarje întregi și se arată ca șarje: 94,1 kg în șarje de 50 kg apare *2 × 50 kg batches = 100 kg* (2 șarje de 50 kg = 100 kg). Căsuța se numește **Batches of 50 kg made** (șarje de 50 kg făcute) și se scrie numărul de șarje, 2, nu kg. La un amestec combinat se rotunjește totalul tuturor liniilor. Surplusul față de comandă nu schimbă lucrarea, cantitățile de formare sau plata. Managerii o setează la pasul din rețeta produsului, la **Mix batch size** (mărimea șarjei).

**Configurare (manageri).** Pe pagina sarcinii de fabricație, **Only these artisans can do it** (doar acești meșteșugari o pot face) arată cine o poate face; acolo se adaugă sau se scot oameni. Formularul de editare are **Paid by piece rate** (plată la bucată; oprit pentru munca la oră) și **Recorded in kg** (înregistrat în kg). Câte kg de amestec intră într-o bucată se trec la pasul din rețeta fiecărui produs.

## Pauza

Sub zona de lucru e un rând **Break:** (pauză) cu **5m**, **20m** și **30m**. Apasă durata pe care o iei și confirmă cu **Yes, start break** (da, începe pauza).

- Dacă ești pontat la intrare, pauza te pontează la ieșire.
- Dacă ai o sarcină deschisă, ceasul ei se oprește pe durata pauzei: timpul de pauză nu se numără ca timp lucrat la sarcină.
- Ecranul devine un panou mare **On break** (în pauză): timpul scurs de la începutul pauzei, **Planned:** (planificat) cu durata aleasă de tine și un buton mare **Finish break** (termină pauza).

**Pauza nu se termină singură.** Când te întorci la locul tău de lucru, apasă **Finish break**. Asta te pontează din nou la intrare și înregistrează cât a durat pauza în realitate. Pontarea cu codul tău QR la kiosk încheie și ea pauza.

Dacă o pauză depășește durata planificată, panoul arată cu cât a depășit-o. Când depășirea e de mai mult de câteva minute (5, dacă Managerul de producție nu a schimbat valoarea), panoul devine roșu, iar pauza apare ca depășită pentru supervizori și manager în fila **Performance** (performanță).

Artizanii plătiți la oră nu au butoane de pauză. Ei fac pauză pontând la ieșire cu codul QR, iar sarcina continuă când se pontează din nou la intrare.

**Pentru supervizori și manageri.** Fila **Performance** are un tabel **Breaks** (pauze): pentru fiecare artizan și zi, numărul de pauze, minutele planificate, minutele luate de fapt și minutele peste plan. Bifează **Breaks over plan only** (doar pauzele peste plan) și apasă **Apply** (aplică) ca să vezi doar pauzele care au depășit. O casetă roșie sus listează pauzele care depășesc chiar acum și pauzele rămase deschise după terminarea turei. Pentru o pauză lăsată deschisă după tură, apasă **Set end** (setează sfârșitul), introdu ora la care s-a întors artizanul și salvează; orele și plata sarcinilor peste care trece pauza se recalculează, iar sfârșitul înregistrat prima dată se păstrează. Pontajele nu se schimbă acolo: se corectează în Human Resources. Managerul de producție stabilește toleranța cu rotița de lângă titlul **Breaks**.

## Lucruri bune de știut

- **O sarcină pe rând.** Cât timp ai o sarcină deschisă, butoanele START sunt dezactivate. Închide-o mai întâi.
- **Plata ta pentru sarcină** urmează numărul din Quantity made, așa cum e explicat în [Poziții de fabrică](/docs/factory-positions-ro). Piesele respinse nu se plătesc.
- **Open jobs** (sarcini deschise) sunt sarcini și pași fără nimeni numit, sau cu altcineva numit. E în regulă să o iei, dacă poziția ta permite; devine a ta până se închide.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Ecranul tău:</b> organizația ta → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Începe:</b> apasă pe rând → <b>START</b>.</li>
<li><b>Termină:</b> scrie <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Mai puțin decât cerut:</b> <b>DONE</b> → <b>Pause</b>, <b>Close at … and move remaining …</b> sau <b>Job finished</b>.</li>
<li><b>Pauza:</b> <b>Break:</b> <b>5m</b>, <b>20m</b> sau <b>30m</b> → <b>Yes, start break</b>. La întoarcere: <b>Finish break</b>.</li>
<li><b>Pauză lăsată deschisă după tură (supervizori):</b> <b>Factory</b> (Fabrică) → <b>Artisans</b> (Artizani) → <b>Performance</b> → <b>Set end</b>.</li>
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
