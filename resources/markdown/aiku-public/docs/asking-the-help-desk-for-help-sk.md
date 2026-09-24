---
title: Ako požiadať help desk o pomoc
summary: Nahláste chybu alebo požiadajte o novú funkciu z aiku alebo zo Slacku, sledujte, čo sa s ňou deje, včas odpovedajte na otázky inžinierov a ohodnoťte opravu, keď je hotová.
date: 2026-09-15
source_date: 2026-09-15
tags: help desk, tickets, bugs
category: help-desk
help_routes: grp.tickets
---

<aside class="tldr">
Keď v aiku niečo nefunguje alebo potrebujete niečo, čo zatiaľ nevie, založte <b>ticket</b>. Dostane sa do fronty help desku, prevezme ho inžinier a vy ho sledujete na stránke <b>Tickets</b>, kým nie je <b>Done</b>. Dobrý ticket hovorí, čo sa deje, obsahuje odkaz na stránku, kde sa to deje, a snímku obrazovky. Všetko, čo na ticket napíšete, vidia ľudia, ktorí na ňom pracujú, preto to píšte tak, ako by ste im to povedali.
</aside>

## Kde nájdete tickety

V ľavom menu otvorte <b>Tickets</b>. <b>Dashboard</b> ukazuje <b>Mine</b>, teda tickety, ktoré ste založili a sú stále otvorené, a nedávno uzavreté tickety. <b>List</b> ukazuje všetky tickety, ktoré môžete vidieť, s filtrami na tie, ktoré ste nahlásili vy, na stav a na typ.

<b>Tip:</b> počítadlo ticketov na pravej strane obrazovky ukazuje vaše tickety v stave <b>To do</b>, <b>In progress</b> alebo <b>Waiting for my reply</b>. Kliknutím ich otvoríte. V časti <b>Recent</b> vidíte posledné zmeny na vašich ticketoch, napríklad nový stav alebo nový komentár. Tie, ktoré ste ešte neotvorili, sú označené bodkou, ktorá zmizne, keď ticket otvoríte.

## Založenie ticketu v aiku

Najrýchlejšie je červené tlačidlo <b>Bug</b> na každej stránke alebo <b>Alt+Shift+B</b>. Samo doplní stránku, na ktorej práve ste. Na stránkach Tickets môžete tiež stlačiť <b>New ticket</b>.

- <b>Subject</b>: jeden riadok, ktorý hovorí, čo je zle alebo čo potrebujete. Je to jediné povinné pole.
- <b>Details</b>: čo ste urobili, čo ste čakali a čo sa stalo namiesto toho. Pri funkcii čo potrebujete a prečo.
- <b>Page where it happens</b>: odkaz na stránku.
- <b>Kind</b>: <b>Bug</b>, keď je niečo pokazené, <b>Feature request</b>, keď potrebujete niečo nové.
- <b>Module</b> a <b>Priority</b>: vyberte ich, ak ich poznáte; prioritu nechajte na normal, pokiaľ práca nestojí.
- Snímky obrazovky a súbory: vložte ich, pretiahnite do poľa s detailmi alebo stlačte <b>Attach</b>. Naraz môžete pridať najviac päť: obrázky, súbory PDF, Word, Excel, CSV, ZIP, RAR a 7z do 10 MB každý a krátke videá do 50 MB. Veľmi pomôže krátky záznam obrazovky, na ktorom je problém vidieť.

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
- <b>Reporter replied</b>: odpovedali ste a je opäť rad na inžinierovi.
- <b>Waiting for deployment</b>: oprava je hotová a do aiku sa dostane s najbližšou aktualizáciou. Vtedy sa ticket uzavrie sám.
- <b>Done</b>: opravené alebo dodané.
- <b>Cancelled</b>: uzavreté bez opravy.

Dostanete upozornenie, keď sa vás inžinier niečo spýta, keď je ticket hotový a keď niekto pridá komentár. Komentáre sa zobrazia v odznaku ticketu a podľa vašich nastavení aj e-mailom, v Slacku alebo ako notifikácia v prehliadači na počítači či telefóne. Pozrite si [Upozornenia na počítači a telefóne](/docs/getting-notifications-on-your-computer-and-phone-sk).

## Keď sa vás inžinier niečo spýta

Ak inžinier potrebuje viac informácií, ticket prejde do stavu <b>Waiting</b> a dostanete jeho otázku. Odpovedzte komentárom na tickete alebo v jeho vlákne v Slacku. Štandardne máte <b>72 hodín</b>; ak nikto neodpovie včas, ticket sa zruší s poznámkou "No reply for … days".

Odpoveď na čakajúci alebo zrušený ticket ho vráti späť inžinierovi, takže neskorá odpoveď sa nikdy nestratí.

## Súbory na tickete

Všetky súbory na vašom tickete sú spolu v časti <b>Attachments</b>. Kliknutím si súbor pozriete bez sťahovania. Keď kliknete na súbor ZIP, RAR alebo 7z, uvidíte, aké súbory obsahuje, a tlačidlo na jeho stiahnutie. Komentáre si tiež môžete zoradiť od najnovších alebo od najstarších a aiku si vašu voľbu zapamätá.

## Uzavretie a hodnotenie

Ak problém zmizol alebo funkciu už nepotrebujete, môžete svoj ticket zrušiť. aiku vás najprv požiada o krátku poznámku, aby inžinier vedel, prečo už ticket nie je potrebný. Keď váš ticket uzavrie inžinier, tiež zanechá poznámku, v ktorej vám napíše, čo urobil. Keď sa ticket uzavrie, zobrazí sa otázka <b>How did we do?</b>: dajte mu hviezdičky, prípadne komentár, a stlačte <b>Send rating</b>. Každý ticket môžete ohodnotiť raz.

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
Každý, kto sa môže prihlásiť do aiku, môže zakladať tickety, komentovať ich a sledovať ich. Ticket posúva ďalej help desk: sami nemôžete meniť jeho stav, prioritu, modul, štítky ani priradenú osobu, ale môžete sledovať každý krok a odpovedať v komentároch. Niekedy na vašom tickete pracuje viac inžinierov: vedie ho inžinier, ktorému je priradený, a ten môže pridať kolegov ako spolupracovníkov (collaborators), aby mu pomohli. Dôverné tickety vidí iba ten, kto ich založil, inžinier, ktorý na nich pracuje, ich spolupracovníci a lead engineers.
</aside>
