---
title: Cenové tipy
summary: Ako aiku každú noc navrhuje zníženie alebo zvýšenie ceny hlavných produktov, čo ukazuje každý tip a ako ho použiť alebo zamietnuť.
date: 2026-09-30
source_date: 2026-09-30
tags: masters, pricing, products, catalogue
category: shop
---

<aside class="tldr">
Každú noc aiku prejde hlavné produkty, ktorých je <b>príliš veľa na sklade</b> alebo ktoré <b>sa míňajú</b>, vrátane nových položiek, a môže navrhnúť zmenu ceny. Tip je len návrh: <b>cena sa nezmení, kým ho niekto nepoužije a neuloží</b>. Tipy uvidíte na karte <b>Pricing</b>, najistejšie sú navrchu.
</aside>

## Ktoré produkty dostanú tip

Cena hlavného produktu je rovnaká v každom obchode, ktorý ho predáva, preto aiku vezme dni zásob každej organizácie a spriemeruje ich podľa toho, **koľko každá organizácia predala za posledný rok**. Organizácia, ktorá predá väčšinu produktu, sa počíta najviac; tá, ktorá predá málo, takmer vôbec.

- **Zníženie ceny:** tento priemer je **120 dní zásob alebo viac**.
- **Zvýšenie ceny:** tento priemer je **pod 45 dní**.

**Nové položky** (bez predaja v tých istých mesiacoch pred rokom) dostanú tip, keď sú v predaji **60 dní**. Keďže nemajú minulý rok na porovnanie, AI ich posúdi podľa predaja od uvedenia na trh v porovnaní s ostatnými produktmi rodiny a podľa ceny v porovnaní s bežnou cenou rodiny a konkurencie.

Niektoré produkty tip nikdy nedostanú:

- produkty, ktoré sa za posledné dva roky nepredali vôbec;
- hlavný obchod Aroma.

## Ako sa tip vypočíta

Pri každom vhodnom produkte sa AI model pozrie na:

- predaj po mesiacoch za posledné dva roky;
- zásoby a dni zásob v každej organizácii a tovar na ceste od dodávateľov a partnerov;
- dni, keď produkt nebol na sklade, pretože vypredanie znižuje predaj, no nie dopyt;
- maržu nad nákladmi;
- bežnú cenu ostatných produktov v tej istej rodine;
- akcie prebiehajúce na produkte alebo jeho rodine;
- predchádzajúce zmeny cien a to, ako sa predaj pohyboval v troch mesiacoch po každej z nich.

Vyberie jednu možnosť: znížiť cenu o 15 %, 10 % alebo 5 %, nechať ju, alebo zvýšiť o 5 % alebo 10 %. Zároveň posúdi, či je pokles predaja **dočasný** (vypredanie, sezóna, jednorazová veľká objednávka minulý rok).

aiku potom pred zobrazením čohokoľvek uplatní pevné pravidlá:

- zmena musí ísť správnym smerom: zníženie len pri nadbytku zásob, zvýšenie len keď sa zásoby míňajú;
- AI musí byť aspoň na 50 % istá, že sa cena má pohnúť týmto smerom. Svoj hlas rozdeľuje medzi viacero veľkostí zníženia alebo zvýšenia, preto sa veľkosti mieriace rovnakým smerom sčítajú a tipom je najpravdepodobnejšia z nich;
- žiadne zníženie, keď pokles predaja vyzerá dočasne (u nových položiek sa nekontroluje, nemajú minulý rok);
- zníženie nikdy nezníži cenu pod **náklady + 25 %**. Ak by to tak bolo, zníženie sa zmenší a dôvod to uvedie.

Keď sa tip nezobrazí, stĺpec **Price tip** to sivým písmom vysvetlí, napríklad *No tip: stock cover is normal (80 days)* (bez tipu: zásoby sú v norme, 80 dní; zásoby nedávajú dôvod meniť cenu, samotná cena sa neposudzovala), *No tip: the AI keeps the price (71% sure)* (AI nechá cenu, istá na 71 %), *No tip: the fall in sales looks temporary (65% likely)* (pokles predaja vyzerá dočasne, s pravdepodobnosťou 65 %) alebo *No tip yet: new, on sale for 30 days* (zatiaľ bez tipu: nový, v predaji 30 dní).

## Čo tip ukazuje

Na karte **Pricing** stĺpec **Price tip** (cenový tip) ukazuje:

- zmenu, napríklad **−10 %** oranžovou pri znížení alebo **+5 %** zelenou pri zvýšení;
- ako si je AI istá, napríklad **72 % sure**;
- odkaz **Dismiss** (zamietnuť).

Prejdením myšou nad zmenou si prečítate dôvod, napríklad: *Zásoby na 400 dní, spriemerované podľa predaja každej organizácie, predaj o 20 % nižší ako minulý rok, 20 kusov ďalej na ceste, marža 80 %, cena −10 % dňa 2025-03-10 zvýšila predaj o +25 %*. Dôvod je zostavený z uvedených čísel, takže každé si môžete overiť.

Produkty s tipom sú v zozname prvé, najistejšie navrchu. Kliknutím na hlavičku stĺpca zmeníte triedenie.

Tipy sa počítajú znova každú noc. Ak sa zmenia zásoby alebo predaj, tip sa zmení alebo zmizne.

## Použitie tipu

1. Kliknite na zmenu (napríklad **−10 %**).
2. Otvorí sa editor cien so všetkými menami už posunutými o toto percento.
3. Skontrolujte ceny a upravte tie, ktoré chcete.
4. Uložte.

Nová cena sa dostane do každého obchodu bežnou aktualizáciou cien, presne ako keď cenu upravíte ručne. Zmena sa zapíše s vaším menom do histórie produktu.

Asi **8 týždňov** po použití tipu aiku porovná predaj produktu v 8 týždňoch po zmene s 8 týždňami pred ňou a s rovnakými týždňami o rok skôr. Kým sa to nezmeria, produkt nedostane nový tip.

## Zamietnutie tipu

Ak je tip nesprávny, kliknite na **Dismiss** a napíšte prečo, napríklad „Vianočná zásoba, predáva sa v decembri" alebo „cena dohodnutá s kľúčovým zákazníkom". Dôvod sa uchová, aby sa dali vylepšiť pravidlá.

Zamietnutý produkt nedostane nový tip **30 dní**. To isté platí pre cenu, ktorú niekto zmenil ručne: taký produkt nedostane tip 30 dní a stĺpec uvedie *No tip: price changed by hand on 7 Oct, no new tip for 30 days after that* (bez tipu: cena bola ručne zmenená 7. októbra, ďalších 30 dní bez nového tipu). Produkt, ktorého evidovaný náklad je vyšší ako cena, namiesto tipu zobrazí *No tip: the cost on record is above the price, check the cost* (bez tipu: evidovaný náklad je vyšší ako cena, skontrolujte náklad), pretože každý návrh postavený na nesprávnom náklade by bol tiež nesprávny.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zobraziť tipy:</b> <b>Masters</b> → otvorte hlavný obchod → <b>Families</b> → otvorte rodinu → karta <b>Pricing</b> → stĺpec <b>Price tip</b>.</li>
<li><b>Použiť:</b> kliknite na percento, skontrolujte ceny, uložte.</li>
<li><b>Zamietnuť:</b> odkaz <b>Dismiss</b> pod percentom.</li>
</ul>
</aside>

<aside class="permissions"><strong>Oprávnenia, ktoré potrebujete</strong>
<p>Hlavné obchody sú na úrovni skupiny. Na zobrazenie tipov potrebujete prístup k masters na úrovni skupiny a editačný prístup k masters na ich použitie alebo zamietnutie.</p>
</aside>
