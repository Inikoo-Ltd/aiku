---
title: Loturi, pachete și loturi parțiale
summary: Fabrica face unități în loturi întregi, depozitul numără pachete. Ce fac packed_in și dimensiunea lotului cu un ordin de lucru, cu ce ajunge pe raft, și cu ce ar trebui să comande un partener.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, procurement, crafts
category: production
series: Ordering from partners
order: 12
---

<aside class="tldr">
Două numărători se întâlnesc la fiecare artefact și nu sunt aceeași numărătoare. Hala lucrează în <b>units</b> (unități) - o bombă de baie, un săpun - și poate face rezonabil doar un <b>batch</b> (lot) întreg din ele, pentru că atât încape în mixer. Depozitul și magazinul lucrează în <b>SKO-uri</b>: pachetul în care marfa e vândută și depozitată, zece la o cutie. <b>Packed in</b> (ambalat în) e singura punte între cele două, iar aiku o traversează acum la ambele capete, în loc să se prefacă că numerele sunt interschimbabile.
</aside>

## Cele trei numere

- **Batch size** (dimensiunea lotului) — câte unități face fabrica odată. Aparține artefactului, se setează pe pagina lui sau în bloc din lista de artefacte, vezi [Schimbarea mai multor artefacte odată](/docs/changing-many-artefacts-at-once-ro).
- **Packed in** (ambalat în) — câte unități intră într-un SKO. Aparține SKO-ului din depozit.
- **Units și SKO-uri** — halei i se cere în unități, tot restul e numărat în SKO-uri.

## Ce se întâmplă la fiecare capăt

**Ridicarea unei lucrări.** Orice s-a cerut — o linie de partener, un deficit de client propriu, o linie de reaprovizionare — e o cantitate în SKO-uri. aiku o înmulțește cu *packed in* ca să obțină unități, apoi rotunjește **în sus** la lotul întreg următor. Cere 1 SKO dintr-un pachet de zece făcut în loturi de 16 și artizanului i se cer 16 unități, nu 1 și nu 10.

**Recepționarea lucrării finite.** Ce a făcut artizanul sunt unități, iar acestea sunt împărțite la *packed in* pe drumul spre raft. Acele 16 unități dintr-un pachet de zece ajung ca 1,6 SKO-uri: o cutie sigilată și șase bucăți desperecheate. Cifra de pe raft e sinceră cu privire la rest, în loc să-l rotunjească.

## Când un lot nu umple pachete întregi

Un lot de 16 și o cutie de 10 nu ies niciodată exact. Nu e o eroare și aiku nu o tratează ca atare: fabrica dimensionează un lot pentru mixer, magazinul vinde în pachete, și ambele au dreptate. Merită doar știut unde se întâmplă, așa că e măsurat:

- o coloană **Batch in SKOs** (lotul în SKO-uri) și un filtru **Batch not whole SKOs** (lotul nu e SKO-uri întregi) pe lista de artefacte;
- o linie pe pagina artefactului care arată lotul în SKO-uri și cea mai apropiată dimensiune de lot care ar ieși întreagă;
- o statistică pe dashboard-ul crafts care numără artefactele la care se întâmplă asta.

Nimic nu te obligă să schimbi o dimensiune de lot. Dacă sugestia e ușor de urmat, urmeaz-o și aritmetica nu mai lasă rest. Dacă mixerul decide lotul, las-o așa.

## Ce ar trebui să comande un partener

Aceeași aritmetică decide cea mai potrivită cantitate de comandă, pe care aiku o numește **order step** (pasul de comandă): cel mai mic număr de SKO-uri pe care loturile întregi îl umplu exact. Pentru un lot de 16 unități în pachete de 10, e 8 SKO-uri — optzeci de unități, cinci loturi, fără rest.

De partea partenerului, în [lista de cumpărături](/docs/buying-from-a-partner-ro) și în lista de stocuri:

- linia spune *made in batches of N units* (făcut în loturi de N unități) și, unde diferă, *full batches every N SKO* (loturi întregi la fiecare N SKO);
- un buton mic rotunjește cantitatea în sus la pasul următor;
- cantitățile **suggested** (sugerate) și tot ce propune Auto-fill sunt deja pe pas;
- o comandă în afara pasului e totuși acceptată, cu o notă că un lot întreg se face oricum, așa că livrarea poate întârzia sau cantitatea poate fi ajustată.

O comandă sub un pas întreg nu poate fi făcută deloc de una singură. Pe panoul [To produce](/docs/fulfilling-partner-orders-ro) așteaptă în spatele mesajului *lines too small for a batch are waiting for company* (linii prea mici pentru un lot așteaptă companie) până apare cerere suficientă, o comandă de client propriu face oricum lucrarea să pornească, sau planificatorul decide să o facă oricum.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Setează o dimensiune de lot:</b> organizația ta → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → deschide artefactul, sau bifează mai multe și setează din bara de selecție.</li>
<li><b>Găsește-le pe cele incomode:</b> lista de artefacte → filtrul <b>Batch not whole SKOs</b>, sau coloana <b>Batch in SKOs</b>.</li>
<li><b>Vezi sugestia:</b> pagina artefactului, sub dimensiunea lotului.</li>
<li><b>Setează packed in:</b> <b>Warehouse → Inventory</b> → deschide SKO-ul → <b>Edit SKO</b>.</li>
<li><b>Comandă pe pas:</b> lista de cumpărături a partenerului → butonul de lângă <i>full batches every N SKO</i>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li>Pozițiile se setează pe fișa angajatului sub Human Resources și aduc cu ele drepturile.</li>
<li>Dimensiunea lotului și durata de viață pe raft la artefacte: dreptul <b>research and development</b> al fabricii, sau supervizor de organizație.</li>
<li>Packed in la un SKO: o poziție de depozit care poate edita inventarul.</li>
</ul>
</aside>
