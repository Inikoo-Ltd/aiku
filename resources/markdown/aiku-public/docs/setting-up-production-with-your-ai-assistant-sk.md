---
title: Nastavenie výroby s vaším AI asistentom
summary: Požiadajte AI asistenta, ktorého pripojíte k aiku, aby vytvoril artefakty, suroviny a úlohy, nastavil jednotkové ceny a dal jednému artefaktu, zoznamu alebo celým rodinám ich výrobné kroky a ingrediencie. Zmenu vám vždy najprv ukáže a každú zmenu možno vrátiť späť.
date: 2026-10-08
source_date: 2026-10-08
tags: production, crafts, ai
category: production
---

<aside class="tldr">
Pre toho, kto navrhuje, čo továreň vyrába. Keď vám to administrátor zapne, AI asistent, ktorého pripojíte k aiku, za vás urobí nastavovaciu prácu zo stránok <b>Crafts</b> (remeslá) bežnými slovami. Vie vytvárať a upravovať <b>artefakty</b>, <b>suroviny</b> a <b>výrobné úlohy</b>, nastavovať <b>jednotkové ceny</b> a dávať artefaktom ich <b>receptúru</b>: kroky v poradí, koľko z každého kroku tvorí jeden artefakt, cieľ za hodinu a suroviny, ktoré každý krok používa. Vie to urobiť pre jeden artefakt, zoznam alebo celé rodiny naraz. Vždy vám ukáže, čo sa chystá zmeniť, a uloží to až po vašom súhlase. Každá zmena sa zapíše spolu s vašimi slovami a dá sa vrátiť späť.
</aside>

## Načo to slúži

Ručné nastavenie novej produktovej línie znamená veľa klikania: vytvoriť úlohy, vytvoriť suroviny, vytvoriť artefakty, otvoriť každý z nich, pridať každý krok, pridať každú ingredienciu. S asistentom namiesto toho opíšete výsledok:

> *"Daj všetkým balzamom na pery ACLB tieto kroky: liatie, etiketovanie, balenie. Balenie sa počíta v škatuľkách po šesť."*

Asistent zistí, o ktoré artefakty ide, ukáže vám plán a po vašom potvrdení urobí presne tie isté zmeny, aké by urobili stránky Crafts, podľa rovnakých pravidiel. Otvorené pracovné príkazy (job orders), ktoré ešte neboli prijaté, preberú nové kroky hneď, rovnako ako pri ručnej zmene krokov.

Funguje v každom asistentovi, ktorý sa vie pripojiť k aiku: Claude, ChatGPT alebo podobný. Hovorte s ním v ľubovoľnom jazyku. Kódy ako `ACLB-01`, `POUR` alebo `RAWM-03` zostávajú tak, ako sú.

## Skôr než začnete

**1. Požiadajte administrátora, aby to zapol.** Na vašom používateľskom účte v časti <b>Access</b> (prístup) zapne <b>Can connect AI assistant</b> (môže pripojiť AI asistenta) a potom <b>Can set up artefacts, raw materials and recipes through their AI assistant</b> (môže cez AI asistenta nastavovať artefakty, suroviny a receptúry). Bez druhého prepínača asistent stále vie čítať zostavy, ale nemôže nič meniť vo výrobe. Ak sa ho spýtate, povie vám to.

**2. Musíte byť aj jedným z ľudí, ktorí danú továreň nastavujú.** Samotný prepínač nestačí. S ním asistent mení továreň iba pre:

- **administrátorov skupiny**;
- **administrátorov organizácie**, do ktorej továreň patrí;
- ľudí s výrobnou pozíciou, ktorá môže danú továreň **upravovať**;
- **administrátorov obchodov a predavačov** (shop admins a shopkeepers) obchodov v organizácii továrne (pre awa: AROMA, ACAR, ACFE, ARFE a EZC), pretože predávajú to, čo vyrába.

Ľudia s výrobnou pozíciou, ktorá môže továreň len prezerať, môžu asistenta stále požiadať, aby *ukázal* receptúry a náklady.

**3. Pripojte asistenta k aiku.** Pridajte aiku ako konektor vo svojom asistentovi s adresou `https://app.aiku.io/mcp/aiku` a keď sa to žiada, prihláste sa svojím účtom aiku. Robí sa to len raz.

**4. Poznajte kód svojej továrne.** Každá požiadavka musí povedať, ktorej továrne sa týka, napríklad `awa`. Ak uvediete takú, ktorá neexistuje alebo ku ktorej nemáte prístup, asistent odpovie zoznamom tovární, ku ktorým prístup máte.

## Ako prebieha rozhovor

Každá zmena prebieha v rovnakých troch krokoch:

1. **Požiadate** vlastnými slovami.
2. **Asistent vám ukáže plán**: ktoré artefakty, ktoré kroky v akom poradí, čísla, ingrediencie. Ak niečo chýba (úloha, ktorá ešte neexistuje, kód rodiny, ktorý zodpovedá dvom rodinám), povie vám to skôr, než niečo zmení.
3. **Potvrdíte** vlastnými slovami: *"áno, pokračuj"*. Až potom uloží. Asistent odovzdá vašu požiadavku aiku a uloží sa spolu so zmenou, aby ktokoľvek neskôr videl, o čo sa žiadalo.

Ak poviete *"nie, PACK má byť 0.25"*, plán opraví a opýta sa znova. Nič sa neuloží, kým nesúhlasíte.

<aside class="tip">
Kedykoľvek si nie ste istí, požiadajte asistenta, aby <b>najprv ukázal a až potom zmenil</b>: <i>"Najprv mi ukáž aktuálnu receptúru ACLB-01."</i> Čítanie nikdy nič nemení.
</aside>

## Slová, ktoré aiku používa

Uvidíte ich v odpovediach asistenta.

| Slovo | Čo znamená | Príklad |
|---|---|---|
| **Artefakt** | Niečo, čo továreň vyrába. Každý artefakt patrí k jednému SKO v sklade. | `ACLB-01`, plechovka s balzamom na pery |
| **Rodina** | Skupina podobných artefaktov. | `ACLB`, A&C balzamy na pery |
| **Výrobná úloha** (manufacture task) | Druh práce, ktorý ľudia zaznamenávajú na tabletoch. | `POUR` liatie, `LABEL` etiketovanie (+ viečka), `PACK` balenie |
| **Krok** | Úloha zaradená do receptúry artefaktu, s jej poradím. | krok 1 POUR, krok 2 LABEL, krok 3 PACK |
| **Jednotky na artefakt** (units per artefact) | Koľko z kroku tvorí jeden artefakt. | 1 pre jednu plechovku; 0.1667, keď sa balenie počíta v škatuľkách po šesť |
| **Cieľ za hodinu** (target per hour) | Koľko jednotiek daného kroku má jeden človek urobiť za hodinu. | POUR 216 plechoviek, PACK 11 škatuliek |
| **Surovina** (raw material) | Z čoho sa artefakt vyrába, s jej jednotkou a jednotkovou cenou. | `RAWM-03`, vosk v kilogramoch |
| **Množstvo na artefakt** (quantity per artefact) | Koľko suroviny spotrebuje jeden artefakt, v jednotke suroviny. | 0.0129 kg vosku na plechovku |
| **Materiálové náklady** (materials cost) | Súčet množstvo × jednotková cena každej suroviny v receptúre, na jeden artefakt. | 0.0757 |

### Jednotky na artefakt, krok za krokom

Tablety počítajú prácu v jednotke kroku. Pri balzamoch na pery liači a etiketovači počítajú plechovky a baliči škatuľky po šesť. Jedna plechovka je jedna jednotka POUR a jedna jednotka LABEL, ale len šestina škatuľky, takže:

| Krok | Jednotky na artefakt | Prečo |
|---|---|---|
| POUR | 1 | jedna naliata plechovka |
| LABEL | 1 | jedna oetiketovaná plechovka |
| PACK | 0.1667 | jedna plechovka je 1/6 škatuľky po šesť |

Pracovný príkaz na 600 plechoviek potom žiada 600 POUR, 600 LABEL a 100 PACK. Ukladá sa až šesť desatinných miest, takže 0.1667 sa uloží presne.

**Cieľ za hodinu** je v rovnakej jednotke ako krok: PACK s hodnotou 11 znamená 11 škatuliek za hodinu, nie 11 plechoviek.

## Vyhľadávanie

Čítanie je bezpečné. Používajte ho, koľko chcete.

> *"Ukáž mi receptúru a materiálové náklady ACLB-01 a ACLB-03 v awa."*

Asistent vypíše kroky každého artefaktu s jednotkami na artefakt a cieľom za hodinu, každú surovinu s jej množstvom, jednotkovou cenou a cenou riadku a celkové materiálové náklady.

> *"Ktoré artefakty sú v rodine ACLB?"*

> *"Vypíš výrobné úlohy v awa."*

> *"Nájdi suroviny, ktoré majú v popise 'včelí vosk'."*

> *"Koľko teraz stojí RAWM-03 a je prepojená so SKO?"*

> *"Ukáž mi artefakt ABB-05a: jeho SKO, rodinu, veľkosť dávky a trvanlivosť."*

## Výrobné úlohy

Úlohy sú spoločné pre celú továreň, preto každú vytvorte raz a použite ju v ľubovoľnom počte receptúr.

**Vytvorenie úloh**

> *"Vytvor v awa tri výrobné úlohy: POUR 'Liatie', LABEL 'Etiketovanie (+ viečka)', PACK 'Balenie'."*

**Premenovanie alebo opis úlohy**

> *"Premenuj úlohu LABEL na 'Etiketovanie a viečkovanie' a pridaj popis 'Etiketa sa lepí pred viečkom'."*

**Vypnutie úlohy**

> *"Nastav úlohu OLDPACK ako neaktívnu."*

Neaktívna úloha zostáva v receptúrach, ktoré ju už používajú. Ak ju z nich chcete odstrániť, zmeňte ich kroky (pozri nižšie).

## Suroviny

**Vytvorenie**

> *"Vytvor v awa surovinu RAWM-90: typ stock, popis 'Bambucké maslo', jednotka kilogram, jednotková cena 6.40."*

Typ je jeden z *stock* (zásoba), *consumable* (spotrebný materiál) alebo *intermediate* (polotovar). Jednotka je jedna z *unit* (kus), *pack* (balenie), *carton* (kartón), *liter* (liter) alebo *kilogram* (kilogram). Ceny sú v mene vašej organizácie, za jednotku.

**Zmena ceny**

> *"Bambucké maslo RAWM-90 stojí teraz 6.85 za kilo."*

**Prepojenie suroviny s jej SKO**

> *"Prepoj RAWM-90 so SKO SHEA-25."*

Keď je surovina prepojená so SKO, jej jednotková cena pochádza od preferovaného dodávateľa tohto SKO a sama sleduje každú zmenu dodávateľskej ceny. Asistent potom odmietne nastaviť cenu ručne, pretože by sa prepísala. Namiesto toho zmeňte cenu u dodávateľa, alebo najprv zrušte prepojenie so SKO, ak sa cena naozaj má nastavovať ručne.

**Oprava popisu alebo jednotky**

> *"RAWM-05 je v tabuľke v gramoch, ale v aiku je kilogram. Nechaj kilogram a zmeň popis na 'Nechtíkový olej (kg)'."*

## Artefakty

### Nový artefakt, ktorého SKO už existuje

> *"Vytvor v awa artefakt ACLB-14 'Balzam na pery Mango', rodina ACLB, prepojený so SKO ACLB-14, veľkosť dávky 120, trvanlivosť 730 dní."*

Obchodná jednotka sa vezme zo SKO automaticky, ak má SKO práve jednu.

### Nový artefakt s novým SKO

Artefakt vždy potrebuje svoje SKO. Artefakt vytvorený bez neho zanechá nedorobený artefakt, ktorý neskôr zavadzia tomu skutočnému. Ak SKO ešte neexistuje, požiadajte asistenta, aby ho vytvoril naraz:

> *"Vytvor artefakt ACLB-15 'Balzam na pery Čerešňa', rodina ACLB, a vytvor aj jeho SKO: jedna plechovka na SKO."*

Asistent naraz vytvorí:

- **zásobu** (stock) a jej **obchodnú jednotku** (trade unit), obe s kódom artefaktu, pre celú skupinu;
- **SKO** vo vašej organizácii;
- **artefakt**, prepojený s oboma.

Počet jednotiek na SKO je jediná vec, ktorú od vás potrebuje. Jedna plechovka na SKO je *units 1*. SKO, ktoré je škatuľka so šiestimi plechovkami, je *units 6*.

### Úprava artefaktov

> *"Nastav veľkosť dávky ACLB-01 na 240."*

> *"Trvanlivosť ABB-01a je 540 dní."*

> *"Presuň ACLB-14 do rodiny ACLB-NEW."*

> *"Označ ACLB-08 ako vyradený."*

Stav je jeden z *in_process* (v procese), *active* (aktívny), *dormant* (spiaci) alebo *discontinued* (vyradený).

<aside class="tip">
Pre rovnakú jednoduchú zmenu na mnohých artefaktoch naraz (veľkosť dávky, trvanlivosť, stav, rodina) je často rýchlejší panel v zozname artefaktov. Pozrite <a href="/docs/changing-many-artefacts-at-once-sk">Hromadná zmena viacerých artefaktov naraz</a>. Asistent je najsilnejší pri receptúrach, kde každý artefakt potrebuje niekoľko krokov a ingrediencií.
</aside>

## Receptúry: kroky a ingrediencie

Zmena receptúry vždy **nahradí celú receptúru** artefaktov, ktoré pomenujete:

- kroky, ktoré uvediete, sa pridajú, alebo sa aktualizujú, ak ich artefakt už má;
- kroky, ktoré artefakt má a v zozname **nie sú**, sa **odstránia aj s ich surovinami**;
- každý krok musí uviesť svoje jednotky na artefakt a svoj cieľ za hodinu (alebo "bez cieľa"). Pri krokoch, ktoré nemeníte, asistent skopíruje súčasné hodnoty, takže sa nič náhodou nevynuluje.

### Jeden artefakt

> *"Daj ACLB-01 tieto kroky: 1 POUR, 1 na plechovku, cieľ 216 za hodinu; 2 LABEL, 1 na plechovku, cieľ 236; 3 PACK, počíta sa v škatuľkách po šesť, cieľ 11 škatuliek."*

### Zoznam artefaktov

Toto je požiadavka, ktorou sa nastavila línia balzamov na pery:

> *"Pre ACLB-01, ACLB-03, ACLB-04, ACLB-05, ACLB-06, ACLB-07, ACLB-08_, ACLB-09, ACLB-10_, ACLB-12_ a ACLB-13_: odstráň krok PROD Making a pridaj POUR 1 na artefakt pri 216 za hodinu, LABEL 1 pri 236, PACK 0.1667 pri 11 škatuľkách po šesť."*

Asistent ukáže jedenásť artefaktov s ich novými krokmi:

| Artefakt | Krok 1 | Krok 2 | Krok 3 |
|---|---|---|---|
| ACLB-01 | POUR ×1 @ 216/h | LABEL ×1 @ 236/h | PACK ×0.1667 @ 11/h |
| ACLB-03 | POUR ×1 @ 216/h | LABEL ×1 @ 236/h | PACK ×0.1667 @ 11/h |
| … | … | … | … |
| ACLB-13_ | POUR ×1 @ 216/h | LABEL ×1 @ 236/h | PACK ×0.1667 @ 11/h |

Po vašom áno uloží všetkých jedenásť naraz. PROD nemusíte pomenovať: zmizne, pretože nie je v novom zozname. Pozrite však varovanie o surovinách nižšie.

### Celé rodiny naraz

Nemusíte vypisovať artefakty. Pomenujte rodinu:

> *"Daj každému artefaktu v rodine ABB tieto kroky: MIX 1 pri 40 za hodinu, MOULD 1 pri 120, WRAP 1 pri 200, PACK počítaný v škatuľkách po dvanásť pri 9."*

Asistent vezme každý artefakt v rodine, ktorý **nie je vyradený**, a pred uložením vám povie, koľko ich je a ktoré.

**Viac rodín**

> *"Rovnaké kroky pre rodiny ABB a ABBL."*

**Rodina okrem niektorých artefaktov**

> *"Celá rodina ACLB okrem ACLB-13_, ktorý sa vyrába inak."*

**Rodina plus niekoľko artefaktov odinakiaľ**

> *"Rodina ABB a tiež ABBTH-01 a ABBTH-02."*

**Rodiny, ktoré majú rovnaký kód**

Dve rodiny môžu mať rovnaký kód. V awa je *ACLB* aj maloobchodné balzamy na pery, aj testery. Asistent sa potom zastaví a opýta sa, ktorú myslíte, a ukáže každú s jej názvom, počtom artefaktov a kódom prvého artefaktu, napríklad:

- `agnes-cat-aclb`: A&C balzamy na pery, 11 artefaktov začínajúcich ACLB-01
- `washes-lotions-aclb`: A&C balzamy na pery, 11 artefaktov začínajúcich TACLB-01

Odpovedzte tou, ktorú chcete (*"tá, čo začína ACLB-01"*), a pokračuje so správnou rodinou. Kým je to nejasné, nič sa nezmení.

Naraz sa dá spracovať až 200 artefaktov. Pri viacerých robte jednu rodinu po druhej.

### Kroky s ingredienciami

Suroviny patria ku kroku: vosk sa používa pri liatí, škatuľka pri balení. Každému kroku dajte jeho ingrediencie s množstvom **na artefakt**:

> *"Pre rodinu ACLB: POUR používa RAWM-03 0.0129 kg a RAWM-05 0.0016 kg; LABEL používa BOKG-03 0.0003 a FLKG-06 0.0002; PACK používa CST-427 0.1667 (jedna škatuľka na šesť plechoviek). Ponechaj rovnaké jednotky a ciele."*

Asistent ukáže materiálové náklady, ktoré bude mať každý artefakt, aby ste ich mohli skontrolovať pred súhlasom.

### Podobné artefakty s jednou odlišnou ingredienciou

Príchute, vône a farby zvyčajne zdieľajú všetky kroky a líšia sa v jednej ingrediencii. Povedzte, čo je spoločné, a potom čo sa líši:

> *"Celá rodina ACLB: POUR používa vosk RAWM-03 0.0129 a olej RAWM-05 0.0016. Okrem ACLB-01, ktorý používa RAWM-06 0.0006 namiesto RAWM-05, a ACLB-07, ktorý používa RAWM-07 0.0016 namiesto RAWM-05. LABEL a PACK ako predtým."*

Asistent dá všetkým spoločný zoznam. Pre ACLB-01 a ACLB-07 použije ich vlastný úplný zoznam pre tento krok: vosk plus ich vlastný olej. Plán, ktorý ukáže, má jeden riadok na artefakt, takže uvidíte, že ACLB-01 má RAWM-06 a nie RAWM-05.

<aside class="tip">
Keď sú rozdiely veľké (iný počet krokov, iné úlohy), urobte tieto artefakty v samostatnej požiadavke, alebo ich z požiadavky na rodinu vynechajte pomocou <i>okrem</i> (except).
</aside>

### Zmena jedného čísla

Keďže sa receptúra vždy nahrádza ako celok, aj malá zmena posiela všetky kroky. Asistent to urobí za vás: prečíta súčasnú receptúru a zmení len to, o čo ste požiadali.

> *"Zvýš cieľ PACK v rodine ACLB na 12 škatuliek za hodinu, všetko ostatné nechaj tak."*

> *"V ACLB-05 používa POUR 0.0135 RAWM-03 namiesto 0.0129."*

Skontrolujte plán, ktorý ukáže. Každé iné číslo by malo byť rovnaké ako predtým.

### Pridanie kroku doprostred

> *"Pridaj krok CURE medzi POUR a LABEL pre celú rodinu ACLB: 1 na plechovku, bez cieľa."*

Asistent kroky prečísluje: 1 POUR, 2 CURE, 3 LABEL, 4 PACK.

### Odstránenie kroku

> *"Vyber LABEL z ACLB-12_, zvyšok nechaj."*

LABEL a jeho suroviny sa z ACLB-12_ odstránia. Samotná úloha zostáva v továrni pre iné receptúry.

### Nahradenie kroku, ktorý má ingrediencie

<aside class="warning">
Keď sa krok odstráni, odstránia sa aj suroviny na ňom. Mnoho artefaktov prenesených zo starého systému nesie celý zoznam ingrediencií na jedinom kroku <b>PROD Making</b>. V awa napríklad sedem balzamov ACLB má šesť surovín na PROD. Nahradenie PROD krokmi POUR, LABEL a PACK bez toho, aby ste povedali, kam týchto šesť ide, by tieto artefakty nechalo <b>bez ingrediencií</b>, takže pri prijatí ich pracovných príkazov by sa už neodpisovali zásoby.
</aside>

Pred nahradením takého kroku sa opýtajte:

> *"Ukáž mi suroviny na kroku PROD rodiny ACLB."*

Potom v tej istej požiadavke ako nové kroky povedzte, kam ktorá ide:

> *"Nahraď PROD v rodine ACLB krokmi POUR, LABEL a PACK ako vyššie. Presuň suroviny: RAWM-03, RAWM-05 a RAWM-06 do POUR, BOKG-03 a FLKG-06 do LABEL, CST-427 do PACK, rovnaké množstvá."*

Ak naozaj chcete ingrediencie zahodiť a doplniť ich neskôr, povedzte to: *"zahoď ingrediencie, receptúru doplním neskôr"*. Ak si to rozmyslíte, zmenu možno vrátiť späť (pozri nižšie).

## Vrátenie zmeny späť

Každá zmena, ktorú asistent urobí, sa zapíše: kto o ňu požiadal, kedy, vaše presné slová a ako to bolo predtým a potom.

> *"Vráť poslednú zmenu, ktorú si urobil."*

> *"Čo som dnes zmenil vo výrobe?"*

> *"Vráť zmenu receptúry v rodine ABB z dnešného rána."*

Asistent vypíše zmeny a po vašom potvrdení vráti veci tak, ako boli.

Vrátenie sa odmietne v dvoch prípadoch:

- **Od vtedy sa to zmenilo znova.** Ak niekto ten istý artefakt potom upravil, ručne alebo cez asistenta, vrátenie sa zastaví, aby neprepísalo jeho prácu. Pozrite sa na to a opravte to ručne.
- **Zmena niečo vytvorila.** Nový artefakt, surovina, úloha alebo SKO sa vrátením nevymaže, pretože na nich už môžu visieť pracovné príkazy, receptúry a zásoby. Nastavte ho namiesto toho ako vyradený alebo neaktívny.

Vrátiť môžete zmeny urobené v továrňach, ktoré smiete nastavovať. Administrátori vidia a môžu vrátiť každú zmenu zo záznamu zmien AI (<b>AI changes</b>).

## Ako sa pýtať dobre

| Namiesto | Povedzte | Prečo |
|---|---|---|
| *"nastav balzamy na pery"* | *"nastav rodinu ACLB v awa"* | kód nenecháva nič na hádanie |
| *"balenie je 6"* | *"balenie sa počíta v škatuľkách po šesť"* | 6 na artefakt a 1/6 na artefakt sú veľmi odlišné veci |
| *"pridaj liatie"* | *"pridaj POUR ako krok 1, 1 na plechovku, 216 za hodinu, ostatné kroky nechaj"* | receptúra sa nahrádza ako celok |
| *"použi vosk"* | *"použi RAWM-03, 0.0129 kg na plechovku"* | množstvo je na artefakt, v jednotke suroviny |
| *"áno"* na dlhý plán, ktorý ste nečítali | prečítajte tabuľku a potom *"áno"* | plán je to, čo sa uloží |

Dobré návyky:

- **Najprv čítajte, potom píšte.** *"Najprv mi ukáž receptúru"* nestojí nič.
- **Jedna rodina na požiadavku**, keď rodiny potrebujú rôzne kroky.
- **Skontrolujte počet.** Ak asistent povie 22 artefaktov a vy ste čakali 11, kód pravdepodobne zodpovedal aj testerom.
- **Skontrolujte materiálové náklady**, ktoré ukáže. Cena desaťkrát vyššia než u susedov zvyčajne znamená množstvo v gramoch tam, kde je jednotka kilogram.

## Keď asistent povie nie

| Čo povie | Čo robiť |
|---|---|
| Nastavovanie výroby nie je pre tohto používateľa povolené | Požiadajte administrátora, aby na vašom účte zapol nastavenie výroby. |
| Tento používateľ nemôže nastavovať výrobu … | Nie ste jedným z ľudí, ktorí túto továreň nastavujú (pozri *Skôr než začnete*). Požiadajte administrátora. |
| Neexistuje úloha / surovina … | Najprv ju vytvorte: *"vytvor úlohu CURE"*, potom požiadavku zopakujte. |
| Viac ako jedna rodina má kód … | Vyberte rodinu zo zoznamu, ktorý ukáže, podľa jej prvého artefaktu alebo názvu. |
| Nový artefakt potrebuje svoje SKO | Pomenujte existujúce SKO, alebo požiadajte, aby SKO vytvoril spolu s artefaktom, a povedzte, koľko jednotiek je na SKO. |
| … je prepojená so SKO, takže jej jednotková cena pochádza od preferovaného dodávateľa | Zmeňte cenu dodávateľského produktu, alebo najprv zrušte prepojenie so SKO. |
| Surovina je v kroku … uvedená dvakrát | Uveďte ju raz so spočítanými množstvami. |
| To je … artefaktov; urobte najviac 200 na jedno volanie | Robte jednu rodinu po druhej. |
| Toto sa po zmene AI znova zmenilo | Niekto to odvtedy upravil. Opravte to ručne na stránkach Crafts. |

## Čo asistent nerobí

- **Nemaže** artefakty, suroviny ani úlohy. Namiesto toho ich vyraďte alebo deaktivujte.
- Nemení **platové stupne** (pay bands) ani odmeňovacie stupne továrne. Ciele za hodinu sú základ, ktorý tieto stupne násobia.
- Nevytvára **pracovné príkazy** (job orders) ani nemení, čo sa vyrába dnes. Mení to, čo budú žiadať nasledujúce a otvorené pracovné príkazy.
- Nedotýka sa **testerov** ani iných artefaktov, ktoré ste nepomenovali alebo nezahrnuli cez rodinu.
- Nevidí ani nemôže meniť továrne, ku ktorým nemáte prístup.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zapnúť to niekomu (administrátori):</b> <b>Sysadmin → Users</b> → otvorte používateľa → <b>Edit</b> → <b>Access</b> → zapnite <b>Can connect AI assistant</b> a potom <b>Can set up artefacts, raw materials and recipes through their AI assistant</b>.</li>
<li><b>Zobraziť každú zmenu, ktorú asistent urobil (administrátori):</b> <b>Sysadmin</b> → box <b>AI insights</b> → <b>All queries & per-user stats</b> → <b>AI changes</b>. Filtrujte podľa typu <b>Artefact recipe</b> alebo <b>Artefact, raw material or task</b>.</li>
<li><b>Skontrolovať receptúru ručne:</b> vaša organizácia → <b>Factory</b> (továreň) → <b>Crafts</b> (remeslá) → <b>All artefacts</b> (všetky artefakty) → otvorte artefakt → záložka <b>Manufacture tasks</b> (výrobné úlohy).</li>
<li><b>Rovnaké zmeny bez asistenta:</b> <a href="/docs/changing-many-artefacts-at-once-sk">Hromadná zmena viacerých artefaktov naraz</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Prepínač <b>Can set up artefacts, raw materials and recipes through their AI assistant</b> na vašom účte.</li>
<li>Na akúkoľvek zmenu: administrátor skupiny, administrátor organizácie, výrobná pozícia, ktorá môže danú továreň upravovať, alebo administrátor obchodu či predavač v jednom z obchodov organizácie továrne.</li>
<li>Len na prezeranie receptúr a nákladov: výrobná pozícia, ktorá môže danú továreň prezerať.</li>
</ul>
</aside>
