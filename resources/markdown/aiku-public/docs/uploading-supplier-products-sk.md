---
title: Nahrávanie nových produktov dodávateľa
summary: Vyplňte šablónu produktov dodávateľa, nahrajte ju na stránke dodávateľa, skontrolujte každý riadok v náhľade, rozhodnite, čo si vyžaduje rozhodnutie, a importujte. Aiku naraz vytvorí trade units, SKO, produkty dodávateľa a koncepty nákupných objednávok a nič sa nevytvorí, kým nestlačíte Import.
date: 2026-10-07
source_date: 2026-10-07
tags: procurement, supply chain, products, upload
category: procurement
help_routes: grp.supply-chain.suppliers.supplier_products.index, grp.supply-chain.suppliers.supplier_products.uploads.show, grp.supply-chain.suppliers.supplier_products.create
---

<aside class="tldr">
Stiahnite si šablónu zo stránky <b>Products</b> (produkty) dodávateľa, vyplňte jeden riadok na produkt a nahrajte ju tlačidlom <b>Attach file</b> (priložiť súbor). Aiku sheet prečíta, skontroluje každý riadok a otvorí <b>náhľad</b>. Riadky označené <b>Fix in the sheet</b> (opraviť v súbore) musíte opraviť v súbore. Riadky označené <b>Needs a decision</b> (vyžaduje rozhodnutie) vyžadujú, aby ste zaškrtli <b>This is OK, I accept responsibility</b> (je to v poriadku, preberám zodpovednosť), alebo riadok vynechali. Keď nezostáva nič na opravu ani rozhodnutie, stlačte <b>Import</b>: aiku vytvorí rodiny, trade units, čiarové kódy, SKO, produkty dodávateľa a koncepty nákupných objednávok. Dovtedy sa nič nevytvorí a <b>Cancel upload</b> (zrušiť nahrávanie) všetko zahodí. Pre jediný produkt robí to isté <b>New Supplier Product</b> (nový produkt dodávateľa) z formulára, bez sheetu.
</aside>

## Čím sa stane jeden riadok

Každý riadok sheetu popisuje jeden produkt, ktorý nám dodávateľ predáva, a Import z neho vytvorí:

| Vytvorí sa | Zo stĺpcov |
| --- | --- |
| **SKO family** a **trade unit family** | Family |
| **Trade unit** (jednotlivá položka, ktorú kupuje zákazník) | Part reference, Unit recommended description, Unit label, Unit weight, Unit dimensions, Materials, Tariff code, Unit barcode |
| **SKO** (to, čo skladník vychystáva) | Part reference, Units per SKO, SKO weight, SKO dimensions |
| **Supplier product** (to, čo kupujeme od tohto dodávateľa) | Supplier's product code, Unit cost, Unit expense, Extra costs %, SKOs per carton, Minimum order, Average delivery time, Carton CBM, Carton Weight |
| **Odporúčané ceny** pre budúci master produkt | Unit recommended price a RRP v £ a € a Recommended SKOs per selling outer |
| **Koncepty nákupných objednávok** | Order Cartons UK / SK / ES / Aroma |

Každá organizácia, ktorá nakupuje od dodávateľa, dostane SKO okamžite, prepojené so svojou kópiou produktu dodávateľa.

Ak Part reference už existuje, nevytvorí sa nič nové: dodávateľ sa k tomuto trade unit pridá ako ďalší zdroj a vyplnia sa iba jeho prázdne polia.

## Šablóna

Stiahnite si ju zo stránky **Products** (produkty) dodávateľa. Nahrávanie číta stĺpce podľa **nadpisu** v riadku nadpisov, takže stĺpce môžete presúvať alebo pridať vlastné pracovné stĺpce; všetko, čo aiku nepozná, sa ignoruje. Poznámky nad riadkom nadpisov sú v poriadku.

Riadok nad nadpismi hovorí **Required** (povinné) alebo **Opt** (voliteľné). Ak chýba nadpis označený Required, celý súbor sa hneď odmietne a správa uvedie chýbajúci stĺpec.

Niekoľko pravidiel, ktoré ušetria väčšinu chýb:

- **Jeden riadok je jedna samostatná jednotka.** "Unit recommended description" je názov jednej položky, nikdy nie "Pack of 6 …". Balenie popisuje Units per SKO.
- **Supplier's product code** môže zostať prázdny: použije sa Part reference.
- **Peňažné stĺpce sú vo svojej vlastnej mene**: Unit cost a Unit expense v mene dodávateľa, odporúčané ceny v £ a €. Bunka naformátovaná alebo zadaná v inej mene sa odmietne.
- **Hmotnosti v kg, rozmery v cm** zapísané ako 20x10x5, Carton CBM v m³, Extra costs ako 40% alebo 0.4.
- **Unit barcode**: skutočný EAN, alebo `auto`, ktoré pri Importe vezme ďalší voľný čiarový kód z poolu. Prázdne znamená žiadny čiarový kód a vyžaduje rozhodnutie.
- **Units per SKO, SKOs per carton a Minimum order** sú celé čísla.

## Údaje o súlade (šablóna v7)

Šablóna má tri karty. **Product data** zachováva všetky vyššie uvedené stĺpce na svojom mieste. Za nimi nasledujú voliteľné stĺpce pre GPSR, regulačnú kategóriu produktu a EUDR. Ostatné dve karty obsahujú to, čo jeden riadok na produkt nepojme. Všetko je voliteľné: súbor bez týchto stĺpcov alebo kariet sa nahrá presne ako predtým.

| Kde | Čím sa to stane |
| --- | --- |
| **Product data**: Manufacturer, EU responsible person, Warnings and safety information, Instructions for use, Languages of warnings and instructions | Polia GPSR trade unit, ktoré zobrazujú a prekladajú produkty, čo ho predávajú |
| **Product data**: Brand, Batch traceability, Regulatory category, Toy status, Batteries / magnets, SVHC above 0.1%, SVHC substance, CLP signal word, Material composition (% by weight) | Karta **Compliance** trade unit |
| **Product data**: EUDR status, commodity, species (scientific name), country of production, region of production, plot geolocation, certification, legality evidence | Blok EUDR na karte **Compliance** trade unit |
| **Packaging components**: jeden riadok na komponent, na úroveň obalu, na časť | Obalová rodina trade unit (PPWR, vratky EPR) |
| **Supplier declarations**: company, signed by, position, date a jedna odpoveď na každé vyhlásenie | Podpísané vyhlásenie na karte **Declarations** dodávateľa |

Stĺpce **Packaging components**: Part reference, Packaging level (Primary, Secondary, Tertiary, Pallet alebo Service), Component, Material, Material code (PAP 20, PE-LD 4 …), Weight (g), Quantity at this level, Recycled content %, Recycled content evidence, Recyclability, Separable, Marks on the packaging, National marks, Artwork owner, Notes. Quantity je počet kusov komponentu na jeho úrovni: 2 etikety na jednej fľaši je 2. Aiku vypočíta, koľko z neho nesie jedna predajná jednotka. Komponent Secondary zdieľajú jednotky v SKO a Tertiary kartón všetky jednotky v ňom. Paletové pomôcky sa na jednotku nepočítajú.

Obal sa zadáva raz. Časti zabalené presne rovnako zdieľajú jednu obalovú rodinu a už známy komponent, napríklad tá istá fľaša alebo kartón, sa zdieľa namiesto kopírovania.

Rovnako ako pri ostatných stĺpcoch, údaje o súlade vyplnia iba to, čo je prázdne. Trade unit, ktorý už má text GPSR, odpoveď o súlade alebo obalovú rodinu, si ich ponechá.

Náhľad upozorní, bez zastavenia importu, keď:

- EUDR status hovorí Yes, ale chýba commodity, country of production, plot geolocation alebo legality evidence. EUDR sa na AW vzťahuje od 30. 12. 2026 a AW podáva vyhlásenie o náležitej starostlivosti, preto tieto údaje potrebuje.
- Material composition nedáva dokopy 100 %.
- SVHC nad 0.1% je deklarovaná bez uvedenia látky.
- Riadok obalu nemá hmotnosť, alebo úroveň, ktorú aiku nepozná (riadok sa potom vynechá).

Náhľad tiež vypíše riadky obalov bez Part reference alebo s takou, ktorá nie je na karte Product data, a každé vyhlásenie dodávateľa, na ktoré nie je odpoveď Yes, vrátane nezodpovedaných.

**Supplier declarations** číta Company, Signed by, Position a Date (každé ako popis s hodnotou vedľa neho), potom nadpis **Statement | Answer** s jedným vyhlásením na riadok. Dátumy sa čítajú v poradí deň-mesiac: 01/10/2026 je 1. október. Vyhlásenie sa uloží, keď sa importuje aspoň jeden riadok, raz na nahratie: opätovné nahratie súboru ho uloží znova, s dátumom tohto nahrania.

## Nahrávanie

Otvorte dodávateľa, prejdite na **Products**, stlačte **Attach file** a vyberte súbor. O niekoľko sekúnd sa otvorí náhľad.

## Pridanie jedného produktu bez sheetu

Pre jediný produkt stlačte **New Supplier Product** na stránke **Products** dodávateľa. Formulár má rovnaké polia ako šablóna, s rovnakými nadpismi, zoskupené do Product, Packing and ordering, Cost and prices a Weights and sizes. Platia rovnaké pravidlá: jedna jednotka na produkt, peniaze v mene stĺpca (symbol je v poriadku, "€10.20" v poli v €, ale suma v £ v poli v € sa odmietne), hmotnosti v kg, rozmery ako 20x10x5, `auto` pre čiarový kód z poolu.

Uloženie má dva kroky:

1. **Save** (uložiť). Počas písania sa pod každým poľom zobrazujú kontroly polí z nahrávania. Stlačením **Save** sa spustia všetky znova a k tomu rovnaké AI kontroly, aké dostane nahrávanie: otázky k jednotlivým produktom a AI review. Trvá to až minútu. Ak nič nevyjde, produkt sa vytvorí hneď.
2. **Check before saving** (skontrolovať pred uložením). Ak niečo vyjde, otvorí sa okno s návrhom opravy od AI, každým zistením a dotknutými poľami, aby ste ich tam mohli opraviť. Zaškrtnite, čo akceptujete, a stlačte **Submit** (odoslať). Submit je konečný: AI sa už nepýta znova. Ak zmeníte pole, na ktoré AI upozornila, upozornenie zmizne. Rozhodnutie, o ktoré AI požiadala, zostane, označené ako týkajúce sa predchádzajúcej hodnoty, a stále vyžaduje zaškrtnutie. Zmena Part reference si vyžaduje nový Save.

Zistenia používajú rovnaké farby ako náhľad:

- **červená**: treba opraviť;
- **oranžová**: vyžaduje rozhodnutie. Zaškrtnite **This is OK, I accept responsibility**, ak je to správne. Ak pole zmeníte a správa sa zmení, zaškrtnite ju znova;
- **modrá**: Part reference už existuje. Zaškrtnutím **Add this supplier to it** (pridať k nemu tohto dodávateľa) pridáte dodávateľa k tomuto trade unit ako ďalší zdroj;
- **jantárová**: stojí za pozretie, nič sa nezaškrtáva.

Ak sa AI kontroly nedajú spustiť, okno namiesto toho žiada **I accept responsibility**, rovnako ako pri nahrávaní.

Keď SKO obsahuje viac než jednu jednotku, pod Packing sa zobrazí **SKO name**, predvyplnené ako "Pack of N …". Zmeňte ho, ak je formulácia nesprávna.

Submit vytvorí rodiny, trade unit, čiarový kód, SKO a produkt dodávateľa naraz, presne ako Import pre riadok, a otvorí nový produkt dodávateľa. Každá organizácia, ktorá nakupuje od dodávateľa, dostane produkt dodávateľa a jeho SKO okamžite, navzájom prepojené, takže produkt možno hneď objednať. Kto každé rozhodnutie zaškrtol a kedy, sa uchová na produkte dodávateľa.

Formulár neobjednáva kartóny. Produkt pridajte do nákupnej objednávky neskôr, alebo použite sheet, keď chcete aj koncepty nákupných objednávok.

## Náhľad

Náhľad zobrazuje každý riadok s tým, čo aiku našlo, v troch druhoch:

| Značka | Význam | Čo robiť |
| --- | --- | --- |
| **Fix in the sheet** | Riadok sa nedá importovať tak, ako je: povinná bunka je prázdna, číslo nie je číslo, čiarový kód je nesprávny, cena je v zlej mene, rovnaká Part reference sa vyskytuje dvakrát | Opravte súbor a nahrajte ho znova, alebo riadok vynechajte |
| **Needs a decision** | Riadok sa dá importovať, ale niečo vyzerá riskantne: názov, ktorý znie ako balenie, marža pod cieľom, existujúci produkt, ktorý sa aktualizuje, chýbajúci čiarový kód, kartón, ktorý sa nedelí na outery | Zaškrtnite **This is OK, I accept responsibility**, ak je to správne; aiku zaznamená, kto to zaškrtol a kedy. Inak opravte súbor |
| **Check** | Stojí za pozretie, nič nezastavuje: nová rodina, zvláštne vyzerajúca hmotnosť alebo rozmer, hodnoty ďaleko od ostatných produktov dodávateľa | Prečítajte si to; nič sa nezaškrtáva |

**Skip row** (vynechať riadok) vynechá riadok z tohto importu. **SKO name** sa zobrazí, keď SKO obsahuje viac než jednu jednotku: je predvyplnené ako "Pack of N …" a môžete ho zmeniť.

**Import** zostáva vypnutý, kým niektorý riadok ešte potrebuje opravu alebo rozhodnutie; zoznam na konci presne hovorí, na čo čaká.

### Čo aiku kontroluje

- **Marže**: naša marža z odporúčanej ceny musí byť po započítaní landed cost (unit cost plus unit expense plus extra costs, prepočítané výmenným kurzom aiku) aspoň 60 %. Marža predajcu (RRP voči našej cene) je zvyčajne okolo 58 %; pod 50 % vyžaduje rozhodnutie.
- **Ceny v £ a €** sa musia po prepočte zhodovať do 25 %.
- **Balenie**: kartón sa musí deliť na celé predajné outery; jednotky sa musia zmestiť do SKO a SKO do kartónu; hmotnosti musia sedieť a hustota musí byť uveriteľná.
- **Rodiny**: nová rodina sa označí, rovnako ako tá, ktorá vyzerá ako existujúca (preklep). Part reference, ktorej prefix nezodpovedá ostatným produktom rodiny, sa odmietne.
- **Existujúce produkty**: existujúca Part reference sa prepojí s týmto trade unit; existujúci kód dodávateľa aktualizuje tento produkt dodávateľa a zmena nákladov nad 20 % vyžaduje rozhodnutie.
- **Minimum order**: kartóny objednané naprieč organizáciami musia dosiahnuť minimum dodávateľa.

### AI kontroly

Kým čítate náhľad, na pozadí bežia dve AI kontroly a stránka sa aktualizuje sama:

- každý riadok sa kontroluje na preklepy, materiály, ktoré k položke nepasujú, čísla, ktoré pre položku vyzerajú nesprávne, produkt v zlej rodine a riadky, ktoré vyzerajú posunuté;
- potom sa skontroluje celý sheet: hore sa zobrazí krátka **AI review** a pod riadkami, ktoré treba zmeniť, poznámka **AI suggests** s presnou opravou.

Import na ne čaká. Ak AI nemôže bežať, každý riadok namiesto toho žiada **I accept responsibility**, takže nahrávanie nikdy neuviazne.

### Zdrojové ceny (dodávatelia z Číny)

Keď je adresa dodávateľa v Číne, AI potom vyhľadá každý nový produkt na zdrojových weboch a pod riadkom zobrazí cenové rozpätie, ktoré našla pre jednu jednotku, s odkazmi na porovnávané položky:

- **Within the sourcing price range**: cena je férová.
- **More than 30% above**: možno preplácame; požiadajte dodávateľa o lepšiu cenu.
- **Well below**: pred objednaním skontrolujte kvalitu a špecifikáciu.

Veľkoobchodné ceny závisia od objednaného množstva, preto rozpätie berte ako orientačné. Vyhľadáva sa iba prvých 20 nových riadkov a názov produktu vyhľadaný za posledných 30 dní použije tú istú odpoveď. Táto kontrola je iba poradná: Import na ňu nečaká. Zdieľa limity výdavkov s AI review a zastaví sa, keď sa dosiahnu.

Zdrojové weby sú konkurenti nastavení na predaj **Factory** na stránke Competitors master shopu. Bez nich sa táto kontrola nespustí.

## Koncepty nákupných objednávok

Stĺpce **Order Cartons UK / SK / ES / Aroma** objednávajú kartóny pre každú organizáciu. Náhľad ukazuje pre každú organizáciu, koľko kartónov a riadkov sa objedná a na ktorej objednávke:

- ak organizácia už má **otvorený koncept** pre tohto dodávateľa (odoslaný cez agenta, keď ho dodávateľ má), riadky sa do neho pridajú a množstvo nastaví sheet;
- inak sa vytvorí nový koncept. Zaškrtnite **New draft instead** (namiesto toho nový koncept), ak chcete vždy nový.

Riadky, ktoré už na koncepte sú a nie sú v sheete, sa nechajú na pokoji. Objednávky zostávajú konceptmi, kým ich niekto neodošle.

## Import

Stlačte **Import**. Každý riadok sa vytvára samostatne, takže jeden zlyhaný riadok ostatné nezastaví; stránka potom ukáže, ktoré riadky boli vytvorené, vynechané alebo zlyhali, a koncepty nákupných objednávok, ktoré sa naplnili.

## Opätovné nahratie toho istého sheetu

Opravený súbor môžete nahrať, koľkokrát potrebujete. Riadky, ktorých kód dodávateľa už existuje, sa ponúknu ako **aktualizácia** tohto produktu dodávateľa a riadky, ktorých Part reference existuje, sa prepoja, nikdy nezduplikujú.

## Po importe

Keď niekto vytvorí **master product** z jedného z týchto trade units, jeho cena a RRP v £ a € sa predvyplnia z odporúčaných cien v sheete a formulár ukáže odporúčaný počet jednotiek na outer. Na nákupných objednávkach sa odhad jednotkových výdavkov dodávateľa pridá ako **Estimated total incl. supplier expenses** pod skutočný súčet, ako pomôcka pri rozpočtoch.
