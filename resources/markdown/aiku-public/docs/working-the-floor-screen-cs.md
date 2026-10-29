---
title: Práce s obrazovkou dílny
summary: Průvodce pro řemeslníka - obrazovka Moje úkoly, seznam úkolů, který se sám aktualizuje, START a DONE, a co dělat, když vyrobíte míň, než bylo požadováno, a jak si dát pauzu.
date: 2026-10-23
source_date: 2026-10-23
tags: production, floor, artisan
category: production
series: Ordering from partners
order: 9
---

<aside class="tldr">
Pro ty, kdo vyrábějí. <b>Factory → Jobs</b> (Továrna → Úkoly) je celý váš den na jedné obrazovce: vlevo úkoly určené vám, uprostřed ten, na kterém právě pracujete. Stiskněte <b>START</b>, vyrobte to, zadejte kolik kusů, stiskněte <b>DONE</b> (Hotovo). Pokud jste vyrobili míň, než bylo požadováno, obrazovka se zeptá jen na jednu věc: pozastavit a pokračovat později, úkol uzavřít a zbytek přenést do nového, nebo ho dokončit tady. Nic dalšího se nevyplňuje.
</aside>

## Obrazovka

Stránka je uspořádaná jako doručená pošta.

**Vlevo, vždy vidět:** seznam. Nahoře dva počítadla - kolik kusů jste dnes vyrobili a kolik úkolů jste dnes dokončili. Pod nimi **Your jobs** (Vaše úkoly), práce určená vám: celé úkoly s vaším jménem a jednotlivé kroky, které vám vedoucí výroby přidělil v denním plánu (viz [Plánování výrobního dne](/docs/planning-the-production-day-cs)). Úkol může mít výrobu u jednoho člověka a balení u jiného; každý vidí jen svůj krok. Pokud vám to vaše pozice umožňuje vybírat i z volných úkolů, následuje sekce **Open jobs** (Volné úkoly). Dole **Finished today** (Dnes dokončeno), co jste už uzavřeli.

**Vpravo:** to, co máte právě vybráno. Než začnete, jsou to detaily úkolu s velkým tlačítkem **START**. Jakmile začnete, je to pracovní karta s hodinami.

Seznam se nemusí obnovovat ručně. Když vám plánovač přiřadí úkol, když kolega začne pracovat na volném úkolu, když vy sami nějaký uzavřete, seznam se změní sám, na všech obrazovkách zobrazujících danou továrnu.

## Jeden řádek v seznamu

Každý řádek je jeden úkol na jednom výrobním příkazu:

| Řádek | Význam |
| --- | --- |
| Kód | výrobek, který se má vyrobit - SKO-01 |
| Název | jeho název |
| Úkol · výrobní příkaz | daný krok (Production, Labelling...) a odkaz na výrobní příkaz |
| 0/25 | vyrobeno zatím / požadováno |

Řádek se zelenou šipkou ▶ a časem je ten, na kterém právě pracujete, a čas ukazuje, kdy jste začali. Oranžová poznámka pod řádkem znamená, že úkol čeká na směs, nebo že ho už má otevřený někdo jiný.

## Práce na úkolu

1. Klepněte na řádek. Vpravo se zobrazí detaily.
2. Stiskněte **START**. Spustí se hodiny a řádek dostane zelenou značku.
3. Vyrobte to.
4. Zadejte počet kusů, které jste vyrobili, do pole **Quantity made** (Vyrobené množství). Pokud jste mistr nebo výše, je tam i pole **Rejected** (Zmetky) pro kusy, které neprošly kontrolou.
5. Stiskněte **DONE**.

Vyrobili jste přesně tolik, kolik bylo požadováno? Tím je hotovo. Úkol se uzavře, výrobní příkaz je dokončen a sklad dostane informaci, že má co uložit - viz [Ukládání hotové výroby](/docs/putting-away-finished-production-cs).

## Kroky počítané v baleních

Některé kroky, hlavně balení, se počítají v baleních a ne v jednotlivých kusech: tácek s 8 koupelovými bombami se počítá jako 1. Obrazovka pak jantarově ukáže **Counted in PACKS of 8** (Počítá se v BALENÍCH po 8) a pole se jmenuje **Packs of 8 made** (Vyrobená balení po 8). Zadejte, kolik plných balení jste naplnili: u 56 bomb na 7 táccích zadejte 7, ne 56. Pod polem obrazovka ukáže, co vaše číslo znamená, *7 packs of 8 = 56 × ABB-06a*, a potvrzení to ukáže znovu. Přijímají se jen celá balení; jedinou výjimkou je přesný počet, který ještě zbývá, a ten vyplníte ťuknutím na velké číslo. Když musí manažer schválit nadvýrobu, okno před schválením ukáže přebytek v baleních i v kusech.

## Když vyrobíte míň, než bylo požadováno

Zadejte skutečný počet a stiskněte **DONE**. Vstupní řádek se nahradí krátkým panelem: *19 hotovo · 6 zbývá* a čtyřmi tlačítky.

- **Pause** (Pozastavit) - stejná směs, pokračujete později. Úkol zůstává otevřený se stejným označením a stejným kódem šarže a počet pokračuje od 19. Použijte, když den skončí dřív než směs.
- **Close at 19 and move remaining 6 to new job …** (Uzavřít na 19 a zbývajících 6 přesunout do nového úkolu …) - úkol se uzavře na 19 a zbývajících 6 přejde do nového výrobního příkazu s dalším písmenem (z 0127 se stane 0127a) a vlastním kódem šarže. Použijte, když zbytek bude nová směs. Obrazovka chce potvrzení tlačítkem **Yes, close and move** (Ano, uzavřít a přesunout), protože uzavřený úkol už nelze znovu otevřít.
- **Job finished** (Úkol dokončen) - úkol se uzavře na 19 a tím to končí. Plánovač uvidí výrobní příkaz, který žádal 25 a dostal 19.
- **Back** (Zpět) - změnit číslo.

Úkol přesunutý do nového ukazuje na své kartě **Continued from 0127** (Pokračování z 0127) a ten, ze kterého vzešel, ukazuje **Continued in 0127a** (Pokračuje v 0127a). Nový úkol zůstává u stejných lidí a ve stejném denním plánu.

## Když vyrobíte víc, než bylo požadováno

Zadejte skutečné číslo a stiskněte **DONE**. Obrazovka upozorní *18 above target, a manager must authorise it* (18 nad cíl, musí to schválit vedoucí) a otevře **Manager authorisation required for overproduction**.

- **Scan the badge** - vedoucí nebo mistr přiloží ke kameře svůj osobní QR kód (stejný, kterým se píchá při příchodu). Přijme se, jakmile je načten. **Switch camera** přepíná mezi přední a zadní kamerou.
- **Use manager PIN** - když ho kamera nenačte, vedoucí místo toho zadá svůj docházkový PIN.

Schválit může jen někdo, kdo vede tuto výrobní halu, a nikdy pro vlastní práci. Po pěti chybných kódech nebo PINech okno 15 minut odmítá.

Po schválení úkol naroste na to, co se skutečně vyrobilo: sklad naskladní všechno, co žádná objednávka nechtěla, jde na sklad, a suroviny se odečtou za celou dávku. Další kroky téhož úkolu narostou také, aby další člověk mohl dokončit všechno. Stránka výrobní zakázky ukazuje, kdo vyrobil přebytek, kdo ho schválil, jak a kdy.

Výjimkou je práce přesunutá na další den. Tam žádný manažer nemůže schválit, aby krok překročil to, co na té části vyrobil předchozí krok: pokud Making skončil na 138, Labelling se zastaví na 138 a obrazovka uvede druhou část práce (například JOaroma-0152a), kde se zbytek vyrobil a kde se má zapsat.

Zadali jste špatné číslo, například kusy tam, kde se žádala balení? Manažer otevře kartu **Performance** (Výkon), najde záznam pod pracovníkem a stiskne **Correct quantity** (Opravit množství). Záznam lze jen snížit. Úkol se vrátí na to, co se opravdu vyrobilo, schválená nadvýroba se sníží spolu s ním a mzda za tento záznam se přepočítá. Funguje to, dokud sklad nezačne úkol naskladňovat.

## Jedna dávka pro několik řádků

Někdy jedna dávka slouží více úkolům. Například 100 bochníků HCS-48 a 100 krájených bochníků SLHCS-48 se stejnou vůní se míchá, odlévá a formuje společně jako 200 bochníků.

**Spojení (vedoucí).** Klepněte na jeden z řádků na obrazovce haly. Pod jeho detaily **Combine Production with other lines into one batch** vypíše ostatní otevřené řádky čekající na stejný krok. Zaškrtněte ty, které patří do stejné dávky, a stiskněte **Combine**. Řádek, který je už spojený, hotový nebo se na něm pracuje, se nenabízí.

**Práce na ní (řemeslníci).** Řádky se teď zobrazují jako jeden řádek s 🔗 a kódy dohromady, *HCS-48 + SLHCS-48*, a celkem, *0/200*. Detaily vypíší každý řádek s jeho zakázkou. Stiskněte jednou **START**, vyrobte dávku, zadejte celkové vyrobené množství - 200 - a jednou stiskněte **DONE**.

**Co s tím Aiku udělá.** Celek se rozdělí mezi řádky podle toho, kolik každý ještě měl vyrobit, v celých kusech: 100 pro HCS-48, 100 pro SLHCS-48. Čas se rozdělí stejně. Každý řádek pak pokračuje svými dalšími kroky - u jednoho balení do fólie, u druhého krájení a pak balení. Stránka zakázky ukazuje krok jako *One batch with* (jedna dávka s) druhým řádkem.

**Odměna a cíle** se počítají za dávku jako celek: 200 bochníků za hodiny, které to trvalo, proti cíli kroku. Spojení zaplatí přesně tolik jako samostatné dávky při stejné rychlosti. Když mají řádky pro krok různé cíle, cíl dávky je ten, který zabere stejné hodiny jako samostatné dávky.

Vyrobit víc, než chtěly všechny řádky dohromady, vyžaduje kód nebo PIN vedoucího, jako výše; přebytek se také rozdělí mezi řádky. Vedoucí může řádky znovu oddělit (**Separate**), kdykoli na nich nikdo nepracuje. Co už se vyrobilo společně, zůstává u každého řádku.

## Míchání a další kroky vážené v kg

Míchání je samostatný krok na začátku receptury a počítá se v kg, ne v kusech. Obrazovka ukazuje u čísel **kg** a pole se jmenuje **Kg made** (Vyrobené kg): u 50 kg směsi zadejte 50. Pod polem obrazovka ukáže, co z toho vyjde, *50 kg of mix = 500 × ABB-06a*. Směs, která vyjde o trochu víc, než úkol požadoval, se přijme tak, jak je, bez manažera.

Krok vidí a mohou ho začít jen řemeslníci, kteří jsou u něj uvedeni; v seznamu nikoho jiného není. Platí se od hodiny základní sazbou: bez cíle, bez platového pásma a bez poznámky o nesplněném cíli, a čas se dál počítá do nákladů výrobku.

Další krok zůstává zamčený s **Waiting for** (Čeká se na) a názvem kroku míchání, dokud se nezapíše nějaká směs, a otevře se hned po zadání první. Jedna směs pro několik řádků funguje jako v části výše: manažer spojí řádky míchání, míchač zadá kg jednou a každý řádek dostane svůj podíl.

**Směsi v celých várkách.** Když má výrobek nastavenou velikost várky směsi (25 kg nebo 50 kg), požadované kg se zaokrouhlí nahoru na celé várky a ukáží se jako várky: 94,1 kg ve várkách po 50 kg se ukáže jako *2 × 50 kg batches = 100 kg* (2 várky po 50 kg = 100 kg). Políčko se jmenuje **Batches of 50 kg made** (Hotové várky po 50 kg) a píšete počet várek, 2, ne kg. U spojené směsi se zaokrouhluje celkový součet všech řádků. Navíc oproti zakázce nemění zakázku, množství na tvarování ani mzdu. Manažeři to nastaví v kroku receptury výrobku v **Mix batch size** (Velikost várky směsi).

**Nastavení (manažeři).** Na stránce výrobního úkolu je v **Only these artisans can do it** (Mohou ho dělat jen tito řemeslníci) seznam lidí, kteří ho smějí dělat; tam je přidáte nebo odeberete. Formulář úprav má **Paid by piece rate** (Placeno od kusu; vypnuto u hodinové práce) a **Recorded in kg** (Zapisuje se v kg). Kolik kg směsi jde na jeden kus, se zadává v kroku receptury každého výrobku.

## Pauza

Pod pracovní plochou je řádek **Break:** (Pauza) s tlačítky **5m**, **20m** a **30m**. Klepněte na délku pauzy, kterou si dáváte, a potvrďte tlačítkem **Yes, start break** (Ano, začít pauzu).

- Pokud jste přihlášeni v docházce, pauza vás odhlásí.
- Pokud máte otevřený úkol, jeho hodiny se na dobu pauzy zastaví: čas pauzy se nepočítá jako čas na úkolu.
- Obrazovka se změní na velký panel **On break** (Na pauze): čas od začátku pauzy, **Planned:** (Plánováno) s délkou, kterou jste zvolili, a velké tlačítko **Finish break** (Ukončit pauzu).

**Pauza sama neskončí.** Až budete zpátky u svého stolu, stiskněte **Finish break**. Tím se znovu přihlásíte v docházce a zapíše se, jak dlouhá pauza opravdu byla. Pauzu ukončí i přihlášení svým QR kódem na terminálu.

Když pauza přesáhne plánovanou délku, panel ukáže, o kolik. Jakmile je to o více než pár minut (5, pokud to vedoucí výroby nezměnil), panel zčervená a pauza se vedoucím směn a manažerovi ukáže jako překročená na kartě **Performance** (Výkon).

Hodinoví řemeslníci tlačítka pauzy nemají. Pauzu si dávají odhlášením pomocí QR kódu a úkol pokračuje, když se znovu přihlásí.

**Pro vedoucí směn a manažery.** Karta **Performance** má tabulku **Breaks** (Pauzy): pro každého řemeslníka a den počet pauz, plánované minuty, skutečně strávené minuty a minuty nad plán. Zaškrtněte **Breaks over plan only** (Jen pauzy nad plán) a stiskněte **Apply** (Použít), aby se zobrazily jen pauzy, které přesáhly plán. Červený rámeček nahoře uvádí pauzy, které právě přesahují plán, a pauzy, které zůstaly otevřené po skončení směny. U pauzy ponechané otevřené po směně stiskněte **Set end** (Nastavit konec), zadejte čas, kdy se řemeslník skutečně vrátil, a uložte; hodiny a mzda úkolů, kterých se pauza týká, se přepočítají a původně zapsaný konec zůstane uchován. Docházka se tam nemění: opravuje se v Human Resources. Povolenou toleranci nastavuje vedoucí výroby ozubeným kolem vedle nadpisu **Breaks**.

## Co je dobré vědět

- **Jeden úkol najednou.** Dokud máte otevřený úkol, tlačítka START jsou vypnutá. Nejdřív ho uzavřete.
- **Vaše odměna za úkol** se řídí číslem v Quantity made, jak je vysvětleno v [Pozice v továrně](/docs/factory-positions-cs). Zmetky se neplatí.
- **Open jobs** jsou úkoly a kroky bez přiřazeného řemeslníka, nebo přiřazené někomu jinému. Vzít si takový úkol je v pořádku, pokud to vaše pozice dovoluje; pak je váš, dokud ho neuzavřete.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Vaše obrazovka:</b> vaše organizace → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Začít:</b> klepněte na řádek → <b>START</b>.</li>
<li><b>Dokončit:</b> zadejte <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Míň, než bylo požadováno:</b> <b>DONE</b> → <b>Pause</b>, <b>Close at … and move remaining …</b> nebo <b>Job finished</b>.</li>
<li><b>Pauza:</b> <b>Break:</b> <b>5m</b>, <b>20m</b> nebo <b>30m</b> → <b>Yes, start break</b>. Zpět: <b>Finish break</b>.</li>
<li><b>Pauza ponechaná otevřená po směně (vedoucí):</b> <b>Factory</b> → <b>Artisans</b> → <b>Performance</b> → <b>Set end</b>.</li>
<li><b>Jedna dávka pro několik řádků:</b> klepněte na řádek → <b>Combine … with other lines into one batch</b> → zaškrtněte řádky → <b>Combine</b>. Zrušení: klepněte na spojený řádek → <b>Separate</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Zobrazení vlastních úkolů, START a DONE: pozice <b>Operative</b> pro danou továrnu.</li>
<li>Zobrazení a přebírání <b>Open jobs</b>, zaznamenávání zmetků: <b>Foreman</b>, <b>Mix preparer</b> nebo <b>Floor supervisor</b>.</li>
<li>Spojování a oddělování řádků: vedoucí, kteří řídí výrobní halu, tytéž osoby, které mohou schválit nadvýrobu.</li>
</ul>
</aside>
