---
title: Pregătirea amestecurilor
summary: Pentru preparator și pentru planificator - cum devine un amestec sau o bază ceva ce fabrica urmărește, cum calculează fila Mixes ce trebuie preparat, și cum curg ordinele de lucru ale preparatorului.
date: 2026-09-08
source_date: 2026-09-08
tags: production, crafts
category: production
series: Ordering from partners
order: 6
---

<aside class="tldr">
Pentru persoana care pregătește amestecuri și baze înainte ca artizanii să poată începe, și pentru planificatorul care le trimite treaba. Un amestec este făcut chiar în fabrică, așa că aiku îl tratează atât ca <b>materie primă</b> (artizanii îl consumă), cât și ca <b>artefact</b> (preparatorul îl face). Odată legate, fila <b>Mixes (Amestecuri)</b> din <a href="/docs/fulfilling-partner-orders-ro">To produce</a> calculează cât lipsește din fiecare amestec pornind de la ordinele de lucru deschise, iar tragerea unei cartele la preparator transformă asta într-un ordin de lucru. Configurarea categoriilor și a artizanilor e explicată în <a href="/docs/who-makes-what-ro">Cine face ce</a>.
</aside>

## De ce un amestec este două lucruri

O rețetă de bombă de baie spune "0,5 kg de amestec de bază pe unitate". Amestecul de bază nu se cumpără, se prepară în fabrică din propriile lui ingrediente. Așa că există de două ori:

- Ca **materie primă**, ca rețetele să îl poată consuma, iar stocul să se scadă când produsul finit e recepționat.
- Ca **artefact**, cu propria rețetă și propriile ordine de lucru, ca preparatorul să aibă de lucru și un lot de primit în stoc.

Legătura dintre cele două este un singur câmp pe materia primă: **Made in-house as (Făcut intern ca)**. Setează-l pe artefactul amestecului. Asta e toată configurarea.

## Cum configurezi un amestec

1. **Creează artefactul** pentru amestec în **Factory → Crafts → Artefacts (Fabrică → Meșteșuguri → Artefacte)**, cu pașii rețetei lui și propriile materii prime, ca orice alt artefact. Dă-i un stoc (SKU) ca loturile primite să aibă unde să meargă.
2. **Creează sau deschide materia primă** pentru amestec în **Factory → Crafts → Raw materials (Fabrică → Meșteșuguri → Materii prime)**. Editeaz-o, setează **Made in-house as (Făcut intern ca)** la artefactul de la pasul 1, și dă-i același stoc (SKU).
3. **Folosește materia primă în rețete.** La fiecare produs care are nevoie de amestec, adaugă amestecul la pasul de rețetă potrivit, cu cantitatea pe unitate.
4. **Atașează preparatorul** la artefactul amestecului, sau la o categorie care ține toate amestecurile, sub *Usually made by (De obicei făcut de)*. Ordinele de lucru pentru amestecuri merg apoi la persoana respectivă.

## Fila Mixes (Amestecuri)

**Factory → To produce → Mixes (Fabrică → De produs → Amestecuri)** e un mic panou cu patru culoare: **Needed** (necesar), **Assigned** (atribuit), **Mixing** (în amestecare) și **Done** (gata). Arată doar materiile prime făcute intern de care are nevoie un ordin de lucru deschis. Un ordin de lucru rămâne deschis din momentul creării până când e recepționat în stoc.

O cartelă din **Needed** e un amestec de care fabrica duce lipsă. Numărul roșu e lipsa: ce au nevoie ordinele de lucru deschise, din cantitățile lor și cantitatea pe unitate din rețetă, minus ce e în stoc, minus ce e deja în amestecare. Dedesubt, *for* listează codurile de produs care așteaptă, ca preparatorul să știe ce e blocat, și numele preparatorului obișnuit dacă e atașat unul.

Trage cartela din **Needed** în **Assigned**. aiku cere cantitatea, propunând lipsa ca s-o rotunjești la un lot rezonabil, și *Cine amestecă?*. Alege preparatorul și un ordin de lucru e creat ca ciornă, adresat lui, cu referința lui pe cartelă. Deschide-l și apasă **Release to floor** (trimite pe hală) când trebuie să înceapă.

**Mixing** și **Done** se mișcă singure: cartela ajunge în Mixing când preparatorul apasă START, și în Done când ultima sarcină e gata. Părăsește panoul când lotul e pus în stoc.

## Ce face preparatorul

Preparatorul își conduce propria linie, așa că are poziția <b>Mix preparer</b> (preparator de amestecuri) pentru fabrică. Asta îi permite să deschidă fila Mixes, să creeze și să trimită propriile ordine de lucru și să le recepționeze în stoc, fără să aștepte pe nimeni. Nu poate umbla la ordinele de lucru adresate altor persoane; acelea rămân la planificator. În hală lucrează ca orice artizan: sarcinile lui apar pe [ecranul halei](/docs/working-the-floor-screen-ro), apasă START și DONE, și când ultimul pas e gata, lotul e pus în stoc cu un cod de lot, fie de depozit din [Dispatching → From production](/docs/putting-away-finished-production-ro), fie de preparator din pagina ordinului de lucru. Din acel moment amestecul apare ca fiind în stoc și artizanii pot face produsele lor.

Dacă preparatorul nu e plătit cu bucata, asta e o setare de salarizare, nu un motiv să sară peste hală. Înregistrarea cine a preparat ce lot și când este ceea ce oferă trasabilitatea de la produsul finit înapoi la ingredientele lui.

## Lucruri bune de știut

- Un amestec nu poate avea nevoie de el însuși. Dacă rețeta artefactului-amestec listează aceeași materie primă, linia aceea e ignorată.
- Fila Mixes citește doar ordinele de lucru din această fabrică. Un produs făcut în altă fabrică nu creează cerere aici.
- Un ordin de lucru pentru amestec numără ca fiind în lucru până e pus în stoc, chiar dacă toate sarcinile sunt gata. Pune loturile la loc la timp și lipsa rămâne corectă.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Leagă un amestec:</b> <b>Factory → Crafts → Raw materials</b> → deschide amestecul → <b>Edit</b> → <b>Made in-house as</b>.</li>
<li><b>Vezi ce trebuie preparat:</b> <b>Factory → To produce → Mixes</b>.</li>
<li><b>Trimite treaba:</b> trage cartela din <b>Needed</b> în <b>Assigned</b> → cantitate și preparator → deschide ordinul de lucru → <b>Release to floor</b>.</li>
<li><b>Fă treaba:</b> <b>Factory → Jobs</b> → <b>START</b> / <b>DONE</b>; apoi lotul e pus în stoc din <b>Warehouse → Dispatching → From production</b> sau din pagina ordinului de lucru.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li>Pozițiile se setează pe fișa angajatului în Human Resources și aduc cu ele drepturile.</li>
<li>Vizualizarea filei Mixes și lucrul în hală: poziția <b>Production operative</b> (operator) pentru fabrică, sau mai sus.</li>
<li>Crearea ordinelor de lucru pentru amestecuri și trimiterea și recepționarea celor proprii: poziția <b>Mix preparer</b> (preparator de amestecuri) pentru fabrică. Preparatorul are nevoie de aceasta.</li>
<li>Tot restul, inclusiv ordinele de lucru ale altor persoane și legarea unei materii prime de artefactul ei: poziția <b>Production floor supervisor</b> (supervizor de hală) pentru fabrică, sau supervizor de organizație.</li>
</ul>
</aside>
