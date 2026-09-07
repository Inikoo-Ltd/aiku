---
title: Sledovanie objednávky od košíka po expedíciu
summary: Pozrite sa na celú cestu, ktorou predajná objednávka prechádza v aiku, od zákazníkovho košíka cez vychystávanie a balenie až po fakturáciu a expedíciu, a kde ju v jednotlivých krokoch skontrolovať.
date: 2026-09-02
source_date: 2026-09-02
tags: orders, orders lifecycle
category: orders
---

<aside class="tldr">
Každá predajná objednávka prechádza pevnou sadou stavov, od <b>In Basket</b> po <b>Dispatched</b>. Priebeh ktorejkoľvek objednávky môžete sledovať z obrazovky obchodu <b>Orders</b>: otvorte objednávku a uvidíte, v akom je stave, jej transakcie, dodacie listy a po vyfakturovaní aj jej faktúru. Tento článok prechádza celou touto cestou v poradí.
</aside>

## Kde objednávka začína: In Basket

Zákazník zostavuje objednávku pridávaním produktov — aiku nazýva každý riadok produktu transakciou. Kým ešte pridáva a mení veci, objednávka je v stave **In Basket**. Nikam ešte nič nebolo odoslané a zákazník ju môže voľne upravovať.

## Submitted

Keď si zákazník prejde pokladňou, objednávka sa stane **Submitted**. Objednávku možno odoslať iba raz — opätovné odoslanie je zablokované, rovnako ako odoslanie objednávky, ktorá nemá vôbec žiadne transakcie.

Ak je objednávka už zaplatená, alebo ide o objednávku na dobierku, aiku ju hneď po odoslaní pošle priamo do skladu. Ak platba ešte chýba, objednávka čaká v stave **Submitted**, kým platba nepríde.

## In Warehouse a potom vychystávanie

Keď je objednávka odoslaná do skladu, aiku pre ňu vytvorí **delivery note** a objednávka sa presunie do stavu **In Warehouse**. Toto je poradová pozícia objednávky — čaká, kým ju vychystávač začne spracovávať.

Odtiaľ objednávka sleduje dodací list cez sklad:

- **Handling** — vychystávač práve objednávku vychystáva.
- **Waiting** (interne nazývané handling blocked) — vychystávanie sa zaseklo, napríklad preto, že sa nenašiel tovar.
- **Picked** — každý riadok je vychystaný.
- **Packing**, potom **Packed** — objednávka je zabalená a pripravená na odchod.

## Finalized: tu vzniká faktúra

Keď je dodací list finalizovaný, aiku presunie objednávku do stavu **Finalized** a v tom istom kroku vygeneruje jej faktúru. Objednávku nemožno finalizovať dvakrát — ak už faktúru má, jej opätovná finalizácia je zablokovaná. Toto je moment, keď sa predaj stáva skutočnou faktúrou v Accounting, a uvidíte ju objaviť sa na záložke objednávky **Invoices**.

## Dispatched

Keď je dodací list skutočne expedovaný zo skladu, stav objednávky sa zmení na **Dispatched**. Tým sa bežná cesta končí — tovar odišiel a predaj je vyfakturovaný.

## Cancelled

Objednávku možno namiesto vyššie uvedených stavov aj zrušiť — napríklad ak sa obchod zatvorí skôr, než sa na objednávke začalo pracovať. Zrušená objednávka sa v procese ďalej neposúva.

## Zmena adresy po odoslaní objednávky

Keď je objednávka odoslaná, aiku si urobí kópiu zákazníkovej fakturačnej a dodacej adresy a uchová ich pri objednávke. Daň a doprava boli vypočítané na základe týchto adries, takže sa objednávka sama od seba potom nemení. Úprava adresy v zákazníckom zázname aktualizuje košíky, ktoré sú ešte otvorené, no nikdy sa nedotkne objednávky, ktorá už bola odoslaná.

Ak chcete zmeniť adresu na odoslanej objednávke, urobte to priamo na objednávke. Otvorte objednávku a vedľa bloku s adresou kliknite na **Edit**, aby ste zmenili dodaciu adresu, alebo na **Edit billing address**, aby ste zmenili fakturačnú. Ak objednávka zobrazuje jednu spoločnú adresu, oba odkazy sú pod ňou. Zmena sa uplatní na objednávku aj jej dodací list a súčty sa prepočítajú, ak sa zmení spôsob zdanenia. Toto je možné až do momentu expedície objednávky.

Po expedícii objednávky sú jej adresy natrvalo pevné. Ak zákazník potom potrebuje inú adresu na faktúre, opravte ju na faktúre: otvorte faktúru zo záložky objednávky **Invoices** a použite ceruzku vedľa adresy.

## Poznámky k objednávke a čo sa tlačí na prepravnom štítku

Otvorte ktorúkoľvek objednávku a hore uvidíte rad farebných políčok s poznámkami. Každé ide inam, preto záleží, do ktorého píšete. Políčko upravíte dvojklikom.

- **Shipping Label (From Customer)** (štítok pre kuriéra od zákazníka) — text vytlačený na kuriérskom štítku pod adresou. Zmestí sa len prvých 34 znakov, takže sem patrí len to, čo potrebuje vodič: „Ring bell at side door", „Open 9-5 Mon-Fri". Zvyčajne ho vypĺňa zákazník ako *pokyny pre doručenie* vo svojom košíku, ale upraviť ho môže aj personál.
- **Customer Instructions** (pokyny zákazníka) — čo zákazník napísal pri objednávaní. Len na čítanie.
- **Public Note** (verejná poznámka) — viditeľná pre zákazníka aj personál.
- **CRMs Note (Private)** (súkromná poznámka CRM) — len pre personál, na dodacom liste sa nezobrazuje.
- **Warehouse Note (Private)** (súkromná poznámka pre sklad) — len pre personál, tlačí sa na dodacom liste pre vychystávačov a baličov.

Niektorí zákazníci potrebujú rovnaký text štítku na každej zásielke, typicky svoje otváracie hodiny. Nezadávajte ho nanovo pri každej objednávke. Otvorte kartu zákazníka a vyplňte **Shipping Label (Permanent)** (trvalý štítok) v riadku poznámok hore. Od tej chvíle každý nový košík, ktorý si zákazník otvorí, začína s týmto textom už predvyplneným v pokynoch pre doručenie, takže zákazník vidí presne to, čo sa vytlačí, a môže si to pre danú objednávku zmeniť. Ak text ponechá tak, ako je, presne to sa dostane na štítok. Objednávky, ktoré už boli v košíku predtým, než ste text nastavili, ho prevezmú pri pokladni, pokiaľ do nich nikto poznámku k štítku nezadal ručne.

Zákazníkov vlastný text má vždy prednosť pred trvalou poznámkou. Je to zámer: osoba, ktorá zásielku preberá, najlepšie vie, čo si má vodič v daný deň prečítať.

V tom istom riadku na karte zákazníka je aj **Warehouse Note (Permanent)** (trvalá poznámka pre sklad), ktorá funguje rovnako pre poznámku pre sklad, a **Warehouse Note (Temporary)** (dočasná poznámka pre sklad), ktorá sa použije jednorazovo, len pri najbližšej objednávke.

## Vyhľadanie objednávky a kontrola jej priebehu

Otvorte obchod a prejdite na **Orders → Orders**, kde uvidíte každú objednávku daného obchodu. Každý riadok zobrazuje stav objednávky ako ikonu, jej referenciu, kedy bola odoslaná alebo expedovaná, zákazníka, stav platby, informácie o doručení a čistú sumu. Zoznam môžete filtrovať podľa stavu a vyhľadávať podľa referencie alebo sledovacieho čísla.

Otvorte ktorúkoľvek objednávku, aby ste videli jej úplný záznam. Stránka zobrazuje referenciu objednávky ako nadpis, s jej aktuálnym stavom vedľa neho, a riadok záložiek:

- **Transactions** — riadky produktov na objednávke.
- **Marketing** — odkiaľ objednávka prišla.
- **Payments** — platby prijaté k objednávke.
- **Invoices** — faktúra vygenerovaná pri finalizácii objednávky.
- **Delivery notes** — skladová dokumentácia, ktorá nesie objednávku cez vychystávanie, balenie a expedíciu.
- **Returns** — akékoľvek vrátenia spojené s objednávkou.
- **Attachments**.
- **Dispatched emails** — emaily, ktoré aiku o tejto objednávke odoslalo.
- **History** — záznam všetkého, čo sa s objednávkou stalo.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zobraziť všetky objednávky:</b> váš obchod → <b>Orders</b> (horné menu) → záložka <b>Orders</b>. Filtrujte podľa stavu alebo vyhľadávajte podľa referencie.</li>
<li><b>Skontrolovať priebeh jednej objednávky:</b> kliknite na ňu v zozname — stav sa zobrazí vedľa jej referencie, a záložky <b>Delivery notes</b> a <b>Invoices</b> ukazujú, čo sa udialo ďalej.</li>
<li><b>Zobraziť, čo čaká na spracovanie:</b> váš obchod → <b>Orders</b> (horné menu) → záložka <b>Backlog</b>.</li>
<li><b>Zmeniť adresu na odoslanej objednávke:</b> otvorte objednávku → <b>Edit</b> alebo <b>Edit billing address</b> pod blokom adresy, kým nie je expedovaná.</li>
<li><b>Opraviť adresu na vystavenej faktúre:</b> otvorte objednávku → záložka <b>Invoices</b> → otvorte faktúru → ceruzka vedľa adresy.</li>
<li><b>Zmeniť, čo sa tlačí na kuriérskom štítku pre jednu objednávku:</b> otvorte objednávku → dvojklik na <b>Shipping Label (From Customer)</b> v riadku poznámok → uložte. Tlačí sa prvých 34 znakov.</li>
<li><b>Nastaviť trvalú poznámku pre štítok zákazníka:</b> váš obchod → <b>Customers</b> → otvorte zákazníka → <b>Shipping Label (Permanent)</b> v riadku poznámok hore. Predvyplní pokyny pre doručenie v každom novom košíku.</li>
</ul>
</aside>

<aside class="permissions"><strong>Oprávnenia, ktoré potrebujete</strong>
Na zobrazenie tohto zoznamu a jeho objednávok potrebujete zobrazovací prístup k Orders pre daný obchod, alebo zobrazovací prístup k Accounting pre organizáciu.
</aside>
