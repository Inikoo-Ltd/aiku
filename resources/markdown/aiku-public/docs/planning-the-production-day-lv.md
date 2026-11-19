---
title: Ražošanas dienas plānošana
summary: Ražošanas vadītājam un maiņas vadītājam - darbu ievietošana dienas plānā, nenorādot nevienu cilvēku, katra soļa nodošana strādniekam un kas notiek ar darbu, kas savā dienā nav pabeigts.
date: 2026-10-15
source_date: 2026-11-19
tags: production, planning, floor
category: production
series: Ordering from partners
order: 13
---

<aside class="tldr">
Divi cilvēki, divi uzdevumi. <b>Ražošanas vadītājs</b> izlemj, ko izgatavo konkrētā dienā: dēlī <b>To produce</b> (Ražojamie darbi) viņa izvēlas kartītes un nospiež <b>Plan for this day</b> (Ieplānot šai dienai), nenorādot nevienu cilvēku. <b>Maiņas vadītājs</b> atver <b>Operations → Day plan</b> (Darbības → Dienas plāns) un katru ieplānotā darba soli nodod kādam strādniekam. Katrs strādnieks pēc tam savus soļus redz ražotnes ekrānā. Viss, kas savā dienā nav pabeigts, paliek plānā oranžā krāsā, līdz kāds to apstiprina jaunai dienai.
</aside>

## Vadītājs plāno dienu

Sadaļā **Factory → To produce** (Ražotne → Ražojamie darbi) dēlis darbojas kā iepriekš: kartītes iet no **Backlog** (Rindā) uz **Preparing** (Sagatavošanā), kur norāda daudzumu, un no **Preparing** uz **Assigned** (Piešķirts), kur tiek izveidots darba uzdevums.

Kad kartīti nomet uz **Assigned**, nelielais logs ar jautājumu *Who makes it?* (Kas to izgatavo?) tagad sākas ar datumu un pogu:

1. Izvēlies dienu. Logs atveras ar šodienu.
2. Nospied **Plan for this day**.

Tiek izveidots darba uzdevums šai dienai, un uz tā nav norādīts neviens cilvēks. Katra kartīte kļūst par savu darba uzdevumu, tāpēc katru var sakārtot un pārvietot atsevišķi. Ja vairākas kartītes atlasa un nomet kopā, tās tiek ieplānotas kopā, katra ar savu darba uzdevumu.

Vārda izvēle no saraksta zemāk darbojas tāpat kā vienmēr: viss darbs nonāk pie šī cilvēka, un tas nav nevienas dienas plānā.

Arī darbu, kam jau ir norādīts cilvēks, var ielikt plānā: nospied zīmuli blakus vārdam uz tā kartītes un tad **Plan for this day**. Vārds no darba tiek noņemts, un tā soļus izdala maiņas vadītājs.

## Maiņas vadītājs izdala soļus

**Factory → Operations → Day plan** uzskaita dienai ieplānotos darbus, katram produktam darbā viena rinda.

| Kolonna | Nozīme |
| --- | --- |
| Order | darba vieta dienā - ieraksti skaitli, lai to pārvietotu |
| Job | darba uzdevums un tā stāvoklis |
| Product | kas tiek izgatavots |
| Qty | cik daudz |
| Steps and operators | katrs receptes solis: līdz šim izgatavots / prasīts, un kam tas nodots |
| Planned | diena, kurai tas ieplānots |

Katram solim ir savs vārdu saraksts. Izvēlies vienu, un šis solis parādās šī cilvēka ražotnes ekrānā un nevienam citam. Solis ar oranžu apmali vēl nav nevienam nodots; josla augšā tos saskaita, *3 steps without an operator* (3 soļi bez strādnieka), lai nekas netiktu aizmirsts.

Lai mainītu, kas veic soli dienas laikā, izvēlies citu vārdu. Lai soli atņemtu cilvēkam, nospied mazo krustiņu sarakstā. Ja cilvēks jau bija sācis, viņa padarītais darbs paliek reģistrēts zem viņa vārda: saraksts nosaka tikai to, kam solis būs redzams turpmāk.

Datums augšā pārslēdz uz citu dienu. Rītdienas plānu var izdalīt jau šodien.

## Dienas gaitas uzraudzība

Tā pati lapa rāda, kā diena virzās, un atjaunojas pati:

- josla augšā: cik darbu, *12 of 18 steps done* (12 no 18 soļiem pabeigti), soļi bez strādnieka, darbi, kas savā dienā nav pabeigti;
- blakus katram solim: pelēks aplis (nav sākts), oranžs griežas aplis (sākts) vai zaļa ķeksīte (pabeigts) ar līdz šim izgatavoto skaitu;
- *working now* (strādā tagad) un vārds, kad kāds solis ir atvērts ražotnes ekrānā.

Cilvēki, kas lapu redz, bet nevada ražotni, redz vārdus, nevis sarakstus.

## Kad maiņas vadītāja nav

Ražošanas vadītājs atver to pašu lapu un soļus izdala pats. Plānošanai un soļu izdalīšanai vajadzīgs viens un tas pats amats.

## Kas netika pabeigts

Ieplānots darbs, kuram pēc savas dienas vēl ir atvērti soļi, netiek aizmirsts. No nākamās dienas tas parādās sadaļā Day plan ar savu veco datumu oranžā krāsā, un josla to saskaita kā *not finished on its day* (savā dienā nepabeigts). Katram no tiem izvēlies:

- **Confirm for** (Apstiprināt) dienu, kuru skati - darbs pāriet uz šīs dienas plānu, secības beigās;
- **Take off the plan** (Noņemt no plāna) - darbs paliek kā darba uzdevums, bet nav nevienas dienas plānā.

Kamēr nav nospiesta viena no abām, darbs katru dienu paliek tavā redzeslokā. Tā soļi paliek arī to strādnieku ražotnes ekrānos, kuriem tie nodoti, tāpēc tas, kurš vakar pārtrauca maisījumu, to var turpināt šorīt.

Kad strādnieks aizver darbu pie mazāka daudzuma un atlikumu pārceļ uz jaunu darbu (skaties [Darbs ar ražotnes ekrānu](/docs/working-the-floor-screen-lv)), jaunais darbs saglabā ieplānoto dienu, vietu secībā un katra soļa strādnieku.

## Kad produktu izgatavo no cita produkta

Daži produkti tiek izgatavoti no cita ražotnes produkta: Puckator liela vannas bumba (PuckJBB-04) ir JBB-04 vannas bumba no krājuma, kas ievilkta plēvē un apzīmēta ar etiķeti. Ja tai krājumā nepietiek JBB-04, kartīte dēlī **To produce** (Ražojamie darbi) un tās darbs sadaļā **Day plan** (Dienas plāns) to norāda oranžā krāsā, piemēram, *Needs 3 SKO JBB-04, not in stock: make JBB-04 first* (Vajag 3 SKO JBB-04, krājumā nav: vispirms izgatavo JBB-04). Plānā jau esošie darbi krājumu ņem pirmie, pēc tiem - pieprasījumu kartītes, vecākās pirmās.

Nospied **Create JBB-04 job** (Izveidot JBB-04 darbu) blakus ziņojumam: atveras logs **Create job order** (Izveidot darba uzdevumu), kurā JBB-04 un trūkstošie SKO jau ir ievadīti, ar piezīmi, kuram produktam tas paredzēts. Pārbaudi, izvēlies strādnieku un saglabā. Kad JBB-04 darbs krājumam ir izveidots, ziņojums kļūst zils, *Waits for 3 SKO JBB-04 from* (Gaida 3 SKO JBB-04 no) jaunā darba, līdz vannas bumbas ir izgatavotas. JBB-04 darbs, kas izgatavots klienta vai partnera pasūtījumam, neskaitās, jo šīs vannas bumbas jau ir apsolītas.

Ziņojums pazūd, kad solis, kas izmanto JBB-04, ir pabeigts.

## Materiālu pieprasīšana noliktavai

Kad ražotnei kaut kas vajadzīgs no noliktavas, ikviens, kas strādā ražošanā, to pieprasa **Factory → Operations → Materials check** (Ražotne → Darbības → Materiālu pārbaude) augšdaļā, sadaļā **Ask the warehouse** (Pieprasīt noliktavai). Katra rinda ir:

| Lauks | Ko ievadīt |
| --- | --- |
| SKO | ieraksti kodu vai nosaukumu; saraksts rāda, cik SKO ir noliktavā un ražotnē |
| Quantity and unit | kā to teiktu noliktavai: *3 Boxes*, *1 Pallet*, *80 Bags*, *2 SKOs* vai *All there is* |
| Drop point | kur ražotnē to atstāt (*Storage room*, *Clean room*, *Bath bomb area*...) |
| Urgent | noliktava steidzamās rindas redz pirmās |
| Note | viss, kas noliktavai jāzina (*250ml*, *lūdzu, ārā uz paletēm*) |

**Add line** (Pievienot rindu) pieprasa vairākas lietas uzreiz, un **Send to warehouse** (Nosūtīt noliktavai) tās nosūta. **Asked this week** (Pieprasīts šonedēļ) zemāk rāda katru pieprasījumu ar to, kas to pieprasīja un kad, un katrai rindai, vai tā ir *To bring* (Jāatved), *Delivered* (Piegādāts; cik SKO, kas, kad) vai *Can't bring* (Nevar atvest) ar noliktavas norādīto iemeslu.

Piegādes vietu sarakstu ar pogu **Drop points** (Piegādes vietas) uztur vadītājs un uzraugs: pievienot, pārdēvēt, pārvietot uz augšu un uz leju vai dzēst. Izdzēsta piegādes vieta saglabā savu nosaukumu jau nosūtītajos pieprasījumos.

## Rīta materiālu pārbaude

Noliktava materiālus uz ražotni atved veselās kastēs, nevis precīzos dienas daudzumos. **Factory → Operations → Materials check** (Ražotne → Darbības → Materiālu pārbaude) uzskaita to, kas dienas plānam vēl vajadzīgs, lai ražotne pārbauda, kas ir uz vietas, un pārējo noliktavai pasūta vienā skaidrā sarakstā.

Katra rinda ir viens materiāls, saskaitīts pa visiem dienas darbiem, ar darbiem, kam tas vajadzīgs (*JOaroma-0141, JOaroma-0145*). Tiek skaitīti tikai vēl neizpildītie soļi: kad izgatavošana ir pabeigta un atlicis tikai iepakošana, rindā redzamas kastes un etiķetes, bet vairs ne pudeles. Maisījumi tiek gatavoti ražotnē, un sarakstā tos nav. Daudzumi nāk no receptēm, tāpēc darbam, kura receptē nav norādīts neviens materiāls, nekas nav vajadzīgs: oranžā josla virs saraksta nosauc šos darbus, lai receptes trūkums būtu redzams.

| Kolonna | Nozīme |
| --- | --- |
| Material | materiāls un noliktavas krājuma kods |
| For jobs | dienas darbi, kuriem tas vajadzīgs |
| Needed | cik daudz patērē vēl veicamie soļi |
| At the factory | cik šobrīd ir ražotnes atrašanās vietā un cik šodien jau pasūtīts |
| Check | atzīmē **Have enough** (Pietiek) vai ieraksti aptuveni, cik trūkst |

Kad ierakstīts trūkstošais skaits, rindā redzams, ko noliktava atvedīs, noapaļojot uz augšu līdz tās kastēm: trūkst 1 200, kastē 500 - *bring 3 boxes (1,500)* (atvest 3 kastes (1 500)). Sarkans *Warehouse short* (Noliktavā nepietiek) nozīmē, ka noliktavā tik daudz nav, un to uzzini uzreiz.

Nospied **Send to warehouse** (Nosūtīt noliktavai). Trūkstošās rindas aiziet noliktavai kā viens pieprasījums, un pieprasījums parādās zem **Asked this week**, katrs materiāls ar atzīmi *To bring* (Jāatved), *Delivered* (Piegādāts) vai *Can't bring*. Ja visu atzīmē kā pietiekošu un nosūta, tiek reģistrēts, ka pārbaude ir veikta. Otra pārbaude vēlāk dienā pievieno tikai to, kas vēl trūkst.

Pārbaudi veic vadītājs vai maiņas vadītājs, vai arī nodod to strādniekam ar **Checked by** (Pārbaudīja) augšā. Šis strādnieks tad ražotnes ekrānā redz atgādinājumu, kas atver pārbaudi.

## Noliktava atved materiālus

Noliktavā karte **For production** (Ražošanai) zem **Action needed** (Nepieciešama rīcība) sadaļā **Inventory** (Krājumi) rāda, cik rindu gaida ražotne, steidzamās uz mazā nozīmītes. Tā atver **Inventory → For production**: katra rinda, ko vēl jāatved, vispirms steidzamās, tad vecākās, ar to, kas pieprasīts, piegādes vietu, kas pieprasīja un kad, piezīmi un plauktu, no kura ņemt. Komplektētājs izvēlas plauktu, ieraksta atvesto SKO skaitu un nospiež **Delivered**. Ja noliktava to nevar atvest, **Can't bring** jautā iemeslu (*out of stock*, *ordered from supplier*) un paziņo ražotnei. **Done** (Pabeigts) uzskaita, kas atvests vai atteikts, un cik ilgi tas aizņēma.

Ja Aiku plauktā rāda mazāk SKO, nekā atvests, piegāde tāpat notiek: tas, kas bija Aiku, pārceļas uz ražotni, bet pārējais tiek atzīmēts kā *not in Aiku, count it* (nav Aiku, saskaiti), lai SKO tiktu saskaitīts.

Piegādātais krājums no plaukta pāriet uz ražotnes atrašanās vietu. Tas paliek krājums: to skaita un redz kā agrāk, un klienta pasūtījums to tur joprojām var komplektēt, ja noliktavas plaukti ir tukši, vienmēr vispirms ņemot no plauktiem. Neizmantotos materiālus var pārvietot atpakaļ uz plauktu no ražotnes atrašanās vietas lapas, tāpat kā jebkuru citu pārvietošanu starp atrašanās vietām.

Kad pabeigts darbs tiek pieņemts krājumā, tā materiāli vispirms tiek norakstīti no ražotnes atrašanās vietas pēc receptes daudzumiem un tikai tad no citām vietām. Ražotnes atrašanās vietu laiku pa laikam saskaiti, lai izlabotu bojājumus un zudumus.

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Ieplānot darbu dienai:</b> <b>Factory</b> → <b>To produce</b> → nomet kartīti uz <b>Assigned</b> → izvēlies dienu → <b>Plan for this day</b>.</li>
<li><b>Nodot soli strādniekam:</b> <b>Factory</b> → <b>Operations</b> → <b>Day plan</b> → saraksts blakus solim.</li>
<li><b>Mainīt dienas secību:</b> ieraksti skaitli laukā <b>Order</b>.</li>
<li><b>Vakardienas nepabeigtais darbs:</b> <b>Confirm for</b> dienu vai <b>Take off the plan</b>.</li>
<li><b>No dēļa uz plānu:</b> saite <b>Day plan</b> virs dēļa.</li>
<li><b>Pieprasīt kaut ko noliktavai:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Ask the warehouse</b> → <b>Send to warehouse</b>.</li>
<li><b>Mainīt piegādes vietas:</b> <b>Materials check</b> → <b>Drop points</b>.</li>
<li><b>Pārbaudīt dienas materiālus:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Have enough</b> vai trūkstošais skaits → <b>Send to warehouse</b>.</li>
<li><b>Nodot pārbaudi strādniekam:</b> <b>Materials check</b> → <b>Checked by</b>.</li>
<li><b>Atvest materiālus (noliktava):</b> <b>Inventory</b> → <b>For production</b> → <b>Delivered</b> vai <b>Can't bring</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Nepieciešamās tiesības</strong>
<ul>
<li>Amati tiek piešķirti darbinieka kartītē sadaļā Human Resources un ar sevi nes tiesības.</li>
<li>Dienas plānošana, soļu izdalīšana, secības maiņa: <b>Floor supervisor</b> šai ražotnei.</li>
<li>Dienas plāna skatīšana bez izmaiņām: <b>Foreman</b>.</li>
<li>Savam vārdam nodota soļa izpilde: <b>Operative</b>, ražotnes ekrānā.</li>
<li>Materiālu pieprasīšana noliktavai: ikviens, kas redz ražotnes Operations.</li>
<li>Materiālu pārbaude un piegādes vietas: <b>Floor supervisor</b>; pārbaudi arī strādnieks, kuram tā nodota.</li>
<li>Materiālu atvešana uz ražotni: noliktavas amats, kas strādā noliktavā.</li>
</ul>
</aside>
