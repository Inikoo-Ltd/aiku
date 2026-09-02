---
title: Nastavenie dochádzkového zariadenia
summary: Vytvorte dochádzkové zariadenie na pár kliknutí, dajte jeho kiosk odkaz na tablet alebo jeho QR kódy na stenu, a vedzte, kde každé pípnutie skončí.
date: 2026-08-31
source_date: 2026-08-31
tags: hr, clocking
category: hr
series: Clocking in and out
order: 2
---

<aside class="tldr">
Vytvorenie zariadenia si vyžiada názov, typ a pracovisko — to je celý formulár. Skutočné nastavenie prichádza v ďalšom kroku: pre zariadenia typu <b>PIN</b>, <b>Barcode Scanner</b> a <b>Camera QR</b> vygenerujete <b>kiosk odkaz</b> a otvoríte ho na tablete pri dverách; pre zariadenie typu <b>QR Code</b> vygenerujete tlačiteľné QR kódy a nalepíte ich na stenu. Každé pípnutie sa potom zobrazí pod zariadením, pracoviskom aj na vlastnom výkaze pracovného času zamestnanca. Nie ste si istí, ktorý typ chcete? Prečítajte si najprv <a href="/docs/types-of-clocking-machines-sk">the types guide</a>.
</aside>

## Skôr než začnete

Dochádzkové zariadenie vždy patrí k **pracovisku** — fyzickému miestu, ktorého dvere stráži. Ak vaša organizácia ešte nemá žiadne pracovisko, vytvorte ho najprv pod **Human Resources → Working place**; formulár na zariadenie vám to nedovolí preskočiť. Ak existuje presne jedno pracovisko, aiku vám ho predvyplní.

## Vytvorenie zariadenia

Choďte na **Human Resources → Clocking machines** a stlačte tlačidlo **Clocking machine** na vytvorenie. Formulár sa pýta na tri veci:

- **Name** — ako ho váš tím volá: "Warehouse door", "Office front", "Packing hall tablet". Názvy musia byť v rámci organizácie jedinečné.
- **Type** — vyberte jeden zo štyroch bežných typov: **QR Code**, **PIN**, **Barcode Scanner** alebo **Camera QR Scanner**.
- **Workplace** — pracovisko, kde sa nachádza.

Uložte, a zariadenie sa objaví v zozname, už aktívne. To je naozaj všetko — identifikačné kódy zariadenia sa vygenerujú za vás na pozadí.

## Pripojenie zariadenia

Čo nasleduje ďalej, závisí od zvoleného typu.

### PIN, Barcode Scanner a Camera QR: kiosk odkaz

Tieto tri typy bežia na zdieľanom tablete a tablet sa k nim dostane cez **kiosk odkaz**. V riadku zariadenia v zozname dochádzkových zariadení stlačte malé tlačidlo **tablet** a potom **Generate link**. aiku vytvorí súkromnú webovú adresu len pre toto zariadenie; **Copy** ju a otvorte v prehliadači na tablete, ktorý necháte pri dverách.

Kiosk stránka nepotrebuje žiadne prihlásenie — tajomstvo je v samotnom odkaze — a zobrazuje presne jednu vec:

- **klávesnicu** na zariadení typu PIN,
- **pole na skenovanie** na zariadení Barcode Scanner (pripojte čítačku čiarových kódov k tabletu),
- **pohľad kamery** na zariadení Camera QR.

Zamestnanci prídu, napíšu alebo naskenujú a hneď vidia potvrdenie o príchode/odchode. Dve poznámky k údržbe: tlačidlo **Regenerate** nahradí odkaz — starý prestane fungovať okamžite, čo je presne to, čo chcete, ak sa tablet stratí — a stĺpec **Kiosk** v zozname na prvý pohľad ukazuje, či je kiosk metóda daného zariadenia zapnutá.

PIN, čiarový kód a QR kód každého zamestnanca sú na jeho vlastnej stránke **Employee Clocking** v aiku, takže nemusíte nič tlačiť ani rozdávať, pokiaľ nechcete kartičky s odznakom.

### Zariadenia QR Code: vytlačiť a nalepiť

Zariadenie typu QR Code nemá vôbec žiadny tablet — prácu vykoná stena. Otvorte zariadenie a použite **Generate QR code**: dajte kódu **label** ("Main entrance", "Fire door") a aiku vytvorí QR obrázok, ktorý môžete vytlačiť. Vygenerujte toľko kódov, koľko máte dverí; zoznam QR kódov zariadenia zobrazuje každý z nich s jeho označením a prepínačom **active**.

Zamestnanec naskenuje vytlačený kód kamerou telefónu, ocitne sa na svojej stránke Employee Clocking v aiku a odpípne príchod alebo odchod ako on sám. Ak je vytlačený kód kompromitovaný — odfotený, zdieľaný, vzatý domov — môžete ho vypnúť alebo **regenerate**, a starý výtlačok slušne odmietne: *"This QR Code is no longer active."*

Na stránke **edit** zariadenia získava zariadenie typu QR Code dodatočné nastavenia: **Allow Coordinates Matching** zapína kontrolu polohy, **map picker** umožňuje umiestniť špendlík na vašu budovu a **Radius (meters)** určuje, ako blízko musí byť telefón. Zariadenie typu QR Code dostáva aj záložku **Clocking policies**, kde možno nastaviť pravidlá onsite/remote/hybrid pre ľudí, ktorí ho používajú.

## Kde sa pípnutia zobrazujú

Každé pípnutie príchodu a odchodu, bez ohľadu na metódu, sa stane záznamom **clocking**, ktorý môžete vidieť z troch uhlov:

- **Zariadenie:** otvorte zariadenie → záložka **Clockings** — všetko, čo toto zariadenie zaznamenalo.
- **Pracovisko:** otvorte pracovisko → jeho zoznam pípnutí — všetko zaznamenané na mieste, naprieč všetkými jeho zariadeniami.
- **Osoba:** pípnutie sa spáruje vo **výkaze pracovného času** zamestnanca, odkiaľ pochádzajú odpracované hodiny.

HR môže tiež otvoriť ktorékoľvek jednotlivé pípnutie na kontrolu alebo opravu — užitočné pri klasickom rozhovore "zabudol som odpípnuť odchod".

## Bežná správa

Stránka **edit** zariadenia obsahuje priebežné ovládacie prvky: premenujte ho, prepnite jeho **status** medzi Connected a Disconnected (odpojené zariadenie zostáva v zozname, ale odpočíva) a zapnite či vypnite jednotlivé dochádzkové metódy. Zariadenie, ktoré dosluhuje, možno zo zoznamu vymazať bežným potvrdením — jeho zaznamenané pípnutia zostávajú na výkazoch pracovného času, pretože história je história.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Vytvoriť zariadenie:</b> vaša organizácia → <b>Human Resources → Clocking machines</b> → tlačidlo <b>Clocking machine</b>. Alebo z pracoviska: <b>Working place</b> → vaše pracovisko → <b>Clocking machines</b> → create.</li>
<li><b>Získať odkaz na tablet:</b> v riadku zariadenia tlačidlo <b>tablet</b> → <b>Generate link</b> → <b>Copy</b> (len zariadenia PIN, Barcode a Camera QR).</li>
<li><b>Tlač QR kódov:</b> otvorte zariadenie QR Code → <b>Generate QR code</b>, zadajte label; spravujte označenia a prepínač active v jeho zozname QR kódov.</li>
<li><b>Kontrola polohy:</b> stránka <b>edit</b> zariadenia → QR Settings → <b>Allow Coordinates Matching</b>, špendlík na mape a rádius.</li>
<li><b>Zobraziť pípnutia:</b> záložka <b>Clockings</b> zariadenia, zoznam pípnutí pracoviska, alebo výkaz pracovného času zamestnanca.</li>
</ul>
<strong>Aké práva potrebujete</strong>
<ul>
<li>Vytváranie, úprava a generovanie kiosk odkazov alebo QR kódov vyžaduje prístup Human Resources <b>edit</b>; zobrazenie zariadení a pípnutí vyžaduje prístup Human Resources <b>view</b>.</li>
</ul>
</aside>
