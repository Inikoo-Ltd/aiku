---
title: Plánování výrobního dne
summary: Pro manažera výroby a vedoucí výroby - zařazení úkolů do plánu dne bez uvedení jména, přidělení každého kroku dělníkovi a co se stane s prací, která se ten den nedokončila.
date: 2026-10-15
source_date: 2026-11-19
tags: production, planning, floor
category: production
series: Ordering from partners
order: 13
---

<aside class="tldr">
Dva lidé, dvě úlohy. <b>Manažer</b> rozhoduje, co se ten den vyrobí: na nástěnce <b>To produce</b> (K výrobě) vybere karty a stiskne <b>Plan for this day</b> (Naplánovat na tento den), aniž by uvedl jméno. <b>Vedoucí výroby</b> otevře <b>Operations → Day plan</b> (Provoz → Denní plán) a každý krok každého naplánovaného úkolu přidělí dělníkovi. Každý dělník pak vidí své kroky na obrazovce dílny. Co se ten den nedokončí, zůstává v plánu oranžově, dokud někdo nepotvrdí nový den.
</aside>

## Manažer plánuje den

Na **Factory → To produce** (Továrna → K výrobě) nástěnka funguje jako dřív: karty putují z **Backlog** (Nevyřízené) do **Preparing** (Příprava), kde se nastaví množství, a z **Preparing** do **Assigned** (Přiděleno), kde se vytvoří výrobní příkaz.

Když se karta pustí na **Assigned**, malé okno s otázkou *Who makes it?* (Kdo to vyrobí?) teď začíná datem a tlačítkem:

1. Vyberte den. Otevře se na dnešku.
2. Stiskněte **Plan for this day**.

Pro ten den se vytvoří výrobní příkaz, na kterém není nikdo uveden. Každá karta se stane samostatným výrobním příkazem, takže každý lze řadit a posouvat zvlášť. Několik karet vybraných a puštěných společně se plánuje společně, každá jako jeden výrobní příkaz.

Výběr jména ze seznamu níže funguje stále jako dřív: celý úkol připadne té osobě a není v plánu žádného dne.

Do plánu lze zařadit i úkol, na kterém už někdo uveden je: stiskněte tužku vedle jména na jeho kartě a pak **Plan for this day**. Jméno se z úkolu odebere a jeho kroky přidělí vedoucí výroby.

## Vedoucí výroby přiděluje kroky

**Factory → Operations → Day plan** vypíše úkoly naplánované na daný den, jeden řádek na výrobek v úkolu.

| Sloupec | Význam |
| --- | --- |
| Order (Pořadí) | místo úkolu v rámci dne - zadáním čísla ho posunete |
| Job (Úkol) | výrobní příkaz a jeho stav |
| Product (Výrobek) | co se vyrábí |
| Qty (Množství) | kolik |
| Steps and operators (Kroky a dělníci) | každý krok receptu: vyrobeno zatím / požadováno a komu je přidělen |
| Planned (Naplánováno) | den, na který bylo naplánováno |

Každý krok má vlastní seznam jmen. Vyberte jedno a krok se objeví na obrazovce dílny té osoby a nikoho jiného. Krok s oranžovým okrajem zatím nikoho nemá; lišta nahoře je počítá, *3 steps without an operator* (3 kroky bez dělníka), aby se omylem nic nevynechalo.

Chcete-li během dne změnit, kdo krok dělá, vyberte jiné jméno. Chcete-li krok někomu odebrat, stiskněte malý křížek v seznamu. Pokud už dotyčný začal, práce, kterou udělal, zůstává zapsaná pod jeho jménem: seznam rozhoduje jen o tom, kdo krok uvidí od teď.

Datum nahoře přepíná na jiný den. Plán na zítřek lze rozdělit už dnes.

## Sledování dne

Stejná stránka ukazuje, jak den probíhá, a obnovuje se sama:

- lišta nahoře: kolik úkolů, *12 of 18 steps done* (12 z 18 kroků hotovo), kroky bez dělníka, úkoly nedokončené v daný den;
- u každého kroku: šedý kruh (nezačato), oranžový kolečkový ukazatel (začato) nebo zelená fajfka (hotovo) s počtem dosud vyrobených kusů;
- *working now* (právě pracuje) a jméno, když má někdo krok otevřený na dílně.

Lidé, kteří stránku vidí, ale dílnu neřídí, vidí jména, ne seznamy.

## Když vedoucí výroby chybí

Manažer otevře stejnou stránku a kroky přidělí sám. Plánování i přidělování kroků vyžaduje stejnou pozici.

## Co se nedokončilo

Naplánovaný úkol s kroky, které zůstaly po jeho dni otevřené, se neztratí. Od dalšího dne se v Day plan zobrazuje se starým datem oranžově a lišta ho počítá jako *not finished on its day* (nedokončeno v daný den). U každého si vyberte:

- **Confirm for** (Potvrdit na) den, který právě prohlížíte - úkol se přesune do plánu toho dne, na konec pořadí;
- **Take off the plan** (Vyřadit z plánu) - úkol zůstane jako výrobní příkaz, ale není v plánu žádného dne.

Dokud se nestiskne jedno z obojího, úkol vám zůstává před očima každý den. Jeho kroky zůstávají i na obrazovkách dílny dělníků, kterým byly přiděleny, takže kdo včera pozastavil směs, může dnes ráno pokračovat.

Když dělník uzavře úkol s menším množstvím a zbytek přesune do nového úkolu (viz [Práce s obrazovkou dílny](/docs/working-the-floor-screen-cs)), nový úkol si ponechá naplánovaný den, místo v pořadí a dělníka u každého kroku.

## Když se výrobek vyrábí z jiného výrobku

Některé výrobky se vyrábějí z jiného výrobku továrny: jumbo bomba do koupele Puckator (PuckJBB-04) je bomba do koupele JBB-04 ze skladu, zabalená do smršťovací fólie a opatřená etiketou. Když na ni není dost JBB-04 na skladě, karta na nástěnce **To produce** a její úkol v **Day plan** to oranžově oznámí, například *Needs 3 SKO JBB-04, not in stock: make JBB-04 first* (Potřebuje 3 SKO JBB-04, není skladem: nejdřív vyrobte JBB-04). Sklad nejdřív dostanou úkoly, které už jsou v plánu, poté karty v Backlog, od nejstarší.

Stiskněte **Create JBB-04 job** (Vytvořit úkol JBB-04) vedle zprávy: otevře se okno **Create job order** (Vytvořit výrobní příkaz) s již vyplněným JBB-04 a chybějícími SKO a s poznámkou, pro který výrobek je určen. Zkontrolujte ho, vyberte dělníka a uložte. Jakmile je úkol JBB-04 vytvořen pro sklad, zpráva zmodrá, *Waits for 3 SKO JBB-04 from* (Čeká na 3 SKO JBB-04 z) nového úkolu, dokud se bomby do koupele nevyrobí. Úkol JBB-04 vytvořený pro objednávku zákazníka nebo partnera se nepočítá, protože tyto bomby jsou už slíbeny.

Zpráva zmizí, jakmile je hotov krok, který JBB-04 používá.

## Vyžádání materiálu od skladu

Kdykoli továrna něco od skladu potřebuje, kdokoli z výroby si to vyžádá nahoře na **Factory → Operations → Materials check** (Továrna → Provoz → Kontrola materiálu), pod **Ask the warehouse** (Požádat sklad). Každý řádek je:

| Pole | Co zadat |
| --- | --- |
| SKO | napište kód nebo název; seznam ukazuje, kolik SKO drží sklad a továrna |
| Quantity and unit (Množství a jednotka) | tak, jak byste to řekli skladu: *3 Boxes*, *1 Pallet*, *80 Bags*, *2 SKOs* nebo *All there is* |
| Drop point (Místo vyložení) | kam v továrně to složit (*Storage room*, *Clean room*, *Bath bomb area*...) |
| Urgent (Naléhavé) | sklad vidí naléhavé řádky jako první |
| Note (Poznámka) | cokoli, co má sklad vědět (*250ml*, *prosím venku na paletách*) |

**Add line** (Přidat řádek) vyžádá více věcí najednou a **Send to warehouse** (Odeslat skladu) je odešle. **Asked this week** (Vyžádáno tento týden) níže ukazuje každý požadavek s tím, kdo ho zadal a kdy, a u každého řádku, zda je *To bring* (K přivezení), *Delivered* (Dodáno; kolik SKO, kým, kdy) nebo *Can't bring* (Nemůžeme přivézt) s důvodem skladu.

Seznam míst vyložení vede tlačítkem **Drop points** (Místa vyložení) manažer a vedoucí výroby: přidat, přejmenovat, posunout nahoru a dolů nebo smazat. Smazané místo vyložení si zachová své jméno u požadavků, které tam už byly odeslány.

## Ranní kontrola materiálu

Sklad vozí materiál do továrny v celých krabicích, ne v přesných množstvích na daný den. **Factory → Operations → Materials check** (Továrna → Provoz → Kontrola materiálu) vypíše, co denní plán ještě potřebuje, takže továrna zkontroluje, co je na místě, a zbytek si vyžádá od skladu jedním přehledným seznamem.

Každý řádek je jeden materiál sečtený přes všechny úkoly dne, spolu s úkoly, které ho potřebují (*JOaroma-0141, JOaroma-0145*). Počítají se jen kroky, které ještě nejsou hotové: když je výroba hotová a zbývá jen balení, řádek ukazuje krabice a etikety, ale už ne lahvičky. Směsi se vyrábějí v továrně a na seznamu nejsou. Množství pocházejí z receptur, takže úkol, jehož receptura žádné materiály neuvádí, nepotřebuje nic: oranžová lišta nad seznamem takové úkoly jmenuje, aby byla mezera v receptuře vidět.

| Sloupec | Význam |
| --- | --- |
| Material (Materiál) | materiál a skladový kód ve skladu |
| For jobs (Pro úkoly) | úkoly dne, které ho potřebují |
| Needed (Potřeba) | kolik spotřebují kroky, které ještě zbývají |
| At the factory (V továrně) | kolik je nyní na místě v továrně a kolik se dnes už vyžádalo |
| Check (Kontrola) | zaškrtněte **Have enough** (Mám dost), nebo napište přibližně, kolik chybí |

Když zadáte chybějící počet, řádek ukáže, co sklad přiveze, zaokrouhleno nahoru na jeho krabice: chybí 1 200 a v krabici je 500, tedy *bring 3 boxes (1 500)* (přivézt 3 krabice (1 500)). Červené *Warehouse short* (Sklad nemá dost) znamená, že sklad tolik nemá, takže to víte hned.

Stiskněte **Send to warehouse** (Odeslat skladu). Chybějící řádky odejdou skladu jako jeden požadavek a ten se zobrazí pod **Asked this week**, každý materiál označený *To bring* (K přivezení), *Delivered* (Dodáno) nebo *Can't bring*. Zaškrtnutí všeho jako dostatečného a odeslání zaznamená, že kontrola proběhla. Druhá kontrola později během dne přidá jen to, co ještě chybí.

Kontrolu provádí manažer nebo vedoucí výroby, nebo ji předá dělníkovi pomocí **Checked by** (Zkontroloval) nahoře. Ten pak na obrazovce dílny uvidí upozornění, které kontrolu otevře.

## Sklad přiveze materiál

Ve skladu karta **For production** (Pro výrobu) pod **Action needed** (Vyžaduje akci) na **Inventory** (Zásoby) ukazuje, na kolik řádků továrna čeká, naléhavé na malém odznaku. Otevře **Inventory → For production**: každý řádek, který je ještě třeba přivézt, nejdřív naléhavé a pak nejstarší, s tím, co se žádá, místem vyložení, kdo to vyžádal a kdy, poznámkou a regálem, ze kterého se bere. Skladník vybere regál, zadá přivezené SKO a stiskne **Delivered**. Když to sklad přivézt nemůže, **Can't bring** se zeptá na důvod (*out of stock*, *ordered from supplier*) a dá to továrně vědět. **Done** (Hotovo) vypíše, co se přivezlo nebo odmítlo, s dobou, jak dlouho to trvalo.

Pokud Aiku ukazuje na regálu méně SKO, než se přivezlo, dodání přesto projde: co Aiku mělo, přejde do továrny a zbytek se označí jako *not in Aiku, count it* (není v Aiku, spočítat), aby se SKO přepočítalo.

Dodané zboží se přesune z regálu na místo v továrně. Zůstává skladovou zásobou: počítá se a vidí se jako dřív a zákaznická objednávka si ho tam může stále vyzvednout, když jsou regály ve skladu prázdné, přičemž regály mají vždy přednost. Nepoužitý materiál lze vrátit na regál ze stránky místa v továrně, jako každý jiný přesun mezi místy.

Když se hotový úkol přijme do skladu, jeho materiály se podle množství v receptuře nejprve odepíšou z místa v továrně a teprve pak z ostatních míst. Místo v továrně občas přepočítejte, aby se opravily rozbité kusy a odpad.

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Naplánovat úkol na den:</b> <b>Factory</b> → <b>To produce</b> → pusťte kartu na <b>Assigned</b> → vyberte den → <b>Plan for this day</b>.</li>
<li><b>Přidělit krok dělníkovi:</b> <b>Factory</b> → <b>Operations</b> → <b>Day plan</b> → seznam vedle kroku.</li>
<li><b>Změnit pořadí dne:</b> zadejte číslo do <b>Order</b>.</li>
<li><b>Včerejší nedokončený úkol:</b> <b>Confirm for</b> den, nebo <b>Take off the plan</b>.</li>
<li><b>Z nástěnky do plánu:</b> odkaz <b>Day plan</b> nad nástěnkou.</li>
<li><b>Požádat sklad o něco:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Ask the warehouse</b> → <b>Send to warehouse</b>.</li>
<li><b>Změnit místa vyložení:</b> <b>Materials check</b> → <b>Drop points</b>.</li>
<li><b>Zkontrolovat materiál dne:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Have enough</b> nebo chybějící počet → <b>Send to warehouse</b>.</li>
<li><b>Předat kontrolu dělníkovi:</b> <b>Materials check</b> → <b>Checked by</b>.</li>
<li><b>Přivézt materiál (sklad):</b> <b>Inventory</b> → <b>For production</b> → <b>Delivered</b>, nebo <b>Can't bring</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Plánování dne, přidělování kroků, změna pořadí: <b>Floor supervisor</b> pro danou továrnu.</li>
<li>Zobrazení Day plan bez možnosti změn: <b>Foreman</b>.</li>
<li>Práce na kroku, který vám byl přidělen: <b>Operative</b>, na obrazovce dílny.</li>
<li>Vyžádání materiálu od skladu: kdokoli, kdo vidí Operations továrny.</li>
<li>Kontrola materiálu a místa vyložení: <b>Floor supervisor</b>; kontrolu také dělník, kterému byla předána.</li>
<li>Přivážení materiálu do továrny: pozice ve skladu, která pracuje ve skladu.</li>
</ul>
</aside>
