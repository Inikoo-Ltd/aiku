---
title: Planificarea zilei de producție
summary: Pentru managerul de producție și supervizorul de hală - cum pui sarcinile în planul unei zile fără să numești pe nimeni, cum dai fiecare pas unui operator și ce se întâmplă cu munca nefinalizată în ziua ei.
date: 2026-10-15
source_date: 2026-11-19
tags: production, planning, floor
category: production
series: Ordering from partners
order: 13
---

<aside class="tldr">
Două persoane, două treburi. <b>Managerul</b> hotărăște ce se face într-o zi: pe tabla <b>To produce</b> (De produs) alege cartelele și apasă <b>Plan for this day</b> (planifică pentru această zi), fără să numească pe nimeni. <b>Supervizorul</b> deschide <b>Operations → Day plan</b> (Operațiuni → Planul zilei) și dă fiecare pas al fiecărei sarcini planificate unui operator. Fiecare operator își vede apoi pașii pe ecranul de fabrică. Ce nu e terminat în ziua lui rămâne în plan, în chihlimbariu, până când cineva îl confirmă pentru o zi nouă.
</aside>

## Managerul planifică ziua

Pe **Factory → To produce** (Fabrică → De produs), tabla funcționează ca până acum: cartelele merg din **Backlog** (restanțe) în **Preparing** (în pregătire), unde se stabilește cantitatea, și din **Preparing** în **Assigned** (repartizate), unde se creează ordinul de lucru.

Când o cartelă e lăsată pe **Assigned**, fereastra mică care întreabă *Who makes it?* (cine o face?) începe acum cu o dată și un buton:

1. Alege ziua. Se deschide pe azi.
2. Apasă **Plan for this day**.

Se creează un ordin de lucru pentru acea zi, fără nimeni numit pe el. Fiecare cartelă devine propriul ei ordin de lucru, deci fiecare poate fi ordonat și mutat separat. Mai multe cartele selectate și lăsate împreună sunt planificate împreună, câte un ordin de lucru fiecare.

Alegerea unui nume din lista de mai jos funcționează ca întotdeauna: toată sarcina merge la acea persoană și nu e în planul niciunei zile.

O sarcină care are deja pe cineva numit poate fi pusă și ea într-un plan: apasă creionul de lângă nume pe cardul ei, apoi **Plan for this day**. Numele este scos de pe sarcină, iar supervizorul dă pașii ei.

## Supervizorul dă pașii

**Factory → Operations → Day plan** listează sarcinile planificate pentru zi, un rând pentru fiecare produs de pe o sarcină.

| Coloană | Ce înseamnă |
| --- | --- |
| Order | locul sarcinii în zi - scrie un număr ca s-o muți |
| Job | ordinul de lucru și starea lui |
| Product | ce se face |
| Qty | câte |
| Steps and operators | fiecare pas din rețetă: făcut până acum / cerut, și cui e dat |
| Planned | ziua pentru care a fost planificată |

Fiecare pas are propria listă de nume. Alege unul și pasul apare pe ecranul de fabrică al acelei persoane, și al nimănui altcuiva. Un pas cu chenar chihlimbariu nu are încă pe nimeni; bara de sus îi numără, *3 steps without an operator* (3 pași fără operator), ca să nu rămână nimic uitat.

Ca să schimbi cine face un pas în cursul zilei, alege alt nume. Ca să iei un pas de la cineva, apasă cruciulița din listă. Dacă persoana începuse deja, munca făcută rămâne înregistrată pe numele ei: lista hotărăște doar cine vede pasul de acum încolo.

Data din partea de sus trece la altă zi. Planul de mâine poate fi dat de azi.

## Urmărirea zilei

Aceeași pagină arată cum merge ziua și se reîmprospătează singură:

- bara de sus: câte sarcini, *12 of 18 steps done* (12 din 18 pași făcuți), pași fără operator, sarcini nefinalizate în ziua lor;
- lângă fiecare pas: un cerc gri (neînceput), un cerc chihlimbariu care se învârte (început) sau o bifă verde (făcut), cu numărul făcut până acum;
- *working now* (lucrează acum) și numele, când cineva are pasul deschis pe hală.

Cei care văd pagina dar nu conduc hala văd numele, nu listele.

## Când supervizorul lipsește

Managerul deschide aceeași pagină și dă pașii el însuși. Planificarea și darea pașilor cer aceeași poziție.

## Ce nu s-a terminat

O sarcină planificată cu pași încă deschiși după ziua ei nu este abandonată. Din ziua următoare apare pe Day plan cu data veche în chihlimbariu, iar bara o numără ca *not finished on its day* (nefinalizată în ziua ei). Pentru fiecare, alege:

- **Confirm for** (confirmă pentru) ziua la care te uiți - sarcina trece în planul acelei zile, la sfârșitul ordinii;
- **Take off the plan** (scoate din plan) - sarcina rămâne ordin de lucru, dar nu e în planul niciunei zile.

Până nu apeși una dintre cele două, sarcina rămâne în fața ta în fiecare zi. Pașii ei rămân și pe ecranele de fabrică ale operatorilor cărora le-au fost dați, așa că cine a pus ieri un mix în pauză poate continua în dimineața asta.

Când un operator închide o sarcină cu mai puțin și mută restul într-una nouă (vezi [Lucrul cu ecranul de fabrică](/docs/working-the-floor-screen-ro)), sarcina nouă păstrează ziua planificată, locul în ordine și operatorul fiecărui pas.

## Când un produs se face din alt produs

Unele produse se fac dintr-un alt produs al fabricii: un jumbo bath bomb Puckator (PuckJBB-04) este o bilă de baie JBB-04 din stoc, învelită în folie termocontractibilă și etichetată. Când nu sunt destule JBB-04 în stoc pentru el, cartela de pe tabla **To produce** și sarcina ei din **Day plan** spun asta în chihlimbariu, de exemplu *Needs 3 SKO JBB-04, not in stock: make JBB-04 first* (are nevoie de 3 SKO JBB-04, nu sunt în stoc: fă mai întâi JBB-04). Sarcinile deja puse în plan iau primele din stoc, apoi cartelele din backlog, cele mai vechi întâi.

Apasă **Create JBB-04 job** lângă mesaj: se deschide fereastra **Create job order** cu JBB-04 și SKO-urile lipsă deja completate, plus o notă care spune pentru ce produs este. Verific-o, alege artizanul și salvează. Odată ce o sarcină JBB-04 este făcută pentru stoc, mesajul devine albastru, *Waits for 3 SKO JBB-04 from* (așteaptă 3 SKO JBB-04 de la) noua sarcină, până când bilele de baie sunt gata. O sarcină JBB-04 făcută pentru o comandă de client sau de partener nu se numără, pentru că acele bile de baie sunt deja promise.

Mesajul dispare când pasul care folosește JBB-04 este terminat.

## Cererea de materiale la depozit

Ori de câte ori fabrica are nevoie de ceva de la depozit, oricine lucrează în producție cere asta sus în **Factory → Operations → Materials check** (Fabrică → Operațiuni → Verificarea materialelor), sub **Ask the warehouse** (Cere de la depozit). Fiecare rând este:

| Câmp | Ce pui |
| --- | --- |
| SKO | scrie codul sau numele; lista arată câte SKO au depozitul și fabrica |
| Quantity and unit | cum i-ai spune depozitului: *3 Boxes*, *1 Pallet*, *80 Bags*, *2 SKOs* sau *All there is* |
| Drop point | unde în fabrică să fie lăsat (*Storage room*, *Clean room*, *Bath bomb area*...) |
| Urgent | depozitul vede primele rândurile urgente |
| Note | orice trebuie să știe depozitul (*250ml*, *vă rog afară pe paleți*) |

**Add line** (Adaugă rând) cere mai multe lucruri deodată, iar **Send to warehouse** (trimite la depozit) le trimite. **Asked this week** (Cerut săptămâna aceasta) de mai jos arată fiecare cerere cu cine a făcut-o și când, iar pentru fiecare rând dacă este *To bring* (de adus), *Delivered* (livrat; câte SKO, de cine, când) sau *Can't bring* (nu se poate aduce) cu motivul depozitului.

Managerul și supervizorul țin lista punctelor de predare cu butonul **Drop points** (Puncte de predare): adaugă, redenumesc, mută în sus și în jos sau șterg. Un punct de predare șters își păstrează numele pe cererile deja trimise acolo.

## Verificarea de dimineață a materialelor

Depozitul aduce materialele în fabrică în cutii întregi, nu în cantitățile exacte ale zilei. **Factory → Operations → Materials check** (Fabrică → Operațiuni → Verificarea materialelor) listează ce mai are nevoie planul zilei, ca fabrica să verifice ce are și să ceară depozitului restul într-o singură listă clară.

Fiecare rând este un material, însumat pe toate sarcinile zilei, cu sarcinile care îl cer (*JOaroma-0141, JOaroma-0145*). Se numără doar pașii încă nefăcuți: când fabricarea s-a terminat și a rămas doar ambalarea, rândul arată cutiile și etichetele, nu mai sticlele. Mixurile se fac în fabrică și nu apar pe listă. Cantitățile vin din rețete, deci o sarcină a cărei rețetă nu listează materiale nu cere nimic: bara chihlimbarie de deasupra listei numește aceste sarcini, ca golul din rețetă să fie văzut.

| Coloană | Ce înseamnă |
| --- | --- |
| Material | materialul și codul de stoc din depozit |
| For jobs | sarcinile zilei care îl cer |
| Needed | câte consumă pașii rămași de făcut |
| At the factory | câte sunt acum în locația din fabrică și câte au fost deja cerute azi |
| Check | bifează **Have enough** (sunt suficiente) sau scrie aproximativ câte lipsesc |

Când scrii un număr de lipsă, rândul arată ce va aduce depozitul, rotunjit în sus la cutiile lui: dacă lipsesc 1.200 și într-o cutie sunt 500, apare *bring 3 boxes (1.500)* (adu 3 cutii). Un *Warehouse short* (depozitul nu are destul) roșu înseamnă că depozitul nu are atâtea, deci știi imediat.

Apasă **Send to warehouse** (trimite la depozit). Rândurile cu lipsă merg la depozit ca o singură cerere, iar cererea apare sub **Asked this week**, fiecare material marcat *To bring* (de adus), *Delivered* (livrat) sau *Can't bring*. Dacă bifezi totul ca suficient și trimiți, se înregistrează că verificarea s-a făcut. O a doua verificare mai târziu în zi adaugă doar ce lipsește în continuare.

Managerul sau supervizorul face verificarea sau o dă unui operator prin **Checked by** (verificat de), din partea de sus. Acel operator vede apoi pe ecranul de fabrică un memento care deschide verificarea.

## Depozitul aduce materialele

În depozit, cardul **For production** (Pentru producție) de sub **Action needed** (Necesită acțiune) din **Inventory** (Inventar) arată câte rânduri așteaptă fabrica, cu cele urgente pe insigna mică. Deschide **Inventory → For production**: fiecare rând încă de adus, întâi cele urgente și apoi cele mai vechi, cu ce s-a cerut, punctul de predare, cine a cerut și când, nota și raftul de unde se ia. Cel care pregătește alege raftul, scrie SKO aduse și apasă **Delivered**. Când depozitul nu poate aduce, **Can't bring** întreabă motivul (*out of stock*, *ordered from supplier*) și anunță fabrica. **Done** (Gata) listează ce s-a adus sau refuzat, cu cât a durat.

Dacă Aiku arată pe raft mai puține SKO decât s-au adus, livrarea trece oricum: ce avea Aiku se mută în fabrică, iar restul este marcat *not in Aiku, count it* (nu e în Aiku, numără-l), astfel încât SKO să fie numărat.

Stocul livrat trece de pe raft în locația din fabrică. Rămâne stoc: este numărat și văzut ca înainte, iar o comandă de client îl mai poate culege de acolo când rafturile depozitului sunt goale, rafturile având mereu prioritate. Materialele nefolosite pot fi mutate înapoi pe un raft din pagina locației din fabrică, ca orice altă mutare între locații.

Când o sarcină terminată este recepționată în stoc, materialele ei se consumă mai întâi din locația din fabrică, după cantitățile din rețetă, și abia apoi din alte locații. Numără din când în când locația din fabrică, ca să corectezi spargerile și pierderile.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Planifici o sarcină pe o zi:</b> <b>Factory</b> → <b>To produce</b> → lasă cartela pe <b>Assigned</b> → alege ziua → <b>Plan for this day</b>.</li>
<li><b>Dai un pas unui operator:</b> <b>Factory</b> → <b>Operations</b> → <b>Day plan</b> → lista de lângă pas.</li>
<li><b>Schimbi ordinea zilei:</b> scrie un număr în <b>Order</b>.</li>
<li><b>Sarcina nefinalizată de ieri:</b> <b>Confirm for</b> ziua, sau <b>Take off the plan</b>.</li>
<li><b>De pe tablă în plan:</b> linkul <b>Day plan</b> de deasupra tablei.</li>
<li><b>Ceri ceva de la depozit:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Ask the warehouse</b> → <b>Send to warehouse</b>.</li>
<li><b>Schimbi punctele de predare:</b> <b>Materials check</b> → <b>Drop points</b>.</li>
<li><b>Verifici materialele zilei:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Have enough</b> sau un număr de lipsă → <b>Send to warehouse</b>.</li>
<li><b>Dai verificarea unui operator:</b> <b>Materials check</b> → <b>Checked by</b>.</li>
<li><b>Aduci materialele (depozit):</b> <b>Inventory</b> → <b>For production</b> → <b>Delivered</b>, sau <b>Can't bring</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li>Pozițiile se setează pe fișa angajatului sub Human Resources și aduc cu ele drepturile.</li>
<li>Planificarea unei zile, darea pașilor, schimbarea ordinii: poziția <b>Floor supervisor</b> pentru fabrică.</li>
<li>Vizualizarea Day plan fără a o modifica: <b>Foreman</b>.</li>
<li>Lucrul la un pas dat ție: <b>Operative</b>, pe ecranul de fabrică.</li>
<li>Cererea de materiale la depozit: oricine vede Operations din fabrică.</li>
<li>Verificarea materialelor și punctele de predare: <b>Floor supervisor</b>; verificarea și operatorul căruia i-a fost dată.</li>
<li>Aducerea materialelor în fabrică: o poziție de depozit care lucrează în depozit.</li>
</ul>
</aside>
