---
title: Hlavné kolekcie: jedna police, každý obchod
summary: Čo je hlavná kolekcia, ako sa rozvetví do kolekcie v každom otvorenom obchode, a ako ju naplniť rodinami, produktmi alebo inými kolekciami, aby to obchody nasledovali.
date: 2026-09-02
source_date: 2026-09-02
tags: masters, collections, catalogue
category: shop
series: Collections and collection offers
order: 1
---

<aside class="tldr">
<b>Hlavná kolekcia</b> žije v hlavnom obchode (master shop) a kopíruje sa do <b>kolekcie</b> v každom otvorenom obchode, ktorý pod neho patrí. Čokoľvek pripojíte k hlavnej kolekcii, teda rodiny, produkty, iné kolekcie, sa pripojí aj ku každej kópii v obchode, priradené k danej vlastnej verzii tej rodiny alebo produktu v danom obchode. Policu postavíte raz a dostane ju dvadsať obchodov. Ak ste používateľ obchodu a potrebujete vidieť alebo upraviť len kópiu vo vašom obchode, prečítajte si radšej <a href="/docs/shop-collections-sk">Kolekcie obchodu</a>.
</aside>

## Na čo je hlavná kolekcia

Kolekcia je tematická polica: produkty z ktorejkoľvek rodiny zoskupené pre merchandising, propagáciu alebo landing page. Hlavná kolekcia je tá istá polica definovaná raz na úrovni skupiny. Keď ju vytvoríte v hlavnom obchode, aiku vytvorí zodpovedajúcu kolekciu v každom otvorenom obchode tohto hlavného obchodu, s rovnakým kódom, názvom, popisom a obrázkom, a udrží spätný odkaz, aby každá kópia v obchode vedela, odkiaľ pochádza.

Obchody, ktoré sa otvoria neskôr, existujúce hlavné kolekcie automaticky nedostanú. Požiadajte vývojárov, aby ich pre nový obchod založili.

## Ako ju vytvoriť

Choďte na **Masters**, otvorte hlavný obchod, potom **Collections** a stlačte tlačidlo na vytvorenie. Formulár sa pýta na:

- **Code** (kód), krátky a jedinečný v rámci hlavného obchodu
- **Name** (názov)
- **Description** (popis), voliteľné
- obrázok, voliteľné

Uložte a kópie v obchodoch sa objavia okamžite. Hlavnú kolekciu môžete vytvoriť aj z hlavného oddelenia alebo pod-oddelenia, čím ju tam obmedzíte.

## Ako ju naplniť

Otvorte hlavnú kolekciu. Jej prehľad zobrazuje, čo sa v nej nachádza, a umožňuje pridať tri druhy členov:

- **Products** (produkty), cez **Add products to collection** (pridať produkty do kolekcie). Každá kópia v obchode dostane vlastný produkt daného obchodu pre ten istý hlavný produkt. Obchod, ktorý ho nepredáva, jednoducho pre danú položku nič nedostane.
- **Families** (rodiny). Pripojenie rodiny prinesie každý produkt tejto rodiny, v každom obchode, a naďalej rodinu sleduje: produkt pridaný do rodiny neskôr sa objaví aj v kolekcii.
- **Other collections** (iné kolekcie), cez **Add collections to collection** (pridať kolekcie do kolekcie), keď chcete policu zloženú z políc.

Odstránenie člena z hlavnej kolekcie ho odstráni z každej kópie v obchode, vrátane produktov, ktoré priniesla odstránená rodina.

## Rodiny alebo produkty: vyberte si jeden spôsob

Rodiny sú pohodlné, ale nasledujú rodinu, nie váš zámer. Rodina, ktorá mieša produkty z dvoch krajín alebo dvoch cenových rozpätí, potiahne všetky so sebou. Keď kolekcia musí byť presná, pripájajte produkty, nie rodiny. Kolekcia postavená pre zľavu by mala byť vždy postavená z produktov, aby nikto nedostal zľavu omylom.

Vývojári majú spôsob, ako naplniť hlavnú kolekciu každým produktom, ktorý zdieľa vlastnosť ako krajina pôvodu. Požiadajte o to namiesto ručného pridávania niekoľkých stoviek produktov.

## Čo vidia obchody

Každá kópia v obchode zobrazuje odkaz **Go to Master collection** (prejsť na hlavnú kolekciu). Obchod môže stále pridať vlastné produkty navyše k tomu, čo posiela hlavná kolekcia, čo je užitočné pre lokálne doplnky, ale čokoľvek hlavná kolekcia odstráni, zmizne aj z kópie v obchode. Tržby a počty produktov v hlavnej kolekcii sa spočítavajú z kópií v obchodoch.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zoznam hlavných kolekcií:</b> <b>Masters</b> → otvorte hlavný obchod → <b>Collections</b>.</li>
<li><b>Vytvoriť novú:</b> tá istá stránka → tlačidlo na vytvorenie → <b>Code</b>, <b>Name</b>, <b>Description</b>.</li>
<li><b>Pridať členov:</b> otvorte hlavnú kolekciu → <b>Add products to collection</b> alebo <b>Add collections to collection</b>; rodiny zo stránky hlavnej rodiny.</li>
<li><b>Zobraziť kópie v obchodoch:</b> otvorte hlavnú kolekciu → jej zoznam kolekcií v obchodoch.</li>
</ul>
</aside>

<aside class="permissions"><strong>Oprávnenia, ktoré potrebujete</strong>
<p>Hlavné obchody sú na úrovni skupiny. Na ich zobrazenie potrebujete prístup k masters na úrovni skupiny a editačný prístup na vytvorenie alebo zmenu hlavnej kolekcie. Používatelia obchodu bez tohto prístupu stále vidia kópiu svojho vlastného obchodu pod <b>Catalogue → Collections</b>.</p>
</aside>
