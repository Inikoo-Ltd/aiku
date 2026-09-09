---
title: Hromadná zmena viacerých artefaktov naraz
summary: Zaškrtnite artefakty a potom v paneli, ktorý sa objaví, použite výber vpravo na nastavenie veľkosti dávky, presun do inej rodiny alebo oddelenia, vyradenie z prevádzky alebo ich vrátenie späť.
date: 2026-09-09
source_date: 2026-09-09
tags: production, crafts
category: production
---

<aside class="tldr">
Pre každého, kto sa stará o katalóg továrne. Každý zoznam artefaktov má zaškrtávacie polia. Zaškrtnite niekoľko riadkov a nad tabuľkou sa objaví panel s výberom vpravo. Tento výber ponúka štyri úkony: <b>Batch size</b> (veľkosť dávky), <b>Move to family</b> (presunúť do rodiny), <b>Move to department</b> (presunúť do oddelenia) a <b>Discontinue</b> (vyradiť z prevádzky), plus <b>Make active</b> (aktivovať) na vrátenie poslednej zmeny. Úprava artefaktov po jednom stále funguje, toto je tá istá úprava vykonaná na päťdesiatich riadkoch naraz.
</aside>

## Kde ho nájsť

Nie je tu nič, čo by ste museli zapínať, ani tlačidlo, ktoré by ho otváralo. Panel je skrytý, kým niečo nezaškrtnete, a preto ho väčšina ľudí nikdy nevidí.

Zaškrtnite pole vľavo od ľubovoľného riadku. Nad tabuľkou sa objaví panel, ktorý ukazuje, koľko artefaktov ste vybrali, s <b>Clear</b> (vyčistiť) vedľa neho a výberom úkonu úplne vpravo. Zaškrtnutím poľa v hlavičke tabuľky vyberiete naraz všetky artefakty na stránke.

Rovnaký panel dostanete na troch miestach a je vždy rovnaký:

| Kde | Čo ponúka |
| --- | --- |
| **Crafts → All artefacts** (remeslá → všetky artefakty) | všetky štyri úkony, na ľubovoľný artefakt v továrni |
| Stránka rodiny, záložka **Artefacts** | všetko okrem presunu do oddelenia |
| Stránka oddelenia, záložka **Artefacts** | všetky štyri úkony, na artefakty daného oddelenia |

## Výber úkonu

Výber pomenúva úkon. Zmeníte ho a spolu s ním sa zmení aj ovládací prvok vedľa neho. Samotný výber sa nikdy nepresúva ani nemení šírku, takže keď raz viete, kde je, môžete pracovať rýchlo.

**Batch size** (veľkosť dávky) vám dá pole a tlačidlo <b>Set</b> (nastaviť). Zadajte počet kusov, ktoré tvorí bežná dávka, a stlačte <b>Set</b>. Musí to byť jeden alebo viac. Toto je číslo, ktoré továreň vidí pri plánovaní pracovného príkazu, a artefakt bez neho sa objaví v červených stĺpcoch v zozname rodín.

**Move to family** (presunúť do rodiny) a **Move to department** (presunúť do oddelenia) vám dajú vyhľadávacie pole. Začnite písať kód alebo názov, vyberte cieľ, stlačte <b>Move</b> (presunúť). Vedľa neho je odkaz <b>New family</b> (nová rodina), ak rodina, ktorú chcete, ešte neexistuje. Rodina patrí do jedného oddelenia, takže presun artefaktov do rodiny ich presunie aj do oddelenia tejto rodiny, bez ohľadu na to, kde boli predtým.

**Discontinue** (vyradiť z prevádzky) sa najprv opýta. Dialóg vám povie, koľko artefaktov chystáte vyradiť, a nič sa nestane, kým nestlačíte <b>Yes, discontinue</b> (áno, vyradiť).

**Make active** (aktivovať) vráti vyradené artefakty späť. Nič sa nepýta, pretože ide o bezpečný smer.

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

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Všetky artefakty:</b> vaša organizácia → <b>Factory</b> (továreň) → <b>Crafts</b> (remeslá) → <b>All artefacts</b> (všetky artefakty).</li>
<li><b>Jedna rodina:</b> <b>Crafts</b> → <b>Families</b> (rodiny) → rodina → záložka <b>Artefacts</b>.</li>
<li><b>Jedno oddelenie:</b> <b>Crafts</b> → <b>Departments</b> (oddelenia) → oddelenie → záložka <b>Artefacts</b>.</li>
<li><b>Začiatok:</b> zaškrtnite riadok → objaví sa panel → vyberte úkon vpravo.</li>
<li><b>Zobraziť vyradené:</b> prepínače <b>State</b> nad tabuľkou → zaškrtnite <b>Discontinued</b>.</li>
<li><b>Len jeden artefakt:</b> otvorte ho a použite ceruzku, rovnaké polia sú aj tam.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú na karte zamestnanca pod <b>Human Resources</b> (ľudské zdroje) a nesú so sebou príslušné práva.</li>
<li>Zobrazenie zoznamov: pozícia vo výrobe pre danú továreň, alebo organisation supervisor.</li>
<li>Používanie panela: tá istá pozícia s právom na úpravy v továrni, alebo organisation supervisor. Bez toho sa zaškrtávacie polia vôbec nezobrazia.</li>
</ul>
</aside>
