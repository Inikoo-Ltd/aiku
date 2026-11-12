---
title: Ako pomôcť zákazníkom nájsť produkty na vašom webe
summary: Dva prepínače v nastaveniach webu pomáhajú obchodným zákazníkom zistiť, čo majú naskladniť. Poradca pre sortiment odpovedá na otázku napísanú do vyhľadávacieho poľa produktmi a dôvodom ku každému a Keep exploring pridáva pod každé vyhľadávanie súvisiace vyhľadávania, rodiny a produkty.
date: 2026-11-12
source_date: 2026-11-12
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
- **Assistant name** (názov asistenta) je meno, ktoré zákazníci vidia, najviac 30 znakov. Ak ostane prázdne, web použije názov nastavený pre celú skupinu, ktorým je „Merlin", pokiaľ ho niekto nezmenil v **Sysadmin → Settings → AI shopping assistant**. Zmeňte ho tam a poradca sa premenuje na každom webe, ktorý nemá vlastný názov.
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

### Panel s návrhmi

Prihlásení zákazníci majú poradcu aj vo svojom účte: kartu **Opýtajte sa asistenta: <meno>** na úvodnej stránke účtu vedľa svojich údajov a noviniek cez WhatsApp, kde sa môžu hneď opýtať, a jej odkaz na návrhy otvorí poradcu priamo v účte s rovnakou konverzáciou, históriou a panelom ako stránka poradcu v obchode. Na stránke poradcu ešte predtým, ako sa na niečo opýtajú, panel s nadpisom "Postrehy od <meno>" ukáže až štyri karty produktov, všetky skladom a za ceny daného zákazníka:

- **Chýba vo vašom sortimente**: čo obchody ako ich kupujú najviac a oni to nikdy nekúpili, z rodín, z ktorých už nakupujú. Zákazník ešte bez objednávok namiesto toho vidí **Najpredávanejšie v obchodoch, ako je váš**.
- **Zabudnuté obľúbené**: produkty, ktoré objednávali, ale rok ich neobjednali.
- **Štartovacie balenia**: až tri rodiny, z ktorých nikdy nenakupovali, každá s tromi alebo štyrmi najpredávanejšími produktmi a tlačidlom **Pridať balenie do košíka**.
- **Novinky pre váš obchod**: novinky, najprv tie, ktoré už kupujú obchody ako ich.

Prázdna karta sa nezobrazí. Každý zoznam má **Pridať všetko do košíka**. Keď nepoznáme typ obchodu zákazníka, panel ho vyzve, aby ho povedal poradcovi, ktorý ho uloží. Zaznamená sa, ktoré karty si pozrel, na ktoré produkty klikol a čo pridal do košíka, jeden záznam panelu na zákazníka a deň.

Poradca si tiež pamätá trvalé fakty, ktoré mu zákazník povie o svojom obchode, napríklad že je kvetinárstvo, že predáva aj online alebo že nechce kadidlo, a každú ďalšiu konverzáciu začína s ich znalosťou. Na paneli sa zobrazujú ako "<meno> si pamätá" s odkazom **Zabudnúť**, ktorý ich vymaže, a pri anonymizácii zákazníka sa zmažú. Nikdy si neukladá čísla. Konverzáciu môže otvoriť krátkym osobným dotykom z toho, čo vie, napríklad krstným menom zákazníka alebo dennou dobou u neho; pri účtoch zamestnancov to môže byť ľahký žart.

### Čo vie a čo nikdy nepovie

Poradca odpovedá zo štyroch vecí: katalóg obchodu, najpredávanejšie produkty za posledných 12 mesiacov medzi zákazníkmi podobnými tomu, kto sa pýta, produkty, ktoré tento zákazník ešte nevyskúšal, a produkty, ktoré kedysi objednával a prestal. „Zákazníci podobní tomu, kto sa pýta" znamená najprv rovnaký typ obchodu v tej istej oblasti, potom tá istá krajina, potom všetci.

Ak zákazník povie, aký obchod prevádzkuje, poradca si to uloží ako jeho **Shop type** (typ obchodu). Na **CRM → Customers → Shop types** sa to počíta v stĺpci **Told us** (povedal nám). Typy obchodov nájdete v sprievodcovi <a href="/docs/looking-after-customers-sk">Starostlivosť o zákazníkov</a>.

Je postavený tak, aby dodržiaval tieto pravidlá:

- Navrhuje len produkty, ktoré sú na webe živé a skladom.
- Vo svojich vetách nepíše ceny, zľavy ani stavy skladu. Cena je na karte produktu, ako všade inde na webe.
- Nič nehovorí o tom, čo kupuje ktorýkoľvek iný zákazník, a nezobrazujú sa počty kupujúcich ani predajné čísla.
- Odpovedá v jazyku obchodu.

### Lepšie ceny a veľké objednávky

Keď zákazník žiada lepšiu cenu alebo zľavu, alebo povie, že je niečo príliš drahé, poradca nevyjednáva. Ukáže lacnejšie produkty takého druhu, aký hľadal, a pridá tri veci vypočítané z našich údajov, nikdy nie umelou inteligenciou:

- či je zákazník členom programu **Gold Reward** a dokedy, alebo ako sa ním stane (vyfakturovaná objednávka z neho urobí člena na 30 dní);
- amnestiu členských cien, keď v obchode prebieha;
- výšku objednávky, od ktorej náš tím volá, aby sa dohodli lepšie podmienky.

Potom sa opýta, koľko plánuje objednať. Keď plánovaná objednávka dosiahne túto výšku, poradca ju hneď odovzdá, ešte predtým, ako zákazník súhlasí s hovorom, takže sa veľká objednávka nikdy nestratí. Ponúkne hovor, opýta sa, či volať na telefón z účtu alebo na iný a kedy sa to hodí, a odpoveď pridá k tej istej príležitosti. Nikdy nepovie, kto zavolá, a nikdy nesľúbi zľavu.

Každá príležitosť je úloha s vysokou prioritou pre tím **zákazníckeho servisu** organizácie, prepojená so zákazníkom, s plánovanou sumou, telefónom, najvhodnejším časom a odkazom na konverzáciu. Celý tím dostane upozornenie v aiku. Manažéri obchodných príležitostí organizácie sú na úlohe a dostanú upozornenie emailom, cez Slack a v prehliadači (každý si to môže zmeniť vo svojom profile). Kým si úlohu nikto nevzal, pripomenie sa im po 4 hodinách a potom raz denne, v pracovné dni medzi 09:00 a 18:00 v časovom pásme organizácie.

Nastavujú to administrátori systému v **Organisations**: otvorte organizáciu, stlačte **Edit** a prejdite do časti **Sales leads**.

- **Sales leads manager** (manažér obchodných príležitostí): kto dostane každú príležitosť. Ak je prázdne, dostanú ju vedúci zákazníckeho servisu a ak žiadni nie sú, všetci administrátori organizácie. Nastavte manažéra, aby príležitosti dostal správny človek, a nie všetci administrátori.
- **Big order from (EUR)** (veľká objednávka od): plánovaná výška objednávky, od ktorej sa ponúka hovor, prepočítaná na menu každého obchodu. Ak je prázdne, je to 2 000 EUR, zhruba najväčšia zo 100 objednávok.
- **Sales leads Slack channel** (kanál v Slacku): kam sa posiela každá príležitosť a jej pripomienky. Bot aiku musí byť členom kanála.

### Kedy bude produkt opäť na sklade

Keď sa zákazník pýta, kedy sa vypredaný produkt vráti, poradca sa pozrie, čo je objednané. Obdobie uvedie len vtedy, keď je dátum spoľahlivý: dodávka na ceste s očakávaným dátumom, ktorý ešte nenastal, alebo objednávka z posledných 9 mesiacov, ktorej očakávaný príchod ešte nenastal. Povie „očakáva sa medzi 1. a 15. decembrom“, nikdy presný deň ani množstvo. Mnohé objednávky nemajú očakávaný dátum a niektoré staré neboli nikdy uzavreté, takže spoľahlivý dátum často nie je.

Vtedy to poradca povie a ponúkne, že sa zákazníkovi ozve náš nákupný tím. Len ak zákazník súhlasí, otázka sa stane úlohou pre ľudí z nákupu v organizácii, prepojenou s produktom, so zákazníkom, jeho otázkou a tým, čo ukazujú naše objednávky. Dostanú upozornenie emailom, cez Slack a v prehliadači a pripomienku, kým si úlohu nikto nevezme, rovnako ako pri veľkých objednávkach.

Ak chcete odpovedať, napíšte odpoveď pre zákazníka do poznámky pri označení úlohy ako hotovej (alebo ako poslednú správu v úlohe pred jej označením). Preloží sa do jazyka zákazníka, pridá sa do jeho konverzácie s poradcom a pošle sa mu emailom upozornenia na chat obchodu s odkazom na konverzáciu. V obchode, kde tento email nie je zapnutý, dostane zákaznícky servis úlohu poslať odpoveď. Ak sa úloha označí ako hotová bez odpovede, nič sa nepošle a úloha to uvedie.

Nastavujú to administrátori systému v **Organisations**: otvorte organizáciu, stlačte **Edit** a prejdite do časti **Restock questions**. Prepínač **Ask purchasing when there is no reliable date** je predvolene zapnutý; vypnutý znamená, že poradca odpovedá len z našich objednávok. **Restock questions go to** určuje, kto ich dostane; ak je prázdne, dostanú ich nákupcovia organizácie.

### Všeobecné otázky a odovzdanie človeku

Okrem produktov asistent odpovedá na otázky o nákupe v obchode: platba, doručenie, vrátenie tovaru, objednávanie a zákaznícky účet. Odpovedá z vlastných stránok obchodu s pravidlami, z nastavení obchodu a z poznámok zákazníckeho servisu a overených faktov, ktoré sú uložené v nastaveniach chatu obchodu, a uvedie odkaz na stránku, ktorú použil. Nikdy neodpovedá zo všeobecných znalostí a číslo, napríklad dodaciu lehotu alebo poplatok, zopakuje len vtedy, keď je napísané v texte, ktorý použil.

Keď si nie je istý alebo sa otázka týka vlastnej objednávky, faktúry či vrátenia peňazí zákazníka, nehádá. Povie to a ponúkne, že sa opýta tímu, jedným ťuknutím na tlačidlo pod odpoveďou. Na stránke asistenta je navyše stále odkaz **Talk to a person** (hovoriť s človekom).

Čo sa stane po ťuknutí, závisí od okamihu a od webu:

- V čase chatu, keď je niekto k dispozícii, sa otvorí chat v chatovom okne webu.
- Inak otázka príde ako emailová konverzácia. Zákazníkovi sa povie, kedy sa tím vráti a že odpoveď príde emailom, a jeho odpoveď na tento email pristane v tej istej konverzácii.
- Obchod bez pripojenej emailovej schránky dostane webový chat, na ktorý sa odpovie neskôr.
- Web bez chatu aj bez emailovej schránky žiadneho človeka neponúka.

Pre chatový tím: tieto chaty prichádzajú do bežnej schránky a upozornení, označené menom asistenta. Filter **From the AI assistant** (od AI asistenta) zobrazí len ich. Konverzácia asistenta so zákazníkom je navrchu chatu, takže vidíte, čo už bolo povedané. Ak bola odpoveď asistenta nesprávna, označte ju tam ako **Wrong answer** (nesprávna odpoveď). Tieto chaty sa nezapočítavajú do čísel o čase odpovede chatu a pokrytí AI; hlásia sa v reporte **AI Assistant**, ako je vysvetlené nižšie.

Aby asistent odpovedal na viac vecí, otvorte report a pozrite sa na **Questions the assistant could not answer** (otázky, na ktoré asistent nevedel odpovedať). Pridajte v nastaveniach chatu obchodu poznámku, ktorá na niektorú z nich odpovedá, a asistent na ňu nabudúce odpovie, približne do hodiny.

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

Navrchu si vyberiete dátumy a môžete zúžiť čísla podľa jazyka, podľa toho, odkiaľ sa konverzácia otvorila (vyhľadávacie pole, stránka profilu, chatové okno alebo panel poradcu pre sortiment; čísla panelu sa ukážu, len keď ho vyberiete), podľa typu obchodu a podľa krajiny. Každé číslo sa porovnáva s obdobím rovnakej dĺžky bezprostredne predtým, ktoré pomenúva riadok pod filtrami. Konverzácie zamestnaneckých účtov sa vynechávajú.

### Záložka Overview

- **Conversations** (konverzácie), s počtom zákazníkov a koľkí z nich sa vrátili.
- **Orders after being shown** (objednávky po zobrazení) a **Revenue of the products shown** (tržby z zobrazených produktov): objednávka sa počíta, keď zákazník objednal produkt, ktorý mu asistent zobrazil, do 7 dní od jeho zobrazenia. Sčítajú sa len riadky týchto produktov, nikdy celá objednávka, a každý riadok sa počíta raz. Najpredávanejšie produkty a bývalé obľúbené sa aj tak znovu objednávajú, preto to čítajte ako „objednané po zobrazení", nie ako dôkaz, že objednávku spôsobil asistent.
- Pod oboma údajmi číslo **strict** (prísne) počíta len riadky, ktoré zákazník pridal do košíka priamo od asistenta. Je to najnižšie, čím môže skutočné číslo byť.
- **Conversion** (konverzia) je podiel konverzácií, po ktorých nasledovala takáto objednávka. Vynecháva konverzácie za posledných 7 dní, pretože sa z nich ešte môžu stať objednávky. Oranžová poznámka hovorí, od ktorého dňa čísla ešte nie sú konečné.
- **AI cost** (náklady na AI), celkovo, na konverziu a ako podiel na tržbách.
- **AI budget this month** (rozpočet na AI tento mesiac) ukazuje pri každom webe so zapnutým poradcom, koľko sa minulo z jeho mesačného rozpočtu. Pruh sa od 80% zafarbí na oranžovo a keď je rozpočet vyčerpaný, na červeno s **Paused until** (pozastavené do) a dátumom.
- **Passed on to the team** (odovzdané tímu) uvádza veľké objednávky a otázky na doplnenie zásob za obdobie, najprv otvorené: zákazníka, plánovanú objednávku alebo produkt, či žiadal hovor alebo ako sa k nemu dostala odpoveď, stav úlohy a kto si ju vzal. Čo si nikto nevzal, je oranžové a ukazuje, koľko pripomienok odišlo.

**Funnel** (lievik) sleduje konverzácie krok za krokom: produkty zobrazené, rozkliknuté, pridané do košíka a objednané, každý s podielom na predchádzajúcom kroku a na všetkých konverzáciách. **Ordered** (objednané) môže byť vyššie ako **Added to basket** (pridané do košíka), pretože počíta aj produkty, ktoré sa do košíka dostali inou cestou. Graf vedľa neho ukazuje rovnaké kroky po dňoch, alebo po týždňoch pri dlhom období.

**Where customers leave** (kde zákazníci odchádzajú) počíta konverzácie podľa počtu položených otázok. **Engagement and value** (zapojenie a hodnota) udáva priemerný čas a počet správ konverzácie, priemernú objednávku zákazníkov, ktorí asistenta použili, v porovnaní s ostatnými zákazníkmi, a rodiny, ktoré zákazník objednal prvýkrát po tom, čo mu ich asistent zobrazil. **Families suggested** (navrhnuté rodiny) uvádza najčastejšie zobrazené rodiny s tým, koľko konverzácií ich rozkliklo, pridalo a objednalo.

**Search on the website** (vyhľadávanie na webe) pokrýva Keep exploring: vyhľadávania za obdobie, tie bez výsledkov, koľko vyhľadávaní dostalo návrhy a koľko zákazníkov ich videlo, a koľko vyhľadávaní bez výsledkov nasledovalo kliknutie na návrh. Pri každom druhu návrhu ukazuje, ako často sa ponúkol, kliknutia a mieru. Riadi sa len dátumami, webom a jazykom.

**From the search box to a question** (od vyhľadávacieho poľa k otázke) ukazuje, koľko vyhľadávaní sa uskutočnilo s ponúkaným tlačidlom **Ask**, v koľkých z nich sa zobrazila ponuka opýtať sa a koľko konverzácií sa otvorilo z vyhľadávacieho poľa, pričom pod tým sú otázky odmietnuté limitmi alebo rozpočtom.

Hlavné čísla a kroky lievika sú odkazy: otvoria konverzácie, ktoré za nimi stoja.

**Answer ratings by customers** počíta odpovede, ktoré zákazníci označili palcom pod odpoveďou ako užitočné alebo neužitočné, v porovnaní s predchádzajúcim obdobím. Obe čísla sú odkazy na konverzácie, ktoré za nimi stoja.

**Customer challenges to act on** je zoznam úloh pre nákup, produkt a webmasterov. Každú noc AI prečíta každú konverzáciu, v ktorej dve hodiny nepribudla správa, a zapíše, čo zákazníkovi bránilo v objednávke: produkt, ktorý nepredávame, niečo vypredané, cena, minimálna objednávka alebo veľkosť kartónu, doprava alebo doručenie, problém s webom alebo čokoľvek iné, čo zákazník opísal. Obyčajná otázka nie je prekážka. Štítky počítajú konverzácie každého druhu a otvárajú ich. Tabuľka uvádza 20 najčastejšie spomínaných vecí, pri každej počet konverzácií, príklad vlastnými slovami zákazníka a odkaz **Open** na najnovšiu konverzáciu. Konverzácie o tej istej veci v rôznych jazykoch sú spojené do jedného riadku. Dnešná konverzácia sa prečíta v noci; riadok nad tabuľkou hovorí, koľko ich čaká. Vyhľadávania bez výsledkov sú v **Search on the website**.

### Všeobecné otázky a odovzdania

V záložke **Overview** časť **General questions and passing to a person** (všeobecné otázky a odovzdanie človeku) ukazuje, ako asistent zvláda otázky o obchode a odovzdania človeku. Počítajú sa len konverzácie, ktoré vznikli po zavedení tejto funkcie. Každé číslo sa porovnáva s predchádzajúcim obdobím a každé je odkazom na konverzácie, ktoré za ním stoja.

- **General questions** (všeobecné otázky) počíta odpovede o obchode (pravidlá, doručenie, vrátenie tovaru, účet) a **Answered by the assistant** (zodpovedal asistent) tie, na ktoré odpovedal sám, bez ponuky človeka.
- **Passed to a person** (odovzdané človeku) počíta zákazníkov, ktorí ťukli na ponuku opýtať sa tímu. **Hand-over rate** (miera odovzdania) je tento počet na jednu všeobecnú otázku, pričom pod ním je podiel konverzácií, ktoré skončili odovzdaním.
- **AI cost of these answers** (náklady na AI za tieto odpovede) sú náklady len na všeobecné odpovede. **Answers marked wrong** (odpovede označené ako nesprávne) počíta odpovede, ktoré chatový tím označil ako nesprávne.
- **Answered from** (odpovedané z) ukazuje, z čoho odpovede vychádzali: stránky s pravidlami, nastavenia obchodu, návody, poznámky, overené fakty alebo naučené pravidlá, opisy rodín alebo naše vlastné údaje.
- **Why a person was offered** (prečo sa ponúkol človek) porovnáva pre každý dôvod (nebol si istý odpoveďou, otázka o vlastnej objednávke, zákazník požiadal o človeka), koľkokrát sa človek ponúkol a koľko zákazníkov sa odovzdalo.
- **How it reached the team** (ako sa to dostalo k tímu) rozdeľuje odovzdania na živý chat, emailový chat s odpoveďou neskôr a webový chat s odpoveďou neskôr.
- **First reply from a person** (prvá odpoveď od človeka) ukazuje v čase chatu a mimo neho, koľko bolo odovzdaní, na koľko odpovedal človek a mediánovú čakaciu dobu na túto prvú odpoveď. Mimo času chatu čakanie zahŕňa noc.
- **After passing to a person** (po odovzdaní človeku) ukazuje, na koľko sa odpovedalo, koľko chatov je uzavretých a koľko zákazníkov zadalo objednávku do 7 dní od odovzdania. Posúdiť sa dajú len odovzdania staršie ako 7 dní a číslo uvádza, koľko ich je.
- **Questions the assistant could not answer** (otázky, na ktoré asistent nevedel odpovedať) uvádza, na čo sa zákazníci pýtali, keď si asistent nebol istý, s počtom opýtaní, jazykmi a posledným časom, a odkazom **Open** (otvoriť) na najnovšiu konverzáciu. Otázky sa zoskupujú len vtedy, keď sú slová rovnaké, takže dva spôsoby, ako sa opýtať na to isté, sa zobrazia ako dva riadky.

V záložke **Conversations** stĺpec ukazuje, ktoré konverzácie boli odovzdané človeku, a prepis pod každou odpoveďou ukazuje, odkiaľ pochádza, kedy sa ponúkol človek a do akého chatu sa odovzdala.

### Záložka Conversations

Jeden riadok na konverzáciu: kedy začala, zákazník, odkiaľ sa otvorila, prvá otázka, počet správ, zobrazené, rozkliknuté a pridané produkty, či nasledovala objednávka a na akú sumu, a náklady na AI. Hľadajte podľa zákazníka alebo podľa slov otázky.

Kliknite na prvú otázku a prečítajte si konverzáciu. Pod každou odpoveďou vidíte produkty, ktoré zobrazila, s uvedeným dôvodom, ktoré zákazník rozklikol, pridal a objednal, ponúknuté ďalšie otázky a hodnotenie zákazníka. Prekážky nájdené v konverzácii sú uvedené hore. Konverzácie sú len pre zamestnancov: otvoriť ich môžu len ľudia, ktorí vidia web v aiku.

## Týždenný súhrn a ako dlho sa konverzácie uchovávajú

Každý pondelok ráno (britského času) príde e-mail ľuďom vybraným v **Sysadmin → Settings → AI shopping assistant → Weekly summary to**. Pokrýva pondelok až nedeľu predchádzajúceho týždňa za všetky weby v porovnaní s týždňom pred ním: konverzácie, zákazníkov, objednávky a tržby po zobrazení produktov, náklady na AI, hodnotenia odpovedí, odovzdania človeku, najčastejšie prekážky zákazníkov a vyhľadávania bez výsledkov, s odkazom na prehľad. Citáty zákazníkov v e-maile nie sú; prečítate si ich v prehľade. Ak nie je nikto vybraný, e-mail sa neodošle.

Pod políčkom, kam píšu, zákazníci vidia, že asistent je AI a môže sa mýliť, že naši zamestnanci môžu konverzácie čítať a že nemajú zdieľať platobné údaje.

To, čo zákazníci napísali a čo dostali ako odpoveď, sa uchováva 24 mesiacov od poslednej správy konverzácie, alebo toľko mesiacov, koľko je nastavené v **Keep conversation texts for (months)** v tých istých nastaveniach. Potom sa texty každú noc vymažú spolu s príkladmi citátov pri prekážkach a otázkami odmietnutými pre limity. Čísla zostávajú, takže prehľad tieto konverzácie, ich produkty, objednávky a náklady naďalej počíta. Anonymizácia zákazníka vymaže jeho konverzácie okamžite. Vymazaná konverzácia to uvádza v hornej časti svojej stránky.
