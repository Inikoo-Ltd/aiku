---
title: Nákup od partnera
summary: Sprievodca pre nákupcov - začnite na nákupnom paneli, doplňte zoznam ručne, z partnerovho katalógu alebo pomocou automatického dopĺňania, a prevezmite tovar po jeho príchode.
date: 2026-10-09
source_date: 2026-10-09
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
- Tam, kde si partner položku vyrába sám, riadok tiež hovorí *made in batches of N units* (vyrába sa v dávkach po N kusoch) a tam, kde sa dve čísla nedelia presne, aj *full batches every N SKO* (celé dávky každých N SKO) — **order step**, najmenšia objednávka, ktorú celé dávky vyplnia presne. Tlačidlo zaokrúhli vaše množstvo nahor naň. Objednávka mimo stepu je povolená a hovorí to: celá dávka sa vyrobí tak či tak, takže objednávka môže byť oneskorená alebo množstvo upravené, a objednávka pod jeden celý step čaká na dielni, kým sa k nej nepridá ďalší dopyt. Viď [Dávky, balenia a čiastočné dávky](/docs/batches-packs-and-part-batches-sk).
- Otvorené riadky sú plne vaše: vyberte **priority** (low → urgent) priamo z rozbaľovacieho zoznamu v tabuľke, alebo riadok odstráňte tlačidlom koša. Na zmenu množstva použite **Browse** — rovnaký prepínač množstva tam upravuje otvorený riadok priamo. Keď si partner riadok vyzdvihne, uzamkne sa a jeho stav vám povie, kde sa nachádza.

## Prehliadanie partnerovho katalógu

Vedľa nákupného zoznamu je záložka **Browse**: celý partnerov katalóg ako obchod, so živým skladom a cenami. Prechádzajte ho podľa **Departments** alebo **Collections**, prepnite sa hlbšie na rodiny, alebo jednoducho píšte do vyhľadávacieho poľa. Každá karta produktu zobrazuje aktuálnu cenu, štítok **Their stock** s tým, čo má partner k dispozícii, a — pri položkách, ktoré používate — vaše vlastné čísla: *our stock*, *our sales / quarter* a *Estimated: Would run out in* toľko a toľko dní (červené, ak sú to dva týždne alebo menej).

O tomto katalógu stojí za to vedieť dve veci. Ceny sú **vaše, nie z regálu**: predajcov cenník s vaším intercompany zľavou už odpočítanou, prepočítaný do meny vašej vlastnej organizácie, takže to, čo čítate, je to, čo bude na faktúre. A obsahuje aj produkty, ktoré partner vytvoril **exkluzívne pre vás** — riadky, ktoré sa nikdy neobjavia v ich verejnom obchode, no existujú pre vašu organizáciu. Ak niečo očakávané nenájdete, oplatí sa spýtať; ak nájdete niečo neočakávané, pravdepodobne je to vaše na základe dohody.

Objednávanie prebieha priamo na karte: pole s množstvom **je** váš nákupný zoznam. Napíšte alebo krokujte číslo a riadok sa pridá alebo aktualizuje v otvorenom zozname; vráťte ho na 0 a riadok sa odstráni. Vedľa neho čipa s prerušovaným okrajom **suggested** ukazuje množstvo, ktoré by objednal aiku — jedno kliknutie vyplní pole ním.

Počas prehliadania vás váš nákupný zoznam sprevádza ako účtenka pripnutá vpravo — každý riadok zoskupený podľa rodiny, s priebežným súčtom — takže vždy viete, na čom objednávka stojí. **Go to Shopping list** vás vráti späť na plne editovateľný zoznam.

<figure><img src="/art/docs/draw-partner-browse.svg" alt="Akvarelová skica prehliadača partnerovho katalógu: vyhľadávacie pole, záložky Departments a Collections, karty produktov s tlačidlami plus a nákupný zoznam ako účtenka pripnutá vpravo s priebežným súčtom" width="1200" height="750" loading="lazy"><figcaption>Partnerov obchod, s vaším zoznamom po boku.</figcaption></figure>

## Auto-fill: rozpočet a, ak chcete, aj pokyn

Auto-fill existuje preto, aby doplňovanie skladu nezáviselo od toho, či si niekto spomenie na každú položku. Zadáte mu jedno číslo — **budget** v rovnakej mene, v akej nakupujete — a on postaví návrh, ktorý sa doň zmestí:

- Prezrie každú položku, ktorú partner vie dodať a ktorú skutočne používate, zoradí ich podľa toho, **ako skoro vám dôjdu** (rovnaká predpoveď *Estimated: Would run out in*, akú vidíte pri prehliadaní), a najprv doplní tie, ktorým dôjde najskôr, každú v jej odporúčanom objednávacom množstve, zaokrúhlenom na order step danej položky.
- Každý navrhnutý riadok ukazuje svoj **dôvod** ("Our sales/quarter ~48 · our stock 0 · we run out now"), množstvo a cenu, takže vidíte, prečo tam je. Množstvá sledujú rovnakú predpoveď ako čipy *suggested* v Browse.
- Pole **instruction box** je voliteľné a prijíma bežný jazyk: *"prioritise essential oils, skip anything we hold over 8 weeks of"*, *"focus on candles, nothing seasonal"*. AI číta váš pokyn spolu s rovnakými údajmi o spotrebe a podľa toho návrh prispôsobí — jeho výstup sa však pred zobrazením overí voči realite: množstvá sú obmedzené tým, čo partner skutočne má, a súčet je vrátený späť do vášho rozpočtu. Ak sa pokyn nedá dodržať, dostanete namiesto neho štandardný návrh.
- **Nič sa nepridáva samo.** Návrh je súbor zaškrtnutých riadkov, ktoré môžete odškrtnúť, prepočítať alebo znovu vygenerovať s iným rozpočtom či pokynom; iba **Add items to shopping list** niečo skutočne uloží.
- **Niektoré položky sa dajú vynechať.** SKO so zapnutým **Do not auto order** (na obrazovke úprav SKO, v časti Stock Data) sa v návrhu nikdy neobjaví — pre položky, ktoré chce nákup ponechať pod ručnou kontrolou. Stále ju môžete objednať ručne z Browse alebo zo skladového zoznamu; vynecháva ju iba automatická cesta. SKO označené ako **On Demand** sú z partnerského nakupovania vynechané úplne.

Auto-fill možno otvoriť aj už zúžený: **+ fill** na rizikovej dlaždici na paneli ho otvorí len pre daný bucket, s návrhom už vygenerovaným. Rovnaké pravidlá — upravíte, odškrtnete a potvrdíte; nič sa nepridáva samo.

Dobrý zvyk: prechádzajte dlaždice na paneli od najhoršej, potom raz za cyklus doplňovania spustite Auto-fill na to, čo zostalo, prečítajte si dôvody, odškrtnite, s čím nesúhlasíte, a zvyšok pridajte.

## Požiadajte o doplnenie riadkov svojho AI asistenta

Ak je váš účet zapísaný, AI asistent, ktorého pripojíte k aiku, vám vie dávať riadky do zoznamu. Plánovanie robte vo vlastnom asistentovi — spýtajte sa ho, čo dochádza, a prejdite si čísla spolu — a keď ste spokojní, povedzte mu: *„ok, pridaj toto do nákupného zoznamu"*. Najprv vám ukáže kódy SKO a množstvá a pridá ich až po vašom potvrdení.

- Pri plánovaní asistent číta rovnaké čísla, aké vidíte vy: sklad, predaj, dni do vypredania, predpokladané množstvo, order step a dobu spotreby (shelf life). Nikdy nenavrhne viac, než sa predá pred uplynutím spotreby; produkt, ktorý ešte nemá zaznamenanú dobu spotreby, plánuje ako produkt s trvanlivosťou jeden rok.
- Množstvá sú v SKO a riadok **nastavujú**: SKO, ktoré už je v zozname, dostane nové množstvo, nie ďalšie navyše.
- Platia rovnaké pravidlá ako pri ručnom pridávaní: kontroly rozpočtu, skladového priestoru a balení uvedené nižšie. Riadok, ktorý zoznam odmietne, sa vráti s dôvodom a ostatné sa pridajú aj tak.
- Iba pridáva a mení množstvá; priority a odstraňovanie riadkov zostávajú v tabuľke nákupného zoznamu.
- Každá zmena sa zaznamenáva spolu s vašou požiadavkou a dá sa vrátiť späť: požiadajte asistenta, aby ju vrátil, alebo ju môže vrátiť administrátor zo záznamu zmien AI (AI changes log).
- Zápis je prepínač na vašom používateľskom účte, ktorý zapína administrátor, a okrem toho potrebujete oprávnenie upravovať nákup (procurement) pre svoju organizáciu.

### Zadanie objednávky cez vášho asistenta

S druhým prepínačom na vašom účte môže asistent objednávku nielen pripraviť, ale aj zadať:

- **Odoslať košík** do výrobného hubu (manufacturing hub): *"odošli to"*. Hub začne vyrábať, čo ste poslali, presne tak, ako keby ste stlačili **Submit**.
- **Zmeniť riadok, ktorý už bol hubu odoslaný**, kým ho hub nezačal spracúvať (žiadna zákazka, nie je predpripravený ani sa nepripravuje). Je to nebezpečné — hub už môže okolo neho plánovať materiály a dávky — preto vás asistent najprv upozorní a pokračuje, len ak na tom trváte.
- **Záchranné objednávky ostatným partnerom**: asistent ukáže, čo môže každý partner zachrániť (SKO, náklady, súčet) a po potvrdení partnera a rozpočtu vytvorí záchrannú nákupnú objednávku (purchase order) a odošle ju do partnerovho skladu.

Zadané objednávky sa zaznamenávajú spolu s vašou požiadavkou, ale zo záznamu zmien AI (AI changes log) sa **nedajú vrátiť späť**: stornujte ich u partnera ako každú inú objednávku.

## Keď zoznam povie nie

Pridávanie sa odmietne v troch prípadoch, zámerne: zoznam dosiahol **budget** pre tohto partnera (položky s rank A a bez skladu sú výnimkou — núdzová situácia sa vždy zmestí), sklad má menej ako 5 % voľných lokácií, alebo si tento partner už vyčerpal svoj spravodlivý podiel voľných miest produktmi, ktoré ste nikdy neskladovali. Riešte hlásenie namiesto hľadania inej cesty — rovnaká poistka platí pre ručné pridávanie, hromadné pridávanie aj Auto-fill. [Článok o paneli](/docs/reading-the-partner-shopping-dashboard-sk) vysvetľuje, odkiaľ tieto limity pochádzajú.

## Keď je tovar na ceste

Keď partner [odošle zásielku do svojho skladu](/docs/fulfilling-partner-orders-sk), pod záložkou **Stock deliveries** vášho partnera sa objaví prichádzajúce **stock delivery**. Nechajte ho tak, kým hovorí confirmed alebo dispatched — zrkadlí predávajúceho sklad a aktualizuje sa samo. Keď škatule fyzicky dorazia: **receive**, skontrolujte a uložte na lokácie presne tak, ako pri akejkoľvek inej dodávke od dodávateľa. Čokoľvek chýba alebo je poškodené, sa rieši po prevzatí, voči prepojenej faktúre — ako peniaze fungujú, nájdete v [prehľade](/docs/ordering-from-a-partner-organisation-sk).

Keď partner expeduje, každý riadok príde s už vyplnenými dávkami, ktoré partner vyskladnil: rovnaký kód dávky a dátum minimálnej trvanlivosti, prepočítané na vaše SKO. Pri kontrole ich len potvrdíte, alebo opravíte, ak tovar hovorí inak, a naskladnením idú tieto dávky na vaše regály.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zistiť, čo treba nakúpiť:</b> vaša organizácia → <b>Procurement → Partners</b> → otvorte partnera → <b>Shopping</b> (panel) → prechádzajte rizikové dlaždice.</li>
<li><b>Pridať do zoznamu:</b> <b>Shopping list</b> → <b>Add stocks</b>, alebo <b>Browse</b> a nastavte množstvá na kartách produktov, alebo <b>Auto-fill</b> (či <b>+ fill</b> na dlaždici panelu) pre návrh.</li>
<li><b>Objednať v celých dávkach:</b> tlačidlo vedľa <i>full batches every N SKO</i> na riadku alebo na karte produktu.</li>
<li><b>Upraviť otvorené riadky:</b> zmeňte prioritu alebo vymažte riadky v tabuľke nákupného zoznamu; množstvá meňte na kartách produktov v <b>Browse</b>.</li>
<li><b>Nechať niekoho dopĺňať zoznam cez jeho AI asistenta (administrátori):</b> <b>Sysadmin → Users</b> → otvorte používateľa → <b>Edit</b> → <b>Access</b> → zapnite <b>Can connect AI assistant</b>, potom <b>Can add to the manufacturing hub shopping list through their AI assistant</b>.</li>
<li><b>Nechať niekoho zadávať objednávky cez jeho AI asistenta (administrátori):</b> rovnaká záložka <b>Access</b> → zapnite <b>Can place orders to the manufacturing hub, partners and suppliers through their AI assistant</b>.</li>
<li><b>Vynechať položku z auto-fillu:</b> vaša organizácia → <b>Warehouse → Inventory</b> → otvorte SKO → <b>Edit SKO</b> → zapnite <b>Do not auto order</b>.</li>
<li><b>Sledovať a prevziať zásielku:</b> tá istá stránka partnera → <b>Stock deliveries</b> → keď tovar dorazí, <b>Receive</b> → skontrolujte → uložte na lokácie.</li>
</ul>
</aside>
