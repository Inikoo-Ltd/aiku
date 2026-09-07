---
title: Ako je usporiadaný katalóg
summary: Oddelenia, pod-oddelenia, rodiny, kolekcie a produkty — na čo slúži každá úroveň, ako sa vnárajú do seba a ako sa produkt medzi nimi presúva.
date: 2026-09-01
source_date: 2026-09-01
tags: catalogue, departments, families, products
category: shop
---

<aside class="tldr">
**Catalogue** každého obchodu je strom: **Department** (oddelenie) môže obsahovať **Sub-departments** (pod-oddelenia) a obe môžu obsahovať **Families** (rodiny). Každý produkt patrí presne do jednej rodiny. **Collections** (kolekcie) stoja popri tomto strome ako druhý spôsob zoskupovania produktov — na účely merchandisingu, nie zakladania. Oddelenia, pod-oddelenia a rodiny sa vytvárajú ručne v obchode; produkty spravidla prichádzajú už hotové a stačí ich zaradiť do správnej rodiny.
</aside>

## Tvar stromu

**Department** je najvyššia úroveň — široká oblasť obchodu, napríklad "Department A". Vnútri oddelenia máte dve možnosti:

- Priamo do **Families** — úrovne, do ktorej produkt naozaj patrí.
- Cez **Sub-department** najprv, ak je oddelenie dosť veľké na to, aby potrebovalo strednú vrstvu, a rodiny sa nachádzajú v ňom.

Takže rodina vždy žije priamo pod oddelením, alebo pod pod-oddelením, ktoré samo žije pod oddelením. Produkt sa vždy pripája iba k rodine, nikdy priamo k oddeleniu alebo pod-oddeleniu.

## Oddelenia

Oddelenia sú miesto, kde začína štruktúra obchodu. Dostanete sa k nim cez **Catalogue → Departments**, ktorá vypíše každé oddelenie s jeho stavom a tržbami. Otvorením jedného sa zobrazia jeho vlastné rodiny (a pod-oddelenia, ak nejaké má).

Ak chcete vytvoriť nové, stlačte **Create Department** a vyplňte:

- **Code** — krátka interná referencia.
- **Name** — plný názov oddelenia.

## Pod-oddelenia

Pod-oddelenie je voliteľná stredná polica vnútri oddelenia, pre prípad, keď treba "Department A" ešte ďalej rozdeliť, než sa dostanete až k rodinám. Vytvoríte ho zvnútra jeho oddelenia, so zadaním rovnakého **Code** a **Name** ako pri oddelení. Všetko pod pod-oddelením funguje presne ako vlastné rodiny oddelenia — táto ďalšia vrstva ovplyvňuje len to, kde sa veci nachádzajú, nie ako sa správajú.

## Rodiny

Rodina je úroveň, v ktorej produkty naozaj sedia — zásuvka, nie skriňa. Kompletný zoznam nájdete v **Catalogue → Families**, alebo sa k jednej dostanete cez jej oddelenie (a pod-oddelenie, ak ho má).

Ak chcete vytvoriť rodinu, stlačte **Create Family** a vyplňte:

- **Code**
- **Name**
- **Description** (voliteľné)

Keď rodina existuje, jej vlastná obrazovka **Products** vypíše všetko, čo je v nej zaradené, a práve sem sa spravidla pridávajú nové produkty pod ňu.

## Produkty

Produkty sú jednotlivé veci, ktoré predávate — jeden riadok na predajnú položku. Vlastná stránka produktu zobrazuje jeho kód, názov a ďalšie údaje; zoznam **Products** pod **Catalogue → Products** zobrazuje každý produkt v obchode.

Väčšina produktov prichádza už zostavená — prevzatá zo širšej knižnice produktov, nie napísaná ručne rodinu po rodine — a stačí ich jednoducho zaradiť do správnej rodiny. Keď produkt ešte nemá pridelenú rodinu, zobrazí sa ako **Orphan Product**. Nástenka Catalogue má dlaždicu **Orphan Products** s priebežným počtom; jej otvorením sa vypíše každý produkt bez rodiny so zaškrtávacím políčkom pri každom riadku. Zaškrtnite tie, ktoré patria dokopy, stlačte **Add … to Family**, vyberte rodinu z vyhľadávacieho poľa, ktoré sa zobrazí, a stlačte **Submit** — každý zaškrtnutý produkt sa presunie naraz. Táto obrazovka je momentálne spôsob, ako presunúť produkt do rodiny zo strany Catalogue; na vlastnej stránke jednotlivého produktu žiadne samostatné tlačidlo "presunúť" nie je.

### Stavy produktu

Každý produkt nesie **state**, ktorý sleduje jeho životný cyklus v katalógu:

- **In Process** — stále sa nastavuje, ešte nie je pripravený.
- **Active** — bežný, predajný.
- **Discontinuing** — vyraďuje sa; sklad ešte môže byť v sklade, ale je na ceste von.
- **Discontinued** — úplne vyradený; už sa nepoužije.

Produkt tiež nesie samostatný **status**, ktorý odráža, či sa dá práve teraz naozaj kúpiť: **For Sale**, **Not For Sale**, **Out of Stock**, **Coming Soon** alebo **Discontinued** (popri **In Process**, kým sa ešte len zostavuje). State sa týka životného cyklu produktu v katalógu; status je bližšie k tomu, čo by videl zákazník.

## Kolekcie

**Collection** je samostatné zoskupenie, popri strome oddelenie/rodina, nie vnútri neho — tematická polica, ktorú budujete na merchandising a ktorá môže ťahať produkty z ľubovoľnej rodiny. Ku kolekciám sa dostanete cez **Catalogue → Collections**, a vytvoriť ich môžete aj zvnútra konkrétneho oddelenia alebo pod-oddelenia, ak ich chcete takto ohraničiť.

Ak chcete vytvoriť kolekciu, stlačte **Create Collection** a vyplňte:

- **Code**
- **Name**
- **Description** (voliteľné)
- **Image** (voliteľné)

To, že je produkt v kolekcii, nemá vplyv na to, do ktorej rodiny patrí — produkt si drží svoju jedinú rodinu bez ohľadu na to, v koľkých kolekciách sa ešte objaví.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Prehliadať alebo vytvárať oddelenia:</b> váš obchod → <b>Catalogue → Departments</b> → <b>Create Department</b>.</li>
<li><b>Pridať pod-oddelenie:</b> otvorte oddelenie, potom vytvorte jedno zvnútra neho.</li>
<li><b>Prehliadať alebo vytvárať rodiny:</b> váš obchod → <b>Catalogue → Families</b> → <b>Create Family</b>, alebo zvnútra oddelenia/pod-oddelenia.</li>
<li><b>Prehliadať produkty:</b> váš obchod → <b>Catalogue → Products</b>.</li>
<li><b>Presunúť produkt do rodiny:</b> dlaždica <b>Orphan Products</b> na nástenke Catalogue → zaškrtnite produkty → <b>Add … to Family</b> → vyberte rodinu → <b>Submit</b>.</li>
<li><b>Prehliadať alebo vytvárať kolekcie:</b> váš obchod → <b>Catalogue → Collections</b> → <b>Create Collection</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Aké oprávnenia potrebujete</strong>
Na prehliadanie katalógu potrebujete prístup na zobrazenie Products pre tento obchod a na vytváranie oddelení, pod-oddelení, rodín alebo kolekcií, prípadne na presúvanie produktov medzi rodinami, potrebujete prístup na úpravu Products pre tento obchod.
</aside>
