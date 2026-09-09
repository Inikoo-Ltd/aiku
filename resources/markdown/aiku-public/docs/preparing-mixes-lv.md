---
title: Maisījumu gatavošana
summary: Gatavotājam un plānotājam - kā maisījums vai bāze kļūst par kaut ko, ko ražotne uzskaita, kā cilne Mixes izrēķina, kas jāgatavo, un kā plūst gatavotāja darba uzdevumi.
date: 2026-09-08
source_date: 2026-09-08
tags: production, crafts
category: production
help_routes: grp.org.productions.show.to_produce.mixes, grp.org.productions.show.crafts.raw_materials
series: Ordering from partners
order: 6
---

<aside class="tldr">
Cilvēkam, kas gatavo maisījumus un bāzes, pirms amatnieki var sākt, un plānotājam, kas viņam nodod darbu. Maisījums tiek gatavots ražotnē, tāpēc aiku to uzskata gan par <b>izejmateriālu</b> (amatnieki to patērē), gan par <b>artefaktu</b> (gatavotājs to izgatavo). Kad tie ir sasaistīti, cilne <b>Mixes</b> (Maisījumi) sarakstā <a href="/docs/fulfilling-partner-orders-lv">To produce</a> izrēķina, cik daudz katra maisījuma trūkst no atklātajiem darba uzdevumiem, un kartītes pavilkšana pie gatavotāja to pārvērš darba uzdevumā. Kategoriju un amatnieku iestatīšana ir aprakstīta rakstā <a href="/docs/who-makes-what-lv">Kurš ko izgatavo</a>.
</aside>

## Kāpēc maisījums ir divas lietas

Vannas bumbas recepte saka "0,5 kg bāzes maisījuma uz vienību". Šo bāzes maisījumu nepērk — to ražotnē gatavo no tā pašu izejvielām. Tāpēc tas eksistē divreiz:

- Kā **izejmateriāls**, lai receptes to var patērēt un krājums tiek norakstīts, kad gatavais produkts tiek saņemts.
- Kā **artefakts**, ar savu recepti un saviem darba uzdevumiem, lai gatavotājam būtu darbs un partija, ko ieskaitīt krājumā.

Saikni starp abiem veido viens lauks izejmateriālam: **Made in-house as** (Ražots ražotnē kā). Iestati to uz maisījuma artefaktu. Tas ir viss iestatījums.

## Maisījuma iestatīšana

1. **Izveido artefaktu** maisījumam sadaļā **Factory → Crafts → Artefacts** (Ražotne → Amatniecība → Artefakti), ar tā recepšu soļiem un savām izejvielām, tāpat kā jebkuram citam artefaktam. Piešķir tam krājuma vienību (SKU), lai saņemtajām partijām būtu, kur nonākt.
2. **Izveido vai atver izejmateriālu** maisījumam sadaļā **Factory → Crafts → Raw materials** (Ražotne → Amatniecība → Izejmateriāli). Rediģē to, iestati **Made in-house as** uz 1. solī izveidoto artefaktu un piešķir to pašu krājuma vienību (SKU).
3. **Izmanto izejmateriālu receptēs.** Katram produktam, kam vajadzīgs maisījums, pievieno to attiecīgajam receptes solim ar daudzumu uz vienību.
4. **Piesaisti gatavotāju** maisījuma artefaktam vai kategorijai, kurā ir visi maisījumi, sadaļā *Usually made by*. Maisījumu darba uzdevumi tad iet šim cilvēkam.

## Cilne Mixes

**Factory → To produce → Mixes** (Ražotne → Ražojamie darbi → Maisījumi) ir neliels dēlis ar četrām joslām: **Needed**, **Assigned**, **Mixing** un **Done**. Tas rāda tikai pašu ražotos izejmateriālus, ko vajag kāds atklāts darba uzdevums. Darba uzdevums ir atklāts no brīža, kad tas izveidots, līdz brīdim, kad tas ieskaitīts krājumā.

Kartīte joslā **Needed** ir maisījums, kura ražotnei trūkst. Sarkanais skaitlis ir trūkums: cik atklātajiem darba uzdevumiem vajag, pēc to daudzumiem un receptes daudzuma uz vienību, mīnus tas, kas ir pieejams, mīnus tas, kas jau tiek maisīts. Zem tā *for* uzskaita produktu kodus, kas gaida, lai gatavotājs zina, kas ir bloķēts, un parastā gatavotāja vārdu, ja tāds ir piesaistīts.

Velc kartīti no **Needed** uz **Assigned**. aiku jautā daudzumu, piedāvājot trūkumu, lai to var noapaļot uz saprātīgu partiju, un *Who mixes it?* (Kurš maisa?). Izvēlies gatavotāju, un tiek izveidots darba uzdevums melnrakstā, adresēts viņam, ar tā atsauci kartītē. Atver to un spied **Release to floor** (Nodot ražotnei), kad tam jāsākas.

**Mixing** un **Done** pārvietojas pašas: kartīte pāriet uz Mixing, kad gatavotājs nospiež START, un uz Done, kad pēdējais uzdevums pabeigts. Tā pamet dēli, kad partija tiek ielikta krājumā.

## Ko dara gatavotājs

Gatavotājs vada savu līniju, tāpēc viņam ir ražotnes amats **Mix preparer** (maisījumu gatavotājs). Tas ļauj viņam atvērt cilni Mixes, izveidot un nodot savus darba uzdevumus un ieskaitīt tos krājumā, negaidot nevienu citu. Viņš nevar aiztikt darba uzdevumus, kas adresēti citiem — tas paliek plānotāja pārziņā. Ražotnē viņš strādā kā jebkurš amatnieks: viņa uzdevumi parādās [ražotnes ekrānā](/docs/working-the-floor-screen-lv), viņš spiež START un DONE, un, kad pēdējais solis pabeigts, partija tiek ielikta krājumā ar partijas kodu — vai nu noliktava no [Dispatching → From production](/docs/putting-away-finished-production-lv), vai gatavotājs no darba uzdevuma lapas. No tā brīža maisījums rādās kā pieejams, un amatnieki var izgatavot savus produktus.

Ja gatavotājam nemaksā par gabalu, tas ir algas iestatījums, nevis iemesls izlaist darbu ražotnē. Ieraksts par to, kurš kuru partiju sagatavoja un kad, dod izsekojamību no gatavā produkta atpakaļ līdz tā sastāvdaļām.

## Noderīgi zināt

- Maisījums nevar būt vajadzīgs pats sev. Ja maisījuma artefakta pašā receptē ir minēts tas pats izejmateriāls, šī rinda tiek ignorēta.
- Cilne Mixes lasa tikai šīs ražotnes darba uzdevumus. Produkts, kas izgatavots citā ražotnē, šeit pieprasījumu neveido.
- Maisījuma darba uzdevums skaitās kā tiek gatavots, kamēr tas nav ielikts krājumā, pat ja katrs uzdevums ir pabeigts. Noliec partijas vietā savlaicīgi, lai trūkums paliktu godīgs.

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Sasaistīt maisījumu:</b> <b>Factory → Crafts → Raw materials</b> → atver maisījumu → <b>Edit</b> → <b>Made in-house as</b>.</li>
<li><b>Redzēt, kas jāgatavo:</b> <b>Factory → To produce → Mixes</b>.</li>
<li><b>Nosūtīt darbu:</b> velc kartīti no <b>Needed</b> uz <b>Assigned</b> → daudzums un gatavotājs → atver darba uzdevumu → <b>Release to floor</b>.</li>
<li><b>Veikt darbu:</b> <b>Factory → Jobs</b> → <b>START</b> / <b>DONE</b>; tad partija tiek ielikta krājumā no <b>Warehouse → Dispatching → From production</b> vai no darba uzdevuma lapas.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Nepieciešamās tiesības</strong>
<ul>
<li>Amati tiek piešķirti darbinieka kartītē sadaļā Human Resources un ar sevi nes tiesības.</li>
<li>Cilnes Mixes redzēšanai un darbam ražotnē: <b>Production operative</b> (strādnieks) ražotnei vai augstāk.</li>
<li>Maisījumu darba uzdevumu izveidei, nodošanai un savu darba uzdevumu ieskaitīšanai: <b>Mix preparer</b> (maisījumu gatavotājs) ražotnei. Gatavotājam šis ir vajadzīgs.</li>
<li>Visam pārējam, ieskaitot citu cilvēku darba uzdevumus un izejmateriāla sasaisti ar artefaktu: <b>Production floor supervisor</b> (ražotnes maiņas vadītājs) ražotnei vai organizācijas vadītājs.</li>
</ul>
</aside>
