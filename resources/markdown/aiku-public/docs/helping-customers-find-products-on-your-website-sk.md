---
title: Ako pomôcť zákazníkom nájsť produkty na vašom webe
summary: Dva prepínače v nastaveniach webu pomáhajú obchodným zákazníkom zistiť, čo majú naskladniť. Poradca pre sortiment odpovedá na otázku napísanú do vyhľadávacieho poľa produktmi a dôvodom ku každému a Keep exploring pridáva pod každé vyhľadávanie súvisiace vyhľadávania, rodiny a produkty.
date: 2026-11-10
source_date: 2026-11-10
tags: website, search, shop, crm
category: marketing
---

<aside class="tldr">
Otvorte <b>Website</b> v menu obchodu a kliknite na ikonu posuvníkov medzi <b>Export</b> a <b>Workshop</b>. Dôležité sú tu dve sekcie. <b>AI shopping assistant</b> (AI nákupný asistent) zapína poradcu pre sortiment: prihlásený obchodný zákazník sa môže spýtať, čo jeho obchod potrebuje, a dostane produkty s jednoriadkovým dôvodom ku každému. <b>Search page</b> (stránka vyhľadávania) zapína <b>Keep exploring</b> (Pokračujte v objavovaní): súvisiace vyhľadávania, rodiny, ktoré sa dobre predávajú spolu, najpredávanejšie produkty, novinky a zabudnuté obľúbené produkty zákazníka pod výsledkami vyhľadávania, aj keď vyhľadávanie nič nenájde. Obe sú vypnuté, kým ich nezapnete, pre každý web zvlášť. Ak chcete vidieť, čo prinášajú, otvorte v menu webu <b>Analytics</b> (analytika) a potom <b>AI Assistant</b> (AI asistent).
</aside>

## Zapnutie

1. Prejdite na **Website** v menu obchodu.
2. Kliknite na ikonu posuvníkov vpravo hore, medzi **Export** a **Workshop**. Otvoria sa nastavenia webu.
3. V zozname vľavo otvorte **AI shopping assistant** alebo **Search page**.
4. Prepnite prepínač a stlačte ikonu uloženia vedľa neho.

Zmena sa na webe prejaví približne do desiatich minút. Každý web má vlastné prepínače, takže môžete najprv vyskúšať jeden web.

## Poradca pre sortiment

Poradca pre sortiment je asistent, ktorý navrhuje, aké produkty by mal predajca naskladniť. Zobrazuje sa len zákazníkom, ktorí sú prihlásení na webe veľkoobchodného obchodu. Návštevníci, ktorí nie sú prihlásení, ho nevidia.

V sekcii **AI shopping assistant** sú tri polia:

- **AI shopping assistant** ho zapína pre tento web.
- **Assistant name** (názov asistenta) je meno, ktoré zákazníci vidia, najviac 30 znakov. Ak ostane prázdne, web použije názov nastavený pre celú skupinu, ktorým je „Aura", pokiaľ ho niekto nezmenil v **Sysadmin → Settings → AI shopping assistant**. Zmeňte ho tam a poradca sa premenuje na každom webe, ktorý nemá vlastný názov.
- **Monthly AI budget** (mesačný rozpočet na AI) je najviac, koľko môže web minúť na odpovede v kalendárnom mesiaci, v amerických dolároch. Ak ostane prázdny, je to US$30. Malé „i" vedľa neho ukazuje, koľko sa tento mesiac minulo.

Skôr než ho prvýkrát zapnete pre obchod, požiadajte help desk, aby preň pripravil katalóg obchodu. Poradca nachádza produkty podľa významu, nielen podľa slov v názve, a to si vyžaduje jednorazovú prípravu produktov obchodu.

### Čo vidí zákazník

- Tlačidlo **Ask** (Spýtať sa) s menom asistenta vo vnútri vyhľadávacieho poľa.
- Keď vyhľadávanie vyzerá ako otázka alebo potreba, ponuka spýtať sa poradcu: v rozbaľovacom zozname vyhľadávania a navrchu stránky s výsledkami. Vyhľadávanie sa považuje za otázku, ak má štyri slová alebo viac, alebo otáznik. Rovnaká ponuka sa zobrazí aj vtedy, keď vyhľadávanie nič nenájde.
- Stránka konverzácie. Zákazník opíše svoj obchod a čo potrebuje a poradca odpovie jednou krátkou vetou a dvoma záložkami produktov, **Must-haves** (nevyhnutné produkty) a **Add-ons to your range** (doplnky k vášmu sortimentu). Produkty sú obvyklé karty produktov, takže ich zákazník odtiaľ môže pridať do košíka, a každá karta nesie jednoriadkový dôvod, napríklad „Popular with shops like yours in your area" (Obľúbené u obchodov ako váš vo vašej oblasti), „You used to order this" (Toto ste objednávali) alebo „Matches what you asked for" (Zodpovedá tomu, na čo ste sa pýtali).
- Odpoveď zobrazí najviac 8 produktov v každej záložke a najviac 12, keď zákazník požiada o viac alebo o širší výber.
- **Add all to basket** (Pridať všetko do košíka) pod každou odpoveďou vloží do košíka jeden kus z každého zobrazeného produktu a tie, ktoré tam už sú, nechá tak. Zákazník o to môže požiadať aj slovami („pridaj mi ich do košíka") a poradca urobí to isté, len s produktmi, ktoré v danej konverzácii zobrazil. Množstvá sa menia na kartách produktov alebo v košíku.
- Zákazník môže poradcu požiadať o vymazanie konverzácie. Tá potom zmizne z jeho obrazovky a už ju nemôže otvoriť. Záznam zostáva v aiku: v záložke **Conversations** má stav **Cleared by customer** (Vymazané zákazníkom) a prepis ukazuje, čo poradca pridal do košíka a že zákazník požiadal o vymazanie.
- Poradca nájde produkt alebo celú rodinu podľa kódu, aj keď je napísaný voľne („tiba 04", „bbskt"), a povie, keď je žiadaný produkt vypredaný.
- Na otázku na najlacnejšie alebo najdrahšie produkty ich zobrazí zoradené podľa ceny. Ceny sám nepíše: sú na kartách.
- Pri otázkach na Vianoce a darčeky pridá produkty, ktoré sa predávali najmä od septembra do Vianoc minulého roka, označené „Sold well last Christmas" (Minulé Vianoce sa dobre predávalo), či už majú Vianoce v názve alebo nie.
- Na otázku, ako rýchlo sa objednávky odosielajú, odpovie skutočným údajom obchodu za posledných 30 dní. Na otázku na cenu dopravy uvedie dopravu aktuálneho košíka zákazníka, ako ju vypočítal košík. Obe vety píše aiku z údajov, nie AI.
- Pod odpoveďou sú tlačidlá s ďalšími otázkami, pole na napísanie ďalšej otázky a **Start a new conversation** (Začať novú konverzáciu).

Bežné vyhľadávania podľa slova alebo kódu produktu sa nemenia a nespomaľujú.

### Čo vie a čo nikdy nepovie

Poradca odpovedá zo štyroch vecí: katalóg obchodu, najpredávanejšie produkty za posledných 12 mesiacov medzi zákazníkmi podobnými tomu, kto sa pýta, produkty, ktoré tento zákazník ešte nevyskúšal, a produkty, ktoré kedysi objednával a prestal. „Zákazníci podobní tomu, kto sa pýta" znamená najprv rovnaký typ obchodu v tej istej oblasti, potom tá istá krajina, potom všetci.

Ak zákazník povie, aký obchod prevádzkuje, poradca si to uloží ako jeho **Shop type** (typ obchodu). Na **CRM → Customers → Shop types** sa to počíta v stĺpci **Told us** (povedal nám). Typy obchodov nájdete v sprievodcovi <a href="/docs/looking-after-customers-sk">Starostlivosť o zákazníkov</a>.

Je postavený tak, aby dodržiaval tieto pravidlá:

- Navrhuje len produkty, ktoré sú na webe živé a skladom.
- Vo svojich vetách nepíše ceny, zľavy ani stavy skladu. Cena je na karte produktu, ako všade inde na webe.
- Nič nehovorí o tom, čo kupuje ktorýkoľvek iný zákazník, a nezobrazujú sa počty kupujúcich ani predajné čísla.
- Odpovedá v jazyku obchodu.

### Limity a čo sa zaznamenáva

Každý zákazník sa môže opýtať 6 otázok za minútu, 40 za hodinu a 120 za deň. Odpoveď trvá niekoľko sekúnd a stránka na ňu čaká. Každá odpoveď je platené použitie AI, preto poradcu zapnite tam, kde chcete, aby ho zákazníci používali.

Keď náklady na AI za mesiac dosiahnu **Monthly AI budget**, poradca sa na tomto webe pozastaví do prvého dňa nasledujúceho mesiaca: tlačidlo **Ask** sa už neponúka a nové otázky sa neprijímajú. Odpoveď, ktorá je už na ceste, ešte dorazí. Zvýšte rozpočet a poradca sa vráti do minúty či dvoch. Ak sa v tej istej chvíli pýta viac zákazníkov, výdavky môžu rozpočet mierne prekročiť.

Každá konverzácia sa uchováva, spolu s produktmi, ktoré poradca zobrazil, tými, na ktoré zákazník klikol, a tými, ktoré pridal do košíka do 24 hodín od ich zobrazenia. Zaznamenáva sa aj zvýšenie množstva produktu, ktorý poradca zobrazil, nezávisle od pridaní. Každé volanie AI sa zaznamenáva s jeho nákladmi a časom trvania, rovnako ako každá otázka odmietnutá limitmi vyššie alebo rozpočtom, spolu s dôvodom.

## Keep exploring

**Search page → Keep exploring suggestions on the search page** (návrhy Keep exploring na stránke vyhľadávania) pridáva blok pod výsledky každého vyhľadávania na webe, pre návštevníkov aj prihlásených zákazníkov. Keď vyhľadávanie nič nenájde, blok sa nachádza hneď pod „We couldn't find any suitable results" (Nenašli sme žiadne vhodné výsledky), takže zákazník má vždy kam ísť ďalej.

Zhora nadol zobrazuje, každú časť len vtedy, keď je čo ukázať:

- **Did you mean** (Mali ste na mysli), len pri vyhľadávaní bez výsledkov, keď je slovo blízkym pravopisom vyhľadávania, ktoré urobilo mnoho zákazníkov a ktoré našlo produkty. „beeswax" ponúkne „bees wax".
- **Related searches** (Súvisiace vyhľadávania): čo iní zákazníci hľadali v tej istej návšteve ako toto vyhľadávanie. Keď žiadne nie sú, nadpis znie **Popular searches** (Populárne vyhľadávania) a zobrazuje najčastejšie hľadané výrazy webu.
- **Sells well with** (Dobre sa predáva s): rodiny nastavené ako súvisiace s rodinami, ktoré vyhľadávanie našlo.
- Záložky produktov: **Best sellers** (Najpredávanejšie), **New arrivals** (Novinky) a **Your forgotten favourites** (Vaše zabudnuté obľúbené).

### Odkiaľ každý návrh pochádza

- **Súvisiace a populárne vyhľadávania** pochádzajú z vyhľadávaní za posledných 180 dní na tomto webe, ktoré našli produkty. Počítajú sa len vyhľadávania prihlásených zákazníkov: výraz potrebuje 5 rôznych zákazníkov, aby bol populárny, a 3 rôznych zákazníkov, aby bol súvisiaci s iným. Výrazy s číslicou alebo znakom @ sa nikdy nezobrazujú, takže kódy produktov, čísla objednávok a e-mailové adresy ostanú von.
- **Best sellers** sú produkty, ktoré za posledných 12 mesiacov kúpilo najviac zákazníkov. Prihlásený zákazník dostane najpredávanejšie medzi zákazníkmi podobnými jemu a záložka sa volá **Best sellers for shops like yours** (Najpredávanejšie pre obchody ako váš) len vtedy, keď každý produkt v nej pochádza z obchodov jeho vlastného typu obchodu. Návštevníci dostanú najpredávanejšie z celého obchodu.
- **New arrivals** sú produkty v prvých 30 dňoch na webe, od najnovších.
- **Your forgotten favourites** sa zobrazuje len prihlásenému zákazníkovi: produkty, ktoré už objednával a za posledných 12 mesiacov ich neobjednal.

Každý zobrazený produkt je v danej chvíli živý a skladom. Predajné čísla za najpredávanejšími a zabudnutými obľúbenými sa prepočítavajú každú noc, populárne vyhľadávania raz denne a návrhy pre jeden hľadaný výraz sa uchovávajú hodinu.

Blok sa načíta po výsledkoch vyhľadávania, takže samotné vyhľadávanie je rovnako rýchle ako predtým.

### Čo sa zaznamenáva

Každé vyhľadávanie zaznamenáva, ktoré návrhy sa ponúkli a či sa blok dostal do zorného poľa na obrazovke zákazníka. Zaznamenáva sa každé kliknutie, na výsledok alebo na návrh, spolu s tým, o aký druh návrhu išlo (did you mean, súvisiace alebo populárne vyhľadávanie, rodina, best sellers, new arrivals alebo forgotten favourites) a či bolo na produkte alebo na tlačidle jeho karty, najviac 20 kliknutí na vyhľadávanie. Každé vyhľadávanie zaznamenáva aj to, či sa tlačidlo **Ask** ponúkalo danému zákazníkovi. Vyhľadávania zamestnaneckých účtov sa nezaznamenávajú vôbec, preto ak chcete vidieť vlastné kliknutia, testujte so zákazníckym účtom, ktorý nie je zamestnanecký.

## Sledovanie výkonu

Prejdite na **Website** v menu obchodu, potom na **Analytics** (analytika) v hornom menu a na **AI Assistant** (AI asistent). Stránka hlási jeden web. Ľudia, ktorí spravujú všetky weby skupiny, ju nájdu aj v **WebOps → AI Assistant**, pre všetky weby spolu a s filtrom webu.

Navrchu si vyberiete dátumy a môžete zúžiť čísla podľa jazyka, podľa toho, odkiaľ sa konverzácia otvorila (vyhľadávacie pole, stránka profilu alebo chatové okno), podľa typu obchodu a podľa krajiny. Každé číslo sa porovnáva s obdobím rovnakej dĺžky bezprostredne predtým, ktoré pomenúva riadok pod filtrami. Konverzácie zamestnaneckých účtov sa vynechávajú.

### Záložka Overview

- **Conversations** (konverzácie), s počtom zákazníkov a koľkí z nich sa vrátili.
- **Orders after being shown** (objednávky po zobrazení) a **Revenue of the products shown** (tržby z zobrazených produktov): objednávka sa počíta, keď zákazník objednal produkt, ktorý mu asistent zobrazil, do 7 dní od jeho zobrazenia. Sčítajú sa len riadky týchto produktov, nikdy celá objednávka, a každý riadok sa počíta raz. Najpredávanejšie produkty a bývalé obľúbené sa aj tak znovu objednávajú, preto to čítajte ako „objednané po zobrazení", nie ako dôkaz, že objednávku spôsobil asistent.
- Pod oboma údajmi číslo **strict** (prísne) počíta len riadky, ktoré zákazník pridal do košíka priamo od asistenta. Je to najnižšie, čím môže skutočné číslo byť.
- **Conversion** (konverzia) je podiel konverzácií, po ktorých nasledovala takáto objednávka. Vynecháva konverzácie za posledných 7 dní, pretože sa z nich ešte môžu stať objednávky. Oranžová poznámka hovorí, od ktorého dňa čísla ešte nie sú konečné.
- **AI cost** (náklady na AI), celkovo, na konverziu a ako podiel na tržbách.
- **AI budget this month** (rozpočet na AI tento mesiac) ukazuje pri každom webe so zapnutým poradcom, koľko sa minulo z jeho mesačného rozpočtu. Pruh sa od 80% zafarbí na oranžovo a keď je rozpočet vyčerpaný, na červeno s **Paused until** (pozastavené do) a dátumom.

**Funnel** (lievik) sleduje konverzácie krok za krokom: produkty zobrazené, rozkliknuté, pridané do košíka a objednané, každý s podielom na predchádzajúcom kroku a na všetkých konverzáciách. **Ordered** (objednané) môže byť vyššie ako **Added to basket** (pridané do košíka), pretože počíta aj produkty, ktoré sa do košíka dostali inou cestou. Graf vedľa neho ukazuje rovnaké kroky po dňoch, alebo po týždňoch pri dlhom období.

**Where customers leave** (kde zákazníci odchádzajú) počíta konverzácie podľa počtu položených otázok. **Engagement and value** (zapojenie a hodnota) udáva priemerný čas a počet správ konverzácie, priemernú objednávku zákazníkov, ktorí asistenta použili, v porovnaní s ostatnými zákazníkmi, a rodiny, ktoré zákazník objednal prvýkrát po tom, čo mu ich asistent zobrazil. **Families suggested** (navrhnuté rodiny) uvádza najčastejšie zobrazené rodiny s tým, koľko konverzácií ich rozkliklo, pridalo a objednalo.

**Search on the website** (vyhľadávanie na webe) pokrýva Keep exploring: vyhľadávania za obdobie, tie bez výsledkov, koľko vyhľadávaní dostalo návrhy a koľko zákazníkov ich videlo, a koľko vyhľadávaní bez výsledkov nasledovalo kliknutie na návrh. Pri každom druhu návrhu ukazuje, ako často sa ponúkol, kliknutia a mieru. Riadi sa len dátumami, webom a jazykom.

**From the search box to a question** (od vyhľadávacieho poľa k otázke) ukazuje, koľko vyhľadávaní sa uskutočnilo s ponúkaným tlačidlom **Ask**, v koľkých z nich sa zobrazila ponuka opýtať sa a koľko konverzácií sa otvorilo z vyhľadávacieho poľa, pričom pod tým sú otázky odmietnuté limitmi alebo rozpočtom.

Hlavné čísla a kroky lievika sú odkazy: otvoria konverzácie, ktoré za nimi stoja.

### Záložka Conversations

Jeden riadok na konverzáciu: kedy začala, zákazník, odkiaľ sa otvorila, prvá otázka, počet správ, zobrazené, rozkliknuté a pridané produkty, či nasledovala objednávka a na akú sumu, a náklady na AI. Hľadajte podľa zákazníka alebo podľa slov otázky.

Kliknite na prvú otázku a prečítajte si konverzáciu. Pod každou odpoveďou vidíte produkty, ktoré zobrazila, s uvedeným dôvodom, ktoré zákazník rozklikol, pridal a objednal, a ponúknuté ďalšie otázky. Konverzácie sú len pre zamestnancov: otvoriť ich môžu len ľudia, ktorí vidia web v aiku.
