---
title: Zadanie objednávky cez agenta
summary: Pre nákupcov - ako zadať purchase order dodávateľovi, od ktorého nakupujete cez agenta, poslať ju agentovi a sledovať ju až po stock delivery.
date: 2026-10-23
source_date: 2026-10-23
tags: procurement, purchase orders, agents
category: procurement
---

<aside class="tldr">
Agent nakupuje pre vás od viacerých dodávateľov. Každý dodávateľ dostane vlastnú <b>purchase order</b> (objednávku) s číslom zo série agenta, pretože každý potvrdzuje, vyrába a odosiela vo svoje dni. Každú pripravujete, odosielate a zasielate samostatne.
</aside>

## Kde to nájdete

**Procurement → Agents →** agent **→ Purchase Orders** zobrazuje každú objednávku zadanú cez agenta s jej dodávateľom, položkami, dátumom a sumou. Zobrazuje ich aj **Procurement → Purchase Orders**, s agentom pod menom dodávateľa.

## Príprava objednávky

Otvorte dodávateľa zo záložky **Suppliers** agenta a založte novú purchase order, alebo ju vytvorte z nahratia produktov dodávateľa či z nákupného zoznamu. Produkty a množstvá pridávate ako pri každom dodávateľovi. Objednávka dostane ďalšie číslo zo série agenta.

## Odoslanie a zaslanie

Keď je objednávka hotová, stlačte **Submit** a potom ju pošlite: agent dostane e-mail (alebo WhatsApp) s PDF objednávky. **PDF** hore vytlačí ten istý dokument kedykoľvek.

## Sledovanie objednávky

Stránka purchase order ukazuje vlastný stav dodávateľa, očakávaný príchod, dátum pripravenosti a zálohu. Potvrdenia, zálohy, dátumy, čisté odovzdanie (clean handover) a stock deliveries sa zaznamenávajú tam. Objednávky zadané pred 21. októbrom 2026 nájdete vo vyhľadávaní purchase orders aj podľa ich starého čísla objednávky agenta.

Keď agent odosiela, jedna stock delivery môže niesť objednávky viacerých jeho dodávateľov: pri potvrdenej objednávke **New stock delivery** ponúka pridať ju k dodávke toho istého agenta, ktorá je ešte rozpracovaná.

## Keď dodávateľ zmení cenu

Ešte pred potvrdením odoslanej objednávky sa agent môže dozvedieť, že dodávateľ chce za produkt novú cenu. Agent ju zaznamená na danom riadku spolu s novou cenou a voliteľnou poznámkou a vy dostanete upozornenie. Riadok zobrazí oranžový box s cenou, ktorú dodávateľ žiada, a cenou, ktorú nahrádza.

Odpovedzte na riadku jedným z troch tlačidiel:

- **Accept**: riadok prevezme novú cenu.
- **Keep old price**: riadok si ponechá svoju cenu. Ak dodávateľ za túto cenu nepredá, agent riadok zruší.
- **Set another price**: zadáte cenu, s ktorou súhlasíte, a riadok ju prevezme.

Ak chcete, aby sa cena, ktorú zvolíte, stala aj cenou produktu dodávateľa, zaškrtnite pred odpoveďou na riadku **Also update supplier price**. Kým je nejaká otázka k cene nezodpovedaná, agent objednávku potvrdiť nemôže: **Confirm** zostane sivé a uvedie, koľko riadkov čaká. Agent dostane upozornenie o vašej odpovedi.

## Keď agent požiada o iné množstvo

Agent môže tiež požiadať o zmenu množstva na riadku, napríklad preto, že dodávateľ predáva iba celé kartóny alebo nedokáže vyrobiť všetko. Agent zaznamená množstvo na danom riadku spolu s voliteľnou poznámkou a vy dostanete upozornenie. Riadok pod svojím množstvom zobrazí oranžový box s požadovaným množstvom a množstvom, ktoré nahrádza.

Odpovedzte na riadku:

- **Accept**: riadok prevezme nové množstvo a jeho suma sa prepočíta.
- **Keep old quantity**: riadok si ponechá svoje množstvo.

Objednávku nemožno potvrdiť, kým je nejaká otázka k množstvu nezodpovedaná, rovnako ako pri cenách. Agent dostane upozornenie o vašej odpovedi. V zozname nákupných objednávok oranžový odznak vedľa referencie počíta riadky čakajúce na vašu odpoveď k cenám alebo množstvám a filter **New price or quantity to answer** zobrazí tieto objednávky.

## Kto to môže používať

Povolenie na úpravu nákupu pre vašu organizáciu na pridávanie, odosielanie a zasielanie. Povolenie na zobrazenie nákupu na otváranie objednávok a ich tlač. Agenti zaznamenávajú vlastné dátumy podľa popisu v [Zaznamenávanie postupu objednávky ako agent](/docs/recording-order-progress-for-agents-sk).

Keď viaceré organizácie objednávajú od rovnakého dodávateľa, pozrite [Objednávanie od agenta spolu s ďalšími organizáciami](/docs/ordering-from-an-agent-with-other-organisations-sk).
