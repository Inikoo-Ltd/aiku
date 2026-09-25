---
title: Vytváranie compliance štítkov
summary: Pre compliance tím — ako SKO získa svoj compliance štítok: kto rozhoduje o tom, čo musí obsahovať, ako umiestniť názvy, zloženie, upozornenia v každom jazyku, symboly a voľný text, odkiaľ pochádza každá informácia, a ako sa štítok publikuje, aby ho mohli agenti tlačiť.
date: 2026-09-25
source_date: 2026-09-25
tags: warehouse, inventory, labels, compliance, printing
category: warehouse
series: Compliance labels
order: 1
---

<aside class="tldr">
Každá SKO môže mať svoj vlastný compliance štítok: názov produktu, hmotnosť, zloženie, upozornenia a návod na použitie v každom jazyku, zodpovednú osobu, symboly recyklácie a bezpečnosti, a číslo šarže s dátumom spotreby. O túto prácu sa delia traja ľudia. <b>Compliance Manager</b> rozhoduje, čo musí obsahovať každý štítok danej SKO, <b>Compliance Worker</b> štítok navrhuje a <b>Compliance Supervisor</b> ho publikuje. Po publikovaní ho agenti, ktorí pre nás daný produkt nakupujú, tlačia s číslom šarže a dátumom spotreby tovaru, ako je vysvetlené v <a href="/docs/printing-labels-as-an-agent-sk">tlačení štítkov ako agent</a>.
</aside>

## Kde sa nachádzajú compliance štítky

Otvorte SKO a vyberte **Labels**, vedľa **Batch codes**. Stránka má dve karty.

- **Labels** — čo musí každý štítok obsahovať, štítky už vytvorené pre túto SKO a tlačidlo na vytvorenie nového.
- **Compliance** — zoznam certifikátov, bezpečnostných testov, colných kódov a ďalších dokumentov, ktoré produkt potrebuje, každý so svojou referenciou, dátumami platnosti a informáciou, či je v súlade.

Štítok patrí SKO organizácie, ktorá produkt nakupuje. Ten istý produkt nakúpený vo Veľkej Británii a na Slovensku má dve SKO, takže každá môže mať správnu zodpovednú osobu a jazyky pre svoj vlastný trh.

Továreň k tým istým štítkom pristupuje cez svoje artefakty. Ako funguje samotný editor štítkov — grafika, rozloženie štítkov na hárku A4, čiarový kód — je vysvetlené v <a href="/docs/designing-and-printing-labels-sk">navrhovaní a tlači štítkov</a>. Tento návod sa venuje tomu, čo pridáva compliance štítok.

## Kto čo robí

| Pozícia | Čo robí |
| --- | --- |
| **Compliance Manager** | Zaškrtáva informácie, ktoré musí obsahovať každý štítok danej SKO. |
| **Compliance Worker** | Navrhuje štítky a udržiava ich aktuálne. |
| **Compliance Supervisor** | Kontroluje štítky a publikuje ich, aby ich agenti mohli tlačiť. |

Agenti štítok nikdy nenavrhujú ani nemenia. Vidia ho až vtedy, keď je publikovaný.

## Rozhodovanie, čo musí štítok obsahovať

V hornej časti karty **Labels** je box **Mandatory information**. Compliance Manager tu zaškrtne, čo musí obsahovať každý štítok tejto SKO — napríklad zloženie, EU zodpovednú osobu, upozornenia v nemčine a symboly recyklácie — a stlačí **Save**.

Každý štítok v zozname potom zobrazuje každú povinnú položku ako malý chip: zelený s fajkou, keď ju štítok obsahuje, červený s výstražným znakom, keď chýba. **Štítok s červeným chipom nemožno publikovať.**

Niekedy je informácia už vytlačená na krabici dodávateľa. V takom prípade zaškrtnite na chipe **on artwork**: štítok ju potom počíta ako prítomnú bez toho, aby ju tlačil druhýkrát.

## Umiestňovanie informácií

Stlačte **New label**, zadajte názov a, ak ju máte, nahrajte grafiku dodávateľa ako pozadie. Potom pridajte, čo má štítok obsahovať.

- **Batch code**, **Expiry date** a **Barcode** majú vlastné tlačidlá. Číslo šarže a dátum spotreby v návrhu sú len príklady: skutočné sa zadávajú vždy pri tlači štítkov.
- Všetko ostatné je v menu **+ Product information**. Vyberte položku a tá sa umiestni na štítok s textom alebo symbolmi prevzatými zo záznamu produktu. Presuňte ju na miesto.

Položka, ktorú záznam produktu ešte nemá, sa v menu zobrazuje ako *not on the product record* a nedá sa vybrať. Najprv doplňte záznam produktu a potom sa vráťte k štítku.

**Text sa skopíruje v momente umiestnenia.** Ak sa záznam produktu neskôr zmení, odstráňte položku zo štítka a umiestnite ju znova, aby štítok zobrazoval nový text.

### Texty vo viacerých jazykoch

Názov produktu, upozornenia a návod na použitie sú ponúkané raz pre každý jazyk: *Warnings (German)*, *Warnings (French)* a podobne. Umiestnite jeden blok pre každý jazyk, ktorý štítok potrebuje.

Ponúkané jazyky sú tie, ktoré vyžaduje záznam produktu, plus každý jazyk, do ktorého sú texty produktu už preložené. Jazyk, ktorý ešte nemá preklad, sa zobrazuje ako *type it in*: umiestnite ho a text na štítok napíšte sami.

Dlhé texty, ako zloženie, upozornenia alebo adresa, by sa mali zalamovať. Vyberte položku, zaškrtnite **Wrap in a box** a nastavte šírku v milimetroch; text sa potom zalomí do riadkov vnútri boxu a zachová si vlastné zalomenia.

### Symboly

Umiestňujú sa ako obrázky, a to len vtedy, keď záznam produktu hovorí, že ich produkt nesie.

| Symbol | Zobrazí sa, keď má záznam produktu |
| --- | --- |
| Piktogramy nebezpečenstva | Zaškrtnuté nebezpečenstvá na produkte. |
| Značky obalového materiálu, napríklad PET 1 alebo PAP 21 | Svoje kódy obalového materiálu. |
| Značky CE, UKCA a WEEE | Zaškrtnutú príslušnú značku. |
| Otvorený pohárik s mesiacmi, napríklad 12M | Zvolenú dobu použiteľnosti po otvorení (PAO) ako svoje best before. |
| Francúzske logo triedenia a inštrukcia ("FR", "Cet emballage se trie") | Zaškrtnuté **Sorting / Recycling Information**. |

Vyberte symbol, ak chcete zmeniť jeho výšku v milimetroch.

### Voľný text

**Free text** slúži pre všetko, čo nie je informácia o produkte: pevné nadpisy ako "Weight / Peso / váha / Waga / Poids / Gewicht", alebo "Ingredients / Ingrédients / Inhaltsstoffe". Umiestnite ho a napíšte, čo má štítok zobrazovať. Free text sa nikdy nepreberá zo záznamu produktu, takže ho nemožno urobiť povinným.

## Odkiaľ informácie pochádzajú

| Na štítku | Vypĺňa sa v |
| --- | --- |
| Názov produktu, čistá hmotnosť, zloženie, krajina pôvodu, výrobca, CPNP, UFI, SCPN | Trade unit. |
| Upozornenia a návod na použitie | Trade unit, v sekcii **GPSR** (**Warnings**, **How To Use**). |
| Názov produktu, upozornenia a návod v iných jazykoch | Produkt v každom shope, v jeho prekladoch **Name** a **GPSR**. |
| Jazyky, ktoré musí štítok obsahovať | Trade unit, **Labeling & Compliance Marks** → **Languages**. |
| Zodpovedná osoba pre UK a EU | Údaje našich vlastných spoločností v UK a EU, ponúknuté, keď trade unit uvádza daný trh pod **Markets**. |
| Importér | Údaje organizácie, ktorá produkt nakupuje. |
| Symboly | Trade unit, **Labeling & Compliance Marks** a jeho nebezpečenstvá. |
| Čiarový kód | Čiarový kód SKO. |
| Číslo šarže a dátum spotreby | Zadáva sa pri tlači štítkov. |

Štítok je len taký dobrý, ako záznam produktu za ním. Ak upozornenie chýba v jednom jazyku, doplňte preklad k produktu namiesto toho, aby ste ho napísali len na jeden štítok: ďalší štítok, aj webová stránka, ho potom budú mať tiež.

## Publikovanie

Keď je každý povinný chip zelený alebo označený ako on artwork, Compliance Supervisor otvorí štítok a stlačí **Publish**. Od toho momentu agenti, ktorí pre nás produkt nakupujú, štítok vidia a môžu ho tlačiť.

Publikovaný štítok sa dá ešte vylepšiť. Zmeňte ho a stlačte **Publish again**: zmeny sa okamžite prejavia a štítok zostáva publikovaný. **Unpublish** ho agentom odoberie, kým nie je znova publikovaný.

<aside class="wayfinder">
<b>Kde kliknúť v aiku</b><br>
Váš sklad → <b>Inventory</b> → <b>SKOs</b> → otvorte SKO → <b>Labels</b>. Karta <b>Labels</b> obsahuje <b>Mandatory information</b> a <b>New label</b>; karta <b>Compliance</b> obsahuje certifikáty a testy. Záznam produktu je v <b>Trade Units</b> → otvorte trade unit → <b>Edit</b>, a preklady pri produkte v každom shope → <b>Edit</b>.
</aside>

<aside class="wayfinder">
<b>Aké práva potrebujete</b><br>
Jednu z pracovných pozícií <b>Compliance Manager</b>, <b>Compliance Worker</b> alebo <b>Compliance Supervisor</b>, v riadku <b>Compliance</b> skupinových oprávnení. Administrátori skupiny môžu vykonávať všetky tri.
</aside>
