---
title: Kontrola SKO čiarových kódov skenerom
summary: Prejdite sklad so skenerom v ruke, čítajte každý SKO štítok ako nápis na náleze a priraďte čiarový kód k správnemu SKO, keď sa štítok a regál nezhodujú.
date: 2026-09-03
source_date: 2026-09-03
tags: warehouse, inventory, barcodes, stock
category: warehouse
---

<aside class="tldr">
Každá vonkajšia škatuľa v sklade nesie <em>SKO čiarový kód</em> a aiku si vedie záznam o tom, ktorému SKO ktorý čiarový kód patrí. Za roky sa štítky prelepujú, škatule sa opakovane používajú a záznamy sa rozchádzajú, takže čiarový kód na regáli a čiarový kód v aiku si prestanú zodpovedať. Stránka <strong>SKO čiarové kódy</strong> (SKO barcodes) je ako archeologický nález: prechádzate uličky so skenerom v ruke, aiku prečíta každý štítok a ukáže vám, čo si myslí, že je vo vnútri, a vy to na mieste potvrdíte alebo opravíte. Zamestnanci skladu môžu kontrolovať; zamestnanci s prístupom na úpravu skladu môžu aj opravovať.
</aside>

## Čo je SKO čiarový kód

SKO je jednotka, ktorú sklad počíta: jedna škatuľa po šesť kusov, jedno vrecko po sto kusoch, jeden samostatný kus. Každé SKO môže niesť dve čísla. **SKO čiarový kód** (SKO barcode) je CODE 128 vytlačený na vonkajšom obale, ten, ktorý skenujú vyskladňovači a pracovníci príjmu tovaru. **Jednotkový EAN13** (unit EAN13) je malý maloobchodný čiarový kód priamo na produkte, ktorý patrí produktu a zobrazuje sa na webovej stránke. Stránka skenera číta oba, ale posúva (moves) len ten vonkajší. Jednotkový EAN má vlastný editor na stránke SKO a tu sa ho netýka.

<figure><img src="/art/docs/draw-barcode-dig.svg" alt="Akvarelová skica bádateľa vo fedore, ktorý si v uličke skladu drepí ako na archeologickom nálezisku a mieri ručným skenerom na vysoký stojaci kameň s vyrytým čiarovým kódom; vedľa sa vznáša karta zobrazujúca SKO s jeho obrázkom, umiestneniami a zásobami, a dve veľké tlačidlá, jedno zelené s nápisom All OK a jedno oranžové s nápisom Move" width="1200" height="750" loading="lazy"><figcaption>Každý štítok je artefakt. Naskenujte ho, prečítajte, rozhodnite.</figcaption></figure>

## Otvorenie náleziska

Vo vašom sklade otvorte **Inventory** a stlačte **Manage barcodes** vpravo hore. Stránka je stavaná pre telefón alebo tablet: jeden stĺpec, jedno políčko na skenovanie a tlačidlá dostatočne široké na palec. Funguje akýkoľvek čiarový skener, ktorý píše ako klávesnica, a nemusíte najprv klepnúť do políčka - stránka počúva skener nech ste kdekoľvek na nej. Ak nemáte skener, zadajte číslo a stlačte Enter.

## Čítanie nápisu

Naskenujte štítok na škatuli. Stane sa jedna z troch vecí.

**Čiarový kód je známy.** Objaví sa karta s obrázkom, kódom a názvom SKO, jeho stavom, veľkosťou balenia, číslom, ktoré sa zhodovalo, a či to bol vonkajší SKO čiarový kód alebo jednotkový EAN, celkovými zásobami a každým umiestnením, kde sa nachádza, s množstvom na každom. Pozrite sa na regál. Ak škatuľa vo vašich rukách je skutočne toto SKO, stlačte **All OK**. Karta zmizne, počítadlo hore stúpne o jednu a prechádzate na ďalšiu škatuľu.

**Čiarový kód je známy, ale regál nesúhlasí.** Karta pomenúva jedno SKO a škatuľa obsahuje iné. Stlačte **Wrong SKO, move barcode** (nesprávne SKO, presunúť čiarový kód). Otvorí sa vyhľadávacie políčko; zadajte časť kódu alebo názov toho, čo je v škatuli naozaj, klepnite naň v zozname a potvrďte tlačidlom **Assign**. Čiarový kód opustí SKO, ktoré ho nesprávne malo, a usadí sa na tom, ktoré ste vybrali. Karta zozelenie s nápisom *Barcode assigned*, takže viete, že sa to podarilo.

**Čiarový kód je neznámy.** Zobrazí sa červená karta *Barcode not found* s číslom, ktoré aiku práve prečítalo. Ak viete, čo je v škatuli, stlačte **Assign to a SKO**, vyhľadajte to, klepnite a stlačte **Assign**. Ak neviete, stlačte **Skip** a pokračujte ďalej; číslo sa nikde nezaznamená.

## Práca opačným smerom

Niekedy začínate od SKO a nie od štítku: škatuľa bola prelepená novým štítkom a chcete zaregistrovať novú nálepku. Na prázdnej stránke stlačte **Find a SKO**, vyhľadajte a klepnite na SKO, potom naskenujte štítok. Tlačidlo **Assign** sa aktivuje, len čo je číslo prečítané, a jeho stlačením priradíte tento čiarový kód danému SKO.

## Čo presun čiarového kódu robí v pozadí

SKO čiarový kód je jediná pravda pre celú skupinu (group). Keď ho presuniete, aiku ho vezme každému SKO, ktoré ho nieslo, vo všetkých organizáciách zdieľajúcich daný sklad, a dá ho SKO, ktoré ste vybrali, opäť vo všetkých organizáciách. Ak zvolené SKO už malo iný vonkajší čiarový kód, nový ho nahradí. Každá zmena sa zapíše do histórie (History) SKO, takže supervízor vždy vidí, kedy sa čiarový kód presunul a kto to urobil.

Sú dve veci, ktoré stránka odmietne. Číslo, ktoré je už čiarovým kódom iného SKO toho istého skladu vo vašej vlastnej organizácii, sa nedá rozdeliť medzi dva; a číslo so znakmi, ktoré tlačiareň štítkov nevie vytlačiť, sa odmietne. V oboch prípadoch to vysvetlí červené upozornenie a nič sa nezmení.

## Dobrá smena

Prechádzajte uličku od začiatku do konca namiesto preskakovania sem-tam, aby vám počítadlo niečo hovorilo. Nájdená karta so zvukom je bežný prípad; tichý bzučiak znamená nenájdené a zaslúži si pozretie. Keď natrafíte na štítok, ktorého SKO neviete identifikovať, preskočte ho, poznačte si umiestnenie a vráťte sa s niekým, kto daný sortiment pozná, namiesto hádania. Nesprávne priradenie sa dá ľahko vrátiť - naskenujte štítok znova a presuňte ho späť - ale odhad, ktorý nikto neskontroluje, zostane nesprávny.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Otvoriť skener:</b> váš sklad → <b>Inventory</b> → tlačidlo <b>Manage barcodes</b> vpravo hore.</li>
<li><b>Potvrdiť zhodu:</b> naskenovať → <b>All OK</b>.</li>
<li><b>Presunúť čiarový kód na správne SKO:</b> naskenovať → <b>Wrong SKO, move barcode</b> → vyhľadať → klepnúť na SKO → <b>Assign</b>.</li>
<li><b>Zaregistrovať neznámy štítok:</b> naskenovať → <b>Assign to a SKO</b> → vyhľadať → klepnúť → <b>Assign</b>; alebo najprv <b>Find a SKO</b> a naskenovať potom.</li>
<li><b>Zobraziť čiarové kódy a históriu SKO:</b> váš sklad → <b>Inventory → SKOs</b> → otvoriť SKO → karta <b>Barcodes</b> a <b>History</b>.</li>
</ul>
</aside>
<aside class="permissions"><strong>Povolenia, ktoré potrebujete</strong>
<p>Skenovanie a potvrdzovanie vyžaduje prístup na zobrazenie inventára alebo skladu pre daný sklad. Presun alebo priradenie čiarového kódu vyžaduje prístup na úpravu skladu (alebo rolu stock supervisor) pre daný sklad; bez neho sú tlačidlá na presun a priradenie skryté a stránka funguje ako kontrola len na čítanie.</p>
</aside>
