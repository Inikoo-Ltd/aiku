---
title: Partijas, iepakojumi un daļējas partijas
summary: Ražotne izgatavo vienības pilnās partijās, noliktava skaita iepakojumus. Ko packed_in un partijas lielums dara ar darba uzdevumu, ar to, kas nonāk plauktā, un ar to, ko partnerim vajadzētu pasūtīt.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, procurement, crafts
category: production
series: Ordering from partners
order: 12
---

<aside class="tldr">
Divi skaitīšanas veidi satiekas pie katra artefakta, un tie nav viens un tas pats skaits. Ražotne strādā **vienībās** - viena vannas bumba, viens ziepju gabals - un var prātīgi izgatavot tikai veselu **partiju** no tām, jo tik daudz ietilpst maisītājā. Noliktava un veikals strādā **SKO** - iepakojumā, kurā prece tiek pārdota un uzglabāta, desmit kastē. **Packed in** ir vienīgais tilts starp abiem, un aiku tagad to šķērso abos galos, nevis izliekas, ka skaitļi ir savstarpēji aizvietojami.
</aside>

## Trīs skaitļi

- **Batch size** (Partijas lielums) — cik vienību ražotne izgatavo vienlaikus. Tas pieder artefaktam, iestatīts tā lapā vai masveidā no artefaktu saraksta, skaties [Daudzu artefaktu maiņa vienlaikus](/docs/changing-many-artefacts-at-once).
- **Packed in** (Iepakots pa) — cik vienību ietilpst vienā SKO. Tas pieder SKO noliktavā.
- **Units un SKOs** (Vienības un SKO) — ražotnei pieprasa vienībās, viss pārējais tiek skaitīts SKO.

## Kas notiek katrā galā

**Darba uzdevuma izveide.** Viss, kas pieprasīts — partnera rinda, pašu klienta trūkums, papildināšanas rinda — ir daudzums SKO. aiku to reizina ar *packed in*, lai iegūtu vienības, tad noapaļo **uz augšu** līdz nākamajai pilnajai partijai. Pieprasi 1 SKO no desmitnieka, kas gatavots partijās pa 16, un amatniekam tiek pieprasītas 16 vienības, nevis 1 un nevis 10.

**Pabeigta darba saņemšana.** Tas, ko amatnieks izgatavoja, ir vienības, un tās tiek dalītas ar *packed in*, ceļā uz plauktu. Tās 16 desmitnieka vienības nonāk kā 1,6 SKO: viena aizzīmogota kaste un sešas vaļīgas. Plaukta skaitlis godīgi rāda atlikumu, nevis to noapaļo prom.

## Kad partija neaizpilda pilnus iepakojumus

Partija pa 16 un kaste pa 10 nekad neiznāk vienmērīgi. Tā nav kļūda, un aiku to par tādu neuzskata: ražotne partiju iemēro maisītājam, veikals pārdod iepakojumos, un abi ir pareizi. Vienkārši ir vērts zināt, kur tas notiek, tāpēc tas tiek mērīts:

- kolonna **Batch in SKOs** (Partija SKO) un filtrs **Batch not whole SKOs** (Partija nav vesels SKO skaits) artefaktu sarakstā;
- rinda artefakta lapā, kas rāda partiju SKO un tuvāko partijas lielumu, kas iznāktu vesels;
- statistika Crafts panelī, kas skaita artefaktus, kur tas notiek.

Nekas neliek mainīt partijas lielumu. Ja ieteikumu ir viegli pieņemt, pieņem to, un aritmētika beidz atstāt atlikumus. Ja partiju nosaka maisītājs, atstāj to.

## Ko partnerim vajadzētu pasūtīt

Tā pati aritmētika izlemj tīrāko pasūtījuma daudzumu, ko aiku sauc par **order step** (pasūtījuma soli): mazāko SKO skaitu, ko pilnas partijas aizpilda tieši. Partijai pa 16 vienībām iepakojumos pa 10 tas ir 8 SKO — astoņdesmit vienības, piecas partijas, nekāda atlikuma.

Partnera pusē, [iepirkumu sarakstā](/docs/buying-from-a-partner-lv) un preču sarakstā:

- rinda saka *made in batches of N units* (izgatavots partijās pa N vienībām) un, ja tie atšķiras, *full batches every N SKO* (pilnas partijas ik pa N SKO);
- maza poga noapaļo daudzumu uz nākamo soli;
- **suggested** (ieteiktie) daudzumi un viss, ko piedāvā Auto-fill, jau ir uz soļa;
- pasūtījums ārpus soļa joprojām tiek pieņemts, ar piezīmi, ka pilna partija tik un tā tiek izgatavota, tāpēc pasūtījums var kavēties vai daudzums tikt koriģēts.

Pasūtījumu, kas mazāks par vienu pilnu soli, vispār nevar izgatavot atsevišķi. Dēlī [To produce](/docs/fulfilling-partner-orders-lv) tas gaida aiz *rindas, kas ir par mazu partijai, gaida sabiedrību*, kamēr rodas pietiekams pieprasījums, pašu klienta pasūtījums tik un tā liek uzdevumam darboties, vai plānotājs izlemj to izgatavot jebkurā gadījumā.

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Iestatīt partijas lielumu:</b> tava organizācija → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → atver artefaktu, vai atzīmē vairākus un iestati no izvēles joslas.</li>
<li><b>Atrast neveiklos:</b> artefaktu saraksts → filtrs <b>Batch not whole SKOs</b>, vai kolonna <b>Batch in SKOs</b>.</li>
<li><b>Redzēt ieteikumu:</b> artefakta lapa, zem partijas lieluma.</li>
<li><b>Iestatīt packed in:</b> <b>Warehouse → Inventory</b> → atver SKO → <b>Edit SKO</b>.</li>
<li><b>Pasūtīt uz soļa:</b> partnera iepirkumu saraksts → poga blakus <i>full batches every N SKO</i>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Nepieciešamās tiesības</strong>
<ul>
<li>Amati tiek piešķirti darbinieka kartītē sadaļā Human Resources un ar sevi nes tiesības.</li>
<li>Partijas lielums un derīguma termiņš artefaktiem: ražotnes <b>research and development</b> (pētniecība un attīstība) tiesības, vai organizācijas vadītājs.</li>
<li>Packed in uz SKO: noliktavas amats, kas var rediģēt krājumu.</li>
</ul>
</aside>
