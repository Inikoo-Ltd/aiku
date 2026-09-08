---
title: Maisījumu gatavošana
summary: Gatavotājam un plānotājam - kā maisījums vai bāze kļūst par kaut ko, ko ražotne uzskaita, kā cilne Mixes izrēķina, kas jāgatavo, un kā plūst gatavotāja darba uzdevumi.
date: 2026-09-02
source_date: 2026-09-02
tags: production, crafts
category: production
series: Ordering from partners
order: 6
---

<aside class="tldr">
Cilvēkam, kas gatavo maisījumus un bāzes, pirms amatnieki var sākt, un plānotājam, kas viņam nodod darbu. Maisījums tiek gatavots ražotnē, tāpēc aiku to uzskata gan par **izejmateriālu** (amatnieki to patērē), gan par **artefaktu** (gatavotājs to izgatavo). Kad tie ir sasaistīti, cilne **Mixes** (Maisījumi) sarakstā <a href="/docs/fulfilling-partner-orders-lv">To produce</a> izrēķina, cik daudz katra maisījuma vajadzīgs no atklātajiem darba uzdevumiem, un viena poga to pārvērš gatavotāja darba uzdevumos. Kategoriju un amatnieku iestatīšana ir aprakstīta rakstā <a href="/docs/who-makes-what-lv">Kurš ko izgatavo</a>.
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

**Factory → To produce → Mixes** (Ražotne → Ražojamie darbi → Maisījumi) uzskaita katru pašu ražotu izejmateriālu, ko vajag kāds atklāts darba uzdevums. Darba uzdevums ir atklāts no brīža, kad tas izveidots, līdz brīdim, kad tas ieskaitīts krājumā.

Katram maisījumam redzi:

- **Needed** (Vajadzīgs): atklāto darba uzdevumu daudzumi, reizināti ar receptes daudzumu uz vienību, saskaitīti pa visiem produktiem.
- **On hand** (Pieejams): maisījuma krājums šobrīd.
- **Being made** (Tiek gatavots): daudzums atklātajos darba uzdevumos pašam maisījumam.
- **Short** (Trūkst): vajadzīgs mīnus pieejams mīnus tiek gatavots. Trūkstošās rindas ir pirmās un rādītas sarkanā krāsā.
- **Needed for** (Vajadzīgs priekš): produktu kodi, kas to patērē, lai gatavotājs zina, kas gaida.

Atzīmē maisījumus, kas jāgatavo, pielāgo daudzumu, ja trūkums nesakrīt ar īsto partiju, un spied **Create job orders** (Izveidot darba uzdevumus). Tiek izveidots viens darba uzdevums katram gatavotājam, adresēts viņam, melnrakstā. Atver to un spied *Release to floor* (Nodot ražotnei), kad tam jāsākas.

## Ko dara gatavotājs

Gatavotājs vada savu līniju, tāpēc viņam ir ražotnes amats **Mix preparer** (maisījumu gatavotājs). Tas ļauj viņam atvērt cilni Mixes, izveidot un nodot savus darba uzdevumus un ieskaitīt tos krājumā, negaidot nevienu citu. Viņš nevar aiztikt darba uzdevumus, kas adresēti citiem — tas paliek plānotāja pārziņā. Ražotnē viņš strādā kā jebkurš amatnieks: viņa uzdevumi parādās ražotnes ekrānā, viņš spiež START un DONE, un, kad pēdējais solis pabeigts, darba uzdevums tiek ieskaitīts krājumā ar partijas kodu. No tā brīža maisījums rādās kā pieejams, un amatnieki var izgatavot savus produktus.

Ja gatavotājam nemaksā par gabalu, tas ir algas iestatījums, nevis iemesls izlaist darbu ražotnē. Ieraksts par to, kurš kuru partiju sagatavoja un kad, dod izsekojamību no gatavā produkta atpakaļ līdz tā sastāvdaļām.

## Noderīgi zināt

- Maisījums nevar būt vajadzīgs pats sev. Ja maisījuma artefakta pašā receptē ir minēts tas pats izejmateriāls, šī rinda tiek ignorēta.
- Cilne Mixes lasa tikai šīs ražotnes darba uzdevumus. Produkts, kas izgatavots citā ražotnē, šeit pieprasījumu neveido.
- "Being made" (Tiek gatavots) skaita darba uzdevumu, kamēr tas nav ieskaitīts krājumā, pat ja katrs uzdevums ir pabeigts. Ieskaiti darba uzdevumus savlaicīgi, lai skaitļi paliktu godīgi.

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Sasaistīt maisījumu:</b> <b>Factory → Crafts → Raw materials</b> → atver maisījumu → <b>Edit</b> → <b>Made in-house as</b>.</li>
<li><b>Redzēt, kas jāgatavo:</b> <b>Factory → To produce → Mixes</b>.</li>
<li><b>Nosūtīt darbu:</b> atzīmē maisījumus → <b>Create job orders</b> → atver darba uzdevumu → <b>Release to floor</b>.</li>
<li><b>Veikt darbu:</b> <b>Factory → Floor</b> (My tasks / Mani uzdevumi) → <b>START</b> / <b>DONE</b>; tad darba uzdevums tiek ieskaitīts krājumā no tā lapas.</li>
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
