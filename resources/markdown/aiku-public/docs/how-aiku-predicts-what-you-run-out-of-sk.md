---
title: Ako aiku predpovedá, čo vám dôjde
summary: Čo v skutočnosti znamená "dôjde nám to za ~12 dní" a navrhované množstvo, prečo bestseller, ktorý je vypredaný, žiada tak veľa, a kedy dôverovať číslu viac než vlastnému úsudku.
date: 2026-10-02
source_date: 2026-10-02
tags: procurement, stock, intercompany, shopping-list
category: procurement
---

<aside class="tldr">
Pre každého, kto nakupuje sklad. Dve čísla sprevádzajú každý SKO na obrazovkách nákupu: <b>dôjde nám to za ~N dní</b> a <b>navrhované</b> množstvo. Táto stránka vysvetľuje, odkiaľ sa berú, aby ste vedeli, kedy im veriť a kedy ich prebiť vlastným úsudkom. Ak len chcete vybaviť objednávku, praktickými sprievodcami sú <a href="/docs/reading-the-partner-shopping-dashboard-sk">nákupný panel</a> a <a href="/docs/buying-from-a-partner-sk">sprievodca pre kupujúceho</a> — sem sa vráťte, keď vám nejaké číslo príde nezmyselné.
</aside>

## Dve čísla

Nech nakupujete kdekoľvek — na kartách **Browse** u partnera, v **Shopping list**, na paneli dodávateľa alebo agenta, v návrhu Auto-fill — tá istá dvojica sprevádza danú položku.

**Dôjde nám to za ~N dní** je to, čo máte na sklade, vydelené tým, ako rýchlo si aiku myslí, že to mizne. Zbelie do červena pri dvoch týždňoch alebo menej, do jantárovej farby do mesiaca. "Dochádza nám to teraz" znamená, že polica je už prázdna.

**Navrhované** je množstvo, ktoré by vás doniesli k ďalšej objednávke a trochu za ňu: dosť na dodaciu lehotu dodávateľa, plus medzeru do ďalšieho bežného objednávania, plus rezervu podľa toho, aká je položka nevyspytateľná — a potom mínus to, čo je na polici, a to, čo je už na ceste. Zaokrúhľuje sa na celé prepravné jednotky, pretože presne to môžete v skutočnosti kúpiť.

Obe čísla sa obnovujú sami vždy, keď sa sklad pohne, takže sú aktuálne v momente, keď sa na ne pozriete: rýchlosť predaja položky sa počíta každú noc a sklad, ktorým sa delí, je dnešný. Na objednávke u dodávateľa sa tá istá predpoveď zobrazuje pod **Stock** ako **Lasts** (vydrží), napríklad *Lasts: 6 weeks (out around 15 Nov) · could be 4 weeks*.

## Myšlienka, vďaka ktorej to funguje: prázdne dni sa nepočítajú

Zjavný spôsob, ako merať, aké rýchlo sa niečo predáva, je spriemerovať jeho tržby za posledné tri mesiace. Táto metóda potichu ničí sklad.

Vezmite položku, ktorá sa vypredala v prvom týždni a zvyšok štvrťroka bola prázdna. Spriemerovaná cez deväťdesiat dní vyzerá, akoby sa sotva hýbala — takže sa nikdy neobjedná znova, takže zostáva prázdna, takže ďalší štvrťrok vyzerá ešte horšie. Čím lepšie sa predáva, tým rýchlejšie mizne, tým neviditeľnejšou sa stáva. Väčšina skladov má hŕstku takýchto položiek a zvyčajne ide o veci, o ktoré ľudia žiadajú.

Preto aiku pri každej položke, ktorá nedávno často chýbala na sklade, nepriemeruje cez kalendár. Deň po dni rekonštruuje, či bola položka skutočne dostupná, a meria rýchlosť predaja **iba za dni, keď ste ju mali na predaj**. Dni, keď bola polica prázdna, sa berú ako dni bez informácie — nie ako dni bez dopytu.

Práve toto jedno pravidlo je dôvod, prečo bestseller, ktorý sedí na nule, ukazuje veľkú navrhovanú objednávku namiesto malej. Nie je to chyba a systém nepanikári. Systém konečne vidí dopyt, ktorý pred ním prázdne týždne skrývali.

## Odkiaľ číslo pochádza a nakoľko mu veriť

Nie každá položka má za sebou dôkazy rovnakej kvality a pomáha vedieť, s ktorým prípadom máte do činenia.

- **Predpoveď vlastných predajov.** Bežný prípad pre položky, ktoré boli za posledné tri mesiace na polici aspoň sedem dní z desiatich. Každú noc predikčný model prečíta až tri roky týždenných predajov položky, vrátane jej sezón, a predpovie nasledujúce týždne. Predpovede všetkých organizácií sa potom porovnajú s tým, čo sa skutočne odoslalo za posledných šesť týždňov, a prispôsobia sa im, takže keď dopyt naprieč všetkým rastie alebo klesá, čísla ho dobehnú v priebehu niekoľkých dní. Na našich vlastných predajoch bola o tretinu bližšie k tomu, čo sa potom skutočne predalo, než nižšie uvedená metóda.
- **Vlastné nedávne dni na sklade.** Pre položky, ktoré v poslednom čase chýbali na sklade viac než tri dni z desiatich, a keď nočná predpoveď nebežala. Je to pravidlo prázdnych dní vyššie. Stabilné predajcovia dostávajú odhad sledujúci trend; pomalé, nárazové položky — tie, čo odchádzajú po troch kusoch raz za pár týždňov — sa merajú inak, podľa toho, aká veľká býva príležitostná objednávka a ako dlho trvajú tiché medzery, čo je o nich najúprimnejší spôsob rozprávania.
- **Rovnaká položka v sesterskej organizácii.** Tu sa za posledné tri mesiace nepredala; niekde inde v skupine áno. aiku si požičia ich rýchlosť a zníži ju na polovicu, pretože iný trh je náznak, nie meranie. Berte to ako východiskový bod.
- **Rodina, do ktorej patrí.** Najslabší prípad: zvyčajne úplne nový produkt bez nedávneho predaja kdekoľvek, odhadnutý podľa susedov a poriadne znížený. Toto je zástupka za váš úsudok, nie jeho náhrada.

Ak ani jeden z týchto zdrojov nemá z čoho vychádzať, odhad nie je žiadny: ani deň vypredania, ani navrhované množstvo. Mimo nočnej predpovede sa staršia história samotnej položky nepoužíva — pri položke, ktorá sa minulý rok dobre predávala, ale za posledné tri mesiace nie, sa nepredpokladá, že bude pokračovať tam, kde prestala. Keď sa to skúšalo, objednávalo sa priveľa.

**Sezóny len tam, kde ich ukazuje vlastná história položky.** Nočná predpoveď vidí až tri roky, takže položka, ktorá mala každé Vianoce špičku, má predpovedanú špičku aj tentokrát. Položky na pravidle dní na sklade, nové položky a položky príliš malé na to, aby sa vzorec ukázal, nedostanú žiadny sezónny nárast: vianočná položka sa v auguste potom predpovedá podľa augustovej rýchlosti. Preto pred špičkou, o ktorej viete, že príde — pred Q4, pri letnom sortimente, pred veľtrhom — skontrolujte návrh a ak nevzrástol, zvýšte ho ručne.

## Prečo číslo môže vyzerať zle (a často aj je)

Predpoveď číta históriu. Čokoľvek sa stalo mimo histórie, nemôže vedieť.

- **Jednorazová hromadná objednávka.** Jeden zákazník, ktorý vás vyprázdnil, vyzerá presne ako náhla obľúbenosť. Prebite to.
- **Položka, ktorú vyraďujete.** História hovorí, že sa predáva; váš plán hovorí, že končí. Systém váš plán nepozná.
- **Akcia, katalógová fotka, spustenie ponuky na marketplace.** Dopyt sa chystá zmeniť z dôvodu, ktorý sa ešte nestal.
- **Známa špička, napríklad Q4.** Špičku dostanú len položky, ktorých vlastná minulosť ju ukazuje; pri ostatných je návrh vypočítaný z pokojných mesiacov pred ňou. Objednajte viac ručne a dosť skoro na dodaciu lehotu.
- **Úplne nový produkt.** Pozrite si prípad rodiny vyššie — to číslo je odhad s presvedčivou tvárou.
- **Niečo, čo sa vôbec nepohlo, ale má hodnotu.** To skončí v **Dead stock** na paneli a potrebuje rozhodnutie od človeka, nie ďalšiu objednávku.

Pravidlo palca: predpoveď je lepšia ako vy v nudnom strede katalógu — stovky obyčajných položiek, o ktorých nikto nemá čas premýšľať — a horšia ako vy pri všetkom, čo má svoj príbeh. Nechajte ju vybaviť objem a svoju pozornosť venujte výnimkám.

## Čítanie v návrhu Auto-fill

Auto-fill zoraďuje kandidátov podľa toho, ako skoro vám dôjdu, a najprv dopĺňa tie najnaliehavejšie, kým sa nevyčerpá rozpočet. Každá navrhnutá položka nesie svoj dôvod jasnými slovami — *"Our sales/quarter ~48 · our stock 0 · we run out now"* — čo je predpoveď, ktorá ukazuje svoj postup. Prečítajte si dôvody pred potvrdením; tam sa najľahšie chytí nesprávne číslo a odškrtnutie riadku zaberie jedno kliknutie. Nič sa neobjedná, kým nestlačíte **Add items to shopping list**.

## Pohľad dopredu: čo dôjde, ak sa nič ďalšie neobjedná

Na paneli **Procurement** graf vypredaní pokračuje za včerajšok dvoma bodkovanými čiarami: koľko SKO bude vypredaných každý deň počas nasledujúcich ôsmich týždňov a aké tržby by za deň stratili. Sklad každého SKO sa posúva dopredu podľa jeho predpovede a každá už otvorená objednávka u dodávateľa i dodávka pristane v deň, na ktorý sa očakáva. Objednávky, ktoré už prekročili očakávaný deň, sa vynechajú, kým nedostanú nový dátum, takže keď neskorú objednávku vyurgujete a znova jej určíte dátum, jej sklad sa do čiary vráti. Nič, čo ste ešte neobjednali, sa nepočíta, takže čiary ukazujú, čo sa stane, ak nikto nič ďalšie neobjedná. Objednajte dnes a zajtrajšia čiara klesne.

Zoznam **Stock levels** (úrovne skladu) za každou úrovňou na paneli má stĺpec **Lost if not ordered** (stratené, ak sa neobjedná): tržby, o ktoré by SKO prišlo počas dodacej lehoty a nasledujúceho mesiaca, ak sa nič ďalšie neobjedná. Zoraďte podľa neho a medzery, ktoré stoja najviac, budú navrchu.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Vidieť čísla pri jednotlivých položkách:</b> <b>Procurement → Partners</b> (alebo <b>Suppliers</b>, alebo <b>Agents</b>) → otvorte jedného → <b>Browse</b>: každá karta ukazuje <i>our stock</i>, <i>our sales / quarter</i>, <i>Estimated: Would run out in</i> a čiarkovaný čip <b>suggested</b>, ktorý vyplní pole s množstvom.</li>
<li><b>Vidieť ich naprieč katalógom:</b> panel **Shopping** toho istého partnera → dlaždice stock-at-risk sú postavené na dni vypredania; kliknite na číslo dlaždice pre položky za ním.</li>
<li><b>Vidieť ich na otvorenej objednávke:</b> <b>Shopping list</b> → stĺpec **Info** nesie príbeh o sklade pre každý riadok.</li>
<li><b>Vidieť, čo dôjde:</b> <b>Procurement</b> → graf vypredaní, bodkované čiary za včerajškom; nad ním vyberte zdroj (agenti, dodávatelia…). Kliknite na úroveň skladu vpravo a potom zoraďte zoznam podľa <b>Lost if not ordered</b>.</li>
<li><b>Vidieť ich na objednávke u dodávateľa:</b> <b>Procurement → Suppliers</b> → otvorte jedného → jeho objednávku → <b>Items</b> alebo <b>Products</b>: riadok <b>Lasts</b> pod <b>Stock</b>, červený do dvoch týždňov, jantárový do šiestich.</li>
<li><b>Prebiť jednu z nich:</b> zadajte vlastné množstvo do poľa na karte <b>Browse</b> — priamo tým upravíte otvorený riadok. Nič sa nad vami znovu nenavrhne.</li>
<li><b>Opraviť dodaciu lehotu za návrhom:</b> nastavenia SKO, alebo nastavenia produktu dodávateľa, kým ešte hovorí <i>estimate</i>.</li>
</ul>
</aside>
