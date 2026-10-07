---
title: Projekty v ticketoch
summary: Zoskupte tickety jednej veľkej práce do projektu, sledujte, ako postupuje, a zverejňujte novinky na jednom mieste namiesto správ v chate.
date: 2026-10-03
source_date: 2026-10-03
tags: tickets, projects, help desk
category: help-desk
---

<aside class="tldr">
Veľká práca - napríklad sťahovanie skladu - sú mnohé tickety a úlohy. <b>Projekt</b> (project) ich drží pokope s cieľom, tímom, míľnikmi a ukazovateľom pokroku. Každý vidí, ako to stojí, a tím zverejňuje <b>novinky</b> (progress updates) so značkou stavu na stránke projektu namiesto posielania správ v chate.
</aside>

## Nájdenie projektov

V ľavom menu otvorte <b>Tickets</b> a potom <b>Projects</b> (projekty) v hornom menu. Každý projekt je karta s jeho stavom zdravia, stavom, ukazovateľom pokroku, vlastníkom a tímom. Kliknutím ho otvoríte.

## Založenie projektu

Stlačte <b>New project</b> (nový projekt) a vyplňte <b>Name</b> (názov), <b>Goal</b> (cieľ - ako vyzerá hotová práca), <b>Start</b> (začiatok), <b>Target</b> (cieľový dátum, dokedy to chcete mať hotové), <b>Owner</b> (vlastník) a <b>Team</b> (tím). Stlačte <b>Create</b> (vytvoriť). Projekt môže založiť ktokoľvek.

## Stránka projektu

Hore pás ukazuje <b>Health</b> (zdravie: značka z poslednej novinky, ktorá nejakú má), <b>Status</b> (stav), <b>Owner and team</b> (vlastník a tím), ukazovatele pokroku a počty <b>Open</b> (otvorené), <b>Done</b> (hotové) a <b>Days left</b> (zostávajúce dni). Tlačidlá <b>New ticket</b> (nový ticket) a <b>New task</b> (nová úloha) vytvoria prácu, ktorá je už v projekte. <b>Edit</b> (upraviť) mení údaje projektu (len pre toho, kto môže projekt meniť, pozri nižšie).

Stránka má päť kariet: <b>Overview</b> (prehľad), <b>Work</b> (práca), <b>Timeline</b> (časová os), <b>Commits</b> (commity) a <b>Activity</b> (aktivita).

## Pridanie práce

Tickety a úlohy sa počítajú spolu. Na karte <b>Work</b>:

- <b>Vloženie referencií:</b> do poľa napíšte referencie, napríklad HELP-123, TASK-45 - aj viac naraz, tickety a úlohy zmiešane. Podľa potreby vyberte míľnik a stlačte <b>Add</b> (pridať).
- <b>New ticket</b> alebo <b>New task:</b> hore na stránke.
- <b>Z ticketu alebo úlohy:</b> bočný panel má štítok <b>Project</b> (projekt) a štítok míľnika. Kliknutím vyberiete projekt alebo míľnik, alebo zvoľte <b>No project</b> (bez projektu) či <b>No milestone</b> (bez míľnika) a odstránite ho.

Na vybratie položky zo stránky projektu kliknite na <b>x</b> vedľa nej.

## Karta Work

Hľadajte a filtrujte podľa <b>Tickets and tasks</b> (tickety a úlohy), míľnika, osoby (<b>Everyone</b> (všetci) alebo <b>Unassigned</b> (nepridelené)) a zaškrtnutím <b>Show closed</b> (zobraziť zatvorené) zahrniete hotovú prácu. Prepínajte medzi <b>List</b> (zoznam) a <b>Board</b> (nástenka; stĺpce <b>Todo</b> (na urobenie), <b>In progress</b> (rozpracované), <b>Done</b> (hotové) a <b>Cancelled</b> (zrušené), keď sa zobrazuje zatvorená práca). V zozname je práca zoskupená podľa míľnika a každý riadok má vlastný výber míľnika.

Práca, ktorú nesmiete vidieť, sa nezobrazí; stránka uvedie, koľko dôverných položiek je skrytých.

## Karta Overview

- <b>Goal</b> (cieľ) projektu.
- <b>Progress updates</b> (novinky): napíšte, čo sa pohlo, čo je zablokované a čo príde ďalej, podľa potreby označte <b>On track</b> (v poriadku), <b>At risk</b> (ohrozené) alebo <b>Off track</b> (mimo plánu) a stlačte <b>Post update</b> (zverejniť novinku). Novinky zostávajú v poradí, najnovšia hore. Vlastník a zvyšok tímu dostanú upozornenie hneď, v zvončeku v Aiku aj e-mailom; ten, kto novinku zverejnil, nie.
- <b>Milestones</b> (míľniky): zoznam so začiatkom a termínom a malým ukazovateľom pokroku pri každom míľniku (hotové položky z celku). Zaškrtnutím ho dokončíte; názov, poradie alebo odstránenie zmeníte malými ovládačmi. Po termíne a nezaškrtnutý je označený ako <b>Overdue</b> (po termíne).
- <b>Workload</b> (vyťaženie): pre každú osobu, koľko položiek je na urobenie, rozpracovaných a hotových.

## Timeline, Commits a Activity

- <b>Timeline:</b> míľniky na kalendári s čiarou <b>Today</b> (dnes) a graf <b>Burn-up</b>, ktorý porovnáva všetku prácu (<b>Scope</b>) s tým, čo je <b>Done</b>, týždeň po týždni, a čiaru <b>Ideal</b>.
- <b>Commits:</b> zmeny kódu, ktoré vyriešili tickety projektu, s <b>Version</b> (verzia), <b>Deployed</b> (nasadené), <b>Commit</b>, <b>Subject</b> (predmet) a <b>Ticket</b>. Objavia sa samy, keď sa nasadí oprava ticketu.
- <b>Activity:</b> čo sa stalo, najnovšie hore: pridaná alebo dokončená práca, novinky, nasadenia. Vytvára sa automaticky.

## Čítanie pokroku

Ukazovatele pokroku ukazujú, koľko práce je hotovej a ako ďaleko sme medzi začiatkom a cieľovým dátumom. Ukazovateľ práce zčervenie, keď je o viac ako 10 bodov za časom. Po cieľovom dátume počíta dni navyše. Zrušená práca sa do súčtov nepočíta.

## Cez AI asistenta

Môžete o to požiadať aj AI asistenta pripojeného k Aiku, vlastnými slovami: „ukáž mi projekt“, „daj HELP-123 a TASK-45 pod prvý míľnik“, „posuň termín druhého míľnika na budúci piatok“, „zverejni novinku: ohrozené, dodávateľ mešká“. Koná vo vašom mene, s rovnakými právami, aké máte na stránke.

## Kto môže čo meniť

Vlastník, členovia tímu a inžinieri, ktorí môžu prideľovať tickety, môžu projekt upravovať (<b>Edit</b>, pridávať alebo vyberať prácu, zverejňovať novinky, meniť míľniky). Ostatní ho môžu len čítať.

Presunúť jeden ticket alebo úlohu do projektu či z neho z jeho vlastného bočného panela môžu aj ľudia, ktorí na ňom pracujú: pri tickete tí, čo ho môžu aktualizovať, a jeho spolupracovníci; pri úlohe pridelená osoba, spolupracovníci a ten, kto ju zadal. Ktokoľvek iný to môže len vtedy, ak môže meniť projekt, v ktorom položka je, aj ten, do ktorého ide.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Pozrieť projekty:</b> <b>Tickets</b> &rarr; <b>Projects</b>.</li>
<li><b>Založiť projekt:</b> <b>New project</b> &rarr; vyplňte formulár &rarr; <b>Create</b>.</li>
<li><b>Pridať existujúcu prácu:</b> otvorte projekt &rarr; <b>Work</b> &rarr; napíšte referencie &rarr; <b>Add</b>.</li>
<li><b>Pridať novú prácu:</b> otvorte projekt &rarr; <b>New ticket</b> alebo <b>New task</b>.</li>
<li><b>Presunúť prácu dnu alebo von:</b> otvorte ticket alebo úlohu &rarr; štítok <b>Project</b> v bočnom paneli.</li>
<li><b>Nahlásiť pokrok:</b> otvorte projekt &rarr; <b>Overview</b> &rarr; píšte do poľa &rarr; <b>Post update</b>.</li>
</ul>
</aside>
