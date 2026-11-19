---
title: Odpovedanie zákazníkom pomocou vášho AI asistenta
summary: Pripojte k aiku AI asistenta, ktorého už používate, a požiadajte ho o pomoc s chatom. Prečíta konverzáciu so všetkým, čo aiku vie (zákazník, jeho objednávky, spomenuté produkty, pravidlá obchodu a jazyk odpovede), a uloží na chat návrh odpovede, ktorý skontrolujete a odošlete z aiku.
date: 2026-10-15
source_date: 2026-10-15
tags: crm, chat, ai
category: help-desk
series: Chat
order: 6
---

<aside class="tldr">
Pre zákaznícky servis. Keď vám to administrátor zapne, AI asistent, ktorého pripojíte k aiku, vám vie vypísať chaty, ktoré na vás čakajú, a otvoriť jeden z nich so všetkým, čo o ňom aiku vie. Povedzte mu, čo má odpoveď obsahovať, a napíše ju v jazyku zákazníka a uloží na chat ako <b>návrh</b>. Návrh si prečítate v aiku, upravíte, čo chcete, a odošlete ho sami. Kým to neurobíte, zákazníkovi nepríde nič.
</aside>

## Načo slúži

Návrhy, ktoré aiku píše samo, majú len jeden pokus a fakty sa im vyberú vopred. Váš asistent môže ísť ďalej: prečíta celú konverzáciu, objednávky zákazníka, produkty, ktoré spomína, a pravidlá obchodu, a môže sa vás opýtať, čo chcete povedať. Rozhodujete vy: o pomoc žiadate, len keď ju chcete, a vy určujete, čo odíde.

> *"Otvor najstarší chat, ktorý na mňa čaká. Zákazník píše, že mu dva hrnčeky prišli rozbité. Napíš odpoveď, v ktorej sa ospravedlníme a požiadame o fotku každého hrnčeka a krabice."*

Funguje s akýmkoľvek asistentom, ktorý sa vie pripojiť k aiku. S asistentom hovorte vo svojom jazyku; zákazníkovi odpovie v jeho.

## Skôr než začnete

**1. Požiadajte administrátora, aby to zapol.** Vo vašom používateľskom účte pod <b>Access</b> (prístup) zapne <b>Can connect AI assistant</b> (môže pripojiť AI asistenta) a potom <b>Can serve customers</b> (môže obsluhovať zákazníkov).

**2. Potrebujete svoje bežné oprávnenie na chat v obchode.** Asistent vidí iba chaty obchodov, kde pracujete s chatom, a návrhy ukladá iba na chaty, ktoré sú vaše alebo ich nikto nemá.

**3. Pripojte asistenta k aiku.** Pridajte v asistentovi aiku ako konektor s adresou `https://app.aiku.io/mcp/aiku` a keď sa to žiada, prihláste sa svojím účtom aiku. Robí sa to raz.

## Ako prebieha konverzácia

1. **Opýtajte sa, čo čaká.** *"Ktoré chaty na mňa čakajú?"* Asistent vypíše chaty vašich obchodov, kde posledná správa zákazníka ešte nemá odpoveď, ktoré drží niekto z vás alebo nikto, od tých, čo čakajú najdlhšie. Každý má svoje Chat ID, obchod, kto písal a pred koľkým časom.
2. **Otvorte jeden.** *"Otvor prvý."* Môžete mu dať aj Chat ID skopírované z panela <b>Overview</b> (prehľad) chatu.
3. **Povedzte, čo má odpoveď obsahovať.** Asistent ju napíše z faktov, ktoré mu aiku dalo.
4. **Návrh uloží na chat.** Otvorte chat v aiku: návrh je v AI poli nad poľom na správu, označený vaším menom a asistentom, ktorého ste použili. Stlačením <b>Use</b> (použiť) ho vložíte do poľa na správu, upravíte, čo chcete, a odošlete. Alebo stlačte <b>Discard</b> (zahodiť).

Asistent vždy napíše celú odpoveď. Tam, kde by ste rozhodli vy, napríklad dobropis alebo výmena, napíše to, čo zvyčajne rozhodujú kolegovia vášho obchodu, a zoznam pod návrhom označí túto otázku ako „naše obvyklé rozhodnutie, skontrolujte". Ak je tento prípad iný, zmeňte to. Obvyklé odpovede vášho obchodu sa učí z odpovedí, ktoré váš tím už poslal.

<aside class="tip">
Požiadajte ho, aby najprv vysvetlil, a až potom písal: <i>"Čo vieme o tejto objednávke?"</i> Čítanie nikdy nič nemení.
</aside>

## Čo asistent dostane s každým chatom

| Čo | Prečo |
|---|---|
| **Konverzácia** | Posledných 30 správ. Slová zákazníka sa berú ako slová zákazníka, nikdy ako pokyny pre asistenta. |
| **Jazyk odpovede** | aiku ho určí podľa toho, čo zákazník napísal. Návrh v inom jazyku sa odmietne. |
| **Zákazník** | Meno, referencia, zostatok a posledné objednávky, ak je chat prepojený so zákazníkom. |
| **Čo aiku vyhľadalo** | Objednávky a produkty, ktoré zákazník spomína, kódom alebo slovami, a odpovede na bežné otázky, napríklad o cene doručenia alebo spôsoboch platby. Objednávky iba pre zákazníka, ktorého aiku už pozná. |
| **Pravidlá obchodu** | Chatová politika obchodu, jeho poznámky a návody celé, stránky s pravidlami, ktoré odpovedajú na to, na čo sa zákazník pýta, a názvy ostatných. |
| **Predchádzajúce konverzácie** | Téma a zhrnutie posledných konverzácií zákazníka. |

## Čo vie a čo nevie

**Vie** vypísať vaše čakajúce chaty, prečítať chat a uložiť naň návrh odpovede.

Tým istým prepínačom môže za vás aj konať v chate, no pri každej z týchto vecí vám vždy najprv presne ukáže, čo urobí, a pokračuje, až keď vlastnými slovami poviete áno: odoslať odpoveď, prevziať, zatvoriť alebo nechať chat čakať, prepojiť zákazníka, vybaviť reklamáciu a zmeniť objednávku, ktorá ešte nie je zabalená. Pozrite si [Rozhovor so zákazníkmi v Chate](/docs/customer-chat-sk).

**Nevie** vidieť chaty obchodov, kde s chatom nepracujete, uložiť návrh na chat kolegu ani odoslať čokoľvek bez vášho áno.

## Dobré vedieť

- **Návrh nahradí každý návrh, ktorý už na tom chate čaká**, vrátane takého, ktorý aiku napísalo samo.
- **Váš manažér vidí, ako sa návrhy používajú.** Chat › AI ukazuje, koľko návrhov od asistentov zamestnancov bolo odoslaných tak, ako boli napísané, upravených alebo zahodených, za každého asistenta.
- **Čo zdieľate, ide poskytovateľovi vášho asistenta.** Konverzácia a údaje zákazníka sa posielajú firme, ktorá stojí za asistentom, ktorého pripojíte. Zapnutie znamená, že s tým váš administrátor za vás súhlasil.
