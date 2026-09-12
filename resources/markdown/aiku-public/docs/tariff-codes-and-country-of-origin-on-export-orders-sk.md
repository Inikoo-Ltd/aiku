---
title: Colné sadzby a krajina pôvodu pri exportných objednávkach
summary: Zistite, kde v dodacom liste žijú colné údaje, prečo sa hmotnosť a hodnota delia medzi jednotlivé časti viacdielneho produktu, a prečo sa karta môže oprávnene líšiť od faktúry.
date: 2026-09-12
source_date: 2026-09-12
tags: dispatch, customs, tariff codes
category: dispatch
---

<aside class="tldr">
Každý dodací list má kartu <b>Tariff codes / Origin</b> [Colné sadzby / Pôvod], vedľa <b>Items</b> [Položky]. Zobrazuje, čo je fyzicky v krabiciach, jeden riadok na kombináciu colnej sadzby a krajiny pôvodu, spolu s príslušnými jednotkami, hmotnosťou a hodnotou. Kartu môžete exportovať do Excelu pre colné papiere. Ak riadku niečo chýba, je pripnutý navrchu, aby ste to rýchlo opravili. Tento článok vysvetľuje, ako kartu čítať, prečo môže viacdielny produkt vyzerať zvláštne, a prečo sa nemusí zhodovať s faktúrou.
</aside>

## Kde ju nájdete

Otvorte dodací list - buď zo zoznamu vášho skladu **Dispatching → Delivery notes** [Expedícia → Dodacie listy], alebo z vlastnej karty **Delivery notes** [Dodacie listy] na objednávke - a uvidíte **Tariff codes / Origin** [Colné sadzby / Pôvod] vedľa **Items** [Položky]. Každý riadok zoskupuje všetko na dodacom liste, čo zdieľa rovnakú colnú sadzbu a rovnakú krajinu pôvodu: samotný kód, jeho popis, vlajku pôvodu, či je označený ako nebezpečný tovar (DG), časti, ktoré pokrýva, prípadné UN čísla, a celkové jednotky, hmotnosť a hodnotu.

## Prečo sa počíta po častiach, nie po produktoch

Produkt môže pozostávať z viacerých častí. Napríklad tvárový roller predávaný s vlastným vreckom sú v skutočnosti dve samostatné SKO (skladové položky), každá s vlastnou colnou sadzbou. Karta zobrazuje každú časť pod jej vlastnou sadzbou, s vlastným podielom jednotiek a hmotnosti. Vrecko predávané samostatne aj to isté vrecko predávané v rámci setu s rollerom nakoniec skončia pod colnou sadzbou vrecka - pretože práve to zaujíma colnicu: čo je skutočne v krabici, nie ako sa to predalo.

Rovnaká logika platí pre hmotnosť a jednotky: počítajú sa po častiach, nie po celom produkte.

## Odkiaľ pochádza suma

Hodnota je zložitejšia, pretože riadok objednávky má cenu ako celý produkt, nie po častiach. Na jej rozdelenie aiku použije najlepší základ, ktorý majú všetky časti daného riadku spoločný: vlastnú predajnú cenu každej časti, ak ju majú všetky, inak náklady dodávateľa, inak skladovú hodnotu, a až ako poslednú možnosť rovnaký podiel medzi časťami. Každá časť dostane svoj spravodlivý podiel a podiely sa spolu rovnajú celkovej sume riadku. Ak sa súčty riadku menia bez zjavného dôvodu, zvyčajne je to preto, že sa zmenila cena alebo náklad niektorej časti.

## Keď niečo chýba

Ak niektorej časti chýba colná sadzba alebo krajina pôvodu, aiku ju nevie zaradiť, takže pripne riadok navrchu s označením chýbajúcej colnej sadzby alebo pôvodu, ktorý presne uvádza, ktoré trade units sú problém. Oba údaje žijú na trade unit, takže tam ich aj opravíte: **Goods → Trade units** [Tovar → Trade units], otvorte trade unit, doplňte ich a uložte. Každý dodací list, ktorý naň odkazuje, zmenu prevezme.

Organizácia môže tiež doladiť colnú sadzbu bez zásahu do zdieľaného 6-miestneho HS kódu, ktorý používajú všetky organizácie: môže nad ním pridať alebo zmeniť posledné národné číslice pre vlastné colné pravidlá, bez toho, aby to ovplyvnilo zdieľaný kód pre ostatných. Karta zobrazuje toto prepísanie, hneď ako existuje.

## Export ako tabuľka

Stlačte **Export** a získate Excel súbor s rovnakým zoskupením, aké vidíte na obrazovke. Pred stiahnutím si môžete vybrať, ktoré stĺpce zahrnúť: tariff code [colná sadzba], description [popis], origin [pôvod], UN numbers [UN čísla], references [referencie častí], weight (kg) [hmotnosť v kg], units [jednotky] a amount [suma]. Vyberte len to, čo daný papier skutočne potrebuje.

## Prečo sa to môže líšiť od faktúry

Faktúra má vlastné zoskupenie **Group by Tariff Code** [Zoskupiť podľa colnej sadzby] pre danú šablónu a export, ktoré zoskupuje podľa celého produktu: viacdielny produkt ide pod sadzbu svojej hlavnej časti a nesie so sebou celú cenu. Karta dodacieho listu naopak rozdeľuje ten istý produkt medzi jeho jednotlivé časti a ich vlastné sadzby.

Obe sú správne - odpovedajú len na iné otázky. Karta odpovedá na otázku "čo je fyzicky v týchto krabiciach, pod akým colným kódom", čo je presne to, čo potrebujú exportné papiere. Faktúra odpovedá na otázku "čo zákazník kúpil a za koľko", čo je obchodný doklad. Hodnota alebo hmotnosť viacdielneho produktu sa môže medzi týmito dvoma miestami oprávnene líšiť; nejde o nezrovnalosť, ktorú treba opravovať.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Zobraziť colné sadzby a pôvod zásielky:</b> otvorte dodací list (zo <b>Dispatching → Delivery notes</b> vášho skladu, alebo z vlastnej karty <b>Delivery notes</b> objednávky) → karta <b>Tariff codes / Origin</b>, vedľa <b>Items</b>.</li>
<li><b>Exportovať kartu ako tabuľku:</b> na karte <b>Tariff codes / Origin</b> stlačte <b>Export</b> a vyberte potrebné stĺpce.</li>
<li><b>Opraviť chýbajúcu colnú sadzbu alebo pôvod:</b> vaša organizácia → <b>Goods → Trade units</b>, otvorte trade unit a doplňte colnú sadzbu a krajinu pôvodu.</li>
<li><b>Zobraziť vlastné zoskupenie faktúry podľa colnej sadzby:</b> otvorte kartu <b>Invoices</b> objednávky → otvorte faktúru → možnosť <b>Group by Tariff Code</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Povolenia, ktoré potrebujete</strong>
Na zobrazenie karty <b>Tariff codes / Origin</b> dodacieho listu potrebujete prístup na zobrazenie dispatchingu alebo fulfilmentu pre daný sklad, alebo prístup na zobrazenie objednávok shopu. Zmena colnej sadzby alebo krajiny pôvodu trade unit, alebo nastavenie prepísania národných číslic organizácie, vyžaduje prístup na úpravu účtovníctva.
</aside>
