---
title: Čtení nákupního dashboardu partnera
summary: Obrazovka, která ukazuje, co koupit od partnera a kolik prostoru na to máte — tři karty kapacity, koláčový graf pokrytí skladu s osmi kategoriemi a objednávkový pipeline.
date: 2026-09-02
source_date: 2026-09-02
tags: procurement, intercompany, shopping-list, stock
category: procurement
series: Ordering from partners
order: 2
---

<aside class="tldr">
Tento dashboard je místo, kde začíná každá nákupní session. Horní řádek ukazuje, kolik máte prostoru — peníze a skladové místo. Prostředek ukazuje, které z partnerových produktů je třeba objednat, od nejhoršího. Spodek ukazuje, co je už na cestě. Nemusíte si nic pamatovat — obrazovka vám řekne, co potřebuje pozornost. Samotné zadávání objednávky je popsáno v <a href="/docs/buying-from-a-partner-cs">Nákup od partnera</a>.
</aside>

Otevřete ji přes **Procurement → Partners → {partner} → Shopping** (Nákup → Partneři → {partner} → Nákup). Použijte ji místo otevírání nákupního seznamu a snahy vzpomenout si, co chybělo.

## Tři karty nahoře: kolik máte prostoru

Tyto karty jsou limity, ne dekorace. Existují proto, že nákupní seznam, do kterého může kdokoli nasypat cokoli, přestává něco znamenat — partner, který dostane tisíc řádků, nepozná, které dva jsou naléhavé.

- **Order budget used** (Využitý objednávkový rozpočet). Hodnota vašeho otevřeného seznamu v porovnání s tím, co vám tento partner skutečně dodá za jeden objednávkový cyklus, zobrazená ve měně vaší vlastní organizace — každé peněžní číslo na těchto obrazovkách je pro vás přepočítané, takže nikdy nemusíte přemýšlet v partnerově měně. Pokud existuje dostatečná historie dodávek, rozpočet se měří ze skutečných dodávek; pokud ne, je to jeden objednávkový cyklus toho, co skutečně prodáváte z jejich produktů. Toto číslo nikdo nezadává ručně — ani vy, ani váš manažer. Když je pruh plný, karta říká **at capacity** (na hranici kapacity).
- **Warehouse space** (Skladový prostor). Kolik míst je volných z celkového počtu, s pruhem rozděleným na to, co je *in use* (využito), co je *inbound* (na cestě) v otevřených objednávkách a dodávkách, a co by zabral *this shopping list* (tento nákupní seznam). Pod tím spravedlivý podíl partnera: kolik z volných míst smí zabrat jeho úplně nové produkty. Místa se počítají jako sloty — nemáme data o objemu, takže nepředstíráme, že měříme krychlové metry.
- **Lead time** (Dodací lhůta). Karta nadepsaná jménem partnera ukazuje jejich naměřenou dodací lhůtu **objednávka → naskladněno**, z kolika dodávek byla naměřena (nebo poznámku, že jde stále jen o odhad), kolik objednávek u nich mešká a o kolik, a jak velký je jejich katalog.

## Pokrytí skladu: koláč a osm kategorií

Tato část pokrývá celý partnerův katalog, rozdělený do osmi kategorií podle toho, jak dlouho vydrží váš vlastní sklad. Rizikové kategorie jsou dimenzované podle naměřené dodací lhůty, ne podle kalendářních týdnů — v tom je celý smysl.

Začíná to **koláčovým grafem**: každý produkt v katalogu, jedna výseč na kategorii, s celkovým počtem uprostřed. Najetím na výseč zobrazíte počet a procento; kliknutím na výseč — nebo na řádek v legendě vedle ní — otevřete danou kategorii v partnerově katalogu. Jeden pohled řekne, jestli je dnešek klidné doplňování nebo požár: hodně červené znamená problém, převážně zelené znamená, že jste v pohodě.

Pod grafem jsou kategorie rozdělené do dvou skupin. **Needs ordering** (Vyžaduje objednání) obsahuje pět, které chtějí vaši pozornost:

- **Out of stock** (Bez skladu) — na polici nezbylo nic.
- **Doomed** (Odsouzeno) — sklad ještě máte, ale dojde dřív, než by mohla dorazit dodávka, i kdybyste objednali hned teď.
- **Critical / Danger / Watch** (Kritické / Nebezpečné / Sledovat) — dojde do dvou, tří nebo čtyř dodacích lhůt.

**Not for ordering** (Neobjednávat) obsahuje další tři:

- **Covered** (Pokryto) — zatím v pořádku.
- **Dead stock** (Mrtvý sklad) — nic se neprodává, peníze leží na polici; řádek ukazuje, jakou má hodnotu.
- **We never stocked** (Nikdy jsme neskladovali) — partner to prodává, ale vy jste to nikdy neměli.

Jeden druh položek se tu neobjeví vůbec: SKO, které jste ve vlastním skladu označili jako **On Demand** (Na vyžádání). Jejich sklad se nesleduje, takže "bez skladu" by nic neznamenalo — dashboard, tabulky kategorií i automatické doplnění je všechny přeskakují.

Každá dlaždice odpovídá na jednu otázku: **kolik jich ještě potřebuje pozornost?** Počet "*N* potřebuje akci" ignoruje vše, co je už na seznamu nebo už na cestě, takže se zmenšuje, jak seznam zpracováváte. Pod ním stejný počet rozdělený podle **ranku** — produkty A první, D a Z vybledlé na konci. Dva produkty ranku A bez skladu jsou důležitější než pět set produktů ranku D, takže v tomto pořadí se pracuje.

Tři věci, které lze udělat z dlaždice:

- **Kliknout na číslo** a otevřít kategorii jako tabulku: každou položku, seřazenou, s jejich skladem, vaším skladem, kdy vám dojde, a polem množství, které zapisuje přímo do nákupního seznamu.
- **Kliknout na název kategorie nebo písmeno ranku** a procházet dané produkty v partnerově katalogu.
- **Fill** (Doplnit) otevře automatické doplnění už zaměřené na danou kategorii a už vygenerované — jen upravíte a potvrdíte. Trochu víc práce než kouzelné tlačítko, ale mnohem větší kontrola. Počty na dlaždici — *N na cestě · N na seznamu* — ukazují, kolik z kategorie už máte vyřešeno.

U **Covered** a **Dead stock** se místo toho objeví červené varování, pokud je na vašem nákupním seznamu něco z dané kategorie: to je sklad, který nepotřebujete. **remove** (odstranit) tyto řádky jedním kliknutím vymaže.

## Objednávkový pipeline

Spodní pruh sleduje vše od potřeby po polici: **on shopping list → being prepared → ready to ship → in transit → arrived, booking in** (na nákupním seznamu → připravuje se → připraveno k odeslání → na cestě → dorazilo, naskladňuje se). Každý sloupec ukazuje své dodávky a kolik je v nich položek; každá karta otevře dodávku, jen ke čtení — sklad prodávajícího ji vlastní, dokud zboží nedorazí k vám.

Karty viditelně stárnou. Po třech násobcích dodací lhůty zežloutnou; po deseti zčervenají. Stará karta je otázka na partnera, ne číslo, na které se dívat. Cokoli je skutečně pozdě, se objeví i v seznamu **Late from this partner** (Zpožděno od tohoto partnera) níže, seřazeno od největšího zpoždění, s příznakem "no delivery date given" (datum dodání nezadáno).

## Proč obrazovka někdy řekne ne

Přidání na seznam může být odmítnuto. To je záměr a existují jen tři důvody:

- **Na hranici rozpočtu** — nejdřív něco odstraňte nebo snižte prioritu. Skutečná nouzová situace se vejde vždy: **produkty ranku A a bez skladu jsou z limitu vyňaty**, takže nouze nikdy nečeká za limitem.
- **Podlaha skladu** — pod 5 % volných míst se od nikoho nepřidá žádný *nový* produkt. Položky, které už skladujete, doplňují svá vlastní místa a projdou volně.
- **Spravedlivý podíl tohoto partnera** — jeden partner může zabrat zhruba pětinu volných míst produkty, které jste nikdy neskladovali. Ostatní dodavatelé taky potřebují prostor.

Stejné pravidlo platí všude, kde přidáváte — ručně, hromadně nebo z automatického doplnění — takže návrh nikdy neobsahuje řádky, které nemůžete potvrdit.

## Naměřeno, nebo poctivě označeno jako odhad

Dvě čísla řídí většinu této obrazovky: dodací lhůta a rozpočet. Pravidlo je pro obě stejné. **Pokud máme historii, číslo je naměřené a nelze ho upravit.** Pokud ji nemáme, karta to řekne a odhad lze upravit — ale v nastavení, nikdy přímo na dashboardu: pole **estimated delivery time** (odhadovaná dodací doba) na produktu dodavatele nebo ve vlastním nastavení SKO. Jakmile existuje dost skutečných dodávek, měření to převezme a pole s odhadem zmizí. Nikdo nemá přepsat to, co se skutečně stalo.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Dashboard:</b> vaše organizace → <b>Procurement → Partners</b> (Nákup → Partneři) → otevřít partnera → <b>Shopping</b> (Nákup).</li>
<li><b>Přejít z koláčového grafu:</b> kliknout na výseč nebo na řádek v legendě a procházet danou kategorii v katalogu.</li>
<li><b>Zpracovat kategorii:</b> kliknout na číslo dlaždice pro tabulku položek, na písmeno ranku pro procházení daných produktů, nebo na <b>Fill</b> (Doplnit) pro zaměřený návrh automatického doplnění.</li>
<li><b>Vyčistit seznam:</b> <b>remove</b> (odstranit) na dlaždici Covered nebo Dead stock.</li>
<li><b>Opravit odhad dodací lhůty:</b> nastavení SKO, nebo nastavení produktu dodavatele — jen dokud stále říká <i>estimate</i> (odhad).</li>
</ul>
</aside>
