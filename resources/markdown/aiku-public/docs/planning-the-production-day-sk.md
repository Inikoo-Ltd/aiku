---
title: Plánovanie výrobného dňa
summary: Pre výrobného manažéra a vedúceho dielne - zaradenie úloh do denného plánu bez uvedenia mena, pridelenie každého kroku operátorovi a čo sa stane s prácou, ktorá sa v daný deň nedokončila.
date: 2026-10-15
source_date: 2026-11-19
tags: production, planning, floor
category: production
series: Ordering from partners
order: 13
---

<aside class="tldr">
Dvaja ľudia, dve úlohy. <b>Manažér</b> rozhoduje, čo sa v ktorý deň vyrába: na tabuli <b>To produce</b> (Na výrobu) vyberie karty a stlačí <b>Plan for this day</b> (Naplánovať na tento deň), bez uvedenia mena. <b>Vedúci dielne</b> otvorí <b>Operations → Day plan</b> (Prevádzka → Denný plán) a každý krok každej naplánovanej úlohy pridelí operátorovi. Každý operátor potom vidí svoje kroky na obrazovke dielne. Všetko, čo sa v daný deň nedokončilo, zostáva v pláne oranžovou farbou, kým to niekto nepotvrdí na nový deň.
</aside>

## Manažér plánuje deň

Na **Factory → To produce** (Továreň → Na výrobu) tabuľa funguje ako doteraz: karty idú z **Backlog** (Nevybavené) do **Preparing** (V príprave), kde sa nastaví množstvo, a z **Preparing** do **Assigned** (Pridelené), kde sa vytvorí pracovný príkaz.

Keď sa karta pustí na **Assigned**, malé okno s otázkou *Kto to vyrobí?* teraz začína dátumom a tlačidlom:

1. Vyberte deň. Predvolene je to dnes.
2. Stlačte **Plan for this day**.

Pracovný príkaz sa vytvorí na daný deň, bez uvedenia mena. Každá karta sa stane samostatným pracovným príkazom, takže každý sa dá zoradiť a posunúť samostatne. Viac kariet vybraných a pustených naraz sa naplánuje spolu, každá ako jeden pracovný príkaz.

Výber mena zo zoznamu nižšie funguje tak ako vždy: celá úloha ide tej osobe a nie je v pláne žiadneho dňa.

Do plánu sa dá zaradiť aj úloha, ktorá už má uvedené meno: stlačte ceruzku vedľa mena na jej karte a potom **Plan for this day**. Meno sa z úlohy odoberie a jej kroky pridelí vedúci dielne.

## Vedúci dielne prideľuje kroky

**Factory → Operations → Day plan** (Továreň → Prevádzka → Denný plán) zobrazuje úlohy naplánované na daný deň, jeden riadok na výrobok v úlohe.

| Stĺpec | Význam |
| --- | --- |
| Order | poradie úlohy v dni - zadaním čísla ju posuniete |
| Job | pracovný príkaz a jeho stav |
| Product | čo sa vyrába |
| Qty | koľko kusov |
| Steps and operators | každý krok receptúry: doteraz vyrobené / požadované, a komu je pridelený |
| Planned | deň, na ktorý bola úloha naplánovaná |

Každý krok má vlastný zoznam mien. Vyberte meno a krok sa objaví na obrazovke dielne tejto osoby, a na nikoho inom. Krok s oranžovým okrajom ešte nikoho nemá; lišta hore ich počíta, *3 steps without an operator* (3 kroky bez operátora), takže sa nič omylom nevynechá.

Ak chcete počas dňa zmeniť, kto krok robí, vyberte iné meno. Ak chcete krok niekomu odobrať, stlačte malý krížik v zozname. Ak už osoba začala, jej odvedená práca zostáva zaznamenaná pod jej menom: zoznam rozhoduje len o tom, kto krok vidí odteraz.

Dátum hore posunie plán na iný deň. Zajtrajší plán sa dá rozdeliť už dnes.

## Sledovanie dňa

Rovnaká stránka ukazuje, ako deň prebieha, a obnovuje sa sama:

- lišta hore: koľko úloh, *12 of 18 steps done* (12 z 18 krokov hotových), kroky bez operátora, úlohy nedokončené v svoj deň;
- vedľa každého kroku: sivý kruh (nezačatý), oranžový kruhový ukazovateľ (začatý) alebo zelená fajka (hotový), s počtom doteraz vyrobených kusov;
- *working now* (pracuje sa) a meno, keď má niekto krok otvorený na dielni.

Ľudia, ktorí stránku vidia, ale dielňu neriadia, vidia mená, nie zoznamy.

## Keď vedúci dielne chýba

Manažér otvorí tú istú stránku a kroky pridelí sám. Plánovanie aj prideľovanie krokov vyžadujú rovnakú pozíciu.

## Čo sa nedokončilo

Naplánovaná úloha s otvorenými krokmi po svojom dni nezmizne. Od nasledujúceho dňa sa zobrazuje v Day plan so starým dátumom oranžovou farbou a lišta ju počíta ako *not finished on its day* (nedokončená v svoj deň). Pri každej vyberte:

- **Confirm for** (Potvrdiť na) deň, ktorý práve máte otvorený - úloha sa presunie do plánu tohto dňa, na koniec poradia;
- **Take off the plan** (Vyradiť z plánu) - úloha zostane ako pracovný príkaz, ale nie je v pláne žiadneho dňa.

Kým sa nestlačí jedno z týchto dvoch, úloha vám zostáva na očiach každý deň. Jej kroky zostávajú aj na obrazovkách dielne operátorov, ktorým boli pridelené, takže kto včera zmes pozastavil, môže pokračovať ráno.

Keď operátor uzavrie úlohu s menším počtom a zvyšok presunie do novej úlohy (pozrite [Práca s obrazovkou dielne](/docs/working-the-floor-screen-sk)), nová úloha si ponechá naplánovaný deň, miesto v poradí a operátora každého kroku.

## Keď sa výrobok vyrába z iného výrobku

Niektoré výrobky sa vyrábajú z iného výrobku továrne: veľká bomba do kúpeľa Puckator (PuckJBB-04) je bomba JBB-04 zo skladu, zabalená do zmršťovacej fólie a označená etiketou. Keď nie je na sklade dosť JBB-04 pre tento výrobok, karta na tabuli **To produce** (Na výrobu) a jej úloha v **Day plan** (Denný plán) to oranžovou farbou oznámia, napríklad *Needs 3 SKO JBB-04, not in stock: make JBB-04 first* (Potrebuje 3 SKO JBB-04, nie sú na sklade: najprv vyrobte JBB-04). Úlohy, ktoré už sú v pláne, berú zásobu ako prvé, potom karty z nevybavených, od najstaršej.

Vedľa správy stlačte **Create JBB-04 job** (Vytvoriť úlohu JBB-04): otvorí sa okno **Create job order** (Vytvoriť pracovný príkaz) s už vyplneným JBB-04 a chýbajúcimi SKO a s poznámkou, pre aký výrobok je určený. Skontrolujte ho, vyberte remeselníka a uložte. Keď je úloha JBB-04 vytvorená pre sklad, správa sa zmení na modrú, *Waits for 3 SKO JBB-04 from* (Čaká na 3 SKO JBB-04 z) novej úlohy, kým sa bomby nevyrobia. Úloha JBB-04 vytvorená pre objednávku zákazníka alebo partnera sa nezapočítava, pretože tieto bomby sú už pridelené.

Správa zmizne, keď je hotový krok, ktorý JBB-04 používa.

## Vyžiadanie materiálu od skladu

Kedykoľvek továreň niečo od skladu potrebuje, ktokoľvek z výroby si to vyžiada hore na **Factory → Operations → Materials check** (Továreň → Prevádzka → Kontrola materiálov), pod **Ask the warehouse** (Požiadať sklad). Každý riadok je:

| Pole | Čo zadať |
| --- | --- |
| SKO | napíšte kód alebo názov; zoznam ukazuje, koľko SKO má sklad a továreň |
| Quantity and unit | tak, ako by ste to povedali skladu: *3 Boxes*, *1 Pallet*, *80 Bags*, *2 SKOs* alebo *All there is* |
| Drop point | kam v továrni to uložiť (*Storage room*, *Clean room*, *Bath bomb area*...) |
| Urgent | sklad vidí naliehavé riadky ako prvé |
| Note | čokoľvek, čo má sklad vedieť (*250ml*, *prosím vonku na paletách*) |

**Add line** (Pridať riadok) vyžiada viac vecí naraz a **Send to warehouse** (Poslať skladu) ich odošle. **Asked this week** (Vyžiadané tento týždeň) nižšie ukazuje každú požiadavku s tým, kto ju zadal a kedy, a pri každom riadku, či je *To bring* (Doviezť), *Delivered* (Dodané; koľko SKO, kým, kedy) alebo *Can't bring* (Nemožno doviezť) s dôvodom skladu.

Zoznam miest vyloženia vedie tlačidlom **Drop points** (Miesta vyloženia) manažér a vedúci dielne: pridať, premenovať, posunúť hore a dolu alebo zmazať. Zmazané miesto vyloženia si zachová svoj názov pri požiadavkách, ktoré tam už boli odoslané.

## Ranná kontrola materiálov

Sklad privádza materiál do továrne v celých škatuliach, nie v presných množstvách na daný deň. **Factory → Operations → Materials check** (Továreň → Prevádzka → Kontrola materiálov) zobrazuje, čo denný plán ešte potrebuje, takže továreň skontroluje, čo má, a zvyšok si vyžiada od skladu jedným prehľadným zoznamom.

Každý riadok je jeden materiál, spočítaný cez všetky úlohy dňa, s úlohami, ktoré ho potrebujú (*JOaroma-0141, JOaroma-0145*). Počíta sa len s krokmi, ktoré ešte nie sú hotové: keď je výroba hotová a zostáva už len balenie, riadok ukáže škatule a etikety a už nie fľaše. Zmesi sa vyrábajú v továrni a v zozname nie sú. Množstvá pochádzajú z receptúr, takže úloha, ktorej receptúra neuvádza žiadne materiály, nepotrebuje nič: oranžová lišta nad zoznamom také úlohy menuje, aby bola medzera v receptúre vidieť.

| Stĺpec | Význam |
| --- | --- |
| Material | materiál a skladový kód v sklade |
| For jobs | úlohy dňa, ktoré ho potrebujú |
| Needed | koľko spotrebujú kroky, ktoré ešte treba urobiť |
| At the factory | koľko je teraz na lokalite v továrni a koľko sa už dnes vyžiadalo |
| Check | zaškrtnite **Have enough** (Mám dosť), alebo zadajte približne, koľko chýba |

Keď sa zadá chýbajúci počet, riadok ukáže, čo sklad privezie, zaokrúhlené nahor na jeho škatule: pri 1 200 chýbajúcich a 500 v škatuli je to *bring 3 boxes (1 500)* (doviezť 3 škatule, 1 500). Červené *Warehouse short* (Sklad nemá dosť) znamená, že sklad toľko nemá, takže to viete hneď.

Stlačte **Send to warehouse** (Poslať skladu). Chýbajúce riadky odídu do skladu ako jedna požiadavka a požiadavka sa zobrazí pod **Asked this week**, pričom každý materiál je označený *To bring* (Doviezť), *Delivered* (Dodané) alebo *Can't bring*. Ak označíte všetko ako dostatočné a odošlete, zaznamená sa, že kontrola prebehla. Druhá kontrola neskôr počas dňa pridá len to, čo ešte chýba.

Kontrolu robí manažér alebo vedúci dielne, alebo ju pridelí operátorovi cez **Checked by** (Skontroloval) hore. Tento operátor potom na obrazovke dielne vidí pripomienku, ktorá kontrolu otvorí.

## Sklad privezie materiály

V sklade karta **For production** (Pre výrobu) pod **Action needed** (Vyžaduje akciu) na **Inventory** (Zásoby) ukazuje, na koľko riadkov továreň čaká, naliehavé na malom odznaku. Otvorí **Inventory → For production**: každý riadok, ktorý treba ešte doviezť, najprv naliehavé a potom najstaršie, s tým, čo sa žiada, miestom vyloženia, kto to vyžiadal a kedy, poznámkou a regálom, z ktorého sa berie. Skladník vyberie regál, zadá privezené SKO a stlačí **Delivered**. Keď to sklad nemôže doviezť, **Can't bring** sa opýta na dôvod (*out of stock*, *ordered from supplier*) a dá to továrni vedieť. **Done** (Hotovo) vypíše, čo sa privezlo alebo odmietlo, s časom, koľko to trvalo.

Ak Aiku ukazuje na regáli menej SKO, než sa privezlo, dodanie aj tak prejde: čo Aiku malo, presunie sa do továrne a zvyšok sa označí ako *not in Aiku, count it* (nie je v Aiku, spočítať), aby sa SKO prepočítalo.

Dodaný tovar sa presunie z regálu na lokalitu v továrni. Zostáva skladovou zásobou: počíta sa a vidno ho ako predtým a zákaznícku objednávku z neho možno stále vychystať, keď sú regály skladu prázdne, pričom regály majú vždy prednosť. Nepoužité materiály sa dajú vrátiť na regál zo stránky lokality v továrni, ako každý iný presun medzi lokalitami.

Keď sa hotová úloha prijme do skladu, jej materiály sa najprv spotrebujú z lokality v továrni podľa množstiev v receptúre a až potom z iných lokalít. Lokalitu v továrni čas od času prepočítajte, aby sa opravili rozbitia a odpad.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Naplánovať úlohu na deň:</b> <b>Factory</b> → <b>To produce</b> → pustite kartu na <b>Assigned</b> → vyberte deň → <b>Plan for this day</b>.</li>
<li><b>Pridelenie kroku operátorovi:</b> <b>Factory</b> → <b>Operations</b> → <b>Day plan</b> → zoznam vedľa kroku.</li>
<li><b>Zmena poradia dňa:</b> zadajte číslo do <b>Order</b>.</li>
<li><b>Včerajšia nedokončená úloha:</b> <b>Confirm for</b> deň, alebo <b>Take off the plan</b>.</li>
<li><b>Z tabule do plánu:</b> odkaz <b>Day plan</b> nad tabuľou.</li>
<li><b>Požiadať sklad o niečo:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Ask the warehouse</b> → <b>Send to warehouse</b>.</li>
<li><b>Zmeniť miesta vyloženia:</b> <b>Materials check</b> → <b>Drop points</b>.</li>
<li><b>Skontrolovať materiály dňa:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Have enough</b> alebo chýbajúci počet → <b>Send to warehouse</b>.</li>
<li><b>Pridelenie kontroly operátorovi:</b> <b>Materials check</b> → <b>Checked by</b>.</li>
<li><b>Priviezť materiály (sklad):</b> <b>Inventory</b> → <b>For production</b> → <b>Delivered</b>, alebo <b>Can't bring</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú v karte zamestnanca v Human Resources a nesú so sebou oprávnenia.</li>
<li>Plánovanie dňa, prideľovanie krokov, zmena poradia: <b>Floor supervisor</b> pre danú továreň.</li>
<li>Pozeranie na Day plan bez možnosti zmeny: <b>Foreman</b>.</li>
<li>Práca na kroku, ktorý vám bol pridelený: <b>Operative</b>, na obrazovke dielne.</li>
<li>Vyžiadanie materiálu od skladu: ktokoľvek, kto vidí Operations továrne.</li>
<li>Kontrola materiálov a miesta vyloženia: <b>Floor supervisor</b>; kontrolu aj operátor, ktorému bola pridelená.</li>
<li>Privážanie materiálov do továrne: skladová pozícia, ktorá pracuje v sklade.</li>
</ul>
</aside>
