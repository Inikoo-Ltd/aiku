---
title: Vystavenie objednávky a prevzatie tovaru
summary: Nákup od bežného dodávateľa - vystavte objednávku, nechajte si ju potvrdiť, potom premeňte dodávku na tovar, ktorý môžete predávať.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, purchase orders, stock deliveries, suppliers
category: procurement
---

<aside class="tldr">
Keď nakupujete od bežného dodávateľa - nie od partnerskej organizácie, ktorá má vlastný návod - proces prebieha v dvoch krokoch. Najprv vystavíte **purchase order** a necháte si ju od dodávateľa potvrdiť. Potom, keď tovar dorazí, zaznamenáte proti tejto objednávke **stock delivery** a prekontrolujete ju, kým nie je tovar uložený na regáloch. Tento článok pokrýva oboje, aj to, čo presne robí každé tlačidlo stavu po ceste.
</aside>

## Dodávatelia a agenti

Každý dodávateľ, od ktorého vaša organizácia nakupuje priamo, sa nachádza v **Procurement → Suppliers**. Stránka každého dodávateľa má tlačidlo **Purchase Order** na založenie novej objednávky, plus bočné menu s **Products**, **Purchase Orders** a doterajšími **Stock Deliveries**.

Niektorí dodávatelia sú dostupní len cez **agenta** - osobu alebo firmu, ktorá nakupuje vo vašom mene namiesto priameho dodávania. Agenti majú vlastný zoznam v **Procurement → Agents** a fungujú rovnako: objednávky a dodávky voči agentovi sa zaznamenávajú na stránke agenta namiesto stránky dodávateľa.

## Vystavenie objednávky

Na stránke dodávateľa stlačte **Purchase Order**. Vytvorí sa nová objednávka v stave **In process** - existuje, ale dodávateľovi ešte nič nebolo odoslané.

Kým je v stave in process:

- Použite **Add Product** na pridanie riadku pre produkt, ktorý chcete, jeden po druhom.
- Každý riadok je možné upraviť, kým je objednávka stále in process.
- **Delete** zmaže celú objednávku, pokiaľ dodávateľovi ešte nič nebolo odoslané.

Keď máte pridané všetko, čo chcete, stlačte **Submit**. Tým sa objednávka odošle ďalej a presunie sa do stavu **Submitted**.

## Čo znamenajú jednotlivé stavy

Objednávka prechádza krátkym, premysleným reťazcom:

- **In process** - stále staviate objednávku. Pridávajte produkty, odošlite ju, alebo ju zmažte.
- **Submitted** - objednávka bola odoslaná dodávateľovi. Môžete ju **Confirm**, akonáhle s ňou dodávateľ súhlasí, **Undo Submit**, aby ste ju vrátili späť do In process, ak treba niečo zmeniť, alebo ju úplne **Cancel**.
- **Confirmed** - dodávateľ objednávku prijal. Môžete nastaviť alebo zmeniť **Delivery date** (odhadovaný dátum príchodu) a stlačiť **New Delivery**, čím vytvoríte stock delivery, ktorá tovar prevezme. Kým pre ňu neexistuje žiadna dodávka, môžete tiež **Undo Confirm** a vrátiť ju späť do Submitted.

Odtiaľto sa objednávka usadí sama, ako postupujú jej dodávky - na samotnej objednávke už nie je čo klikať. Nakoniec skončí v stave **Settled**, keď všetko dorazí, alebo **Not Received**/**Cancelled**, ak to nevyšlo.

## Stock delivery: zaznamenanie toho, čo dorazilo

Stlačením **New Delivery** na potvrdenej objednávke sa za vás vytvorí stock delivery, už prepojená s riadkami danej objednávky. Môžete tiež založiť dodávku od začiatku v **Procurement → Stock Deliveries**, kde stačí zadať **number** a **date** dodávky.

Stránka stock delivery má karty pre jej **Items**, ešte nevyriešené **Pending Items**, **Done Items**, **Attachments** a **History**.

Dodávka následne prechádza vlastnými stavmi:

- **In process / Confirmed / Ready to ship** - kým je stále na ceste, môžete stlačiť **Mark as Dispatched**, akonáhle ju dodávateľ odoslal, **Mark as Received**, ak už dorazila, alebo **Delete**, ak bola založená omylom.
- **Dispatched** - zásielka je na ceste. **Mark as Received**, keď dorazí do vášho skladu, alebo **Unmark as Dispatched**, ak sa to má vrátiť späť, pretože v skutočnosti ešte neodišla.
- **Received** - tovar je fyzicky v sklade. Odtiaľto skontrolujete každú položku voči tomu, čo bolo objednané; dodávka sa stane **Checked**, keď je to hotové, alebo môžete **Unmark as Received**, alebo celú dodávku **Cancel**.
- **Checked** - ak ešte nič nebolo uložené na sklad, stále tu môžete **Cancel**.
- **Booking in / Booked in** - skontrolované množstvá sa naskladňujú do skladu.
- **Booked in** - stlačte **Place**, aby ste prevzatý tovar uložili na miesto. Toto je posledný pracovný stav dodávky.

Kontrola položky znamená potvrdenie, koľko z každého riadku skutočne dorazilo - nie každá objednávka dorazí kompletná, a chýbajúce alebo prebytočné množstvá sa zobrazia na karte **Under/Over delivered items**, takže sa nič nestratí v rozdiele medzi tým, čo ste objednali, a tým, čo prišlo.

## Ako to celé zapadá

V skratke: vystavte objednávku voči dodávateľovi, odošlite ju, počkajte, kým ju dodávateľ potvrdí, potom z potvrdenej objednávky vytvorte dodávku. Označte dodávku ako odoslanú, keď ju dodávateľ odošle, ako prijatú, keď dorazí, prekontrolujte jednotlivé položky a nakoniec ju uložte na miesto - vtedy je tovar v sklade a pripravený na predaj.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Nájsť dodávateľa alebo agenta:</b> vaša organizácia → <b>Procurement → Suppliers</b> (alebo <b>Agents</b> pre dodávateľov spravovaných agentom).</li>
<li><b>Vystaviť objednávku:</b> na stránke dodávateľa stlačte <b>Purchase Order</b>; pridajte riadky pomocou <b>Add Product</b>, potom <b>Submit</b>, keď ste pripravení.</li>
<li><b>Posunúť ju ďalej:</b> na stránke objednávky použite <b>Confirm</b>, <b>Undo Submit</b>, alebo <b>Cancel</b>, kým je submitted; po potvrdení nastavte <b>Delivery date</b> a stlačte <b>New Delivery</b>.</li>
<li><b>Prevziať tovar:</b> na stránke stock delivery postupujte cez <b>Mark as Dispatched → Mark as Received</b>, skontrolujte kartu <b>Items</b>, potom <b>Place</b>, keď je naskladnená.</li>
<li>Dodávku môžete tiež založiť od začiatku v <b>Procurement → Stock Deliveries</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Povolenia, ktoré potrebujete</strong>
Na zobrazenie objednávok a dodávok potrebujete povolenie na zobrazenie procurementu pre danú organizáciu, a na ich vystavenie, odoslanie, potvrdenie alebo inú zmenu potrebujete povolenie na úpravu procurementu.
</aside>
