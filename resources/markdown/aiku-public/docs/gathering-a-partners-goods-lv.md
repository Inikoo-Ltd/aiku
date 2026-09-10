---
title: Partnera preču savākšana
summary: Noliktavas rokasgrāmata - ko nozīmē pre-pick, kāpēc krājums partnera savākšanas vietā vairs neskaitās kā pieejams, un kā strādāt ar Pre-pick sarakstu sadaļā Dispatching.
date: 2026-09-09
source_date: 2026-09-09
tags: dispatch, procurement, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 8
---

<aside class="tldr">
Noliktavai. Daļu no tā, ko pieprasa partnerorganizācija, šeit vispār neražo - pudeles, maisiņi, kastes, nūjiņas. Nav ko ražot: kādam vienkārši jānoņem tas no plaukta un jānoliek attiecīgā partnera vietā. Šo pastaigu sauc par <b>pre-pick</b> (iepriekšēju komplektēšanu), vieta ir <b>goods out gathering location</b> (izejošo preču savākšanas vieta), un no brīža, kad krājums tur atrodas, tas vairs neskaitās kā pieejams nevienam citam. Tavs veicamo pastaigu saraksts ir <b>Dispatching → Pre-pick</b>.
</aside>

## Kāpēc pre-pick pastāv

Partnerorganizācijas pieprasījums uzreiz nekļūst par pasūtījumu. Tas paliek sarakstā, kamēr kāds no šīs puses ar to kaut ko izdara, un par pasūtījumu, piegādes pavadzīmi un stock delivery tas kļūst tikai tad, kad tiek nosūtīts noliktavai.

Tas rada atstarpi. Partneris šodien pieprasa 490 pudeļu un vāciņu komplektus, tie tiks sūtīti tikai pēc nedēļas, un pa to laiku nekas neliedz šīs pudeles pārdot vai izmantot citur. Nekas aiku sistēmā pats no sevis krājumu netur atpakaļ - ne pieprasījums, ne pasūtījums, pat ne piegādes pavadzīme. Vienīgais, kas krājumu tiešām rezervē, ir tā pārvietošana vietā, no kuras to nevar paņemt.

Tieši tāpēc pastāv **goods out gathering location**: parasta noliktavas vieta, atzīmēta kā savākšanas punkts vienam partnerim. Kas tajā atrodas, pieder viņiem.

## Ko nozīmē abi jēdzieni

- **Pre-pick** - preces noņemšana no plaukta iepriekš un novietošana attiecīgā partnera vietā, pirms pasūtījums vēl kaut kur dodas. Ražotnes pusē tas nozīmē arī "šo mēs neražosim, ņemsim no krājuma".
- **Goods out gathering location** - vieta, kas atzīmēta tā, lai viss, kas tajā atrodas, vairs neskaitītos kā pieejams. Krājums joprojām ir mūsu, joprojām tiek skaitīts, novērtēts un auditēts. Tas ir vienkārši jau kādam apsolīts.

## Veicamo pastaigu saraksts

**Warehouse → Dispatching → Pre-pick.**

Katra rinda ir viena pastaiga:

| Kolonna | Ko tā rāda |
| --- | --- |
| For | kurai partnerorganizācijai preces paredzētas |
| SKO | ko atnest - kods un nosaukums |
| From | vieta, ko sistēma iesaka - tā, kurā ir visvairāk |
| To | attiecīgā partnera savākšanas vieta |
| Staged | cik daudz jau atrodas vietā |
| To move | cik daudz vēl jāatnes |

Atnes preces, noliec tās vietā, tad spied **Moved** (Pārvietots). Tas šo pārvietojumu ieraksta aiku sistēmā, rinda pati pazūd, un daudzums izkrīt no pieejamības.

Saraksts katru reizi tiek pārrēķināts no jauna, atverot to - tas nav uzdevumu kopums, ko kāds atzīmē vai sakārto. Ja krājums jau atrodas vietā, rindas tur vienkārši nav. Ja kāds pieprasījumam pievieno vairāk, rinda parādās atkal.

Cilne parādās tikai organizācijām, kurām partnerim ir izveidota savākšanas vieta. Ja to neredzi, tas vēl nav izdarīts.

## Ražotnes puse tam pašam darbam

Pastaigas no kaut kurienes rodas: kādam ražotnē jāizlemj, ka rinda tiek ņemta no krājuma, nevis ražota. Šim lēmumam ir sava lapa, **Factory → Pre-pick** (Ražotne → Iepriekšēja komplektēšana), un tā ir iepriekšējā saraksta dvīnis.

Tā uzskaita katru atklāto partnera rindu, kurai ir krājums aizmugurē, neatkarīgi no tā, vai šī ražotne šo artefaktu izgatavo, un nekad nerāda rindu, kas jau iepriekš komplektēta. Katra rinda nes pieprasītāju, artefaktu, cik **pieprasīts**, cik ir **krājumā** un cik **var komplektēt** - abus ierobežojot vienu ar otru, lai rindai nekad neapsola vairāk, nekā ir. Kategorija, pieprasītājs un steidzamība filtrē sarakstu, un skaitļi uz filtriem ir īstie skaitļi, nevis tikai tas, kas iekļaujas lapā.

**Pre-pick** uz rindas, **Pre-pick selected** (Iepriekš komplektēt atzīmētās) atzīmētajām, vai **Pre-pick all** (Iepriekš komplektēt visas) visam, ko rāda pašreizējie filtri. Iepriekšēja komplektēšana apsola krājumu šim partnerim un liek pastaigu noliktavas sarakstā; ja pieejama tikai daļa no pieprasītā, rinda sadalās - apsolītā daļa aiziet, pārējais paliek atklāts. Nekas netiek pārdots un netiek izveidots neviens pasūtījums - krājums vienkārši pārstāj būt pieejams jebkuram citam.

Skaitlis blakus **Pre-pick** ražotnes sānjoslā rāda, cik rindu gaida šo lēmumu, un tas atjaunojas pats.

## Ko redz partneris

Nekas nav jāpasaka ar roku. Savā iepirkumu sarakstā katrai rindai ir redzams tās statuss: **Requested**, **Being made**, **Pre-picked**, **Staged for you**, **Being picked**, **On its way** - blakus ar darba uzdevuma, pasūtījuma vai piegādes pavadzīmes numuru.

**Staged for you** nozīmē tieši to, ko tu izdarīji: viņu preces atrodas viņu savākšanas vietā, gaidot nākamo nosūtīšanu.

## Kad tas tiešām tiek nosūtīts

Savākšana nav nosūtīšana. Preces izbrauc, kad kāds nosūta sakomplektēto pasūtījumu noliktavai lapā **To produce** (Ražojamie darbi), kas savāktos pieprasījumus pārvērš par pasūtījumu, piegādes pavadzīmi un stock delivery partnera pusē. Skaties [Darbs ar sarakstu To produce](/docs/fulfilling-partner-orders-lv).

Tā kā preces jau atrodas vienā vietā, komplektēšana tajā brīdī ir pastaiga uz vienu vietu, nevis apstaigāšana pa visu noliktavu.

## Noderīgi zināt

- **Savākšanas vieta nav uzglabāšana.** Viss, kas tur atstāts, ir neredzams visiem pārējiem - to nepiedāvās komplektētājam un tas neparādīsies kā pieejams pārdošanai. Preces tur liec tikai tad, ja tās iet pie šī partnera.
- **Esošas vietas atzīmēšana uzreiz maina skaitļus.** Ja vietā jau ir krājums, kad to atzīmē par savākšanas punktu, šis krājums uzreiz izkrīt no pieejamības. Pirms atzīmēšanas pārbaudi, kas vietā atrodas.
- **Nekas cits nerezervē.** Diviem cilvēkiem var teikt, ka tās pašas vienības ir brīvas, kamēr kāds tās nav faktiski aiznesis uz savākšanas vietu. Ja kaut ko nedrīkst pārdot partnerim no zem rokas, pārvieto to.
- **Pārvietojot atpakaļ, tas atbrīvojas.** Izņem krājumu no savākšanas vietas vai noņem vietai savākšanas atzīmi, un daudzums atgriežas pieejamībā.
- **Viena vieta katram partnerim** ir ierastā kārtība, nosaukta partnera vārdā, lai komplektētājs to var uzreiz atpazīt.

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Izlemt, ka rinda tiek ņemta no krājuma:</b> tava organizācija → <b>Factory</b> → <b>Pre-pick</b> → <b>Pre-pick</b> uz rindas, vai atzīmē un izmanto <b>Pre-pick selected</b> / <b>Pre-pick all</b>.</li>
<li><b>Veicamo pastaigu saraksts:</b> tava organizācija → <b>Warehouse</b> → <b>Dispatching</b> → cilne <b>Pre-pick</b>.</li>
<li><b>Ieraksti pastaigu:</b> spied <b>Moved</b> uz rindas, kad preces fiziski atrodas vietā.</li>
<li><b>Pārbaudi, kas atrodas vietā:</b> <b>Warehouse</b> → <b>Locations</b> → vieta → cilne <b>SKOs</b>.</li>
<li><b>Atzīmē vietu kā savākšanas punktu:</b> <b>Warehouse</b> → <b>Locations</b> → vieta → <b>Overview</b> → <b>Goods out gathering</b>.</li>
<li><b>Norādi partnerim viņa vietu:</b> ne no ekrāna - jautā administratoram, tas apzināti iestatīts no konsoles, lai to nevarētu nejauši mainīt.</li>
<li><b>Nosūti savāktās preces:</b> <b>Factory</b> → <b>To produce</b> → <i>Picked orders</i> → <b>Send to warehouse</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Nepieciešamās tiesības</strong>
<ul>
<li>Amati tiek piešķirti darbinieka kartītē sadaļā Human Resources un ar sevi nes tiesības.</li>
<li>Saraksta redzēšanai un pastaigas ierakstīšanai: dispatching amats noliktavai vai organizācijas vadītājs.</li>
<li>Vietas atzīmēšanai par savākšanas punktu: noliktavas amats, kas var rediģēt vietas.</li>
<li>Partnera norādīšanai uz vietu: administrators, no konsoles.</li>
</ul>
</aside>
