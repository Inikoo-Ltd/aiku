---
title: Pre-pick
summary: Ražotnes rokasgrāmata sadaļai Factory → Pre-pick - kādas partnera rindas šeit nonāk, ko nozīmē katrs statuss un ko darīt, kad krājums ir, kad ir tikai daļa, kad vēl kas ir ceļā un kad tas vairs neatnāks.
date: 2026-11-25
source_date: 2026-11-25
tags: production, procurement, intercompany, warehouse
category: production
series: Ordering from partners
order: 8
---

<aside class="tldr">
Rindas, kas jāizgatavo, iet uz <b>To produce</b> (Jāizgatavo); rindas, kas jau ir uz plauktiem, nonāk sarakstā <b>Pre-pick</b> (Iepriekšēja komplektēšana). Katrai rindai sarakstā Pre-pick:
<ul>
<li><b>Tev ir viss krājums:</b> nedari neko. aiku pats nosūta to noliktavai desmit minūšu laikā. Ja krājums aptver rindu tieši un nekas nepaliek pāri, rinda gaida: spied <b>Pre-pick</b>.</li>
<li><b>Tev ir tikai daļa, un vēl kas ir ceļā no piegādātāja:</b> gaidi. Kad piegāde ir ievadīta krājumā, aiku pats nosūta visu rindu. Vai spied <b>Pre-pick</b>, lai uzreiz nosūtītu to, kas tev ir; pārējais iet uz <b>To produce</b>.</li>
<li><b>Tev ir tikai daļa, un to ražo pats:</b> gaidīšana neko nedod, jo neviens netiek lūgts izgatavot pārējo. Spied <b>Pre-pick</b>, lai nosūtītu to, kas ir; pārējais iet uz <b>To produce</b>, lai to izgatavotu.</li>
<li><b>Tas vairs neatnāks (izņemts no sortimenta, nav pieejams):</b> spied <b>Close short</b> (Slēgt īsāku), lai nosūtītu to, kas ir, un atceltu pārējo, vai <b>Can't be done</b>, lai nenosūtītu neko; partneris tiek informēts. Ja krājumā nekā nav, rinda ir sarakstā <b>To produce</b>: skaties 3. gadījumu zemāk.</li>
</ul>
</aside>

<figure><img src="/art/docs/draw-pre-pick-fork.svg" alt="Akvareļa skice: partnera iepirkumu saraksta rinda nonāk pie ceļa rādītāja, kas rāda uz To produce, darbgaldu, kur preces izgatavo, vai uz Pre-pick, plauktu ar krājumu. No plaukta viss krājumā esošais pats aiziet uz partnera vietu desmit minūšu laikā, daļa krājumā aiziet uz vietu, kamēr pārējais gaida piegādes kravas auto, bet krājumam, kas neatnāks, uzspiež sarkanu zīmogu Close short, un partnerim lido ziņa atpakaļ." width="1200" height="750" loading="eager"><figcaption>Uz plaukta: Pre-pick. Nav uz plaukta: To produce.</figcaption></figure>

## Kāpēc rinda nonāk šeit, nevis sarakstā To produce

Partnera pasūtījums atnāk kā rindas iepirkumu sarakstā. Katrai rindai aiku skatās uz tavu **brīvo krājumu**: kas ir plauktā, atņemot to, kas jau apsolīts citām partneru rindām.

- **Brīvais krājums neaptver neko** - rinda iet uz **To produce**, lai to izgatavotu. Skaties [Darbs ar sarakstu To produce](/docs/fulfilling-partner-orders-lv).
- **Brīvais krājums aptver daļu vai visu** - rinda nonāk sarakstā **Factory → Pre-pick**.

Tātad viens un tas pats pasūtījums var būt sadalīts pa abām lapām: tēja, ko tu sajauc, iet uz To produce, bet pudeles, maisiņi un kastes, kas jau ir tavā rīcībā, nonāk šeit. Rinda, kuras krājums vēlāk izbeidzas, pati pāriet uz To produce.

Rindas tiek apkalpotas rindā, katram produktam atsevišķi: vispirms **urgent** (steidzami), tad **high** (augsta), **normal** (parasta) un **low** (zema); vienādas prioritātes ietvaros - tā, kas vajadzīga drīzāk, tad vecākā. Rinda ņem vērā tikai to krājumu, kas atlicis pēc visām rindām pirms tās.

## Saraksta lasīšana

| Kolonna | Ko tā rāda |
| --- | --- |
| For | kura partnerorganizācija pieprasīja |
| Artefact | produkta kods un nosaukums |
| To | attiecīgā partnera savākšanas vieta, kurp preces nonāks |
| Asked | cik partneris pieprasīja |
| In stock | kas pašlaik ir uz taviem plauktiem |
| Can pick | ko tu vari dot šai rindai tagad, pēc rindām, kas ir pirms tās |
| Shortfall | cik trūkst, lai aptvertu visu rindu |
| Status | ko aiku ar rindu darīs, skaties zemāk |
| Priority, Added | kur rinda atrodas rindā |

Kategorija, pieprasītājs un steidzamība filtrē sarakstu; skaitļi uz filtriem ir īstie skaitļi.

## Ko nozīmē statuss

| Status | Ko tas nozīmē | Ko tu dari |
| --- | --- | --- |
| **Going to the warehouse** (Ceļā uz noliktavu) | krājums aptver visu rindu, un vēl kaut kas paliek pāri | nekas - desmit minūšu laikā aiku to iepriekš sakomplektē pats un rinda pazūd no saraksta |
| **Held, one more needed on the shelf** (Aizturēts, plauktā jāpaliek vēl vienam) | krājums aptver rindu tieši, nekas nepaliek pāri | nekas, vai spied **Pre-pick**, ja tai jāiet tagad; aiku rindu pats nosūta tikai tad, ja vismaz viena vienība paliek plauktā |
| **Awaiting full stock, production manager to decide** (Gaida pilnu krājumu, lēmums ražošanas vadītājam) | krājumā ir tikai daļa no rindas | izlem, skaties gadījumus zemāk |
| **In To produce** | krājums ir, bet rindas pirms šīs to jau ir paņēmušas | šeit nekas; rinda gaida lapā To produce |

Iepriekšēja komplektēšana nav nosūtīšana. Tā apsola krājumu šim partnerim un ieliek pastaigu noliktavas sarakstā: tur preci noņem no plaukta un noliek partnera vietā. Preces aiziet, kad aiziet partnera nākamā sūtījums - pēc grafika vai kad kāds nospiež **Send to warehouse** (Nosūtīt uz noliktavu). Skaties [Partnera preču savākšana](/docs/gathering-a-partners-goods-lv).

## 1. gadījums: krājums ir viss

Nedari neko. Rinda rāda **Going to the warehouse**, un aiku to iepriekš sakomplektē desmit minūšu laikā. Ja tā rāda **Held, one more needed on the shelf** un tai nevajag gaidīt, spied **Pre-pick** uz rindas.

Lai nosūtītu vairākas rindas uzreiz, atzīmē tās un spied **Pre-pick selected** (Iepriekš komplektēt atzīmētās), vai **Pre-pick all** (Iepriekš komplektēt visas) visam, ko rāda pašreizējie filtri.

## 2. gadījums: krājumā ir daļa, vai vēl kas ir ceļā

Rinda rāda **Awaiting full stock, production manager to decide**. Ir trīs izvēles:

- **Gaidīt pārējo.** Nedari neko. Kad pasūtītās preces no piegādātāja atnāk un tiek ieliktas krājumā, brīvais krājums pieaug; līdzko tas aptver visu rindu un vismaz viena vienība paliek plauktā, aiku to iepriekš sakomplektē pats. Izvēlies to, kad piegāde ir tuvu un partneris labāk vēlas saņemt visu uzreiz. Gaidīšana der tikai tad, kad krājums tiešām ir ceļā: produktam, ko tu pats ražo, neviens netiek lūgts izgatavot pārējo, kamēr rinda gaida šeit, tāpēc tā gaidītu mūžīgi.
- **Nosūtīt to, kas ir tagad, pārējo vēlāk.** Spied **Pre-pick** (daudzums parādās kā *partial pick* - daļēja komplektēšana). Daļa, kas ir krājumā, aiziet uz vietu; pārējais paliek partnera sarakstā kā sava atvērta rinda un pāriet uz **To produce**. Produkts, ko tu pats ražo, tur parādās uz dēļa, lai to izgatavotu; jebkas cits parādās cilnē **All** un atgriežas šeit, kad atnāk jauns krājums.
- **Nosūtīt to, kas ir, un tur apstāties.** Spied **Close short** (Slēgt īsāku). aiku lūdz apstiprināt, nosūta to, kas ir krājumā, un atceļ pārējo. Partnera saraksts samazinās līdz nosūtītajam daudzumam, un tas, kurš rindu pievienoja, un partnera iepirkumu komanda tiek informēti, ka tā slēgta īsāka, lai viņi var nopirkt to citur.

## 3. gadījums: krājums neatnāks

Produkts ir pārtraukts, piegādātājam tā vairs nav, vai tas ilgi nebūs pieejams.

- **Daļa ir krājumā** - spied **Close short**: partneris saņem to, kas ir, un viņam pasaka, ka pārējais neatnāks. Ja nevari nosūtīt arī neko no tā, spied tā vietā **Can't be done** uz rindas un norādi iemeslu.
- **Krājumā nav nekā, un tu to ražo** - rinda ir kartīte uz **To produce** dēļa. Pavelc to no **Backlog** (Rindā) uz **Preparing** (Sagatavo), vai uz **Producing** (Ražo), ja tava ražotne lieto vienkāršo dēli, un atvērtajā logā spied **Can't be done** (Nevar izdarīt), norādot iemeslu. Rinda pazūd no dēļa, un tas, kurš to pievienoja, un partnera iepirkumu komanda tiek informēti kopā ar tavu iemeslu, lai viņi var sagādāt citur.
- **Krājumā nav nekā, un tu to neražo** - rinda ir sadaļā **To produce → All**. Spied **Can't be done** uz tās rindas un norādi iemeslu; tiek informēti tie paši cilvēki.

Neatstāj šādas rindas gaidām bez ziņas: partneris tās redz kā **Requested** (Pieprasīts) savā sarakstā un turpina uz tām rēķināties.

## Ko redz partneris

Savā iepirkumu sarakstā katra rinda saka, līdz kuram posmam tā ir tikusi: **Requested**, **Being made**, **Pre-picked**, **Staged for you**, **Being picked**, **On its way**.

Kad rinda ir slēgta īsāka vai to nevar izdarīt, cilvēki partnera pusē tiek informēti kopā ar iemeslu.

## Noderīgi zināt

- **Nekas nerezervē krājumu, kamēr rinda nav iepriekš sakomplektēta.** Rinda, kas rāda *Awaiting full stock*, nav neko nolikusi malā: tās krājumu joprojām var pārdot vai iedot citai rindai. Ja to nedrīkst paņemt, iepriekš sakomplektē to, kas ir.
- **Ja plaukts izrādās tukšāks** - audits atrod mazāk, vai tas ir pārdots citur - aiku atņem solījumu, sākot ar jaunākajām iepriekšējām komplektēšanām, un šīs rindas atgriežas sarakstā vai uz To produce.
- **Saraksts tiek pārrēķināts no jauna katru reizi, kad to atver.** Rindas pašas pazūd, tiklīdz tās ir iepriekš sakomplektētas; nav ko sakārtot.
- **Skaitlis blakus Pre-pick** ražotnes sānjoslā rāda, cik rindu gaida, un tas atjaunojas pats.

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Saraksts:</b> tava organizācija → <b>Factory</b> → <b>Pre-pick</b>.</li>
<li><b>Nosūtīt rindu tagad:</b> <b>Pre-pick</b> uz rindas, vai atzīmē rindas un spied <b>Pre-pick selected</b>, vai <b>Pre-pick all</b>.</li>
<li><b>Nosūtīt to, kas ir, un atcelt pārējo:</b> <b>Close short</b> uz rindas.</li>
<li><b>Rinda, ko nevar izdarīt:</b> <b>Can't be done</b> uz tās rindas sadaļā <b>Pre-pick</b> vai <b>Factory</b> → <b>To produce</b> → <b>All</b>, vai pavelc tās kartīti no <b>Backlog</b> uz dēļa.</li>
<li><b>Pastaigas uz partnera vietu:</b> <b>Warehouse</b> → <b>Dispatching</b> → cilne <b>Pre-pick</b>, skaties <a href="/docs/gathering-a-partners-goods-lv">Partnera preču savākšana</a>.</li>
<li><b>Nosūtīt savāktās preces:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>, vai ļauj to izdarīt partnera sūtījumu grafikam.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Nepieciešamās tiesības</strong>
<ul>
<li>Amati tiek piešķirti darbinieka kartītē sadaļā Human Resources un ar sevi nes tiesības.</li>
<li>Saraksta redzēšanai: jebkurš ražotnes amats, kas var skatīt ražošanu, vai organizācijas vadītājs.</li>
<li>Iepriekšējai komplektēšanai un Close short: ražotnes amats, kas plāno vai sagatavo ražošanu, iepirkumu redaktors vai organizācijas vadītājs.</li>
<li><b>Can't be done</b>: ražotnes amats, kas plāno vai sagatavo ražošanu, vai organizācijas vadītājs. Skaties <a href="/docs/factory-positions-lv">Ražotnes amati</a>.</li>
</ul>
</aside>
