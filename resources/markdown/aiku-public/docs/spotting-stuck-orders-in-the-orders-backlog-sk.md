---
title: Ako nájsť zaseknuté objednávky v backlogu objednávok
summary: Backlog objednávok teraz ukazuje, ktoré objednávky sú príliš dlho v jednej fáze, ktoré majú riadky, pri ktorých skladník čaká na tovar, a kto má ktorú objednávku, takže nemusíte čakať, kým sa ozve sklad.
date: 2026-10-10
source_date: 2026-10-10
tags: orders, backlog, picking, out of stock, customer service, stuck orders
category: crm
help_routes: grp.org.shops.show.ordering.backlog
---

<aside class="tldr">
Otvorte <b>Orders → Backlog</b>. Štyri boxy hore sú objednávky, ktoré potrebujú človeka: <b>Stuck</b> (viac ako 2 pracovné dni v tej istej fáze), <b>Waiting for stock</b> (skladník nenašiel riadok a zákaznícky servis o tom nevie), <b>With customer service</b> (riadky poslané vám na rozhodnutie) a <b>Waiting longest</b>. Kliknutím na názov fázy v boxe otvoríte presne tieto objednávky. V každom zozname stĺpec <b>In this stage</b> ukazuje, ako dlho je objednávka vo fáze, najstaršie hore a červenou, ak je zaseknutá, stĺpec <b>Progress</b> ukazuje, koľko je vychystané alebo zabalené, a stĺpce <b>Picker</b> / <b>Packer</b> ukazujú, kto má objednávku.
</aside>

Nájdete ho v **váš obchod → Orders → Backlog**. Nepotrebujete prístup k obrazovkám Goods out v sklade: všetko nižšie je v backlogu obchodu.

## Boxy hore

- **Stuck** — objednávky, ktoré sú v tej istej fáze viac ako 2 pracovné dni. Pod súčtom je štítok pre každú fázu s počtom zaseknutých; po prejdení myšou uvidíte vek najstaršej, kliknutím otvoríte zoznam.
- **Waiting for stock** — objednávky s aspoň jedným riadkom, ktorý skladník nenašiel a nechal čakať na doplnenie zo skladu. Tieto riadky sa **neposielajú** zákazníckemu servisu, takže ich doteraz mimo skladu nikto nevidel. Ak tu riadok čaká dlho, opýtajte sa skladu, či tovar príde, alebo či vám má riadok poslať.
- **With customer service** — objednávky s riadkami, ktoré vám skladník poslal. **Handle the waiting lines** otvorí zoznam, kde zvolíte Don't pick, Replace alebo Send back.
- **Waiting longest** — päť objednávok, ktoré sú zaseknuté najdlhšie, s ich fázou. Kliknutím na referenciu otvoríte objednávku.

Keď nič nie je zaseknuté a žiadny riadok nečaká, zobrazí sa jeden zelený riadok.

Odoslané, ale nezaplatené objednávky čakajú na zákazníka, preto sa nepočítajú ako zaseknuté.

## V každom zozname

Kliknutím na číslo v boxoch fáz otvoríte danú fázu. Zvýraznená je len vybraná fáza a riadok nad zoznamom jednoducho hovorí, čo znamená, napríklad *Ready to be picked — Sent to the warehouse, picking has not started*.

Stĺpce vľavo opisujú objednávku; stĺpce vpravo, ako postupuje skladom.

- **Reference, Customer, Net, Submitted** — objednávka. Malá ikona pred sumou je stav platby; po prejdení myšou uvidíte detail.
- **In this stage** — ako dlho je objednávka v aktuálnej fáze. Zoznamy sa otvárajú **od najstarších**. Červený štítok znamená zaseknutú objednávku. Po prejdení myšou uvidíte presný dátum.
- **Progress** — pruh, percento a počet riadkov v zátvorke. Po fázu Picked ide o postup vychystávania; vo fáze Packing o to, koľko vychystaných riadkov je zabalených. Jantárový štítok vedľa znamená riadky, ktoré čakajú na tovar alebo sú u zákazníckeho servisu. Fázy Packed a Waiting for dispatch pruh nemajú, pretože práca je hotová.
- **Picker / Packer** — kto má objednávku. Vo fáze *Ready to be picked* je stĺpec Picker prázdny: objednávka ešte nebola nikomu pridelená.
- **Delivery** — od fázy Packed: dopravca a pod ním malým písmom celé sledovacie číslo.

Objednávka vo fáze Picking, ktorá nemá nič v stĺpcoch Progress a Picker, nemá otvorený dodací list: nikto na nej nepracuje. Otvorte objednávku a overte to v sklade.

## Filtrovanie

Vedľa Destination a Channel sú teraz **Payment** (Paid, Unpaid) a **Needs attention** (Stuck, Waiting for stock, With customer service). Každý ukazuje počet pre fázu, v ktorej ste. Kliknutím zobrazíte len tieto objednávky, ďalším kliknutím filter zrušíte.
