---
title: Vystavenie objednávky a prevzatie tovaru
summary: Nákup od bežného dodávateľa - vystavte objednávku, nechajte si ju potvrdiť, potom premeňte dodávku na tovar, ktorý môžete predávať.
date: 2026-10-10
source_date: 2026-10-10
tags: procurement, purchase orders, stock deliveries, suppliers, supplier claims, customs
category: procurement
---

<aside class="tldr">
Keď nakupujete od bežného dodávateľa - nie od partnerskej organizácie, ktorá má vlastný návod - proces prebieha v dvoch krokoch. Najprv vystavíte **purchase order** a necháte si ju od dodávateľa potvrdiť. Potom, keď tovar dorazí, zaznamenáte proti tejto objednávke **stock delivery** a prekontrolujete ju, kým nie je tovar uložený na regáloch. Tento článok pokrýva oboje, aj to, čo presne robí každé tlačidlo stavu po ceste.
</aside>

## Dodávatelia a agenti

Každý dodávateľ, od ktorého vaša organizácia nakupuje priamo, sa nachádza v **Procurement → Suppliers**. Stránka každého dodávateľa má tlačidlo **Purchase Order** na založenie novej objednávky, plus bočné menu s **Products**, **Purchase Orders** a doterajšími **Stock Deliveries**.

Niektorí dodávatelia sú dostupní len cez **agenta** - osobu alebo firmu, ktorá nakupuje vo vašom mene namiesto priameho dodávania. Agenti majú vlastný zoznam v **Procurement → Agents**. Objednávka cez agenta je stále jedna objednávka na dodávateľa, odoslaná agentovi, a objednávky, ktoré zadáte spolu, tvoria jednu **agent order** (objednávku agenta). Viď [Zadanie objednávky cez agenta](/docs/placing-an-order-through-an-agent-sk).

## Vystavenie objednávky

Na stránke dodávateľa stlačte **Purchase Order**. Vytvorí sa nová objednávka v stave **In process** - existuje, ale dodávateľovi ešte nič nebolo odoslané.

Kým je v stave in process:

- Použite **Add Product** na pridanie riadku pre produkt, ktorý chcete, jeden po druhom.
- Každý riadok je možné upraviť, kým je objednávka stále in process.
- **Delete** zmaže celú objednávku, pokiaľ dodávateľovi ešte nič nebolo odoslané.

Keď máte pridané všetko, čo chcete, stlačte **Submit**. Tým sa objednávka odošle ďalej a presunie sa do stavu **Submitted**.

## Dodávatelia, ktorí predávajú v inej jednotke

Niektorí dodávatelia ponúkajú a fakturujú v jednotke, ktorá nie je naša - napríklad vonné tyčinky na **kg**, ktoré my počítame v 500 g vreckách. Na stránke **Edit** produktu dodávateľa nastavte **Supplier sells in** (kg, g, liter, meter, balenie, sada, tucet alebo kus) a **Our units in one supplier unit** (2 pre 500 g vrecko predávané na kg, 0,2 pre 5 kg balenie predávané na kg). Ak zvolíte kg alebo g a číslo necháte prázdne, aiku ho vypočíta z hmotnosti obchodnej jednotky a pomocný text poľa vás upozorní, keď sa zadané číslo s touto hmotnosťou nezhoduje.

Množstvá sa stále ukladajú v našich jednotkách. Jednotka dodávateľa mení len to, ako ich zadávate a čítate:

- Na objednávke v stave in process vám karta **Ordering supplier units** umožní zadať množstvo v jednotke dodávateľa a každý riadok ho ukazuje vedľa kusov, SKO a kartónov (napríklad *160u. | 160sko. | 1.6C. | 80 kg*).
- PDF objednávky odoslané dodávateľovi zobrazuje tieto riadky v jednotke dodávateľa a s cenou za jednotku (*80 kg* za *253.00 / kg*).
- Stock delivery ukazuje obe jednotky a kontrola faktúry na paneli **Costing** pred porovnaním prevedie kg z faktúry na naše jednotky.

## Cez vášho AI asistenta

Ak vám administrátor zapol zadávanie objednávok, váš AI asistent môže objednávku vystaviť a odoslať za vás, aj pri dodávateľoch, od ktorých nakupujete cez agenta. Povedzte mu dodávateľa a čo chcete, napríklad *"objednaj 144 CIC-25 a 20 TIB-134SET od RME"*.

- Najprv ukáže objednávku: dodávateľa, agenta, každý riadok s kusmi, kartónmi a cenou, súčet a prípadnú objednávku, ktorá sa pre tohto dodávateľa už pripravuje.
- Množstvá sú v kusoch a zaokrúhľujú sa nahor na celé kartóny, nikdy pod minimálny počet kartónov dodávateľa.
- Až po vašom potvrdení pridá riadky (do pripravovanej objednávky alebo novej) a odošle ju cez **Submit**. Neposiela ju e-mailom: dodávateľovi ju pošlite zo stránky objednávky.
- Objednávka sa zaznamená s vašou požiadavkou, ale v zázname AI zmien sa **nedá vrátiť**: na objednávke použite **Undo Submit** alebo **Cancel**.

## Čo znamenajú jednotlivé stavy

Objednávka prechádza krátkym, premysleným reťazcom:

- **In process** - stále staviate objednávku. Pridávajte produkty, odošlite ju, alebo ju zmažte.
- **Submitted** - objednávka bola odoslaná dodávateľovi. Môžete ju **Confirm**, akonáhle s ňou dodávateľ súhlasí, **Undo Submit**, aby ste ju vrátili späť do In process, ak treba niečo zmeniť, alebo ju úplne **Cancel**.
- **Confirmed** - dodávateľ objednávku prijal. Môžete nastaviť alebo zmeniť **Delivery date** (odhadovaný dátum príchodu) a stlačiť **New Delivery**, čím vytvoríte stock delivery, ktorá tovar prevezme. Kým pre ňu neexistuje žiadna dodávka, môžete tiež **Undo Confirm** a vrátiť ju späť do Submitted.

Odtiaľto sa objednávka usadí sama, ako postupujú jej dodávky - na samotnej objednávke už nie je čo klikať. Nakoniec skončí v stave **Settled**, keď všetko dorazí, alebo **Not Received**/**Cancelled**, ak to nevyšlo.

## Stock delivery: zaznamenanie toho, čo dorazilo

Stlačením **New Delivery** na potvrdenej objednávke sa za vás vytvorí stock delivery, už prepojená s riadkami danej objednávky. Môžete tiež založiť dodávku od začiatku v **Procurement → Stock Deliveries**, kde stačí zadať **number** a **date** dodávky.

Stránka stock delivery má karty pre jej **Items**, ešte nevyriešené **Pending Items**, **Done Items**, **Under/Over delivered items**, keď je zaknihovaná, **Customs**, keď bola odoslaná, **Attachments** a **History**.

Dodávka následne prechádza vlastnými stavmi:

- **In process / Confirmed / Ready to ship** - kým je stále na ceste, môžete stlačiť **Mark as Dispatched**, akonáhle ju dodávateľ odoslal, **Mark as Received**, ak už dorazila, alebo **Delete**, ak bola založená omylom.
- **Dispatched** - zásielka je na ceste. **Mark as Received**, keď dorazí do vášho skladu, alebo **Unmark as Dispatched**, ak sa to má vrátiť späť, pretože v skutočnosti ešte neodišla.
- **Received** - tovar je fyzicky v sklade. Odtiaľto skontrolujete každú položku voči tomu, čo bolo objednané; dodávka sa stane **Checked**, keď je to hotové, alebo môžete **Unmark as Received**, alebo celú dodávku **Cancel**.
- **Checked** - ak ešte nič nebolo uložené na sklad, stále tu môžete **Cancel**.
- **Booking in / Booked in** - skontrolované množstvá sa naskladňujú do skladu.
- **Booked in** - stlačte **Place**, aby ste prevzatý tovar uložili na miesto. Toto je posledný pracovný stav dodávky.

Kontrola položky znamená potvrdenie, koľko z každého riadku skutočne dorazilo - nie každá objednávka dorazí kompletná, a chýbajúce alebo prebytočné množstvá sa zobrazia na karte **Under/Over delivered items**, takže sa nič nestratí v rozdiele medzi tým, čo ste objednali, a tým, čo prišlo.

Pod skontrolovaným množstvom **Batch** (dávka) zaznamená kód dávky a dátum minimálnej trvanlivosti vytlačené na tovare. Jeden riadok môže mať viac dávok: stlačte **Add batch** a rozdeľte množstvo medzi ne. Pri naskladnení idú dávky na regál v poradí, v akom ste ich zadali, takže sklad vie, ktorá dávka je kde, a inventárne prehľady môžu ukázať dátumy minimálnej trvanlivosti. Rodiny označené ako **Batch tracked** (na stránke úpravy rodiny skladových položiek) zobrazujú upozornenie, kým každé skontrolované SKO nemá dávku, a označia dávku bez dátumu minimálnej trvanlivosti. Nič neblokuje príjem, takže dodávka bez vytlačených kódov môže ísť na regál aj tak. Dávku, ktorá už je na regáli, nemožno znížiť pod naskladnené množstvo; najprv zrušte to naskladnenie.

## Keď sa dorazené líši od očakávaného

Karta **Under/Over delivered items** vypisuje každý riadok, ktorého počet sa líši, s rozdielom v kusoch, SKO a hodnote a so značkou **Flag**:

- **Under delivered** alebo **Over delivered** - skutočný rozdiel.
- **Possible unit mismatch** - počet je presný násobok očakávaného (2×, 5×, 10× a podobne, v oboch smeroch). Takmer vždy ide o dodávateľa, ktorý fakturuje v inej jednotke, nie o stratený tovar, preto pred reklamáciou skontrolujte jednotku.
- **Within tolerance** - dosť malý na zobrazenie, nie na označenie. Toleranciu nastavujú administrátori (percento, suma, alebo oboje) v **Organisations** → organizácia → **Edit** → **Procurement**; začína na 0, takže sa označí každý rozdiel.

Stlačte **Resolve** pri riadku a vyberte, čo sa stalo:

- **Recount** - sklad dostane úlohu riadok znova spočítať. Keď ju uzavrie, dostanete správu; potom sa vráťte a vyberte jeden z ostatných výsledkov.
- **Unit error** - opravte, čo sa očakávalo (v našich jednotkách, alebo v jednotke dodávateľa, ak je nastavená), a hodnotu riadku. Spočítané množstvo sa nikdy nemení. Ak zostane skutočný rozdiel, riadok zostane označený, aby ste ho mohli reklamovať - riadok očakávaný ako 80 kg 500 g vreciek sa stane 160 očakávanými vreckami a 120 spočítaných sa zobrazí ako 40 chýbajúcich.
- **Supplier claim** - len pre chýbajúci riadok. Zadajte reklamované kusy a hodnotu a pridajte fotografie z goods in; pridajú sa do príloh dodávky. Reklamácia začína ako **Open**: kliknutím na ňu v stĺpci **Outcome** ju označíte ako **Sent to supplier**, **Credit received** (s číslom dobropisu, sumou a dátumom) alebo **Rejected**. Reklamácia sa len sleduje - nemení kalkuláciu nákladov dodávky.
- **Accept surplus** - len pre riadok navyše: ponechať prebytočné kusy.

Oprava chyby jednotky na uloženej dodávke prepočíta cenu tovaru, ktorý sa z nej uložil, preto ju urobte pred dokončením kalkulácie nákladov; po dokončení treba kalkuláciu najprv znovu otvoriť.

## Colné riadky a clo

Na karte **Customs** prepíšte colné vyhlásenie: jeho **MRN**, **Release date** a jeho tarifné riadky, každý s tarifným kódom, clom v %, colnou hodnotou, clom a dovoznou DPH. aiku colné vyhlásenie nikdy nemení; to ostáva na colnom agentovi.

Pri uložení sa každá položka priradí k riadku s najbližším tarifným kódom k tarifnému kódu jej obchodnej jednotky a pri každej položke to môžete zmeniť. Náklad na clo dodávky sa potom rozdelí po riadkoch: každá položka preberie svoj podiel cla vlastného riadku, podľa hodnoty medzi položkami na tomto riadku, takže riadok bez cla neberie nič. Položky bez riadku si rozdelia clo, ktoré riadky nepokrývajú. Dovozná DPH sa len eviduje; odpočíta sa v daňovom priznaní k DPH a nikdy sa nezahrnie do nákladov. Reklamácia dodávateľovi ukazuje, či jej riadok platil clo, aby nikto nežiadal colnú opravu pri riadku bez cla.


## Ako to celé zapadá

V skratke: vystavte objednávku voči dodávateľovi, odošlite ju, počkajte, kým ju dodávateľ potvrdí, potom z potvrdenej objednávky vytvorte dodávku. Označte dodávku ako odoslanú, keď ju dodávateľ odošle, ako prijatú, keď dorazí, prekontrolujte jednotlivé položky a nakoniec ju uložte na miesto - vtedy je tovar v sklade a pripravený na predaj.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Nájsť dodávateľa alebo agenta:</b> vaša organizácia → <b>Procurement → Suppliers</b> (alebo <b>Agents</b> pre dodávateľov spravovaných agentom).</li>
<li><b>Vystaviť objednávku:</b> na stránke dodávateľa stlačte <b>Purchase Order</b>; pridajte riadky pomocou <b>Add Product</b>, potom <b>Submit</b>, keď ste pripravení.</li>
<li><b>Posunúť ju ďalej:</b> na stránke objednávky použite <b>Confirm</b>, <b>Undo Submit</b>, alebo <b>Cancel</b>, kým je submitted; po potvrdení nastavte <b>Delivery date</b> a stlačte <b>New Delivery</b>.</li>
<li><b>Prevziať tovar:</b> na stránke stock delivery postupujte cez <b>Mark as Dispatched → Mark as Received</b>, skontrolujte kartu <b>Items</b>, potom <b>Place</b>, keď je naskladnená.</li>
<li>Dodávku môžete tiež založiť od začiatku v <b>Procurement → Stock Deliveries</b>.</li>
<li><b>Dodávateľ, ktorý predáva na kg:</b> produkt dodávateľa → <b>Edit</b> → <b>Supplier sells in</b> a <b>Our units in one supplier unit</b>.</li>
<li><b>Uzavrieť chýbajúci alebo prebytočný riadok:</b> stock delivery → <b>Under/Over delivered items</b> → <b>Resolve</b>; reklamáciu sledujte v stĺpci <b>Outcome</b>.</li>
<li><b>Zadať colné vyhlásenie:</b> stock delivery → <b>Customs</b> → <b>Add line</b> → <b>Save customs</b>.</li>
<li><b>Nastaviť toleranciu dodávok (administrátori):</b> <b>Organisations</b> → organizácia → <b>Edit</b> → <b>Procurement</b>.</li>
<li><b>Nechať niekoho zadávať objednávky cez jeho AI asistenta (administrátori):</b> <b>Sysadmin → Users</b> → otvorte používateľa → <b>Edit</b> → <b>Access</b> → zapnite <b>Can place orders to the manufacturing hub, partners and suppliers through their AI assistant</b>. Potrebuje aj oprávnenie upravovať nákup.</li>
</ul>
</aside>

<aside class="permissions"><strong>Povolenia, ktoré potrebujete</strong>
Na zobrazenie objednávok a dodávok potrebujete povolenie na zobrazenie procurementu pre danú organizáciu, a na ich vystavenie, odoslanie, potvrdenie alebo inú zmenu potrebujete povolenie na úpravu procurementu.
</aside>
