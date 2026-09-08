---
title: Práca so zoznamom To produce
summary: Sprievodca pre továreň - jedna fronta všetkého, čo továreň dlhuje, partnerským organizáciám aj vlastným zákazníkom, zoskupená tak, ako uvažuje plánovač výroby.
date: 2026-09-08
source_date: 2026-09-08
tags: production, procurement, intercompany, dispatch
category: production
series: Ordering from partners
order: 4
---

<aside class="tldr">
Pre ľudí, ktorí <em>vyrábajú</em>, a pre toho, kto plánuje deň v továrni. <b>To produce</b> (na výrobu) je fronta továrne: každý riadok, o ktorý požiadala partnerská organizácia, plus každý riadok, ktorý si objednal vlastný zákazník a ktorý továreň nemá na sklade. <b>Board</b> je miesto, kde plánujete: pretiahnete riadok naprieč dráhami, aby ste rozhodli, koľko sa má vyrobiť a kto to vyrobí, a remeselníkovi sa vytvorí pracovný príkaz. Pohľady v podobe zoznamov zoskupujú tie isté riadky podľa remeselníka, kategórie alebo odberateľa, a odtiaľ odškrtnete, čo môžete poslať partnerom; zvyšok papierovania sa vybaví sám. Ste v tomto flow noví? Začnite <a href="/docs/ordering-from-a-partner-organisation-sk">prehľadom</a>. Chcete, aby zoznam vedel, kto čo vyrába? Prečítajte si najprv <a href="/docs/who-makes-what-sk">Kto čo vyrába</a>.
</aside>

## Odkiaľ riadky pochádzajú

**Factory → To produce** sa napĺňa z dvoch zdrojov. Riadok sem nikdy nepíšete ručne.

- **Požiadavky partnerov.** Sesterské organizácie dávajú, čo potrebujú, na svoj [nákupný zoznam](/docs/buying-from-a-partner-sk). Každý otvorený riadok adresovaný vašej továrni sa tu objaví s odberateľom, množstvom a prioritou, ktorú mu nastavili.
- **Vlastní zákazníci.** Keď je vo vašom vlastnom obchode odoslaná objednávka, aiku sa pozrie na každý produkt. Ak je sklad za ním nedostatočný a tento sklad vyrába továreň, chýbajúce množstvo sem pristane ako riadok, označený zákazníkom a číslom objednávky. Keď je táto objednávka expedovaná, riadok sa sám uzavrie.

Objednávky, ktoré prichádzajú cez starý systém, zoznam nenapájajú. Iba objednávky odoslané v aiku.

Filter **Source** hore na záložke *All* vám umožní vidieť iba riadky partnerov alebo iba riadky vlastných zákazníkov.

## Pohľady

Lišta záložiek nad nadpisom je celý zmysel stránky. Rovnaké riadky, šesť spôsobov, ako sa na ne pozrieť.

- **Board.** Plánovací pohľad, a ten, ktorým sa stránka otvára. Každý riadok je karta, ktorá postupuje dráhami od *Backlog* po *Done*. Vysvetlené v ďalšej časti.
- **All.** Plochá tabuľka, zoraditeľná a prehľadávateľná, s počtom otvorených riadkov. Použite ju, keď hľadáte jednu konkrétnu vec.
- **By artisan.** Jeden blok na osobu, podľa remeselníka priradeného k artefaktu, alebo ak taký nie je, k jeho kategórii. Riadky bez nikoho priradeného sedia pod *Unassigned*. Toto je pohľad na rozdeľovanie práce na deň.
- **By category.** Jeden blok na kategóriu artefaktov, takže výrobca kúpeľových gulí vidí kúpeľové gule a výrobca mydla vidí mydlo.
- **By buyer.** Jeden blok na partnerskú organizáciu alebo vlastného zákazníka, na chvíle, keď zostavujete zásielku.
- **Mixes.** Základy a zmesi, ktoré potrebujú otvorené pracovné príkazy, pre prípravára. Vysvetlené v [Príprava zmesí](/docs/preparing-mixes-sk).

V zoskupených pohľadoch má každý blok kapsulu nad zoznamom, ktorá ukazuje jeho názov a počet riadkov. Kliknutím na kapsulu blok skryjete, ďalším kliknutím ho vrátite späť. aiku si vašu voľbu pamätá v tomto prehliadači, takže plánovač, ktorému záleží len na dvoch kategóriách, vidí vždy len tie dve.

## Board

Šesť dráh, zľava doprava. Karta sa posúva doprava, ako práca postupuje, a väčšina presunov je pretiahnutie (drag).

| Dráha | Čo tam sedí |
| --- | --- |
| Pre-pick | riadky, pri ktorých sa nič nevyrába, lebo tovar je na sklade. Skrytá, kým nestlačíte **Pre-pick** nad boardom. Sklad ich zhromaždí, viď [Zhromažďovanie tovaru pre partnera](/docs/gathering-a-partners-goods-sk). |
| Backlog | riadky s artefaktom, na ktorý sa ešte nikto nepozrel |
| Preparing | riadky, o ktorých ste rozhodli, že sa vyrobia, s ustáleným množstvom |
| Assigned | pracovný príkaz existuje a je adresovaný remeselníkovi, ale nikto ho ešte nezačal |
| Producing | remeselník stlačil START na jednej z jeho úloh |
| Done | všetky úlohy pracovného príkazu sú hotové; čaká, kým ho sklad uloží |

Každá karta zobrazuje produkt, požadované množstvo, kto ho žiadal, a **In stock** (na sklade), aby ste videli, či sa vôbec oplatí vyrábať.

**Backlog → Preparing.** Pustite kartu a aiku sa opýta *Koľko vyrobiť?*. Navrhne požadované množstvo; napíšete viac a extra sa označí *pre sklad*. Ak má artefakt odporúčanú veľkosť dávky, malé tlačidlo **↑** zaokrúhli množstvo na celé dávky. Číslo zostáva na karte upraviteľné, kým je v Preparing.

**Preparing → Assigned.** Pustite kartu a aiku sa opýta *Kto to vyrobí?*. Navrhne remeselníka priradeného k artefaktu alebo jeho kategórii, viď [Kto čo vyrába](/docs/who-makes-what-sk). Vyberte meno a pracovný príkaz sa vytvorí v stave návrhu (draft), adresovaný danej osobe. Otvorte pracovný príkaz a stlačte **Release to floor** (uvoľniť na dielňu), keď má začať; dovtedy ho remeselník nevidí. Remeselníka neskôr zmeníte kliknutím na meno na karte.

**Producing** a **Done** sa posúvajú samy podľa toho, čo sa deje na obrazovke dielne. Karta opustí board, keď sklad uloží hotový tovar, viď [Uloženie hotovej výroby](/docs/putting-away-finished-production-sk), alebo keď je pracovný príkaz prijatý na sklad zo svojej vlastnej stránky.

Viac kariet naraz: kliknutím ich vyberte, potom potiahnite ktorúkoľvek z nich a presunie sa celý výber. Ponuka **Everybody** nad boardom ho zúži na jedného alebo dvoch remeselníkov, a filtre na rodinu, odberateľa a prioritu robia to isté pre karty.

Pod Boardom a pohľadom By artisan sedí **Open job orders per artisan** (otvorené pracovné príkazy na remeselníka): jeden čip na osobu s počtom otvorených pracovných príkazov. Červená znamená žiadny, jantárová znamená jeden; každý by mal mať aspoň dva, aby nikomu nedošla práca. Krížik na čipe označí osobu ako nie-remeselníka a skryje ju z počtu.

## Odosielanie riadkov partnerom

Riadky partnerov sa odosielajú odtiaľto; riadky vlastných zákazníkov nie, tie cestujú so svojou vlastnou objednávkou.

- Odškrtnite riadky partnerov, ktoré viete odoslať. Množstvo upravte pri **čiastočnom výbere** (partial pick), zvyšok zostáva otvorený na neskoršiu zásielku.
- **Pick into order** zhromaždí vaše zaškrtnutia do čakajúcej zásielky pre danú nákupnú organizáciu. Zostáva otvorená v boxe *Picked orders*, kým ju neodošlete.
- **Send to warehouse** odovzdá zásielku vášmu skladu ako bežnú objednávku: vychystanú, zabalenú, expedovanú a fakturovanú ako všetko ostatné. Prichádzajúca dodávka skladu (stock delivery) nákupnej organizácie sa im vytvorí a sleduje priebeh práce vo vašom sklade. Nikto stranu kupujúceho neaktualizuje ručne.

Zaškrtnutie riadku vlastného zákazníka nemá žiadny účinok. Pri stlačení Pick into order sa preskočí, pretože daný produkt už patrí zákazníckej objednávke.

## Čo je dobré vedieť

- Otvorený zoznam odberateľa je obmedzený zhruba na jeden objednávací cyklus toho, čo mu historicky dodávate, takže to, čo sa k vám dostane, je filtrovaná požiadavka, nie celý katalóg. Ak vám riadok príde zvláštny, opýtajte sa; odberateľ musel niečo iné obetovať, aby ho tam mohol zaradiť.
- Prvý výber pre nového partnera vytvorí vo vašom obchode zákaznícky účet pomenovaný podľa nákupnej organizácie. To je očakávané. Upozornite zákaznícky servis, aby to nikto "neupratal".
- Kým nestlačíte Send to warehouse, vychystaná objednávka je na bežných obrazovkách objednávok neviditeľná; jej domovom je stránka To produce.
- To, čo expedujete, je to, čo hovorí dodávka skladu kupujúceho. Nikdy nedopĺňajte množstvá, aby "sedeli so zoznamom".

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zobraziť frontu:</b> vaša organizácia → <b>Factory</b> → <b>To produce</b>. Prepínajte pohľady záložkami <b>Board · All · By artisan · By category · By buyer · Mixes</b>.</li>
<li><b>Rozhodnúť množstvo:</b> <i>Board</i> → potiahnite kartu z <b>Backlog</b> do <b>Preparing</b> → napíšte číslo, alebo stlačte <b>↑</b> pre celé dávky.</li>
<li><b>Vytvoriť pracovný príkaz:</b> potiahnite kartu z <b>Preparing</b> do <b>Assigned</b> → vyberte remeselníka → otvorte pracovný príkaz → <b>Release to floor</b>.</li>
<li><b>Riadky, ktoré potrebujú iba vychystanie:</b> tlačidlo <b>Pre-pick</b> nad boardom.</li>
<li><b>Skryť blok:</b> v zoskupenom pohľade kliknite na jeho kapsulu nad zoznamom. Ďalším kliknutím ho zobrazíte.</li>
<li><b>Iba partneri alebo iba zákazníci:</b> záložka <i>All</i> → filter <b>Source</b>.</li>
<li><b>Odoslať partnerovi:</b> odškrtnite riadky → <b>Pick into order</b> → <b>Send to warehouse</b> v boxe <i>Picked orders</i>.</li>
<li><b>Rozhodnúť, kto čo vyrába:</b> pozrite <a href="/docs/who-makes-what-sk">Kto čo vyrába</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú v karte zamestnanca v Human Resources a nesú so sebou oprávnenia.</li>
<li>Vidieť zoznam: pozícia <b>Production operative</b> (operátor) pre továreň, alebo vyššia.</li>
<li>Presúvanie kariet na Boarde, vytváranie a uvoľňovanie pracovných príkazov, vychystávanie a odosielanie: pozícia <b>Production floor supervisor</b> (vedúci dielne) pre továreň, alebo organisation supervisor. <b>Mix preparer</b> (prípravár zmesí) môže to isté iba pre zmesi.</li>
</ul>
</aside>
