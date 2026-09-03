---
title: Nákup od partnera
summary: Sprievodca pre nákupcov - začnite na nákupnom paneli, doplňte zoznam ručne, z partnerovho katalógu alebo pomocou automatického dopĺňania, a prevezmite tovar po jeho príchode.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, intercompany, shopping-list
category: procurement
series: Ordering from partners
order: 3
---

<aside class="tldr">
Pre ľudí, ktorí <em>zadávajú</em> objednávky partnerom. Vediete si jeden otvorený zoznam toho, čo vaša organizácia potrebuje; partner ho plní vlastným tempom. Začnite na <a href="/docs/reading-the-partner-shopping-dashboard-sk">nákupnom paneli</a>, kde uvidíte, čo je v ohrození a koľko priestoru máte, potom pridávajte riadky ručne, z ich katalógu, alebo nechajte auto-fill navrhnúť doplnenie v rámci rozpočtu. Ste v tomto noví? Začnite <a href="/docs/ordering-from-a-partner-organisation-sk">prehľadom</a>.
</aside>

## Začnite na paneli

**Procurement → Partners → {partner} → Shopping** otvorí [nákupný panel](/docs/reading-the-partner-shopping-dashboard-sk): čo čoskoro dôjde, čo je už na ceste, a dva limity, v rámci ktorých váš zoznam žije — **order budget** pre tohto partnera a dostupný **warehouse space**. Odtiaľ prechádzajte rizikové dlaždice a väčšina zoznamu sa napíše sama; všetko nižšie popisuje, ako sa zoznam správa, keď už ste v ňom.

## Nákupný zoznam

Vedľa panelu záložka **Shopping list** obsahuje každý otvorený riadok.

- **Add stocks** otvorí partnerov skladový zoznam s ich dostupnosťou, spôsobom balenia každej položky, vaším vlastným aktuálnym stavom skladu a tým, koľko ste spotrebovali za posledné štyri štvrťroky. Množstvá sú v predajných jednotkách predávajúceho (SKO).
- Každý riadok na prvý pohľad rozpráva príbeh skladu — *ich sklad*, *náš sklad* a kedy *nám dôjde* — plus sumu vo vašej nákupnej cene, so súčtom otvorených položiek v päte tabuľky.
- Otvorené riadky sú plne vaše: vyberte **priority** (low → urgent) priamo z rozbaľovacieho zoznamu v tabuľke, alebo riadok odstráňte tlačidlom koša. Na zmenu množstva použite **Browse** — rovnaký prepínač množstva tam upravuje otvorený riadok priamo. Keď si partner riadok vyzdvihne, uzamkne sa a jeho stav vám povie, kde sa nachádza.

## Prehliadanie partnerovho katalógu

Vedľa nákupného zoznamu je záložka **Browse**: celý partnerov katalóg ako obchod, so živým skladom a cenami. Prechádzajte ho podľa **Departments** alebo **Collections**, prepnite sa hlbšie na rodiny, alebo jednoducho píšte do vyhľadávacieho poľa. Každá karta produktu zobrazuje aktuálnu cenu, štítok **Their stock** s tým, čo má partner k dispozícii, a — pri položkách, ktoré používate — vaše vlastné čísla: *our stock*, *our sales / quarter* a *we run out in* toľko a toľko dní (červené, ak sú to dva týždne alebo menej).

O tomto katalógu stojí za to vedieť dve veci. Ceny sú **vaše, nie z regálu**: predajcov cenník s vaším intercompany zľavou už odpočítanou, prepočítaný do meny vašej vlastnej organizácie, takže to, čo čítate, je to, čo bude na faktúre. A obsahuje aj produkty, ktoré partner vytvoril **exkluzívne pre vás** — riadky, ktoré sa nikdy neobjavia v ich verejnom obchode, no existujú pre vašu organizáciu. Ak niečo očakávané nenájdete, oplatí sa spýtať; ak nájdete niečo neočakávané, pravdepodobne je to vaše na základe dohody.

Objednávanie prebieha priamo na karte: pole s množstvom **je** váš nákupný zoznam. Napíšte alebo krokujte číslo a riadok sa pridá alebo aktualizuje v otvorenom zozname; vráťte ho na 0 a riadok sa odstráni. Vedľa neho čipa s prerušovaným okrajom **suggested** ukazuje množstvo, ktoré by objednal aiku — jedno kliknutie vyplní pole ním.

Počas prehliadania vás váš nákupný zoznam sprevádza ako účtenka pripnutá vpravo — každý riadok zoskupený podľa rodiny, s priebežným súčtom — takže vždy viete, na čom objednávka stojí. **Go to Shopping list** vás vráti späť na plne editovateľný zoznam.

<figure><img src="/art/docs/draw-partner-browse.svg" alt="Akvarelová skica prehliadača partnerovho katalógu: vyhľadávacie pole, záložky Departments a Collections, karty produktov s tlačidlami plus a nákupný zoznam ako účtenka pripnutá vpravo s priebežným súčtom" width="1200" height="750" loading="lazy"><figcaption>Partnerov obchod, s vaším zoznamom po boku.</figcaption></figure>

## Auto-fill: rozpočet a, ak chcete, aj pokyn

Auto-fill existuje preto, aby doplňovanie skladu nezáviselo od toho, či si niekto spomenie na každú položku. Zadáte mu jedno číslo — **budget** v rovnakej mene, v akej nakupujete — a on postaví návrh, ktorý sa doň zmestí:

- Prezrie každú položku, ktorú partner vie dodať a ktorú skutočne používate, zoradí ich podľa toho, **ako skoro vám dôjdu** (rovnaká predpoveď *we run out in*, akú vidíte pri prehliadaní), a najprv doplní tie, ktorým dôjde najskôr, každú v jej odporúčanom objednávacom množstve.
- Každý navrhnutý riadok ukazuje svoj **dôvod** ("Our sales/quarter ~48 · our stock 0 · we run out now"), množstvo a cenu, takže vidíte, prečo tam je. Množstvá sledujú rovnakú predpoveď ako čipy *suggested* v Browse.
- Pole **instruction box** je voliteľné a prijíma bežný jazyk: *"prioritise essential oils, skip anything we hold over 8 weeks of"*, *"focus on candles, nothing seasonal"*. AI číta váš pokyn spolu s rovnakými údajmi o spotrebe a podľa toho návrh prispôsobí — jeho výstup sa však pred zobrazením overí voči realite: množstvá sú obmedzené tým, čo partner skutočne má, a súčet je vrátený späť do vášho rozpočtu. Ak sa pokyn nedá dodržať, dostanete namiesto neho štandardný návrh.
- **Nič sa nepridáva samo.** Návrh je súbor zaškrtnutých riadkov, ktoré môžete odškrtnúť, prepočítať alebo znovu vygenerovať s iným rozpočtom či pokynom; iba **Add items to shopping list** niečo skutočne uloží.
- **Niektoré položky sa dajú vynechať.** SKO so zapnutým **Do not auto order** (na obrazovke úprav SKO, v časti Stock Data) sa v návrhu nikdy neobjaví — pre položky, ktoré chce nákup ponechať pod ručnou kontrolou. Stále ju môžete objednať ručne z Browse alebo zo skladového zoznamu; vynecháva ju iba automatická cesta. SKO označené ako **On Demand** sú z partnerského nakupovania vynechané úplne.

Auto-fill možno otvoriť aj už zúžený: **+ fill** na rizikovej dlaždici na paneli ho otvorí len pre daný bucket, s návrhom už vygenerovaným. Rovnaké pravidlá — upravíte, odškrtnete a potvrdíte; nič sa nepridáva samo.

Dobrý zvyk: prechádzajte dlaždice na paneli od najhoršej, potom raz za cyklus doplňovania spustite Auto-fill na to, čo zostalo, prečítajte si dôvody, odškrtnite, s čím nesúhlasíte, a zvyšok pridajte.

## Keď zoznam povie nie

Pridávanie sa odmietne v troch prípadoch, zámerne: zoznam dosiahol **budget** pre tohto partnera (položky s rank A a bez skladu sú výnimkou — núdzová situácia sa vždy zmestí), sklad má menej ako 5 % voľných lokácií, alebo si tento partner už vyčerpal svoj spravodlivý podiel voľných miest produktmi, ktoré ste nikdy neskladovali. Riešte hlásenie namiesto hľadania inej cesty — rovnaká poistka platí pre ručné pridávanie, hromadné pridávanie aj Auto-fill. [Článok o paneli](/docs/reading-the-partner-shopping-dashboard-sk) vysvetľuje, odkiaľ tieto limity pochádzajú.

## Keď je tovar na ceste

Keď partner [odošle zásielku do svojho skladu](/docs/fulfilling-partner-orders-sk), pod záložkou **Stock deliveries** vášho partnera sa objaví prichádzajúce **stock delivery**. Nechajte ho tak, kým hovorí confirmed alebo dispatched — zrkadlí predávajúceho sklad a aktualizuje sa samo. Keď škatule fyzicky dorazia: **receive**, skontrolujte a uložte na lokácie presne tak, ako pri akejkoľvek inej dodávke od dodávateľa. Čokoľvek chýba alebo je poškodené, sa rieši po prevzatí, voči prepojenej faktúre — ako peniaze fungujú, nájdete v [prehľade](/docs/ordering-from-a-partner-organisation-sk).

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zistiť, čo treba nakúpiť:</b> vaša organizácia → <b>Procurement → Partners</b> → otvorte partnera → <b>Shopping</b> (panel) → prechádzajte rizikové dlaždice.</li>
<li><b>Pridať do zoznamu:</b> <b>Shopping list</b> → <b>Add stocks</b>, alebo <b>Browse</b> a nastavte množstvá na kartách produktov, alebo <b>Auto-fill</b> (či <b>+ fill</b> na dlaždici panelu) pre návrh.</li>
<li><b>Upraviť otvorené riadky:</b> zmeňte prioritu alebo vymažte riadky v tabuľke nákupného zoznamu; množstvá meňte na kartách produktov v <b>Browse</b>.</li>
<li><b>Vynechať položku z auto-fillu:</b> vaša organizácia → <b>Warehouse → Inventory</b> → otvorte SKO → <b>Edit SKO</b> → zapnite <b>Do not auto order</b>.</li>
<li><b>Sledovať a prevziať zásielku:</b> tá istá stránka partnera → <b>Stock deliveries</b> → keď tovar dorazí, <b>Receive</b> → skontrolujte → uložte na lokácie.</li>
</ul>
</aside>
