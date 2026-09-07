---
title: Skladové oblasti, lokácie a zásoby
summary: Pochopte, ako sa sklad delí na oblasti a lokácie, ako vytvoriť novú lokáciu a ako sa zásoba umiestňuje a presúva medzi lokáciami.
date: 2026-09-01
source_date: 2026-09-01
tags: warehouse, locations, stock
category: warehouse
---

<aside class="tldr">
Sklad sa delí na <b>oblasti</b> — sekcie ako Goods In alebo pickovacia zóna — a každá oblasť obsahuje súbor <b>lokácií</b>, jednotlivých políc alebo boxov, kde zásoba skutočne leží. Každé SKO (stock keeping object) je umiestnené v jednej alebo viacerých lokáciách a medzi lokáciami ho presúvate, alebo opravujete jeho počet, priamo z obrazovky danej lokácie.
</aside>

## Skladové oblasti

Otvorte svoj sklad a v ľavej navigácii choďte na **Locations → Areas**. Zoznam ukazuje meno každej oblasti, jej pickovacie poradie, koľko zásoby drží v hodnote, koľko lokácií sa v nej nachádza a koľko z nich je prázdnych.

Stlačte **Areas** hore v zozname na vytvorenie novej. Formulár sa pýta na:

- **Code** — interná skratka, do 16 znakov.
- **Name** — ako sa oblasť volá.
- **Picking position** — voliteľné číslo, ktoré určuje, kde sa táto oblasť nachádza v pickovacom poradí.

Otvorte ktorúkoľvek oblasť a ocitnete sa na jej karte **Overview**, vedľa ktorej sú karty **Locations** a **History**. Hlavička stránky ukazuje, koľko lokácií oblasť obsahuje, a odtiaľto môžete priamo v tejto oblasti vytvoriť novú lokáciu.

## Lokácie

Lokácie sú uvedené pod **Locations → Locations** v ľavej navigácii skladu. Zoznam sa dá filtrovať na **All**, **Empty** alebo **Partially empty** lokácie a každý riadok ukazuje kód lokácie, jej maximálnu hmotnosť, maximálny objem (v kubických metroch), koľko má skladových slotov a koľko z nich je prázdnych.

### Vytvorenie lokácie

Stlačte tlačidlo vytvorenia a vyplňte:

- **Code** — referenčný kód lokácie.
- **Max weight (kg)** — najviac, čo by mala lokácia uniesť podľa hmotnosti.
- **Max volume (m³)** — najviac, čo by mala pojať podľa objemu.

Lokácia sa dá vytvoriť priamo pod skladom, alebo vnútri konkrétnej oblasti — v oboch prípadoch skončí v zozname lokácií skladu.

### Čo ukazuje stránka lokácie

Otvorenie lokácie vás zavedie na jej kartu **Overview**, ktorá ukazuje jej maximálnu hmotnosť a objem, koľko skladových slotov má celkovo a koľko je prázdnych. V závislosti od nastavenia lokácie sa objavia ďalšie karty:

- **SKOs** — zásoba momentálne držaná v tejto lokácii, keď lokácia smie držať zásobu.
- **Pallets** — palety, ktoré sa nachádzajú v lokácii, keď sa lokácia používa na fulfilment.
- **Stock movements** — každá zmena množstva zaznamenaná voči tejto lokácii, keď lokácia smie držať zásobu.
- **History** — záznam úprav vykonaných na samotnej lokácii.

Lokácia sa dá zapnúť alebo vypnúť pre držanie bežnej zásoby, fulfilment paliet alebo dropshippingu — karty vyššie sa objavia len vtedy, keď je príslušné nastavenie zapnuté.

## Zásoba (SKOs)

Každý produkt držaný v sklade sa sleduje ako SKO. Celoskladový zoznam sa nachádza pod **Inventory → SKOs** a ukazuje referenciu, rodinu a meno každého SKO, koľko je ho na sklade, jeho hodnotu zásoby, potenciálne predaje, zásobu na ceste od dodávateľov a koľko dní pokrytia aktuálna zásoba dáva.

Vlastná zásoba SKO je rozložená v jednej alebo viacerých lokáciách — to isté SKO môže sedieť vo viacerých boxoch naraz, každý s vlastným množstvom.

## Presun zásoby medzi lokáciami

Z karty **SKOs** lokácie môžete presunúť zásobu z danej lokácie dvoma spôsobmi:

- **Move All SKO** — presunie všetky SKO momentálne v lokácii do inej lokácie podľa vášho výberu, naraz.
- **Partialy Move SKO** — najprv vyberte konkrétne SKO zo zoznamu, potom presuňte len tie do inej lokácie; zároveň si vyberiete, či sa dané SKO po presune odstráni z pôvodnej lokácie.

Oba formuláre sa vás pýtajú na **cieľovú lokáciu** a **dôvod presunu** (napríklad medziskladový presun, oprava chyby pri pickovaní alebo oprava zlého boxu), s voliteľnou poznámkou. Tento spôsob presunu udržiava množstvo SKO v každej lokácii presné a zaznamenáva presun, takže sa objaví na karte **Stock movements** oboch lokácií.

## Kontroly zásob a audity

Keď zásoba SKO v rámci celého skladu klesne pod prah nízkej zásoby daného skladu, objaví sa na obrazovke **Low Stock Audits**, dostupnej z dlaždice **Low Stock Audits** na dashboarde **Inventory** skladu. Tento zoznam ukazuje referenciu SKO, jeho rodinu, meno, aktuálnu zásobu a lokácie, v ktorých je držané — je to zoznam, ktorý personál prechádza, aby zásobu prepočítal a potvrdil.

Audit zásoby lokácie zaznamená rozdiel medzi tým, čo systém očakáva, a tým, čo ste skutočne napočítali: zadáte napočítané množstvo a dôvod (prepočet, prebytok pri počítaní, nedostatok pri počítaní, poškodené, expirované a ďalšie), a aiku zaznamená úpravu ako pohyb zásoby voči danej lokácii a označí zásobu ako skontrolovanú.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zobraziť alebo pridať skladové oblasti:</b> váš sklad → <b>Locations → Areas</b> → tlačidlo <b>Areas</b> na vytvorenie novej.</li>
<li><b>Zobraziť alebo pridať lokácie:</b> váš sklad → <b>Locations → Locations</b>, alebo z karty <b>Locations</b> vnútri oblasti — použite tlačidlo vytvorenia, alebo <b>New location</b> na stránke oblasti.</li>
<li><b>Zobraziť skladovú zásobu:</b> váš sklad → <b>Inventory → SKOs</b>.</li>
<li><b>Presunúť zásobu medzi lokáciami:</b> otvorte lokáciu → karta <b>SKOs</b> → <b>Move All SKO</b> alebo vyberte riadky a použite <b>Partialy Move SKO</b>.</li>
<li><b>Skontrolovať zásobu:</b> váš sklad → dashboard <b>Inventory</b> → dlaždica <b>Low Stock Audits</b>.</li>
</ul>
</aside>
<aside class="permissions"><strong>Permissions you need</strong>
<p>Zobrazenie oblastí, lokácií a zásoby vyžaduje prístup na zobrazenie inventára alebo lokácií pre daný sklad. Vytváranie alebo úprava oblastí a lokácií, a presun zásoby medzi lokáciami, vyžaduje prístup na úpravu lokácií (alebo rolu supervízora lokácií) pre daný sklad.</p>
</aside>
