---
title: Ako požiadať help desk o pomoc
summary: Nahláste chybu alebo požiadajte o novú funkciu z aiku alebo zo Slacku, sledujte, čo sa s ňou deje, včas odpovedajte na otázky inžinierov a ohodnoťte opravu, keď je hotová.
date: 2026-09-14
source_date: 2026-09-14
tags: help desk, tickets, bugs
category: help-desk
help_routes: grp.tickets
---

<aside class="tldr">
Keď v aiku niečo nefunguje alebo potrebujete niečo, čo zatiaľ nevie, založte <b>ticket</b>. Dostane sa do fronty help desku, prevezme ho inžinier a vy ho sledujete na stránke <b>Tickets</b>, kým nie je <b>Done</b>. Dobrý ticket hovorí, čo sa deje, obsahuje odkaz na stránku, kde sa to deje, a snímku obrazovky. Všetko, čo na ticket napíšete, vidia ľudia, ktorí na ňom pracujú, preto to píšte tak, ako by ste im to povedali.
</aside>

## Kde nájdete tickety

V ľavom menu otvorte <b>Tickets</b>. <b>Dashboard</b> ukazuje <b>Mine</b>, teda tickety, ktoré ste založili a sú stále otvorené, a nedávno uzavreté tickety. <b>List</b> ukazuje všetky tickety, ktoré môžete vidieť, s filtrami na tie, ktoré ste nahlásili vy, na stav a na typ.

## Založenie ticketu v aiku

Najrýchlejšie je červené tlačidlo <b>Bug</b> na každej stránke alebo <b>Alt+Shift+B</b>. Samo doplní stránku, na ktorej práve ste. Na stránkach Tickets môžete tiež stlačiť <b>New ticket</b>.

- <b>Subject</b>: jeden riadok, ktorý hovorí, čo je zle alebo čo potrebujete. Je to jediné povinné pole.
- <b>Details</b>: čo ste urobili, čo ste čakali a čo sa stalo namiesto toho. Pri funkcii čo potrebujete a prečo.
- <b>Page where it happens</b>: odkaz na stránku.
- <b>Kind</b>: <b>Bug</b>, keď je niečo pokazené, <b>Feature request</b>, keď potrebujete niečo nové.
- <b>Module</b> a <b>Priority</b>: vyberte ich, ak ich poznáte; prioritu nechajte na normal, pokiaľ práca nestojí.
- Snímky obrazovky: vložte ich alebo pretiahnite do poľa s detailmi, najviac päť.

## Založenie ticketu zo Slacku

V Slacku môžete na akejkoľvek správe použiť skratku <b>Raise ticket</b> alebo napísať <b>/ticket</b>. Obe otvoria krátky formulár s rovnakými poľami. <b>/ticket</b> nasledovaný textom založí ticket hneď: prvý riadok je predmet a zvyšok sú detaily. Správu môžete zmeniť na ticket aj reakciou s emoji ticketu.

Ak ticket nemá odkaz ani snímku obrazovky, help desk o ne požiada vo vlákne. Bez nich sa ticket opravuje oveľa ťažšie.

Každý ticket má vlákno v Slacku. Odpovede v tomto vlákne sa pridajú k ticketu ako komentáre a komentáre napísané v aiku sa objavia vo vlákne.

## Sledovanie ticketu

Ticket prechádza týmito stavmi:

- <b>Todo</b>: čaká na inžiniera.
- <b>Assigned</b>: inžinier ho má vo svojom zozname.
- <b>In progress</b>: niekto na ňom pracuje.
- <b>Waiting</b>: inžinier potrebuje od vás odpoveď.
- <b>Done</b>: opravené alebo dodané.
- <b>Cancelled</b>: uzavreté bez opravy.

Dostanete upozornenie, keď sa vás inžinier niečo spýta, keď je ticket hotový a keď niekto pridá komentár. Komentáre sa zobrazia v odznaku ticketu a podľa vašich nastavení aj e-mailom, v Slacku alebo ako notifikácia v prehliadači na počítači či telefóne, v ľubovoľnej kombinácii.

## Keď sa vás inžinier niečo spýta

Ak inžinier potrebuje viac informácií, ticket prejde do stavu <b>Waiting</b> a dostanete jeho otázku. Odpovedzte komentárom na tickete alebo v jeho vlákne v Slacku. Štandardne máte <b>72 hodín</b>; ak nikto neodpovie včas, ticket sa zruší s poznámkou "No reply for … days".

Odpoveď na čakajúci alebo zrušený ticket ho vráti do stavu <b>Todo</b>, takže neskorá odpoveď sa nikdy nestratí.

## Uzavretie a hodnotenie

Ak problém zmizol alebo funkciu už nepotrebujete, môžete svoj ticket zrušiť. Keď sa ticket uzavrie, zobrazí sa otázka <b>How did we do?</b>: dajte mu hviezdičky, prípadne komentár, a stlačte <b>Send rating</b>. Každý ticket môžete ohodnotiť raz.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Nahlásiť chybu na stránke, kde práve ste:</b> červené tlačidlo <b>Bug</b> alebo <b>Alt+Shift+B</b>.</li>
<li><b>Založiť ticket z menu:</b> <b>Tickets</b> → <b>New ticket</b>.</li>
<li><b>Pozrieť si otvorené tickety:</b> <b>Tickets</b> → <b>Dashboard</b> → <b>Mine</b>.</li>
<li><b>Odpovedať na otázku inžiniera:</b> otvorte ticket → napíšte komentár alebo odpovedzte v jeho vlákne v Slacku.</li>
<li><b>Ohodnotiť uzavretý ticket:</b> otvorte ticket → <b>How did we do?</b> → <b>Send rating</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Potrebné oprávnenia</strong>
Každý, kto sa môže prihlásiť do aiku, môže zakladať tickety, komentovať ich a sledovať ich. Stav svojho ticketu môžete zmeniť, ale nie jeho prioritu, modul, štítky ani priradenú osobu; to patrí help desku. Dôverné tickety vidí iba ten, kto ich založil, a lead engineers.
</aside>
