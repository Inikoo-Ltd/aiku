---
title: Ako zmeniť, čo používateľ vidí a môže robiť
summary: Oprávnenia v aiku sú pracovné pozície. Kde nájsť pozície používateľa, ako pridať alebo odobrať pozíciu, čo znamená každý stupeň a prečo niektorý prístup prichádza spolu s iným oddelením.
date: 2026-09-02
source_date: 2026-09-02
tags: hr, sysadmin, permissions
category: hr
---

<aside class="tldr">
V aiku nikto nedostáva oprávnenie priamo. Ľuďom sa prideľujú <em>pracovné pozície</em> (Accounting worker, Warehouse supervisor, Organisation administrator...) a každá pozícia nesie pevný balík oprávnení. Ak chcete zmeniť, čo niekto vidí, zmeníte jeho pozície. K tej istej obrazovke vedú dvoje dvere: <b>Sysadmin → Users</b> pre ktoréhokoľvek používateľa v skupine a <b>HR → Employees</b> pre zamestnanca vašej organizácie. Menu sa prestavia pri najbližšom načítaní stránky.
</aside>

## Dvoje dvere, jedna obrazovka

**Cez Sysadmin.** Otvorte **Sysadmin → Users**, kliknite na používateľské meno, stlačte **Edit** a otvorte kartu **Permissions**. Dá sa otvoriť pre ktoréhokoľvek používateľa v skupine bez ohľadu na to, v ktorej organizácii pracuje. Na to musíte vidieť menu Sysadmin.

**Cez HR.** Otvorte svoju organizáciu, prejdite na **HR → Employees**, kliknite na osobu, stlačte **Edit** a posuňte sa na **Job Positions (permissions)**. Je to ten istý ovládací prvok, obmedzený na organizáciu, v ktorej sa nachádzate.

V oboch prípadoch uvidíte zoznam oddelení, v každom stupeň na výber, a ikonu **uložiť**. Nič sa nezmení, kým nestlačíte uložiť.

## Ako čítať obrazovku oprávnení

Obrazovka má dve časti.

**Group permissions** sú hore. Platia všade, v každej organizácii:

- **Group admin** — vidí a môže robiť všetko, v každej organizácii. Po jeho výbere sa ostatné možnosti zošednú, lebo už niet čo udeliť.
- **Group sysadmin** — používateľské účty, menu Sysadmin, systémové nastavenia.
- **Group webmaster** — webové stránky a webový obsah v celej skupine.
- **Supply Chain**, **Goods**, **Masters** — spoločný katalóg a nákup, ktoré stoja nad jednotlivými organizáciami. Masters má štyri stupne: Manager, Clerk, Media a Viewer.

**Organisations** sú uvedené nižšie s počtom pozícií, ktoré osoba v každej z nich má. Kliknutím na názov organizácie ju rozbalíte a uvidíte jej oddelenia, jeden riadok na oddelenie:

| Oddelenie | Dostupné stupne |
|---|---|
| Org admin | Organisation Administrator — všetko v tejto organizácii |
| Human Resources | Supervisor, Worker |
| Accounting | Supervisor, Worker |
| Shop admin | Shop Administrator — všetko vo zvolených obchodoch |
| Shopkeeping | Supervisor, Worker |
| Marketing | Supervisor, Worker |
| PPC | PPC |
| Customer Service | Supervisor, Worker, Viewer |
| Buyer | Buyer |
| Warehouse | Supervisor, Stock Controller |
| Goods out | Supervisor, Picker, Replenisher, Packer |
| Production | Supervisor, Worker |
| Fulfilment | Supervisor, Warehouse Clerk, Office Clerk |

Oddelenia, ktoré patria k obchodu, skladu alebo fulfilmentu (Shopkeeping, Marketing, Customer Service, Warehouse, Goods out, Fulfilment...), sa pýtajú, *ktoré* obchody alebo sklady pozícia pokrýva. Stlačte **Show details** v riadku a zaškrtnite ich (tlačidlo sa zobrazí, len keď má organizácia na výber viac ako jeden). Osoba môže byť Customer Service worker v jednom obchode a v inom nič.

**Supervisor verzus Worker.** Worker vidí a upravuje bežné záznamy oddelenia. Supervisor má to isté plus riadiace obrazovky a nastavenia oddelenia. Vyberte jedno alebo druhé; výber stupňa supervisor nahradí stupeň worker v danom oddelení.

**Organisation Administrator** zaškrtne všetky oddelenia v danej organizácii naraz, takže ak ho zvolíte, na ostatných riadkoch tej organizácie už nezáleží.

## Ako urobiť zmenu

1. Otvorte oprávnenia používateľa cez ktorékoľvek z dverí vyššie.
2. Rozbaľte organizáciu.
3. Kliknite na požadovaný stupeň v riadku oddelenia. Kliknutie na už zvolený stupeň ho odoberie, takže oddelenie bez výberu znamená žiadny prístup k nemu.
4. Pri oddeleniach viazaných na obchod alebo sklad stlačte **Show details** a zaškrtnite obchody alebo sklady.
5. Stlačte ikonu **uložiť** pre danú organizáciu. Group permissions majú vlastnú ikonu uložiť hore.

Osoba sa nemusí odhlásiť. Jej menu sa prestavia pri najbližšom načítaní stránky.

## Prístup, ktorý prichádza spolu s iným oddelením

Niektoré pozície zahŕňajú náhľad, len na čítanie, do susedného oddelenia, lebo to práca vyžaduje. Najčastejšia otázka: **Accounting worker a Accounting supervisor vidia Human Resources**, len na čítanie. Preto sa menu HR zobrazuje finančným pracovníkom, ktorí nikdy nedostali pozíciu v HR. Je to súčasť samotnej pozície Accounting a nedá sa vypnúť pre jednotlivca. Odobrať to znamená zmeniť, čo pozícia Accounting obsahuje, pre všetkých, čo je zmena v aiku a nie nastavenie.

Samostatný prístup do Human Resources len na čítanie nie je pozícia, ktorú by sa dalo na obrazovke vybrať. Ak má niekto HR iba *vidieť*, dnes je voľba Accounting worker alebo nič.

## Ako zistiť, kto má k niečomu prístup

Ak chcete zistiť, kto v organizácii vidí dané oddelenie, najrýchlejšia cesta je **HR → Employees**, kde sú uvedené pozície každého zamestnanca, a **Sysadmin → Users** pre účty, ktoré nie sú zamestnancami vašej organizácie. Pamätajte, že ktokoľvek s **Group admin** alebo **Organisation Administrator** má prístup bez toho, aby sa zobrazovala pozícia oddelenia.

<aside class="wayfinder">
<h3>Kde kliknúť v aiku</h3>
<ul>
<li><b>Sysadmin → Users → </b><i>používateľské meno</i><b> → Edit → Permissions</b> — ktorýkoľvek používateľ v skupine.</li>
<li><i>Organizácia</i><b> → HR → Employees → </b><i>meno</i><b> → Edit → Job Positions (permissions)</b> — zamestnanci vašej organizácie.</li>
<li>V každom bloku organizácie: kliknite na stupeň, <b>Show details</b> na výber obchodov alebo skladov, potom ikona <b>uložiť</b> pre danú organizáciu.</li>
</ul>
</aside>

<aside class="wayfinder">
<h3>Oprávnenia, ktoré potrebujete</h3>
<ul>
<li>Dvere Sysadmin vyžadujú pozíciu <b>Group sysadmin</b> (alebo Group admin).</li>
<li>Dvere HR vyžadujú pozíciu <b>Human Resources</b>, Worker alebo Supervisor, v danej organizácii (alebo Organisation Administrator).</li>
</ul>
</aside>
