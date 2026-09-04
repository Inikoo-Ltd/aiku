---
title: Ako čítať HR nástenku
summary: Prvá obrazovka v HR — kto je dnes v práci, kto meškal, kto je neprítomný a prečo, plus dovolenky tohto týždňa, typy neprítomnosti tohto mesiaca a narodeniny. Každé číslo je odkaz na ľudí, ktorí sa za ním skrývajú.
date: 2026-09-02
source_date: 2026-09-02
tags: hr, attendance, leave, clocking
category: hr
---

<aside class="tldr">
Otvorte <b>HR</b> a pristanete práve tu. Horný pruh počíta, čo váš HR modul obsahuje. Päť kariet pod ním odpovedá na dennú otázku — <em>kto je tu, kto meškal, kto je neprítomný, kto chýba</em> — a kliknutím na ktorúkoľvek kartu sa zobrazia mená. Tabuľka pod kartami je dochádzka dňa a môžete sa vrátiť na ktorýkoľvek minulý deň. Spodný riadok je o neprítomnostiach a narodeninách. Ak riadite len ľudí, čítajte sekcie o dochádzke. Ak prevádzkujete aj dochádzkové terminály, pozrite si <a href="/docs/setting-up-a-clocking-machine-sk">Nastavenie dochádzkového terminálu</a>, kde sa dozviete, odkiaľ pochádzajú čísla "present" a "late".
</aside>

Otvorte ju vo **vašej organizácii → HR**. Nástenka je domovská stránka HR, takže sem vedie aj odkaz HR v bočnom paneli.

## Horný pruh: čo modul obsahuje

Šesť malých počítadiel, každé je odkaz na vlastný zoznam:

- **Employees** — ľudia, ktorí aktuálne *pracujú* (nie tí, čo odišli alebo ešte nenastúpili). Otvorí zoznam zamestnancov už filtrovaný na aktívnych.
- **Working places** — pracoviská, na ktorých sa ľudia zapisujú.
- **Responsibilities** — pracovné pozície, ktoré ľudia môžu zastávať.
- **Clocking machines** — kiosky a QR body, ktoré zaznamenávajú príchody.
- **Timesheets** — každý zaznamenaný pracovný deň.
- **Staff chat** — správy za posledných tridsať dní; otvorí analytiku chatu.

Toto je inventár, nie stav. Nič z toho sa počas dňa nemení.

## Päť kariet: kto je dnes kde

Tento riadok je časť, ktorú treba čítať každé ráno. Nadpis každej karty hovorí **today**, kým sa pozeráte na dnešok, a toto slovo zmizne, keď sa vrátite na skorší dátum.

- **Present** — zamestnanci s timesheetom za daný deň, teda tí, ktorí sa aspoň raz zapísali. Kto prišiel a už odišiel, stále počíta ako prítomný.
- **Annual leave** — zamestnanci so *schválenou* neprítomnosťou, ktorej typ patrí do kategórie dovolenky a ktorá pokrýva daný deň.
- **Sick leave** — to isté pre typy neprítomnosti v zdravotnej kategórii.
- **Late** — prítomní zamestnanci, ktorých *prvý* zápis dňa bol neskorý. Meškanie sa rozhodne v okamihu zápisu: neskôr než plánovaný začiatok plus pätnásťminútová tolerancia. Zamestnanci na čiastočný úväzok a dni označené v rozvrhu ako nepracovné nikdy nemeškajú.
- **Absent** — aktívni zamestnanci, ktorí sa ani nezapísali, ani nemajú schválenú neprítomnosť pokrývajúcu daný deň. Toto je karta, na ktorej záleží: je to zoznam ľudí, ktorým možno budete musieť zavolať.

**Kliknite na ktorúkoľvek kartu** a uvidíte mená. Karta dostane zvýraznený rám a tabuľka pod ňou sa prepne na danú skupinu:

- Present a Late zobrazia tabuľku dochádzky (Late ukáže len riadky s meškaním).
- Annual leave a Sick leave zobrazia každú osobu s jej typom neprítomnosti a dátumami.
- Absent zobrazí každú osobu a jej pracovné zaradenie. Kliknutím na meno otvoríte zamestnanca.

Odkaz **Show all** vedľa nadpisu tabuľky zruší výber. Výber žije v adrese stránky, takže kolegovi môžete poslať odkaz na "dnes neprítomných".

Dve veci o aritmetike. Zamestnanec na neprítomnosti, ktorý sa aj tak zapíše, sa objaví v Present *aj* na svojej karte neprítomnosti. A Present počíta každého, kto sa zapísal, vrátane človeka, ktorého stav zamestnania nie je "working" — takže v deň s návštevami alebo odchádzajúcimi sa karty nemusia presne zhodovať s počítadlom Employees.

## Tabuľka dochádzky

Pod kartami je dochádzka dňa, **najskoršie príchody hore**. Každý riadok je deň jedného zamestnanca:

- **Name** a pracovné zaradenie. Meno otvorí timesheet. Obrázok vedľa neho je fotka zo záznamu zamestnanca, prípadne avatar, ktorý si daná osoba nastavila vo svojom profile v aiku; bez jedného aj druhého sú to jej iniciály na jednoduchom kruhu. aiku nikdy nikomu nevymýšľa tvár.
- **Start at** — prvý zápis príchodu, červenou, ak bol neskorý.
- **End at** — posledný zápis odchodu alebo *Still working*, ak bola ich poslednou akciou registrácia príchodu.
- **Status** — *Late*, *Working* (stále na pracovisku) alebo *On time*.
- **Notes** — čo daná osoba napísala na dochádzkovom termináli pri prvom zápise, ak niečo napísala. Od neskorých príchodov sa zvyčajne žiada dôvod; ten skončí tu.
- **Working** a **Breaks** — doterajšie hodiny a minúty podľa timesheetu.
- **Clock in** a **Clock out** — koľko bolo jedných a druhých. Rozdiel jedného znamená, že sú stále vnútri.

### Pohľad na iný deň

Šípky a výber dátumu vedľa nadpisu presunú celý pohľad — karty aj tabuľku — na daný deň. Za dnešok sa dostať nedá. Zelená plaketka **N present** a počty na kartách sledujú zvolený dátum, takže "Absent" za minulý utorok je presne to, kto minulý utorok chýbal. **Today** vás vráti späť.

## Neprítomnosti a narodeniny

Spodný riadok nezávisí od zvoleného dátumu; vždy popisuje súčasnosť.

- **Leave overview** — stĺpec za každý pracovný deň, pondelok až piatok aktuálneho týždňa, s počtom zamestnancov so schválenou neprítomnosťou v daný deň. Dnešný stĺpec je zelený.
- **Employee leaves** — najbližších dvadsať schválených neprítomností, ktoré sa ešte neskončili, najskoršie hore, s typom a dátumami. Toto je váš zoznam "kto čoskoro nebude".
- **Leave types** — donut schválených neprítomností tohto mesiaca, jeden výsek na typ, počítajú sa zamestnanci, nie dni. V strede je celkový počet zamestnancov, ktorí majú tento mesiac nejakú neprítomnosť.
- **Birthdays this month** — aktívni zamestnanci, ktorí majú tento mesiac narodeniny, podľa dátumu, dnešní označení tortou.

Neprítomnosti, ktoré ešte *čakajú* na schválenie, sa na tejto stránke neobjavia nikde. Najprv ich schváľte v **Leave requests**.

## Rýchle akcie

Panel vpravo sú štyri veci, ktoré HR robí najčastejšie:

- **Create employee** — formulár popísaný v <a href="/docs/setting-up-a-new-employee-sk">Nastavenie nového zamestnanca</a>.
- **Record leave** — zapíšte neprítomnosť za niekoho iného; schváli sa v okamihu uloženia, takže sa na nástenke započíta hneď.
- **Leave requests** — fronta toho, o čo zamestnanci požiadali.
- **Leave calendar** — mesačný pohľad na to, kto je neprítomný.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Nástenka:</b> vaša organizácia → <b>HR</b>.</li>
<li><b>Kto je neprítomný:</b> kliknite na kartu <b>Absent</b>; kliknutím na meno otvoríte zamestnanca.</li>
<li><b>Prečo niekto meškal:</b> kliknite na kartu <b>Late</b> a prečítajte si stĺpec <b>Notes</b>, alebo otvorte timesheet z jeho mena.</li>
<li><b>Fotka namiesto iniciál:</b> otvorte zamestnanca → <b>Edit</b> → <b>Photo</b> (s právami na úpravu HR). Kto má prihlásenie do aiku, môže si vlastný avatar nastaviť aj v <b>Profile</b>.</li>
<li><b>Iný deň:</b> šípky alebo výber dátumu nad tabuľkou; <b>Today</b> pre návrat.</li>
<li><b>Čas začiatku, voči ktorému sa meria meškanie:</b> <b>HR → Shift Schedules</b>. Pätnásťminútová tolerancia je pevná; ak vaša organizácia potrebuje inú, spýtajte sa podpory aiku.</li>
<li><b>Dostať čakajúcu neprítomnosť na nástenku:</b> <b>HR → Leave requests</b> → schváliť.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Oprávnenia, ktoré potrebujete</strong>
<ul>
<li>Na zobrazenie nástenky potrebujete v organizácii práva <b>HR view</b> alebo rolu HR supervízora.</li>
<li>Rýchle akcie (vytváranie zamestnancov, zápis neprítomnosti) potrebujú práva <b>HR edit</b>.</li>
</ul>
</aside>
