---
title: Ražotnes krājuma uzturēšana
summary: Lapa To restock - kuri artefakti beidzas pirmie, cik ilgi ražotnei vajag, lai jebko izgatavotu, un kā savu papildināšanas darbu novietot uz dēļa To produce.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, planning
category: production
series: Ordering from partners
order: 11
---

<aside class="tldr">
Personai, kas plāno ražotnes nedēļu. <a href="/docs/fulfilling-partner-orders-lv">To produce</a> atbild uz jautājumu <i>ko kāds ir pieprasījis</i>. <b>To restock</b> (Jāpapildina) atbild uz citu jautājumu: <i>kas mums beigsies, neatkarīgi no tā, vai kāds jau ir pieprasījis</i>. Tā sakārto visu, ko ražotne izgatavo, pēc tā, cik dienu seguma atlicis, mērot pret to, cik ilgi šai ražotnei tiešām vajag, lai kaut ko izgatavotu, un ļauj virzīt to, ko ir vērts gatavot, uz dēli To produce.
</aside>

## Izgatavošanas laiks ir mērauklas

Katrs grozs šajā lapā tiek mērīts **izgatavošanas laikos** (lead times), nevis dienās. Izgatavošanas laiks ir vidējais dienu skaits no darba sākuma ražotnē līdz tā atgriešanai noliktavā, ņemts no šīs ražotnes pašas darba uzdevumiem pēdējā gada laikā. Ja pabeigto darba uzdevumu ir mazāk par pieciem, mērīt nav ko, tāpēc aiku izmanto novērtējumu — septiņas dienas, ja vien ražotnei nav iestatīts cits skaitlis — un pie skaitļa raksta *estimate* (novērtējums).

Tāpēc grozi izskatās tā, kā izskatās. Artefakts ar četru dienu segumu nav briesmās ražotnē, kas darbu izpilda divās dienās; tas jau ir pazudis tādā, kurai vajag nedēļu.

## Grozi

| Grozs | Ko tas nozīmē |
| --- | --- |
| Out of stock (Nav krājumā) | plauktā nekā nav |
| Doomed (Nolemts) | tas beigsies, pirms jebkas, kas sākts šodien, varētu pienākt |
| Critical (Kritisks) | beigsies divu izgatavošanas laiku laikā |
| Danger (Bīstami) | beigsies triju izgatavošanas laiku laikā |
| Watch (Vērot) | beigsies četru izgatavošanas laiku laikā |
| Covered (Segts) | vairāk nekā četru izgatavošanas laiku segums |
| Dead stock (Nekustīgs krājums) | vērtība plauktā un vispār nekāda patēriņa |
| Never made yet (Vēl nekad nav gatavots) | artefakts bez jebkāda krājuma ieraksta aizmugurē |

Katrs grozs nes trīs skaitļus: cik artefaktu tajā ir, cik ir **jau darbā**, un cik ir **neaiztikti**. Jau darbā nozīmē, ka kāds ar to jau ir kaut ko darījis — atklāta rinda uz dēļa To produce vai darba uzdevums ražotnē. Neaiztikti ir skaitlis, ar ko strādāt.

Klikšķini uz groziem, lai izvēlētos, ko rāda saraksts zemāk. Tas atveras ar **Out of stock, Doomed and Critical** (Nav krājumā, Nolemts un Kritisks), kas ir godīgs rīta saraksts.

## Joslas

Zem groziem tas pats darbs ir izklāts četrās joslās:

- **To do** (Jāizdara) — artefakti izvēlētajos grozos, ar kuriem nekas nav darīts. Vispirms steidzamie. Katra rinda nes krājuma kodu, cik ir plauktā, seguma dienas, artefakta saimi, kurš to parasti izgatavo, un **vienību** skaitu, par kādu tiktu izveidots darba uzdevums: ieteicamais pasūtījuma daudzums, pārvērsts vienībās un noapaļots uz nākamo pilno partiju. Viss, ko partneris jau ir pieprasījis, ir izlaists — tas ir To produce darbs, nevis šīs lapas.
- **Queued** (Rindā) — rindas, kas jau gaida uz dēļa To produce, vai nu no partnera, vai no šejienes.
- **Producing** (Ražo) — rindas ar darba uzdevumu ražotnē, ar tā numuru un amatnieku.
- **Restocked** (Papildināts) — kas pēdējo divu nedēļu laikā atgriezies no ražotnes, lai redzētu, ka lapa strādā.

## Darba novietošana uz dēļa

Atzīmē rindas sadaļā **To do** un spied pogu, lai tās ievietotu rindā. Katra kļūst par rindu uz dēļa To produce bez partnera un bez klienta aizmugurē: vienkārši darbs, ko ražotne ir parādā pati sev. No turienes to plāno, piešķir un izgatavo tieši tāpat kā partnera rindu, un tā pamet dēli, kad gatavā produkcija tiek novietota vietā.

Rinda tiek izlaista, un tas tiek pateikts, ja tas pats krājums jau ir atklāts uz dēļa. Vienu un to pašu nevar ievietot rindā divreiz, spiežot pogu divreiz.

## Noderīgi zināt

- **On demand vienību šeit nav.** Artefakts, kura SKO ir atzīmēts kā *On Demand* (Pēc pieprasījuma), tiek izgatavots, kad tas tiek pieprasīts, un tam nav seguma, kam beigties.
- **Dead stock ir jautājums, nevis uzdevums.** Vērtība, kas stāv nekustīga bez patēriņa, parasti vēlas sarunu ar veikalu, nevis darba uzdevumu.
- **Lapa tiek aprēķināta no jauna katru reizi.** Nekas netiek glabāts, nekas nav jāsakārto, un grozs iztukšojas pats, kad prece nonāk vietā.
- **Seguma dienas nāk no tās pašas prognozes**, ko izmanto pārējā aiku sistēma, skaties [Kā aiku prognozē, kas tev beigsies](/docs/how-aiku-predicts-what-you-run-out-of).

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Lapa:</b> tava organizācija → <b>Factory</b> → <b>To restock</b>.</li>
<li><b>Mainīt, ko rāda joslas:</b> klikšķini uz groza kartītēm augšā.</li>
<li><b>Ievietot rindā savu darbu:</b> atzīmē rindas sadaļā <b>To do</b> → poga → tās parādās sadaļā <b>To produce</b>.</li>
<li><b>Iestatīt novērtēto izgatavošanas laiku,</b> kamēr vēsture ir plāna: ražotnes pašas iestatījumos; kad pabeigti pieci darba uzdevumi, mērītais skaitlis pārņem pats.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Nepieciešamās tiesības</strong>
<ul>
<li>Amati tiek piešķirti darbinieka kartītē sadaļā Human Resources un ar sevi nes tiesības.</li>
<li>Lapas redzēšanai: <b>Production operative</b> (strādnieks) ražotnei vai augstāk.</li>
<li>Darba ievietošanai rindā uz To produce: <b>Production floor supervisor</b> (ražotnes maiņas vadītājs) ražotnei vai organizācijas vadītājs.</li>
</ul>
</aside>
