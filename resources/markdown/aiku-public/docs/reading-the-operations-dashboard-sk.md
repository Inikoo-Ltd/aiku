---
title: Ako čítať nástenku Operations
summary: Skladový pohľad na nástenku — čo vyžaduje pozornosť hneď teraz, kde sa nachádza každý dodací list, ako rýchlo objednávky odchádzajú, kto zbiera a balí, čo prichádza a čo to uvoľní. Obnovuje sa sama každú minútu.
date: 2026-10-05
source_date: 2026-10-05
tags: warehouse, dispatch, goods in, picking, packing, dashboard
category: warehouse
---

<aside class="tldr">
Otvorte <b>Dashboard</b> a vyberte kartu <b>Operations (In/Out)</b>. Pracovníci príjmu tovaru, výdaja tovaru a fulfilmentu sa na nej ocitnú rovno. Najprv si prečítajte farebný pruh navrchu: každá dlaždica počíta niečo, čo vyžaduje človeka hneď teraz, a keď nie je čo robiť, je sivá. Pod ním pipeline ukazuje, kde sa nachádza každý dodací list, potom rýchlosť expedície, tím zberačov a baličov, príjem tovaru, zásoby a vratky a úplne dole malá karta s predajmi. Každé číslo je odkaz na zoznam, ktorý za ním stojí.
</aside>

Otvorte ju cez **Dashboard → Operations (In/Out)**. Ak pracujete na príjme tovaru, výdaji tovaru alebo vo fulfilmente, nástenka sa otvorí na tejto karte. Karta, ktorú vyberiete naposledy, sa zobrazí aj nabudúce, takže ak chcete, môžete sa natrvalo vrátiť na **Sales** alebo **Stock**.

Stránka každého skladu (**vaša organizácia → Warehouses → sklad**) sa tiež otvára na karte **Operations (In/Out)**: rovnaký pohľad, len pre daný sklad.

## Filtre a čas

Navrchu sú dva filtre:

- **Warehouse** (sklad) — jeden sklad (spolu s jeho organizáciou) alebo všetky. Vidíte len sklady, v ktorých pracujete.
- **Channel** (kanál) — Trade, Retail, Dropshipping, Marketplace alebo Fulfilment client.

Váš výber sa zapamätá. Vpravo sa pri každom sklade zobrazuje jeho **miestny čas**. „Dnes“, „včera“ a „rovnaký deň“ vždy znamenajú kalendár daného skladu, nie UTC. Kým je stránka otvorená, každú minútu si načíta čerstvé údaje. Tlačidlo **Live** ich načíta okamžite.

Pri vybraných **všetkých skladoch** je pod súčtom na každej dlaždici krátky kód každého skladu (napríklad *ED 7 · PAR 20*). Kliknutím na kód otvoríte zoznam daného skladu. Pri jednom vybranom sklade je odkazom samotné veľké číslo.

## Needs attention now (Vyžaduje pozornosť hneď)

Osem dlaždíc. Dlaždica je **sivá pri nule**, **oranžová**, keď si zaslúži pozornosť, a **červená** iba vtedy, keď je prekročený limit. Otáznik ? pri každej dlaždici ukazuje presnú definíciu.

- **Urgent queue not picked** — dodacie listy s prémiovou expedíciou, ktoré ešte nikto nezačal zbierať. Červená, keď najstarší čaká viac ako hodinu.
- **At risk of missing collection** — vyžaduje časy vyzdvihnutia každého prepravcu pre každý sklad. Tie zatiaľ nie sú nastavené, preto dlaždica ukazuje pomlčku.
- **Blocked orders** — dodacie listy, na ktorých sa zbieranie zastavilo, rozdelené podľa dôvodu: **Stock** (položka čaká na sklad) alebo **CS** (položka čaká na zákaznícky servis). Oranžová nad 10, červená, keď je niektorý zablokovaný viac ako 24 hodín.
- **CS waiting for decision** — dodacie listy v zbieraní alebo zablokované s položkou čakajúcou na zákaznícky servis, s vekom najstaršieho. Červená po 24 hodinách. Otvorí zoznam položiek čakajúcich na zákaznícky servis.
- **Out of stock on open orders** — SKO bez zásob na akomkoľvek mieste, ktoré sa ešte majú zbierať na otvorenom dodacom liste, a koľko dodacích listov zdržiavajú.
- **Replenishment needed** — zbierkové miesta pod svojím minimom, zatiaľ čo na inom mieste zásoby ešte sú.
- **Overdue deliveries** — prichádzajúce dodávky tovaru, ktoré ešte nedorazili a ich očakávaný dátum už uplynul. Očakávaný dátum je dátum samotnej dodávky, alebo dátum objednávky dodávateľovi, ak dodávka vlastný nemá.
- **Stock errors** — miesta so zápornou zásobou.

## Order pipeline (Priebeh objednávok)

Každý otvorený dodací list podľa fázy:

- **To assign** — v sklade, nepridelený zberačovi.
- **Queued** — pridelený zberačovi, ešte nezačatý.
- **Picking** — práve sa zbiera.
- **Blocked** — zbieranie zastavené (pozri vyššie).
- **Packing** — zozbierané alebo sa balí.
- **Packed** — zabalené, ešte nevyfakturované a nedokončené.
- **Waiting for dispatch** — vyfakturované a pripravené, čaká na prepravcu.
- **Dispatched today** — dnes expedované.

Pod každým počtom vidíte, ako dlho je v tejto fáze **najstarší** z nich. Ak môžete vidieť údaje o predaji, vidíte aj hodnotu objednávok. Náhrady sa počítajú tiež a zobrazujú sa osobitne, pretože ide o skladovú prácu bez hodnoty objednávky.

Táto karta počíta **dodacie listy**, teda to, s čím sklad pracuje. Karta Sales počíta **objednávky**, takže sa obe líšia o náhrady a o objednávky s viac ako jedným dodacím listom. Karta **Stock** zobrazuje len stav zásob; práca skladu je tu.

**Why the delivery notes are waiting** (prečo dodacie listy čakajú) rozdeľuje tie v stave To assign alebo Queued podľa zásob, ktoré sú práve teraz na regáloch: **pickable now** (všetko je k dispozícii), **partly pickable** (čiastočne) alebo **awaiting stock** (nič nie je k dispozícii). Iné objednávky žiadajúce rovnaký tovar sa neodpočítavajú, preto „pickable“ chápte ako „oplatí sa poslať zberača“.

## Next collections and dispatched today (Najbližšie vyzdvihnutia a dnešná expedícia)

Odpočítavanie do príchodu prepravcu vyžaduje časy vyzdvihnutia pre každého prepravcu a sklad. Kým nie sú nastavené, karta to oznamuje.

**Dispatched today, by this time** porovnáva dnešok so včerajškom a s rovnakým dňom v týždni minulý týždeň, pričom každý sa počíta len do aktuálneho času dňa. Zobrazuje dodacie listy, balíky a riadky.

## Time to dispatch (Čas do expedície)

Čas od príchodu dodacieho listu do skladu po jeho expedíciu, len pre objednávky (nie náhrady). Vyberte **Today**, **Last 7 days** alebo **Last 30 days**.

- **Median** a **90th percentile**. Pri vybraných všetkých skladoch je 90. percentil hodnotou najpomalšieho skladu.
- **Same day** — podiel expedovaných v ten istý kalendárny deň, keď dorazili.
- **Within SLA** — vyžaduje úroveň služby pre každý kanál a fulfilment klienta, ktorá zatiaľ nie je nastavená.

**Open delivery notes by age** ukazuje všetko, čo je ešte otvorené, v štyroch skupinách: do 4 hodín, 4 až 24 hodín, 1 až 2 dni a nad 2 dni.

## Pickers and packers (Zberači a baliči)

Len tímové čísla. Čísla za jednotlivých ľudí čakajú na schválenie HR na Slovensku a v Španielsku.

- **Pickers** — každý zozbieraný riadok sa zaznamenáva so zberačom a časom, takže karta počíta dnes zozbierané riadky, **na človeka za hodinu** (hodiny každého človeka sa počítajú od jeho prvého po posledný zber), poslednú hodinu a **short picks** (riadky označené ako nezozbierané).
- **Packers** — dnes zabalené dodacie listy, na človeka za hodinu a za poslednú hodinu.

**Active now** znamená, že niekto zaznamenal prácu za posledných 15 minút. **Idle** znamená, že niekto pracoval za poslednú hodinu, ale nie za posledných 15 minút.

## Goods in (Príjem tovaru)

Počty dodávok, ktoré sú **on the way** (na ceste), **overdue** (meškajú), **to book in** (dorazili alebo boli skontrolované) a **booking in** (práve sa prijímajú). Potom **dock to stock**, medián a 90. percentil času od príchodu po naskladnenie za posledných 90 dní, a koľko otvorených dodávok nemá **no ETA** alebo **no purchase order**.

Tabuľka uvádza otvorené dodávky, najprv tie, ktoré meškajú. **Releases** je počet otvorených dodacích listov čakajúcich na zásoby, ktoré dodávka prináša. Ako prvú naskladnite dodávku, ktorá uvoľní najviac objednávok. Kliknutím na dodávateľa otvoríte dodávku.

## Stock and locations (Zásoby a miesta)

**Empty locations** z celkového počtu miest, miesta so zásobou **not counted in 90 days** (nepočítané 90 dní), **negative stock** a **replenishments due**. Pod nimi **Out of stock with open orders** uvádza SKO, ktoré zdržiavajú najviac dodacích listov, s očakávaným dátumom prichádzajúcej dodávky, ak nejaká je na ceste.

Kapacita miest (ako veľmi je zóna zaplnená) vyžaduje kapacity zadané na miestach, ktoré zatiaľ nie sú zaznamenané.

## Returns (Vratky)

**To process** — vratky prijaté v sklade, ktoré ešte nie sú spracované, s najstaršou. **Customer returns received** a očakávané. Potom jednotky spracované tento mesiac (vrátené na sklad, poškodené, nevrátené) a najčastejšie dôvody vrátenia.

## Sales by organisation (Predaje podľa organizácie)

Zbaliteľná karta úplne dole s jedným riadkom na organizáciu, ako signál pracovnej záťaže:

- **Orders in today** so siedmimi poslednými dňami ako malým stĺpcovým grafom.
- **vs same weekday LY** — oproti rovnakému dňu v týždni minulý rok, do rovnakého času.
- Číslo sa zmení na **červené**, keď je dnešok o viac ako 25 % nad priemerom posledných štyroch rovnakých dní v týždni: znamená to, že treba pridať ruky.

Ak môžete vidieť údaje o predaji, vidíte aj **value in today**, **in warehouse pipeline**, **month to date** (vyfakturované) a **% of month target**, každú organizáciu vo vlastnej mene, so skupinovým riadkom v librách.
