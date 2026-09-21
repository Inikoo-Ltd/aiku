---
title: Založenie ticketu z chatu
summary: Premeňte konverzáciu so zákazníkom na ticket bez prepisovania, držte chat otvorený, kým nie je práca hotová, a nechajte toho, kto to opraví, oznámiť to zákazníkovi a konverzáciu zavrieť.
date: 2026-09-21
source_date: 2026-09-21
tags: chat, tickets, help desk
category: crm
---

<aside class="tldr">
Keď si konverzácia vyžaduje prácu, ktorá ju prežije - chybu, chýbajúcu objednávku, niečo, čo treba zapísať - založte <b>ticket</b> priamo z chatu. Ticket si so sebou nesie zákazníka, obchod a konverzáciu, takže nikto nemusí neskôr hľadať, o čo išlo. Zaškrtnite <b>Mark as blocked</b> (označiť ako blokované) a chat nemožno zatvoriť, kým ticket nie je <b>Done</b> alebo <b>Cancelled</b>, čím sľub zákazníkovi prestane byť na konci zmeny zabudnutý.
</aside>

## Založenie

Otvorte konverzáciu v <b>Chat</b>, potom menu <b>&#8942;</b> vpravo hore vo vlákne, vedľa <b>Ignore</b> a tlačidla zákazníka. Zvoľte <b>Create Ticket</b> (založiť ticket).

Konverzácia patrí tomu, kto ju drží, a rovnako aj toto. Ak je konverzácia pridelená kolegovi, možnosť je sivá a povie vám, kto sa jej venuje: najprv chat prevezmite alebo požiadajte supervízora. Supervízori a organizační administrátori majú túto možnosť dostupnú na každej konverzácii, takže chat zanechaný niekým, kto už odišiel domov, sa dá vyriešiť aj tak.

## Vyplnenie

<b>Subject</b> (predmet) je jeden riadok hovoriaci, čo je zle. Je to to, čo každý vidí v zoznamoch ticketov, preto píšte problém, nie pozdrav: "Chýba sledovanie zásielky pri objednávke 57316", nie "Otázka zákazníka".

<b>Details</b> (detaily) je to, čo zákazník nahlásil, jeho vlastnými slovami. Markdown funguje, takže <b>**tučné**</b>, zoznamy aj odkazy vyjdú tak, ako čakáte. Vložte snímku obrazovky priamo do poľa alebo do neho pretiahnite súbory - až päť obrázkov alebo dokumentov.

<b>Priority</b> (priorita) zostáva Normal, pokiaľ zákazník nie je blokovaný alebo nejde o peniaze. <b>Kind</b> (typ) je Bug, Documentation alebo Data integrity; nechajte ho nenastavený, ak si nie ste istí, inžinier ho môže nastaviť neskôr. Nič na tomto formulári nie je konečné - všetko sa dá na tickete neskôr zmeniť.

Riadok dole hovorí, že ticket sa založí ako <b>Customer support</b> (zákaznícka podpora) ticket, čo znamená, že nesie referenciu CUS a zostáva pri zákazníckom servise.

## Mark as blocked

Po zaškrtnutí nemožno túto konverzáciu zatvoriť, kým ticket nie je vyriešený alebo zrušený. Ukončenie chatu je odmietnuté, a odmietnutie uvedie, ktorý ticket ho drží.

Drží to iba nás. Zákazník môže konverzáciu zatvoriť aj tak zo svojej strany a chat, ktorý stíchne, sa aj tak zatvorí automaticky - zmyslom je zabrániť, aby <em>my</em> odložili konverzáciu, ktorej práca ešte stále nie je hotová.

Voľba typu <b>Bug</b> zaškrtne políčko za vás, pretože pri chybe zvyčajne zákazník čaká na nás. Je to návrh, nie pravidlo: odškrtnite ho, ak je toto nahlásenie chyby len poznámka na neskôr, a nie niečo, na čo zákazník čaká.

<b>Kedy ho zaškrtnúť.</b> Zaškrtnite ho, keď zákazníkovi dlhujeme odpoveď, ktorá závisí od ticketu. Nechajte nezaškrtnuté, keď je ticket naša vlastná interná záležitosť - poznámka do dokumentácie, upratanie - a zákazník už bol obslúžený. Chat držaný otvorený kvôli internej záležitosti je chat, ktorý nikto nikdy nezatvorí.

## Nechať ho zavrieť vývojárovi

Zaškrtnutie <b>Mark as blocked</b> ponúkne druhú možnosť: <b>Let the developer close this chat when the ticket is settled</b> (nechať vývojára zavrieť tento chat, keď sa ticket vybaví).

Nezaškrtnuté - chat čaká na vás: ticket sa vybaví, blokovanie sa uvoľní a zákazníkovi to poviete a konverzáciu zavriete vy.

Zaškrtnuté - urobí to za vás ten, kto ticket vybaví. Pri označení <b>Done</b> (hotovo) alebo <b>Cancel</b> (zrušiť) napíše záverečnú poznámku, tá sa pošle tomuto zákazníkovi do tejto konverzácie a konverzácia sa zavrie. Ide von tým kanálom, ktorým písal - chat na webe, e-mail alebo WhatsApp - podpísaná ako vývojár, nie menom, s ktorým zákazník nikdy nehovoril. Oba dialógy na zavretie upozornia toho, kto píše, že si to zákazník prečíta.

<b>WhatsApp má limit, ktorý nestanovujeme my.</b> Meta prenesie napísanú odpoveď len do dňa od poslednej správy zákazníka. Na neskôr vybavený ticket sa tam už odpovedať nedá, takže sa konverzácia zavrie bez správy a na tickete zostane, že zákazníkovi to nebolo možné oznámiť. Čo sa stalo - oznámené a zavreté, zavreté potichu, alebo ani jedno - je zapísané na tickete, pretože "zákazníkovi sme to povedali" a "skúsili sme to" sú dve rôzne veci.

<b>Na čo to je.</b> Chyba, na ktorú zákazník čaká: správu si zaslúži v deň, keď je opravená, nie vtedy, keď si niekto nabudúce prečíta ticket. Na čo to nie je: čokoľvek, kde odpoveď potrebuje vaše slová, nie slová inžiniera.

## Sledovanie ticketu neskôr

Hlavička vlákna počíta, čo je na konverzácii ešte otvorené. Kliknutím na tento počet sa bočný panel otvorí na <b>Tickets</b>, so zoznamom každého ticketu založeného z tohto chatu, s jeho referenciou, stavom, typom a dátumom. Kliknutím na riadok si prečítate ticket bez opustenia konverzácie.

Keď je niektorý z nich blokujúci, tlačidlo počtu v hlavičke sa zmení na jantárové a nesie zámok, rovnako aj riadok daného ticketu v zozname. Podržanie kurzora nad zámkom povie prečo. Tlačidlo zmizne, keď je všetko založené z konverzácie vybavené.

Na uvoľnenie blokovania dokončite prácu a nastavte ticket na <b>Done</b>, alebo ho <b>Cancel</b>-nite (zrušte), ak sa ukázalo, že nešlo o nič. Blokovanie sa uvoľní okamžite; nič ďalšie netreba robiť. Ak blokuje viacero ticketov, musia byť vybavené všetky.

## Čo sa dostane na ticket

Zákazník a obchod sa doplnia automaticky a vy ste zaznamenaný ako reportér. Ak chyba potrebuje inžiniera urýchlene, spomeňte ho menom v komentári - ticket povie, koho a ako spomenúť.

Ticket si drží samotnú konverzáciu, pod popisom: kto je zákazník, s odkazom na jeho kartu, kto držal chat, a odkazy späť naň. Otvorte tam <b>Conversation</b> (konverzácia) a celú výmenu si prečítate priamo na tickete, v poradí, bez toho, aby ste niekam odchádzali - len na čítanie, pretože ticket nie je miesto, odkiaľ sa odpovedá zákazníkovi.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Založiť ho:</b> <b>Chat</b> &rarr; otvorte konverzáciu &rarr; <b>&#8942;</b> &rarr; <b>Create Ticket</b>.</li>
<li><b>Držať chat otvorený:</b> zaškrtnite <b>Mark as blocked</b> pred stlačením <b>Create</b>.</li>
<li><b>Pozrieť, čo je otvorené:</b> tlačidlo s počtom v hlavičke vlákna &rarr; <b>Tickets</b> v bočnom paneli.</li>
<li><b>Uvoľniť blokovanie:</b> otvorte ticket &rarr; <b>Done</b>, alebo <b>Cancel</b>.</li>
<li><b>Nechať vývojára oznámiť to zákazníkovi:</b> zaškrtnite aj <b>Let the developer close this chat</b> pred stlačením <b>Create</b>.</li>
<li><b>Prečítať si konverzáciu na tickete:</b> otvorte ticket &rarr; <b>Conversation</b>, pod popisom.</li>
</ul>
</aside>
