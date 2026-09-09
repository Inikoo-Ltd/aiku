---
title: Gatavās produkcijas pieņemšana noliktavā
summary: Noliktavas rokasgrāmata - kur parādās pabeigtie darba uzdevumi, kā aiku nosaka, vai tie iet uz partnera nodalījumu vai parasto krājumu, un kā tos ar vienu kodu pieņemt.
date: 2026-09-08
source_date: 2026-09-08
tags: dispatch, production, intercompany, warehouse
category: dispatch
series: Ordering from partners
order: 10
---

<aside class="tldr">
Noliktavai. Kad amatnieki pabeidz darba uzdevumu, tas pats no sevis par krājumu nekļūst - kādam tas jāaiznes uz kādu vietu un jāpasaka, uz kuru. Šis saraksts ir <b>Dispatching → From production</b> (Nosūtīšana → No ražotnes). Katra rinda pasaka, kam preces paredzētas, un iesaka vietu: partnera savākšanas nodalījumu, ja viss darbs paredzēts vienam partnerim, citādi krājuma vietu, ko ieraksti pats. Spied <b>Put away</b> (Novietot) un preces tiek pieņemtas šajā vietā ar partijas kodu.
</aside>

## No kurienes rodas rindas

Darba uzdevums šeit parādās brīdī, kad visi tā uzdevumi ražotnes ekrānā atzīmēti kā DONE, un paliek te, kamēr netiek novietots. Nevienam tas nav jāsūta tev - tas notiek pats.

Darba uzdevumi, kas vēl tiek strādāti, šeit nav redzami. Ja jāredz, kas tuvojas, ražotnes lapā **To produce** (Ražojamie darbi) ir sleja **Done** (Pabeigts) ar tiem pašiem darba uzdevumiem - skaties [Darbs ar sarakstu To produce](/docs/fulfilling-partner-orders-lv).

## Kā lasīt rindu

| Kolonna | Ko tā rāda |
| --- | --- |
| Job order | numurs, piemēram, JOxxx-0001 |
| Artisan | kas izgatavoja |
| Made | cik un kā - 20 × SKO-01, viena rinda katram produktam |
| For | partnerorganizācijas kods vai *Stock* (Krājums) |
| To location | vieta, kurā jānovieto |

**For** tiek noteikts pēc pieprasījumiem, kas ir aiz darba uzdevuma. Ja visas rindas pieprasījusi viena un tā pati partnerorganizācija, preces pieder viņiem un rinda to rāda, ar šī partnera savākšanas nodalījumu jau ierakstītu laukā **To location**. Tas ir tas pats nodalījums, ko izmanto [pre-pick saraksts](/docs/gathering-a-partners-goods-lv): viss, kas tajā atrodas, ir apsolīts un vairs neskaitās kā pieejams nevienam citam.

Ja darba uzdevums izgatavots krājumam, savam klientam vai vairāk nekā vienam partnerim, rinda rāda *Stock*, un vietas lauks ir tukšs. Ieraksti tās vietas kodu, kurā preces novieto.

## Novietošana

1. Aiznes preces uz norādīto vietu vai uz to, ko izvēlējies pats.
2. Pārbaudi kodu laukā **To location**. Nomaini to, ja preces noliec citur.
3. Spied **Put away** (Novietot).

Tas pieņem preces šajā vietā, piešķir tām partijas kodu no darba uzdevuma numura un produkta koda, atskaita recepte paredzētās izejvielas un atzīmē darba uzdevumu kā saņemtu. Rinda pazūd, un ražotnes lapā rinda pazūd no slejas **Done**.

Pieņemtais daudzums ir tas, ko amatnieki faktiski izgatavoja, nevis tas, kas tika prasīts. Darba uzdevums, kas prasīja 25 un saņēma 19, pieņem 19.

## Kas notiek tālāk

- **Partnera preces** paliek partnera nodalījumā, kamēr kāds lapā **To produce** nosūta sakomplektēto pasūtījumu noliktavai. Komplektēšana tad ir pastaiga uz vienu nodalījumu. Partneris savā iepirkumu sarakstā redz rindu ar statusu *Staged for you* (Sagatavots tev).
- **Sava klienta preces** iet parastajā krājumā, un gaidošā piegādes pavadzīme tiek atlaista komplektēšanai, jo trūkums, kas to turēja, ir novērsts.
- **Krājums** vienkārši kļūst pieejams.

## Noderīgi zināt

- **Cilne parādās tikai** organizācijām, kurām ir ražotne.
- **Viens darba uzdevums, viena vieta.** Ja darba uzdevums patiešām jāsadala starp divām vietām, novieto to krājumā un ļauj pre-pick sarakstam pārvietot partnera daļu.
- **Nepareiza vieta labojama kā jebkura cita krājuma kļūda** - pārvieto krājumu starp vietām. Darba uzdevums pats netiek atvērts no jauna.
- **Nekas šeit nav rezervēts, kamēr nav nodalījumā.** Preces, kas novietotas parastajā krājumā, var komplektēt jebkuram.

<aside class="wayfinder"><strong>Kur klikšķināt aiku sistēmā</strong>
<ul>
<li><b>Saraksts:</b> tava organizācija → <b>Warehouse</b> → <b>Dispatching</b> → cilne <b>From production</b>.</li>
<li><b>Pieņem preces:</b> pārbaudi <b>To location</b> → <b>Put away</b>.</li>
<li><b>Pārbaudi, kas atrodas nodalījumā:</b> <b>Warehouse</b> → <b>Locations</b> → vieta → cilne <b>SKOs</b>.</li>
<li><b>Ko ražotne vēl ir parādā:</b> <b>Factory</b> → <b>To produce</b> → <b>Board</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Nepieciešamās tiesības</strong>
<ul>
<li>Amati tiek piešķirti darbinieka kartītē sadaļā Human Resources un ar sevi nes tiesības.</li>
<li>Saraksta redzēšanai un novietošanai: dispatching amats noliktavai vai organizācijas vadītājs.</li>
</ul>
</aside>
