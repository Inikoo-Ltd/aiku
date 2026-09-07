---
title: Partnera iepirkumu paneļa lasīšana
summary: Ekrāns, kas rāda, kas jāpērk no partnera un cik daudz vietas tam ir — trīs kapacitātes kartītes, krājuma seguma donuts ar astoņiem grozivām un pasūtījumu plūsma.
date: 2026-09-02
source_date: 2026-09-02
tags: procurement, intercompany, shopping-list, stock
category: procurement
series: Ordering from partners
order: 2
---

<aside class="tldr">
Šis panelis ir vieta, kur sākas katra pirkšanas sesija. Augšējā rinda rāda, cik daudz vietas tev ir — nauda un noliktavas vieta. Vidus rāda, kuri partnera produkti jāpasūta, vispirms sliktākie. Apakša rāda, kas jau ir ceļā. Nekas nav jāatceras — ekrāns pats pasaka, kam jāpievērš uzmanība. Pati pasūtīšana ir aprakstīta rakstā <a href="/docs/buying-from-a-partner-lv">Pirkšana no partnera</a>.
</aside>

Atver to **Procurement → Partners → {partner} → Shopping** (Iepirkums → Partneri → {partneris} → Iepirkšanās). Izmanto to, nevis mēģini atvērt iepirkumu sarakstu un atcerēties, kā trūka.

## Trīs kartītes augšā: cik daudz vietas tev ir

Šīs kartītes ir limiti, nevis dekorācija. Tās pastāv tāpēc, ka iepirkumu saraksts, kurā jebkurš var iemest jebko, zaudē jēgu — partneris, kas saņem tūkstoš rindu, nevar pateikt, kuras divas ir steidzamas.

- **Order budget used** (Izmantotais pasūtījuma budžets). Tava atklātā saraksta vērtība salīdzinājumā ar to, ko šis partneris tev tiešām piegādā vienā pasūtījuma ciklā, tavas organizācijas valūtā — katrs naudas skaitlis šajos ekrānos tev tiek pārrēķināts, tāpēc partnera valūtā domāt nekad nevajag. Ja ir pietiekami piegāžu vēstures, budžets tiek mērīts no reālām piegādēm; ja nav, tas ir viens pasūtījuma cikls no tā, ko tu tiešām pārdod no viņu produktiem. Šo skaitli neviens neieraksta pats — ne tu, ne tavs vadītājs. Kad josla ir pilna, kartīte saka **at capacity** (pie kapacitātes robežas).
- **Warehouse space** (Noliktavas vieta). Cik vietu ir brīvu no kopējā skaita, ar joslu, kas sadalīta pa *in use* (aizņemts), *inbound* (ceļā ar atklātiem pasūtījumiem un piegādēm) un *this shopping list* (ko aizņemtu šis iepirkumu saraksts). Zem tā — partnera taisnīgā daļa: cik daudz no brīvajām vietām drīkst izmantot viņu pavisam jauni produkti. Vietas tiek skaitītas kā slots — mums nav tilpuma datu, tāpēc mēs neizliekamies, ka mērām kubikmetrus.
- **Lead time** (Piegādes laiks). Kartīte ar partnera vārdu virsrakstā rāda viņu izmērīto **pasūtīts → ieskaitīts** laiku, no cik piegādēm tas izmērīts (vai piezīmi, ka tā vēl ir aplēse), cik daudz pasūtījumu viņi kavē un par cik, un cik liels ir viņu katalogs.

## Krājuma segums: donuts un astoņi grozi

Šī sadaļa aptver visu partnera katalogu, sadalītu astoņos grozos pēc tā, cik ilgi izturēs tavs pašreizējais krājums. Riskantie grozi ir izmērīti pēc izmērītā piegādes laika, nevis kalendāra nedēļām — tas ir viss būtiskais.

Tā sākas ar **donuta diagrammu**: katrs produkts katalogā, viena šķēle katram grozam, ar kopsummu centrā. Turi peli virs šķēles, lai redzētu skaitu un procentus; klikšķini uz šķēles — vai uz rindas leģendā tai blakus — lai pārlūkotu šo grozu partnera katalogā. Viens skatiens pasaka, vai šodien ir mierīga papildināšana vai ugunsgrēks: daudz sarkanā nozīmē problēmas, galvenokārt zaļš — tev viss kārtībā.

Zem diagrammas grozi sadalīti divās grupās. **Needs ordering** (Jāpasūta) satur piecus, kas prasa uzmanību:

- **Out of stock** (Nav krājumā) — plauktā nekas nav palicis.
- **Doomed** (Lemts trūkumam) — krājums vēl ir, bet tas beigsies, pirms varētu pienākt piegāde, pat ja pasūtītu tūlīt.
- **Critical / Danger / Watch** (Kritisks / Bīstams / Vērojams) — beigsies divu, trīs vai četru piegādes laiku robežās.

**Not for ordering** (Nav jāpasūta) satur pārējos trīs:

- **Covered** (Nodrošināts) — pagaidām kārtībā.
- **Dead stock** (Nekustīgs krājums) — nekas neietiek pārdošanā, nauda guļ plauktā; rinda rāda, cik tas vērts.
- **We never stocked** (Nekad neesam turējuši) — partneris to pārdod, bet tu to nekad neesi turējis krājumā.

Viens vienību veids šeit nekad neparādās: SKO, ko esi atzīmējis kā **On Demand** (Pēc pieprasījuma) savā krājumā. To krājums netiek uzskaitīts, tāpēc "nav krājumā" neko nenozīmētu — tos izlaiž gan panelis, gan groza tabulas, gan automātiskā aizpilde.

Katra kartīte atbild uz vienu jautājumu: **cik man vēl jāpievērš uzmanība?** Skaitlis "*N* need action" (N prasa darbību) ignorē visu, kas jau sarakstā vai jau ceļā, tāpēc tas samazinās, strādājot cauri. Zem tā tas pats skaitlis sadalīts pēc **ranga**: A produkti pirmie, D un Z izbalinātas beigās. Divi nulles atlikuma A produkti ir svarīgāki par pieci simti D produktiem, tāpēc tāda ir darba secība.

Trīs lietas, ko vari darīt no kartītes:

- **Click the number** (klikšķini uz skaitļa), lai atvērtu grozu kā tabulu: katra vienība, sarindota, ar viņu krājumu, tavu krājumu, kad beigsies un daudzuma lauku, kas raksta tieši iepirkumu sarakstā.
- **Click the bucket's name or a rank letter** (klikšķini uz groza nosaukuma vai ranga burta), lai pārlūkotu šos produktus partnera katalogā.
- **Fill** (Aizpildīt), lai atvērtu automātisko aizpildi, jau iezīmētu tam grozam un jau izveidotu — tu tikai pielāgo un apstiprini. Nedaudz vairāk darba nekā ar burvju pogu, bet daudz vairāk kontroles. Skaitļi kartītē — *N on the way · N on list* (N ceļā · N sarakstā) — rāda, cik daudz no groza jau esi apstrādājis.

Uz **Covered** un **Dead stock** kartītēm parādās sarkans brīdinājums, ja kaut kas no šī groza atrodas tavā iepirkumu sarakstā — tas ir krājums, kas tev nav vajadzīgs. **remove** (noņemt) vienā klikšķī notīra šīs rindas.

## Pasūtījumu plūsma

Apakšējā josla seko visam no vajadzības līdz plauktam: **on shopping list → being prepared → ready to ship → in transit → arrived, booking in** (sarakstā → tiek gatavots → gatavs nosūtīšanai → ceļā → pienācis, tiek ieskaitīts). Katra kolonna rāda savas piegādes un cik vienību tajās ir; katra kartīte atver piegādi tikai skatīšanai — pārdevēja noliktava to pārvalda, kamēr prece nav sasniegusi tevi.

Kartītes redzami noveco. Pēc trīskāršota piegādes laika tās kļūst dzeltenas; pēc desmitkāršota — sarkanas. Veca kartīte ir jautājums, kas jāuzdod partnerim, nevis skaitlis, uz ko lūkoties. Viss, kas patiešām kavējas, parādās arī sarakstā **Late from this partner** (Kavē šis partneris) apakšā, vispirms lielākais kavējums, ar atzīmi "no delivery date given" (piegādes datums nav norādīts).

## Kāpēc ekrāns dažreiz saka nē

Pievienošanu sarakstam var atteikt. Tas ir ar nolūku, un iemesli ir tikai trīs:

- **Sasniegts budžeta griesti** — vispirms noņem vai pazemini prioritāti kaut kam citam. Reāla ārkārtas situācija vienmēr iederas: **A ranga un nulles atlikuma preces ir atbrīvotas**, tāpēc ārkārtas gadījums nekad negaida aiz griestiem.
- **Noliktavas grīda** — ja brīvas mazāk par 5% vietu, neviena *jauna* produkta pievienot vairs nevar, no neviena partnera. Vienības, ko jau turi krājumā, papildina savas vietas un iet cauri brīvi.
- **Šī partnera taisnīgā daļa** — viens partneris var izmantot aptuveni piektdaļu no brīvajām vietām ar nekad neturētiem produktiem. Arī pārējiem piegādātājiem vieta ir vajadzīga.

Tas pats noteikums attiecas visur, kur pievieno — ar roku, masveidā vai no automātiskās aizpildes — tāpēc priekšlikumā nekad nav rindu, ko nevar apstiprināt.

## Izmērīts, vai godīgi apzīmēts kā minējums

Divi skaitļi virza lielāko daļu šī ekrāna: piegādes laiks un budžets. Noteikums abiem ir vienāds. **Ja ir vēsture, skaitlis ir izmērīts un to nevar rediģēt.** Ja vēstures nav, kartīte to pasaka, un minējumu var rediģēt — bet iestatījumos, nevis tieši panelī: katram produktam atsevišķi **estimated delivery time** (aplēstais piegādes laiks) piegādātāja produktā vai paša SKO iestatījumos. Tiklīdz uzkrājas pietiekami daudz reālu piegāžu, izmērījums pārņem, un aplēses lauks pazūd. Neviens nedrīkst pārrakstīt to, kas patiešām noticis.

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Panelis:</b> tava organizācija → <b>Procurement → Partners</b> → atver partneri → <b>Shopping</b>.</li>
<li><b>Pāriet no donuta:</b> klikšķini uz šķēles vai rindas leģendā, lai pārlūkotu šo grozu katalogā.</li>
<li><b>Strādāt ar grozu:</b> klikšķini uz kartītes skaitļa vienību tabulai, uz ranga burta, lai pārlūkotu šos produktus, vai <b>Fill</b> iezīmētam automātiskās aizpildes priekšlikumam.</li>
<li><b>Iztīrīt sarakstu:</b> <b>remove</b> uz Covered vai Dead stock kartītes.</li>
<li><b>Labot piegādes laika aplēsi:</b> SKO iestatījumos vai piegādātāja produkta iestatījumos — tikai kamēr tur vēl rakstīts <i>estimate</i>.</li>
</ul>
</aside>
