---
title: Plánovanie objednávok u dodávateľov pre predobjednávky
summary: Ako nákupný tím vidí všetky predobjednávky, ktoré ešte čakajú na tovar, zoskupené podľa dodávateľa, porovnáva ich s minimálnou objednávkou a dátumom pre zadanie objednávky u dodávateľa, a označuje objednávku u dodávateľa ako zadanú alebo predobjednávky ruší.
date: 2026-09-28
source_date: 2026-09-28
tags: procurement, orders
category: procurement
series: Pre-orders
---

<aside class="tldr">
<b>Procurement › Pre-orders</b> zobrazuje všetky zákaznícke predobjednávky, ktoré ešte čakajú na tovar, zoskupené podľa dodávateľa toho, na čo čakajú. Pri každom dodávateľovi vidíte, koľko predobjednávok je, ich množstvo, predajnú hodnotu a približnú cenu u dodávateľa, vedľa dodávateľovej <b>minimum order</b> a <b>order-by date</b>. Keď objednávku u dodávateľa zadáte, označte to cez <b>Mark supplier ordered</b>. Ak sa minimum nedá v termíne dosiahnuť, rozhodnete, či objednať aj tak, alebo predobjednávky zrušiť cez <b>Cancel all, full refund</b>.
</aside>

## Čo stránka zobrazuje

Zákazníci si môžu predobjednať produkty označené ako **back-order** alebo **made-to-order** (pozri [Predaj predobjednávok](/docs/selling-pre-orders-sk)). U niektorých dodávateľov sa objednáva pri každej zákazníckej objednávke zvlášť; u iných až vtedy, keď sa nazbiera dosť predobjednávok na naplnenie zásielky. Táto stránka slúži na ich zoskupenie.

Každý dodávateľ je jeden riadok, pričom hore sú dodávatelia, ktorých order-by date je najbližšie:

- **Pre-orders** — koľko zákazníckych objednávok na tohto dodávateľa čaká.
- **Quantity** — koľko z ich SKO čaká, v jednotkách SKO.
- **Sales value** — za koľko sa čakajúce položky predávajú, v mene organizácie.
- **At supplier cost (approx.)** — čakajúce množstvo pri aktuálnej cene dodávateľa, v mene dodávateľa. Vedľa neho je dodávateľovo **minimum**, zelené, keď je dosiahnuté, a červené, keď nie.
- **Order by** — dátum, kedy by mala byť objednávka u dodávateľa zadaná.

Kliknutím na dodávateľa zobrazíte každý čakajúci riadok: objednávku, obchod, zákazníka, produkt a SKO, množstvo, kedy bola objednaná, kedy bola zadaná objednávka u dodávateľa a dátum, dokedy má byť odoslaná.

Predobjednávka čaká na **preferred supplier** každého zo svojich SKO. Keď jedna objednávka čaká na dvoch dodávateľov, zobrazí sa pod oboma.

## Nastavenie minima a dátumu pre zadanie objednávky

Obe sú na dodávateľovi: **Minimum order** v jeho nákupných nastaveniach a **Order by date** a **pre-order lead time** pod **Pre-orders**. Dodacia lehota je to, čo sa hovorí zákazníkom, aby čakali, takže ju držte reálnu: je to čas od objednania zákazníkom po naše odoslanie.

## Zadanie objednávky u dodávateľa

Keď ste u dodávateľa objednali, stlačte **Mark supplier ordered** na riadku daného dodávateľa. Označí to každú predobjednávku v skupine, ktorá ešte nie je označená.

Toto je pre zákazníka dôležité: obchodný zákazník môže položku made-to-order zrušiť zadarmo **dovtedy, kým nie je zadaná objednávka u dodávateľa**. Odvtedy si pri zrušení záloha ponecháva. Označte to v deň, keď objednávate, nie skôr.

## Keď sa minimum nedosiahne

Ak predobjednávky nedosiahnu dodávateľovo minimum do order-by date, nákupný tím rozhodne:

- **Objednať aj tak** — zadajte objednávku u dodávateľa a označte ju ako vyššie.
- **Zrušiť ich** — stlačte **Cancel all, full refund**. Každá predobjednávka v skupine sa zruší a každému zákazníkovi sa vráti všetko, vrátane zálohy, na jeho zostatok na účte. Dostanú e-mail.

Jednu predobjednávku môžete zrušiť aj zo stránky jej objednávky.

## Keď tovar dorazí

Tu nemusíte robiť nič: keď sa tovar naskladní, čakajúce predobjednávky si ho prevezmú, najstaršie ako prvé, a zmiznú z tejto stránky. Zákazníci sú vyzvaní na doplatok a každá objednávka ide do skladu hneď, ako je zaplatená.

<aside class="wayfinder">

### Kam kliknúť v aiku

- **Otvorenie stránky** — **Procurement**, potom karta **Pre-orders** na dashboarde.
- **Zobrazenie riadkov dodávateľa** — kliknite na riadok dodávateľa.
- **Označenie objednávky u dodávateľa ako zadanej** — **Mark supplier ordered** na riadku dodávateľa.
- **Zrušenie predobjednávok dodávateľa** — **Cancel all, full refund** na riadku dodávateľa.
- **Nastavenie minima, order-by date a dodacej lehoty** — otvorte dodávateľa, **Edit**: **Minimum order**, potom **Pre-orders**.

### Aké oprávnenia potrebujete

- Zobrazenie stránky vyžaduje oprávnenie zobrazovať procurement v organizácii.
- **Mark supplier ordered** a **Cancel all, full refund** vyžadujú oprávnenie upravovať procurement.

</aside>
