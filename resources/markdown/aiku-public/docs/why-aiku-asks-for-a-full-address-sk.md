---
title: Prečo aiku pýta úplnú adresu
summary: Čo sa počíta ako úplná adresa, prečo objednávka bez nej čaká pred skladom, ako ju uvoľniť a čo robiť, keď sa zákazník nedá zastihnúť.
date: 2026-09-10
source_date: 2026-09-10
tags: orders, crm, invoices, addresses, accounting
category: orders
help_routes: grp.org.shops.show.crm.customers, grp.org.shops.show.ordering.orders.show, grp.org.accounting.invoices
---

<aside class="tldr">
Všade, kde adresu píše človek, aiku teraz pýta tie časti, ktoré daná krajina naozaj používa. Ak zákazník nemá na účte adresu, jeho objednávka sa normálne zaplatí, ale <b>zastaví sa pred skladom</b> namiesto vychystania: nič sa neodošle a nevystaví sa faktúra s prázdnou adresou. Doplňte adresu k zákazníkovi a objednávka pokračuje sama. Ak sa zákazník nedá zastihnúť, je tu tlačidlo poslať ju aj tak.
</aside>

## Čo je úplná adresa

Adresa nie je v každej krajine to isté, takže aiku pýta to, čo daná krajina používa, a nič navyše:

- **Spojené kráľovstvo** — ulica, mesto a PSČ.
- **Írsko** — ulica a mesto. PSČ sa nepýta, írske adresy ho v tomto formáte nepoužívajú.
- **Španielsko a Taliansko** — ulica, mesto, provincia a PSČ.
- **Spojené arabské emiráty** — ulica a emirát. Žiadne mesto.

Vychádza to z toho istého zoznamu krajín, ktorý rozhoduje, aké políčka sa na formulári zobrazia, takže políčka, ktoré vidíte, sú presne tie, ktoré treba vyplniť. Samotná **0** sa za adresu nepovažuje; kedysi sa prijímala a práve ona sa tlačila ako nuly na dokladoch.

Úplnú adresu budete zadávať všade, kde ju píše človek: registrácia na webe obchodu, zákazník upravujúci svoje údaje, pridanie doručovacej adresy, úprava zákazníka, objednávky alebo faktúry v systéme a obrazovky obchodu, dodávateľa, agenta, skladu a firmy.

Objednávky, ktoré prichádzajú samy z predajných kanálov — Shopify, eBay, Amazon, TikTok a ďalšie — sa pre neúplnú adresu **nikdy** neodmietnu. Platba za ne už prebehla inde, takže odmietnutie by znamenalo stratu objednávky. To isté platí pre nočné importy dát.

## Čo sa stane, keď zákazník nemá adresu

Niektoré účty vznikli skôr, než sa adresa vyžadovala, takže žiadnu nemajú. Keď taký zákazník objedná:

1. Zaplatí normálne. Platba sa pre chýbajúcu adresu nikdy neodmieta.
2. Objednávka sa normálne odošle.
3. **Zastaví sa pred skladom.** Nevytvorí sa dodací list, takže nie je čo vychystať ani zabaliť.
4. Zostane v zozname **submitted** s upozornením v skladovej poznámke, že zákazník nemá adresu.

Nič sa nestratí a nikomu sa neúčtuje niečo, čo nedostane — objednávka jednoducho čaká, kým niekto adresu vyrieši.

## Ako uvoľniť zastavenú objednávku

**Správna cesta: doplniť adresu k zákazníkovi.** Otvorte zákazníka, doplňte adresu a objednávka si ju prevezme a pokračuje do skladu sama. Túto cestu uprednostnite, pretože zároveň vyrieši každú ďalšiu objednávku daného účtu.

**Druhá cesta: doplniť adresu na objednávku.** Otvorte objednávku a upravte jej fakturačnú adresu, prípadne doručovaciu, ak chýba tá. Len čo má objednávka všetko potrebné, sama pokračuje do skladu. Vyrieši to jednu objednávku, nie účet.

Ak stlačíte **Send to warehouse**, kým adresa chýba, aiku vám to povie, namiesto toho aby sa nestalo nič.

## Keď sa zákazník nedá zastihnúť

Niekedy nikto neodpovedá a tovar musí ísť. Zastavená objednávka má tlačidlo **Send anyway, no address**. Robí presne to, čo hovorí: objednávka ide do skladu a normálne sa vychystá, zabalí a odošle, a do skladovej poznámky pribudne riadok, že bola zámerne poslaná bez adresy.

Použite ho ako poslednú možnosť, kvôli tomu, čo nasleduje: faktúra k tejto objednávke bude mať v políčku adresy len krajinu. Vystavený doklad sa už nemení, takže sa oplatí skúsiť zákazníka zastihnúť ešte raz.

## Prečo na tom záleží

Predtým účet bez adresy viedol k faktúre s nulami tam, kde mala byť adresa, a dialo sa to niekoľkokrát denne, každý deň. Zákazník dostane doklad, ktorý vyzerá pokazene; nikto si to nevšimne, kým sa nepozrie. Adresa je zároveň tá, na ktorú je faktúra vystavená, takže prázdna adresa je skutočná diera v dokladoch, nie kozmetický detail.

<aside class="wayfinder">

### Kde v aiku kliknúť

- **Vidieť čakajúce objednávky** — zoznam objednávok organizácie, **Submitted**. Zastavená má upozornenie v skladovej poznámke.
- **Doplniť adresu zákazníkovi** — **CRM → Customers**, otvorte zákazníka, upravte adresu. Uvoľní to všetky jeho zastavené objednávky.
- **Opraviť len jednu objednávku** — otvorte objednávku a upravte fakturačnú adresu, prípadne doručovaciu, ak chýba tá.
- **Poslať bez adresy** — otvorte zastavenú objednávku a použite **Send anyway, no address**.
- **Opraviť adresu na faktúre** — otvorte faktúru a kliknite na ceruzku pri adrese.

### Aké oprávnenia potrebujete

- Úprava zákazníkov a objednávok patrí do bežnej práce zákazníckeho servisu v danom obchode.
- Ceruzka pri adrese na faktúre je pre **accounting supervisorov** v danej organizácii.
- **Send anyway, no address** vyžaduje rovnaké oprávnenie ako posielanie akejkoľvek objednávky do skladu.

</aside>
