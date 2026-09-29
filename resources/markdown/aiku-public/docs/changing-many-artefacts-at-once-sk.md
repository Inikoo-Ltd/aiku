---
title: Hromadná zmena viacerých artefaktov naraz
summary: Zaškrtnite artefakty a potom v paneli, ktorý sa objaví, použite výber vpravo na nastavenie veľkosti dávky alebo trvanlivosti, nastavenie rovnakých výrobných krokov pre všetky, presun do inej rodiny alebo oddelenia, vyradenie z prevádzky alebo ich vrátenie späť.
date: 2026-09-29
source_date: 2026-09-29
tags: production, crafts
category: production
---

<aside class="tldr">
Pre každého, kto sa stará o katalóg továrne. Každý zoznam artefaktov má zaškrtávacie polia. Zaškrtnite niekoľko riadkov a nad tabuľkou sa objaví panel s výberom vpravo. Tento výber ponúka úkony: <b>Batch size</b> (veľkosť dávky), <b>Shelf life</b> (trvanlivosť), <b>Manufacture task</b> (výrobná úloha), <b>Move to family</b> (presunúť do rodiny), <b>Move to department</b> (presunúť do oddelenia) a <b>Discontinue</b> (vyradiť z prevádzky), plus <b>Make active</b> (aktivovať) na vrátenie poslednej zmeny. Úprava artefaktov po jednom stále funguje, toto je tá istá úprava vykonaná na päťdesiatich riadkoch naraz.
</aside>

## Kde ho nájsť

Nie je tu nič, čo by ste museli zapínať, ani tlačidlo, ktoré by ho otváralo. Panel je skrytý, kým niečo nezaškrtnete, a preto ho väčšina ľudí nikdy nevidí.

Zaškrtnite pole vľavo od ľubovoľného riadku. Nad tabuľkou sa objaví panel, ktorý ukazuje, koľko artefaktov ste vybrali, s <b>Clear</b> (vyčistiť) vedľa neho a výberom úkonu úplne vpravo. Zaškrtnutím poľa v hlavičke tabuľky vyberiete naraz všetky artefakty na stránke.

Rovnaký panel dostanete na troch miestach. Vyzerá vždy rovnako, ale nie každý úkon je ponúknutý všade:

| Kde | Čo ponúka |
| --- | --- |
| **Crafts → All artefacts** (remeslá → všetky artefakty) | všetky úkony, na ľubovoľný artefakt v továrni |
| Stránka rodiny, záložka **Artefacts** | veľkosť dávky, výrobná úloha, presun do rodiny, vyradenie a aktivácia |
| Stránka oddelenia, záložka **Artefacts** | veľkosť dávky, výrobná úloha, presun do rodiny alebo oddelenia, vyradenie a aktivácia |

## Výber úkonu

Výber pomenúva úkon. Zmeníte ho a spolu s ním sa zmení aj ovládací prvok vedľa neho. Samotný výber sa nikdy nepresúva ani nemení šírku, takže keď raz viete, kde je, môžete pracovať rýchlo.

**Batch size** (veľkosť dávky) vám dá pole a tlačidlo <b>Set</b> (nastaviť). Zadajte počet kusov, ktoré tvorí bežná dávka, a stlačte <b>Set</b>. Musí to byť jeden alebo viac. Toto je číslo, ktoré továreň vidí pri plánovaní pracovného príkazu, a artefakt bez neho sa objaví v červených stĺpcoch v zozname rodín.

**Shelf life** (trvanlivosť) funguje rovnako, v dňoch: 365 je rok, 730 sú dva roky. Je to, ako dlho artefakt vydrží po výrobe, a obmedzuje, koľko má továreň vyrobiť naraz. Ponúka ju len zoznam **All artefacts**.

**Manufacture task** (výrobná úloha) vám dá tlačidlo <b>Make a unified manufacture task</b> (vytvoriť jednotnú výrobnú úlohu), ktoré otvorí okno, kde kroky nastavíte raz pre všetky zaškrtnuté artefakty. Má vlastnú časť nižšie.

**Move to family** (presunúť do rodiny) a **Move to department** (presunúť do oddelenia) vám dajú vyhľadávacie pole. Začnite písať kód alebo názov, vyberte cieľ, stlačte <b>Move</b> (presunúť). Vedľa neho je odkaz <b>New family</b> (nová rodina), ak rodina, ktorú chcete, ešte neexistuje. Rodina patrí do jedného oddelenia, takže presun artefaktov do rodiny ich presunie aj do oddelenia tejto rodiny, bez ohľadu na to, kde boli predtým.

**Discontinue** (vyradiť z prevádzky) sa najprv opýta. Dialóg vám povie, koľko artefaktov chystáte vyradiť, a nič sa nestane, kým nestlačíte <b>Yes, discontinue</b> (áno, vyradiť).

**Make active** (aktivovať) vráti vyradené artefakty späť. Nič sa nepýta, pretože ide o bezpečný smer.

## Rovnaké výrobné kroky pre mnoho artefaktov

Každý artefakt má zoznam krokov, ktoré remeselník robí pri jeho výrobe, napríklad liatie, zmršťovanie fóliou a balenie do krabice. Každý krok je **manufacture task** (výrobná úloha), vytvorená raz pre továreň a opakovane používaná každým artefaktom, ktorý ju potrebuje. Kroky jedného artefaktu nastavíte na jeho záložke **Manufacture tasks**. Keď sa celý sortiment vyrába rovnako, úkon **Manufacture task** to urobí pre všetky naraz.

Okno má kroky vľavo a zaškrtnuté artefakty vpravo.

**Nastavenie krokov.** Každá karta kroku má:

- **Task** (úloha): samotná práca, vybraná z výrobných úloh továrne. Písaním hľadáte podľa názvu alebo kódu.
- **Units per artefact** (jednotky na artefakt): koľko jednotiek tejto úlohy potrebuje jeden artefakt. Pracovný príkaz na 10 artefaktov pri 2 jednotkách na artefakt žiada od remeselníka 20 jednotiek práce.
- **Raw materials** (suroviny): čo krok spotrebuje na každú jednotku práce, s množstvom pre každú.

Stlačte <b>Add step</b> (pridať krok) pre ďalšiu kartu. Šípky na karte ju posunú hore alebo dole a kôš ju odstráni. Kroky sú očíslované v poradí, v akom sa robia, krok 1 prvý, a remeselníci ich v tomto poradí vidia na výrobnej ploche. Úlohu možno v zozname použiť len raz. Ak úloha, ktorú potrebujete, ešte neexistuje, odkaz <b>Task missing? Create a manufacture task</b> (chýba úloha? vytvoriť výrobnú úlohu) vás zavedie na stránku, kde sa úlohy vytvárajú.

**Iné suroviny pre jeden artefakt.** Väčšinou všetky artefakty používajú rovnaké suroviny, a tak zoznam vpravo aj začína: **All artefacts** (všetky artefakty), so spoločnými surovinami. Keď jeden artefakt potrebuje niečo iné, napríklad iný vonný olej:

1. Kliknite na tento artefakt v zozname vpravo. Každý krok teraz ukazuje jeho suroviny pre tento artefakt.
2. Na kroku, ktorý sa líši, stlačte <b>Use different materials for</b> (použiť iné suroviny pre) daný artefakt. Začína ako kópia spoločných surovín.
3. Zmeňte, pridajte alebo odstráňte suroviny len pre tento artefakt.

Artefakt s vlastnými surovinami dostane v zozname štítok <b>Own materials</b> (vlastné suroviny) a každý krok, kde sa suroviny líšia, má červený okraj a červenú hviezdičku v rohu. Keď prejdete myšou nad hviezdičku, uvidíte, ktoré artefakty sa líšia. Ak sa chcete vrátiť k spoločným surovinám, znova vyberte artefakt a na danom kroku stlačte <b>Use the same materials as all artefacts</b> (použiť rovnaké suroviny ako všetky artefakty).

**Uloženie nahradí to, čo tam bolo.** Toto je časť, pri ktorej treba byť opatrný. Po uložení bude mať každý zaškrtnutý artefakt presne tie kroky, ktoré sú v okne, nič navyše:

- Kroky, ktoré už mali a v zozname nie sú, sa **odstránia aj s ich surovinami**. To zahŕňa aj štandardný krok **Production (PROD)**, ktorý aiku dáva každému novému artefaktu.
- Kroky, ktoré už mali a v zozname sú, zostanú, ale prevezmú nové jednotky na artefakt a nové suroviny.
- Nedá sa to vrátiť späť. Ak sa chcete vrátiť, musíte staré kroky nastaviť znova.

Okno vám to pripomenie v žltom rámčeku nad tlačidlami. Tlačidlo na uloženie zostane sivé, kým nezaškrtnete <b>I understand the existing steps will be replaced</b> (rozumiem, že existujúce kroky budú nahradené). Tlačidlo hovorí, koľko artefaktov zmení, napríklad <b>Replace steps on 5 artefacts</b> (nahradiť kroky na 5 artefaktoch).

**Pracovné príkazy, ktoré už bežia.** Pracovné príkazy, ktoré sú ešte otvorené, dostanú nové kroky. Odstránený krok z nich zmizne len vtedy, ak ho ešte nikto nezačal. Práca už zaznamenaná na výrobnej ploche zostáva.

## Čo vyradenie z prevádzky robí a nerobí

Vyradený artefakt opustí pracovné zoznamy. Zachová si všetko ostatné: svoj recept, úlohy, históriu a pracovné príkazy, na ktorých bol. Nič sa nemaže a žiadny sklad sa nehýbe.

Zoznamy artefaktov sa otvárajú iba na stavoch **In process** (v procese) a **Active** (aktívny), takže vyradený artefakt zmizne z pohľadu. Ak ich chcete vidieť znova, použite prepínače <b>State</b> (stav) nad tabuľkou a zaškrtnite <b>Discontinued</b> (vyradené).

Rodiny preberajú svoj stav od artefaktov, ktoré obsahujú. Rodina je aktívna, kým je v nej aspoň jeden artefakt aktívny alebo v procese, a stane sa vyradenou až keď sú vyradené všetky. To znamená, že vyradenie posledného artefaktu v rodine potichu odstráni aj rodinu zo zoznamu rodín, a tie isté prepínače <b>State</b> ju vrátia späť.

## Čo je dobré vedieť

- **Artefakty, ktoré už sú v stave, ktorý ste zvolili, sa preskočia.** Vyradenie výberu, ktorý je napoly vyradený, nahlási len tie, ktoré sa skutočne zmenili.
- **Výber platí len pre to, čo vidíte.** Zaškrtnutie poľa v hlavičke vyberie riadky na stránke, nie všetky artefakty za filtrom. Ak chcete viac, najprv zmeňte veľkosť stránky.
- **Nič tu sa nedotýka skladu.** Toto sú záznamy receptov, nie tovar v sklade.
- **Počty v zozname rodín sa aktualizujú okamžite.** Nastavte veľkosť dávky na dvadsiatich artefaktoch a červený stĺpec v zozname rodín klesne o dvadsať hneď po znovunačítaní stránky.
- **Presun nemá späťvzatie.** Presun artefaktov do nesprávnej rodiny sa opraví presunom späť, čo sú tie isté dva kliky.
- **Ani nahradenie krokov sa nedá vrátiť.** Predtým, ako uložíte výrobnú úlohu na mnohých artefaktoch, skontrolujte, či zoznam vpravo obsahuje tie artefakty, ktoré ste chceli.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Všetky artefakty:</b> vaša organizácia → <b>Factory</b> (továreň) → <b>Crafts</b> (remeslá) → <b>All artefacts</b> (všetky artefakty).</li>
<li><b>Jedna rodina:</b> <b>Crafts</b> → <b>Families</b> (rodiny) → rodina → záložka <b>Artefacts</b>.</li>
<li><b>Jedno oddelenie:</b> <b>Crafts</b> → <b>Departments</b> (oddelenia) → oddelenie → záložka <b>Artefacts</b>.</li>
<li><b>Začiatok:</b> zaškrtnite riadok → objaví sa panel → vyberte úkon vpravo.</li>
<li><b>Rovnaké kroky pre mnoho:</b> vyberte <b>Manufacture task</b> → <b>Make a unified manufacture task</b>.</li>
<li><b>Vytvoriť výrobnú úlohu:</b> <b>Factory</b> → <b>Operations</b> (prevádzka) → <b>Tasks</b> (úlohy).</li>
<li><b>Zobraziť vyradené:</b> prepínače <b>State</b> nad tabuľkou → zaškrtnite <b>Discontinued</b>.</li>
<li><b>Len jeden artefakt:</b> otvorte ho a použite ceruzku, rovnaké polia sú aj tam. Jeho kroky sú na záložke <b>Manufacture tasks</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú na karte zamestnanca pod <b>Human Resources</b> (ľudské zdroje) a nesú so sebou príslušné práva.</li>
<li>Zobrazenie zoznamov: pozícia vo výrobe pre danú továreň, alebo organisation supervisor.</li>
<li>Používanie panela: tá istá pozícia s právom na úpravy v továrni, alebo organisation supervisor. Bez toho sa zaškrtávacie polia vôbec nezobrazia.</li>
</ul>
</aside>
