---
title: Pirkšana no partnera
summary: Pircēja rokasgrāmata - sāc no iepirkumu paneļa, aizpildi sarakstu ar roku, no partnera kataloga vai ar automātisku aizpildi, un saņem preci, kad tā pienāk.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, intercompany, shopping-list
category: procurement
series: Ordering from partners
order: 3
---

<aside class="tldr">
Cilvēkiem, kas <em>pasūta</em> preces no partneriem. Tu uzturi vienu atklātu sarakstu ar to, kas vajadzīgs tavai organizācijai; partneris to izpilda savā tempā. Sāc ar <a href="/docs/reading-the-partner-shopping-dashboard-lv">iepirkumu paneli</a>, lai redzētu, kas ir riskā un cik daudz vietas tev ir, tad pievieno rindas ar roku, no viņu kataloga, vai ļauj automātiskai aizpildei piedāvāt papildināšanu budžeta ietvaros. Jauns šajā plūsmā? Sāc ar <a href="/docs/ordering-from-a-partner-organisation-lv">pārskatu</a>.
</aside>

## Sāc no paneļa

**Procurement → Partners → {partner} → Shopping** (Iepirkums → Partneri → {partneris} → Iepirkšanās) atver [iepirkumu paneli](/docs/reading-the-partner-shopping-dashboard-lv): kas drīz beigsies, kas jau ir ceļā, un divus limitus, kuros tavs saraksts dzīvo — **order budget** (pasūtījuma budžets) šim partnerim un pieejamā **warehouse space** (noliktavas vieta). Strādā ar riska kartītēm no turienes, un lielākā daļa saraksta uzrakstās pati; viss zemāk aprakstīts par to, kā saraksts uzvedas, kad esi tajā iekšā.

## Iepirkumu saraksts

Blakus panelim cilne **Shopping list** (Iepirkumu saraksts) satur visas atklātās rindas.

- **Add stocks** (Pievienot preces) atver partnera preču sarakstu ar viņu pieejamību, iepakojumu, tavu pašreizējo krājumu un cik daudz esi izmantojis pēdējos četros ceturkšņos. Daudzumi ir pārdevēja nosūtīšanas vienībās (SKO).
- Katra rinda uzreiz stāsta krājuma stāstu — *viņu krājums*, *mūsu krājums* un kad *mums beigsies* — plus summa tavā pirkšanas cenā, ar atklāto vienību kopsummu tabulas apakšā.
- Atklātās rindas pilnībā pieder tev: izvēlies **priority** (prioritāti, no zema līdz steidzamam) tieši nolaižamajā izvēlnē tabulā vai noņem rindu ar tās papīrgrozu. Lai mainītu daudzumu, izmanto **Browse** (Pārlūkot) — tā paša produkta soļotājs tur tieši rediģē atklāto rindu. Tiklīdz partneris rindu nokomplektē, tā tiek slēgta, un tās statuss parāda, kur tā atrodas.

## Partnera kataloga pārlūkošana

Blakus iepirkumu sarakstam ir cilne **Browse** (Pārlūkot): visa partnera katalogs kā veikals, ar reāllaika krājumu un cenām. Pārvietojies pa to ar **Departments** (Nodaļas) vai **Collections** (Kolekcijas), ej dziļāk uz saimēm vai vienkārši raksti meklēšanas laukā. Katra produkta kartīte rāda pašreizējo cenu, nozīmīti **Their stock** (Viņu krājums) ar partnera pieejamo daudzumu un — priekšmetiem, ko izmanto — tavus pašus skaitļus: *mūsu krājums*, *mūsu pārdošana / ceturksnī* un *mums beigsies pēc* tik daudz dienām (sarkanā krāsā, ja tas ir divas nedēļas vai mazāk).

Divas lietas par šo katalogu ir vērts zināt. Cenas ir **tavas, nevis plaukta**: pārdevēja saraksta cena ar jau atskaitītu tavu starporganizāciju atlaidi, pārrēķināta tavas organizācijas valūtā — tātad tas, ko lasi, ir tas, ko teiks rēķins. Un tajā ir arī produkti, ko partneris izgatavojis **tikai tev** — rindas, kas nekad neparādās viņu publiskajā veikalā, bet pastāv tavai organizācijai. Ja neatrodi kaut ko, ko gaidīji, par to ir vērts pajautāt; ja atrodi kaut ko negaidītu, tas, visticamāk, ir tavs pēc vienošanās.

Pasūtīšana notiek tieši uz kartītes: daudzuma lauks **ir** tavs iepirkumu saraksts. Ieraksti vai palielini skaitli, un rinda tiek pievienota vai atjaunināta atklātajā sarakstā; iestati atpakaļ uz 0, un rinda tiek noņemta. Blakus tam pārtraukts **suggested** (ieteiktais) žetons rāda daudzumu, ko aiku pasūtītu — viens klikšķis aizpilda lauku ar to.

Kamēr pārlūko, tavs iepirkumu saraksts brauc līdzi kā kvīts, piestiprināts labajā pusē — katra rinda sagrupēta pēc saimes, ar kopsummu — tāpēc tu vienmēr zini, kur pasūtījums atrodas. **Go to Shopping list** (Doties uz iepirkumu sarakstu) aizved atpakaļ uz pilno rediģējamo sarakstu.

<figure><img src="/art/docs/draw-partner-browse.svg" alt="Akvareļa skice no partnera kataloga pārlūka: meklēšanas lauks, cilnes Departments un Collections, produktu kartītes ar plusa pogām un iepirkumu saraksta kvīts piestiprināta labajā pusē ar kopsummu" width="1200" height="750" loading="lazy"><figcaption>Partnera veikals, ar tavu sarakstu līdzi braucot.</figcaption></figure>

## Automātiskā aizpilde: budžets un, ja vēlies, instrukcija

**Auto-fill** (Automātiskā aizpilde) pastāv, lai papildināšana nebūtu atkarīga no tā, vai kāds atceras katru vienību. Tu dod tai vienu skaitli — **budžetu** tajā pašā valūtā, kādā pērc — un tā izveido priekšlikumu, kas tajā iekļaujas:

- Tā aplūko katru vienību, ko partneris var piegādāt un ko tu tiešām izmanto, sarindo pēc tā, **cik drīz tev beigsies** (tā pati *mums beigsies pēc* prognoze, ko redzi pārlūkojot), un vispirms papildina to, kam beigsies visdrīzāk, katru ar tā ieteicamo pasūtījuma daudzumu.
- Katra piedāvātā rinda rāda savu **iemeslu** ("Mūsu pārdošana/ceturksnī ~48 · mūsu krājums 0 · beigsies tagad"), daudzumu un izmaksas, tāpēc redzi, kāpēc tā tur ir. Daudzumi seko tai pašai prognozei, kas ir *suggested* (ieteikto) žetonos sadaļā Browse.
- **Instruction box** (instrukcijas lauks) ir neobligāts un pieņem vienkāršu valodu: *"prioritāri ēteriskās eļļas, izlaid visu, kā ir vairāk par 8 nedēļām"*, *"fokusējies uz svecēm, nekā sezonāla"*. Mākslīgais intelekts izlasa tavu instrukciju kopā ar to pašu izlietojuma datu un attiecīgi pārveido priekšlikumu — bet rezultāts pirms parādīšanas tiek pārbaudīts pret realitāti: daudzumi ir ierobežoti ar to, kas partnerim tiešām ir, un kopsumma tiek piespiedu kārtā ievietota tavā budžetā. Ja instrukciju nevar izpildīt, tu saņem standarta priekšlikumu.
- **Nekas netiek pievienots pats no sevis.** Priekšlikums ir atzīmētu rindu kopa, ko vari noņemt no atzīmes, mainīt daudzumu vai izveidot no jauna ar citu budžetu vai instrukciju; tikai **Add items to shopping list** (Pievienot iepirkumu sarakstam) kaut ko apstiprina.
- **Dažas vienības atsakās.** SKO ar ieslēgtu **Do not auto order** (Nepasūtīt automātiski) (SKO rediģēšanas ekrānā, sadaļā Stock Data) priekšlikumā nekad neparādās — vienībām, ko Procurement vēlas turēt manuālā kontrolē. To joprojām vari pasūtīt ar roku no Browse vai preču saraksta; tikai automātiskais ceļš to izlaiž. SKO, kas atzīmēti kā **On Demand** (Pēc pieprasījuma), no partneru iepirkšanās ir izslēgti pavisam.

Automātisko aizpildi var atvērt arī jau iezīmētu: **+ fill** (+ aizpildīt) uz riska kartītes panelī atver to tikai tam grozam, ar jau izveidotu priekšlikumu. Tie paši noteikumi — tu pielāgo, noņem atzīmi un apstiprini; nekas netiek pievienots pats no sevis.

Laba paraža: strādā ar paneļa kartītēm no sliktākā uz labāko, tad palaid automātisko aizpildi vienreiz papildināšanas ciklā tam, kas atlicis, izlasi iemeslus, noņem atzīmi no tā, kam nepiekrīti, un pievieno pārējo.

## Kad saraksts saka nē

Pievienošana tiek atteikta trīs gadījumos, un tas ir ar nolūku: saraksts sasniedzis **budžeta** griestus šim partnerim (A ranga un nulles atlikuma preces ir atbrīvotas — ārkārtas gadījums vienmēr iederas), noliktavai palikuši mazāk par 5% brīvu vietu, vai šis partneris jau ir izmantojis savu taisnīgo daļu no brīvajām vietām ar produktiem, ko tu nekad neesi turējis krājumā. Risini ziņojumu, nevis meklē citu ceļu — tas pats slēdzis darbojas manuālai pievienošanai, masveida pievienošanai un automātiskajai aizpildei. [Paneļa raksts](/docs/reading-the-partner-shopping-dashboard-lv) izskaidro, no kurienes šie limiti nāk.

## Kad prece ir ceļā

Tiklīdz partneris [nosūta piegādi savai noliktavai](/docs/fulfilling-partner-orders-lv), pie tava partnera sadaļā **Stock deliveries** (Piegādes) parādās ienākoša **stock delivery** (piegāde). Atstāj to mierā, kamēr tā rāda statusu confirmed vai dispatched — tā spoguļo pārdevēja noliktavu un atjauninās pati. Kad kastes fiziski pienāk: **receive** (saņem), pārbaudi un novieto vietās tieši tāpat kā jebkurai piegādātāja piegādei. Ar visu, kas pienāk nepilnā apjomā vai bojāts, rīkojas pēc saņemšanas, pret saistīto rēķinu — skaties [pārskatu](/docs/ordering-from-a-partner-organisation-lv), kā strādā nauda.

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Redzēt, kas jāpērk:</b> tava organizācija → <b>Procurement → Partners</b> → atver partneri → <b>Shopping</b> (panelis) → strādā ar riska kartītēm.</li>
<li><b>Pievienot sarakstam:</b> <b>Shopping list</b> → <b>Add stocks</b>, vai <b>Browse</b> un iestati daudzumus uz produktu kartītēm, vai <b>Auto-fill</b> (vai <b>+ fill</b> uz paneļa kartītes) priekšlikumam.</li>
<li><b>Pielāgot atklātās rindas:</b> maini prioritāti vai dzēs rindas iepirkumu saraksta tabulā; maini daudzumus no produktu kartītēm sadaļā <b>Browse</b>.</li>
<li><b>Izslēgt vienību no automātiskās aizpildes:</b> tava organizācija → <b>Warehouse → Inventory</b> → atver SKO → <b>Edit SKO</b> → ieslēdz <b>Do not auto order</b>.</li>
<li><b>Sekot un saņemt piegādi:</b> tā pati partnera lapa → <b>Stock deliveries</b> → kad prece pienāk, <b>Receive</b> → pārbaudi → novieto vietās.</li>
</ul>
</aside>
