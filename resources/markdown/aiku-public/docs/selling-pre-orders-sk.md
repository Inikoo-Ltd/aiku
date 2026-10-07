---
title: Predaj predobjednávok
summary: Ako možno produkt objednať aj nad rámec skladu, ako back-order alebo made-to-order, čo zákazník vidí a platí, ako objednávka čaká na svoj tovar a ako sa rieši doplatok a zrušenie.
date: 2026-09-28
source_date: 2026-09-28
tags: orders, shop, catalogue, procurement, payments
category: orders
series: Pre-orders
---

<aside class="tldr">
<b>Predobjednávka</b> umožňuje zákazníkovi kúpiť viac, než máme na sklade. <b>Back-order</b> je skladová položka, ktorá je dočasne vypredaná: zaplatí sa v plnej výške a odošle sa, keď dorazí ďalšia dodávka. Položka <b>made-to-order</b> sa vôbec neskladuje: objednáme ju u dodávateľa, až keď si ju zákazník kúpi, a obchodní zákazníci pri pokladni platia len <b>zálohu</b>. Na webe sa nič nemení, kým nie je zapnutý prepínač obchodu <b>Enable pre-orders</b> a produkt nie je na predobjednávku označený. Predobjednávka čaká mimo skladu, kým nedorazí jej tovar, potom zákazník doplatí zvyšok a tovar sa odošle.
</aside>

## Back-order a made-to-order

Produkt možno označiť dvoma spôsobmi a oba sú vypnuté, kým ich niekto nezapne:

- **Back-order** — produkt, ktorý bežne skladujeme. Kým je vypredaný, zákazníci si ho stále môžu objednať a odošle sa, keď dorazí ďalšia dodávka. Odhadovaný termín odoslania vychádza z očakávaného príchodu otvorenej objednávky u dodávateľa.
- **Made-to-order** — produkt, ktorý neskladujeme, napríklad nábytok alebo sochy od našich zahraničných dodávateľov. Objednávame ho u dodávateľa, až keď si ho zákazník kúpi. Odhadovaný termín odoslania vychádza z dodacej lehoty.

Ak sú zapnuté obe, produkt sa považuje za made-to-order.

Produkty, ktoré takto označené nie sú, sa správajú presne ako doteraz: keď sú vypredané, nedajú sa kúpiť.

## Zapnutie predobjednávok pre obchod

Každá podmienka, ktorú zákazník odsúhlasí, je nastavením obchodu, takže každý web môže mať svoju vlastnú. Všetky sú spolu v nastaveniach obchodu pod **Pre-orders**:

- **Enable pre-orders** — prepínač pre celý obchod. Kým je vypnutý, označené produkty sa správajú ako každý iný produkt.
- **Default lead time** a **Dispatch estimate range** — web zobrazuje rozsah v týždňoch, nie dátum. Dodacia lehota 12 týždňov s rozsahom 2 sa zobrazí ako „estimated dispatch 12–14 weeks“.
- **Made-to-order deposit** — časť, ktorá sa platí pri pokladni za položky made-to-order; ak nie je zmenené, 30 %.
- **Pay in full below** — objednávky made-to-order v hodnote nižšej, než je táto suma, sa platia v plnej výške, bez zálohy.
- **Balance due within**, dva dni **balance reminder** a **Cancel unpaid balance after** — čo sa deje po tom, čo je zákazník vyzvaný na doplatok.
- **Free cancellation of made-to-order** — počet pracovných dní, počas ktorých môže zákazník zrušiť objednávku zadarmo, kým nezadáme objednávku u dodávateľa.
- **Full refund when late by** — o koľko môžeme meškať, kým zákazník smie zrušiť objednávku a dostať všetko späť.
- limity **Pallet delivery**, **pallet quote tolerance** a **pallet rate per country** — pozri nižšie.

Sumy sú vo vlastnej mene obchodu, takže EU obchod má svoj vlastný ekvivalent súm z UK.

## Označenie produktu

Predobjednávky sa nastavujú na **hlavnom produkte** (master product), raz, a skopírujú sa na daný produkt v každom obchode. Vlastný produkt obchodu má rovnaké polia, ale zmena na hlavnom produkte ich prepíše.

V sekcii **Pre-order** na produkte zvolíte **Back-order** alebo **Made-to-order** a voliteľne:

- vlastnú **Lead time** v dňoch namiesto dodávateľovej;
- vlastnú **Made-to-order deposit** namiesto obchodovej;
- **Maximum quantity per order**. Ak ju necháte prázdnu, limit nie je žiadny.

Dodacia lehota sa berie v tomto poradí: najprv vlastná lehota produktu, potom **pre-order lead time** jeho preferovaného dodávateľa, potom predvolená hodnota obchodu. Dodávateľova lehota sa dá zadať v dňoch alebo v týždňoch. Pri back-order má prednosť zadaný dátum príchodu na otvorenej objednávke u dodávateľa alebo v dodávke tovaru.

## Čo zákazník vidí a platí

Zákazník vidí len slovo **Pre-order** (predobjednávka): back-order a made-to-order zostávajú v aiku, kde určujú, ako zákazník platí.

Kým je produkt **na sklade**, web ukazuje bežný stav skladu a žiadnu správu o predobjednávke. Keď sa **vypredá**, „Out of stock“ nahradí „Available to pre-order · Estimated dispatch 13–15 weeks“, spolu s tým, čo sa platí teraz („Pay in full now“ alebo „30% deposit now, balance when the goods arrive“), a odkazom na podmienky predobjednávky.

Keď je na sklade len časť toho, čo zákazník chce, košík to uvedie pri riadku, napríklad „2 will be sent now, 3 are pre-ordered (estimated dispatch 13–15 weeks)“. Platba a podmienky predobjednávky platia len pre 3 predobjednané kusy.

Pri pokladni musí zákazník pred zobrazením platby **zaškrtnúť súhlas s podmienkami predobjednávky**. Ide o podmienky, ktoré sa naňho vzťahujú, napríklad:

- odhadovaný termín odoslania a to, že ide len o odhad;
- podmienky zálohy alebo platby a podmienky zrušenia;
- že ručne vyrábané položky sa líšia veľkosťou, farbou, kresbou dreva a povrchovou úpravou a že rozmery sú približné;
- že doprava na palete je len k obrubníku.

Čo sa platí pri pokladni:

- **Trade, back-order** — platí sa v plnej výške, ako obvykle.
- **Trade, made-to-order** — len záloha, alebo všetko, ak je objednávka v hodnote nižšej, než je hranica obchodu.
- **Dropshipping** — vždy všetko.

Pastpay, dobierka a bankový prevod sa pri košíku s predobjednávkou neponúkajú: predobjednávka sa platí pri pokladni, celá alebo záloha, a žiadny z nich pri pokladni neplatí.

Tie isté podmienky sa zopakujú v e-maile s potvrdením objednávky a na faktúre.

## Keď sa v košíku miešajú skladové položky a predobjednávky

Keď je objednávka zadaná, skladové položky a položky na predobjednávku sa stanú **dvoma objednávkami**. Skladová objednávka ide do skladu hneď, s bežným doručením. Položky na predobjednávku tvoria vlastnú objednávku, s vlastným poplatkom za doručenie, ktorá čaká, kým tovar nedorazí. Na oboch objednávkach je poznámka odkazujúca na tú druhú.

Pri pokladni zmiešaný košík uvádza „We'll send your in-stock items now and your pre-order items as soon as they arrive.“ Zákazník môže namiesto toho zaškrtnúť **Hold my order and send everything together**: vtedy sa nič nerozdelí a celá objednávka čaká na tovar z predobjednávky. Košík len s predobjednanými položkami uvádza „We'll dispatch your order as soon as the goods arrive.“

Zákazník platí raz, pri pokladni. Časť tejto platby, ktorá patrí predobjednávke, sa na ňu presunie cez zostatok na účte zákazníka, takže samotný zostatok sa nemení.

## Čakanie na tovar

Predobjednávka počas čakania nikdy sama neprejde do skladu. Na objednávke uvidíte panel **Pre-order** s jej stavom, odhadovanými termínmi odoslania, tým, čo už bolo zaplatené, a tým, čo ešte treba doplatiť.

Keď dorazí sklad všetkého, čo je v predobjednávke, prevezmú si ho čakajúce predobjednávky, najstaršie ako prvé. Od toho okamihu je tovar **rezervovaný pre ne** a už sa na webe neponúka. Potom:

- ak nezostáva nič na doplatenie, objednávka ide rovno do skladu;
- inak dostane zákazník e-mailom odkaz na zaplatenie **doplatku**, splatného v lehote obchodu. Hneď ako je zaplatený, objednávka sama prejde do skladu.

Ak doplatok nie je zaplatený, zákazník dostáva pripomienky v pripomienkových dňoch obchodu. Po prekročení limitu obchodu sa objednávka zruší a tovar sa vráti do predaja. Ponechá sa len záloha za made-to-order položky; zvyšok zaplatenej sumy sa vráti a pri dropshippingu sa vráti všetko.

Ak objednávka u dodávateľa pre back-order teraz dorazí neskôr, než sme sľúbili pri odoslaní, zákazníkovi sa automaticky e-mailom pošlú nové termíny spolu s možnosťami zrušenia. Keď sa zmenia termíny inej predobjednávky, použite **Change dispatch dates** na jej paneli a zákazník dostane e-mail rovnako.

## Doprava na palete

Produkty ťažšie alebo dlhšie, než sú limity obchodu pre paletu, sú označené ako **pallet delivery**. Stránka produktu a košík zobrazujú hrubý odhad pre krajinu zákazníka, napríklad „Estimated pallet delivery to Germany: approx. €150“, podľa sadzieb obchodu pre paletovú dopravu.

- **Trade** — keď tovar dorazí, objednávka čaká, kým zamestnanec zadá skutočnú cenu cez **Pallet quote**. Táto ponuka sa odošle spolu so žiadosťou o doplatok. Ak je vyššia než odhad o viac, než je tolerancia obchodu, zákazník môže zrušiť objednávku a dostať zálohu späť.
- **Dropshipping** — s objednávkou sa účtuje odhad. Prípadný rozdiel oproti konečnej cene sa po doručení vyfaktúruje alebo vráti.

## Zrušenie

Zákazník nemôže zrušiť predobjednávku sám: kontaktuje zákaznícky servis, ktorý ju zruší z panela predobjednávky a vyberie dôvod, podľa ktorého sa určí vrátená suma. Vrátená suma ide na zostatok na účte zákazníka.

- **Naše zlyhanie pri dodaní** — meškáme viac, než je limit obchodu po odhadovanom termíne odoslania, dodávateľ nedokáže dodať, nebola dosiahnutá minimálna objednávka u dodávateľa, alebo je cenová ponuka za paletu vyššia než odhad: **vráti sa všetko**, vrátane zálohy, pre trade aj dropshipping.
- **Trade, back-order** — dá sa zrušiť kedykoľvek pred odoslaním, s plným vrátením peňazí.
- **Trade, made-to-order** — zadarmo, kým nezadáme objednávku u dodávateľa, v rámci pracovných dní obchodu. Potom sa **záloha za made-to-order ponechá** a zvyšok sa vráti.
- **Dropshipping** — akonáhle je objednávka zadaná, platba sa nevracia.

<aside class="wayfinder">

### Kam kliknúť v aiku

- **Zapnutie predobjednávok pre obchod** — otvorte obchod, **Settings**, potom **Pre-orders** › **Enable pre-orders**. Ostatné podmienky sú v tej istej sekcii.
- **Zmena textov pre zákazníka** — otvorte obchod, **Settings**, potom **Pre-order texts**. Je tam každý text, ktorý zákazník vidí, v každom jazyku obchodu: označenia, správy na stránke produktu, v košíku a pri pokladni, podmienky a zaškrtávacie políčko, e-maily a text v potvrdení objednávky a na faktúre. Prázdne pole použije predvolený text zobrazený sivou. Slová v zložených zátvorkách, ako {weeks}, {deposit_percent}, {balance_due_date} a {order_number}, sa vyplnia samy. Už zadané objednávky si ponechajú podmienky, ktoré zákazník prijal.
- **Označenie produktu** — **Masters**, otvorte hlavný produkt, **Edit**, potom **Pre-order**. Vlastný produkt obchodu má rovnakú sekciu pod **Edit**.
- **Nastavenie dodacej lehoty dodávateľa** — otvorte dodávateľa, **Edit**, potom **Pre-orders**: **Pre-order lead time**, **Lead time in** (days or weeks) a **Order by date**.
- **Sledovanie predobjednávky** — otvorte objednávku. Panel **Pre-order** hore má **Supplier ordered**, **Goods arrived**, **Pallet quote**, **Change dispatch dates**, **Send to warehouse** a **Cancel pre-order**.
- **Zobrazenie všetkých otvorených predobjednávok podľa dodávateľa** — pozri [Plánovanie objednávok u dodávateľov pre predobjednávky](/docs/planning-supplier-orders-for-pre-orders-sk).

### Aké oprávnenia potrebujete

- Zmena nastavení obchodu vyžaduje **organisation admin** alebo **shop admin**.
- Označovanie produktov vyžaduje oprávnenie upravovať katalóg, na hlavných produktoch alebo v danom obchode.
- Tlačidlá na paneli predobjednávky vyžadujú oprávnenie upravovať objednávky v danom obchode.

</aside>
