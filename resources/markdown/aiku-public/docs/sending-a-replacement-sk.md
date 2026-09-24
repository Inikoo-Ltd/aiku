---
title: Odoslanie náhrady
summary: Znovu odošlite položky z expedovanej objednávky, ku každej uveďte dôvod, ktorý zaznamená, kto chybu spôsobil, a nechajte poznámku pre sklad.
date: 2026-09-16
source_date: 2026-09-16
tags: dispatch, replacements, customers
category: dispatch
help_routes: grp.org.shops.show.ordering.orders.show.replacement.create, grp.org.shops.show.crm.customers.show.replacements.index
---

<aside class="tldr">
Keď niečo z expedovanej objednávky príde poškodené, stratí sa alebo je nesprávne, pošlete zákazníkovi <em>náhradu</em>: novú dodaciu listinu len s položkami, ktoré sa posielajú znova. Každá položka náhrady potrebuje dôvod a každý dôvod sa zaznamená tomu, kto problém spôsobil — dopravcovi, skladu, dodávateľovi alebo zákazníkovi — takže vzniká záznam o chybných produktoch a chybách skladu.
</aside>

## Začatie náhrady

Otvorte objednávku. Keď je **Dispatched**, v záhlaví stránky sa objaví tlačidlo **Replacement**. Stlačte ho a otvorí sa obrazovka náhrady so zoznamom všetkých položiek, ktoré odišli na dodacej listine objednávky.

## Výber toho, čo poslať znova

Každý riadok zobrazuje **Quantity Dispatched** a políčko **Quantity Resend**. Napíšte, koľko kusov danej položky sa má poslať znova — nemôže to byť viac, než bolo expedované. **Replace All** vyplní každý riadok celým expedovaným množstvom, keď musí znova odísť celá objednávka.

Riadky ponechané na nule nie sú súčasťou náhrady.

## Uvedenie dôvodu pri každej položke

Každý riadok s množstvom na opätovné odoslanie potrebuje **Reason**. **Save** zostáva neaktívne, kým ho každý taký riadok nemá.

| Dôvod | Zaznamenané komu |
|---|---|
| Damaged by courier | Dopravca |
| Lost by courier | Dopravca |
| Wrong item sent | Sklad |
| Missing from parcel | Sklad |
| Broken, poor packaging | Sklad |
| Faulty product | Dodávateľ |
| Customer error | Zákazník |
| Other | Neznáme |

Vyberte dôvod, ktorý zodpovedá tomu, čo sa naozaj stalo: takto sa chyby neskôr počítajú, nie je to len poznámka k tejto objednávke.

## Poznámka pre sklad

Nad položkami je políčko **Note to warehouse**. Použite ho na čokoľvek, čo majú skladníci a baliči urobiť tentoraz inak — skontrolovať položku pred zabalením, pridať extra balenie, priložiť niečo, čo chýbalo. Poznámka sa uloží k novej náhrade. Nechajte ju prázdnu, ak sa má zachovať skladová poznámka samotnej objednávky.

## Po uložení

Stlačením **Save** sa vytvorí dodacia listina náhrady, ktorá ide do skladu ako každá iná (pozrite [Vychystanie a zabalenie dodacej listiny](/docs/picking-and-packing-a-delivery-note-sk)). Dôvod každej položky sa zobrazuje vedľa jej názvu na stránke dodacej listiny náhrady a na obrazovke vychystávania, takže sklad vie, prečo ide znova von.

Náhrady zákazníka sú uvedené na jeho stránke v **CRM** obchodu. Náhrady vytvorené pred zavedením dôvodov nemajú zobrazený žiadny dôvod.
