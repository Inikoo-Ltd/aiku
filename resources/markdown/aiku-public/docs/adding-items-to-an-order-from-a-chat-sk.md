---
title: Pridanie položiek do objednávky z chatu
summary: Zákazník v chate zabudol niečo pridať. Doplňte to do objednávky, ktorú už zadal, alebo založte nadväzujúcu objednávku, ktorá pôjde v tom istom balíku, a pošlite platobný odkaz na rozdiel - všetko priamo z konverzácie.
date: 2026-09-21
source_date: 2026-09-21
tags: chat, orders, payments, customer service
category: crm
---

<aside class="tldr">
Zákazník napíše, že mu v práve zadanej objednávke chýba položka. Otvorte bočný panel konverzácie a pozrite sa na <b>Last orders</b> (posledné objednávky): objednávka, ktorú sklad ešte nedokončil vychystávať, zobrazuje <b>+ Add items</b> (pridať položky) a zabudnuté produkty sa doplnia priamo do tejto objednávky a na zoznam vychystávača. Objednávka, ktorá je už vychystaná, namiesto toho zobrazuje <b>+ Follow-up order</b> (nadväzujúca objednávka), ktorá vytvorí druhú objednávku a na oboch oznámi skladu, aby ich poslal spolu. Každá objednávka, ktorá ešte niečo dlhuje, zobrazuje <b>Payment link</b> (platobný odkaz), ktorý vytvorí kartový odkaz presne na dlžnú sumu a skopíruje ho, aby ste ho mohli vložiť do chatu.
</aside>

## Kde to nájdete

Otvorte konverzáciu v <b>Chat</b>. Bočný panel vpravo sa otvorí na <b>Overview</b> (prehľad) a pod kontaktnými údajmi je <b>Last orders</b> (posledné objednávky): posledných päť objednávok zákazníka s ich stavom, dátumom a sumou. Tlačidlá sú pri každom riadku, vedľa stavu, a zobrazujú sa len tie, ktoré pre danú objednávku dávajú zmysel.

Zobrazujú sa ľuďom, ktorí môžu v danom obchode upravovať objednávky. Nezobrazujú sa pri objednávkach z trhoviska - Faire a podobné - pretože tieto objednávky sa riadia tým, čo hovorí trhovisko, a zmena na našej strane by sa len prepísala späť. Hostia nemajú žiadne objednávky, takže tam nie je čo zobraziť.

## Add items (pridať položky)

Zobrazuje sa, kým je objednávka <b>Submitted</b> (zadaná), <b>In warehouse</b> (na sklade), sa <b>vychystáva</b> alebo <b>čaká</b> na zákaznícky servis. Inými slovami: dokým ju ešte niekto bude na sklade fyzicky vychystávať.

Stlačte <b>+ Add items</b>, vyhľadajte produkty, nastavte množstvo pri každom a stlačte <b>Add</b> (pridať). Kým nestlačíte toto tlačidlo, nič sa neuloží, takže si to dovtedy môžete voľne rozmyslieť.

Čo sa stane ďalej:

- Produkty sa doplnia do <b>tej istej objednávky</b>. Žiadna druhá objednávka, žiadny druhý balík, žiadna poznámka na písanie.
- Ak sklad už objednávku drží, nové riadky idú rovno na <b>zoznam vychystávača</b>. Ak sa vychystávanie už začalo, tieto riadky sú zvýraznené a vychystávač uvidí upozornenie, že objednávka bola zmenená - rovnaké, aké vidí pri zmene množstva.
- Ak objednávka už tento produkt obsahovala, zvýši sa jeho množstvo namiesto toho, aby vznikol duplicitný riadok.
- Suma objednávky sa zvýši a objednávka, ktorá bola zaplatená, sa teraz zobrazuje ako <b>nezaplatená v plnej výške</b>. To je správne: zákazník dlhuje rozdiel. Pozrite <b>Payment link</b> nižšie.

<b>Ak vám systém povie, že je neskoro.</b> Vychystávač môže objednávku dokončiť v tých pár sekundách medzi otvorením okna a stlačením <b>Add</b>. Vtedy sa nič nepridá a správa povie, aby ste založili nadväzujúcu objednávku. Obnovte panel a riadok už ponúkne presne túto možnosť.

## Follow-up order (nadväzujúca objednávka)

Zobrazuje sa, keď je objednávka <b>Picked</b> (vychystaná), <b>Packing</b> (baliaca sa), <b>Packed</b> (zabalená) alebo <b>Finalised</b> (dokončená) - na sklade hotová, ale ešte neodoslaná. Ďalšie položky sa už nemôžu pridať do balíka, ktorého vychystávanie je uzavreté, takže cestujú ako druhá objednávka v tej istej škatuli.

Stlačte <b>+ Follow-up order</b>. Vytvorí sa nová, prázdna objednávka pre toho istého zákazníka a otvorí sa v novej karte. Obe objednávky teraz nesú v poznámke pre sklad riadok <b>Send together with order ...</b> (poslať spolu s objednávkou ...) - to je poznámka, ktorá sa tlačí pre ľudí, ktorí vychystávajú a balia, a ktorú si aj čítajú. Sami nič nepíšete a poznámka, ktorá tam už bola, zostáva zachovaná.

Potom na novej objednávke: pridajte produkty a zadajte ju rovnako, ako by ste zadali akúkoľvek inú objednávku pre zákazníka.

Nová objednávka preberá obvyklú doručovaciu adresu zákazníka. Ak prvá objednávka smerovala inam, zmeňte adresu na novej tak, aby sedela - dve objednávky na dve adresy nemôžu zdieľať jeden balík.

## Payment link (platobný odkaz)

Zobrazuje sa pri každej objednávke, ktorá ešte niečo dlhuje, bez ohľadu na jej stav, v obchodoch, ktoré prijímajú platby kartou.

Stlačte <b>Payment link</b>. Vytvorí sa kartový platobný odkaz presne na <b>dlžnú sumu</b> - sumu objednávky mínus to, čo už bolo zaplatené - a skopíruje sa. Vložte ho do konverzácie. Potvrdenie vám ukáže sumu, aby ste ju vedeli povedať zákazníkovi.

Keď zákazník zaplatí, platba sa sama objaví na objednávke a objednávka sa zobrazí ako zaplatená. Nič netreba ručne párovať ani zapisovať a odkaz netreba vytvárať na stránke poskytovateľa platieb.

Dve veci, ktoré treba vedieť:

- Odkaz platí na sumu dlžnú <b>v okamihu jeho vytvorenia</b>. Ak sa objednávka neskôr zmení, vytvorte nový odkaz.
- Odkaz zostáva platný sedem dní.

Nadväzujúca objednávka sa v <b>Last orders</b> zobrazí spolu s tlačidlom <b>Payment link</b> hneď, ako je zadaná.

## Ktorú možnosť použiť?

Nemusíte sa rozhodovať: riadok ponúkne len to, čo daná objednávka umožňuje. Ešte sa vychystáva: <b>Add items</b>. Už vychystaná: <b>Follow-up order</b>. Už odoslaná: ani jedna z možností, pretože už nie je k akému balíku sa pridať - zadajte novú objednávku bežným spôsobom.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Pozrieť objednávky zákazníka:</b> <b>Chat</b> &rarr; otvorte konverzáciu &rarr; bočný panel &rarr; <b>Overview</b> &rarr; <b>Last orders</b>.</li>
<li><b>Pridať zabudnuté položky do tej istej objednávky:</b> <b>+ Add items</b> pri riadku objednávky &rarr; vyberte produkty a množstvá &rarr; <b>Add</b>.</li>
<li><b>Objednávka je už vychystaná:</b> <b>+ Follow-up order</b> pri riadku &rarr; pridajte produkty na objednávke, ktorá sa otvorí &rarr; zadajte ju.</li>
<li><b>Vybrať rozdiel:</b> <b>Payment link</b> pri riadku &rarr; vložte do konverzácie.</li>
<li><b>Otvoriť samotnú objednávku:</b> kliknite na jej referenciu v <b>Last orders</b>.</li>
</ul>
</aside>
