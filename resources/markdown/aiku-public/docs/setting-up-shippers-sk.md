---
title: Nastavenie prepravcov
summary: Pridajte dopravcov, s ktorými váš sklad expeduje, prepojte tých, ktorí si tlačia vlastné štítky, a naučte aiku, ktorého prepravcu uprednostniť pre jednotlivé destinácie.
date: 2026-08-31
source_date: 2026-08-31
tags: dispatch, shippers
category: dispatch
---

<aside class="tldr">
<em>Prepravca</em> je dopravca, ktorému váš sklad odovzdáva zásielky — APC, GLS, DPD, Packeta alebo miestny kuriér spoza rohu. Zoznam vediete na obrazovke skladu <b>Dispatching → Shippers</b> a v nastaveniach organizácie viete aiku povedať, ktorého prepravcu má pre danú destináciu navrhnúť — alebo na ňom trvať. Potom si obrazovka expedície väčšinou vyberá sama.
</aside>

## Zoznam prepravcov

Každá organizácia si vedie vlastný zoznam dopravcov. Nájdete ho vo vnútri skladu: otvorte svoj sklad a potom **Dispatching → Shippers**. Karta **Current** ukazuje dopravcov, ktorí sa dnes používajú; karta **Inactive** uchováva vyradených, aby staré zásielky stále vedeli, kto ich viezol.

Každý riadok ukazuje meno prepravcu, meno, pod ktorým obchoduje, a jeho **typ** — ten hovorí, či aiku s daným dopravcom komunikuje priamo (viac nižšie).

## Pridanie prepravcu

Stlačte **Create Shipper** hore v zozname. Formulár sa pýta na štyri veci:

- **Code** — krátka interná skratka, napríklad `APC` alebo `GLS`.
- **Name** — plný názov dopravcu tak, ako ho pozná váš tím.
- **Trade as** — krátky názov, ktorý sa objavuje na zásielkach a dokladoch.
- **Tracking url** — sledovacia stránka dopravcu. Keď niekto ručne zadá sledovacie číslo, aiku podľa tejto adresy vytvorí odkaz, na ktorý môže zákazník kliknúť.

To je všetko, čo základný prepravca potrebuje. Od chvíle, keď je uložený, sa dá vybrať pri expedícii dodacieho listu: tím vyberie prepravcu, zadá sledovacie číslo z vlastného systému dopravcu a aiku uchová záznam aj sledovací odkaz.

## Prepojení prepravcovia: štítky bez písania

Niektorí dopravcovia robia viac než len sedia v zozname. aiku vie komunikovať priamo s **APC**, **GLS** (Slovensko a Španielsko), **DPD** (Spojené kráľovstvo a Slovensko), **Packeta**, **CTT** a **ITD**. Pri prepojenom prepravcovi vytvorenie zásielky na dodacom liste vyžiada od dopravcu reálnu zásielku: sledovacie číslo príde samo a **prepravný štítok sa zaradí rovno do tlačiarne** — nikto nič nezadáva, nikto ručne neprepisuje adresu na stránke dopravcu.

Prepojenie potrebuje prístupové údaje z vašej zmluvy s dopravcom, takže sa nastavuje spoločne s tímom aiku, nie cez formulár vytvorenia. Ak si u niektorého z uvedených dopravcov otvoríte účet, požiadajte o jeho prepojenie — rozdiel pri baliacom stole je citeľný.

## Preferovaní prepravcovia: naučiť aiku, kde je ktorý dopravca najlepší

Väčšina skladov nechce, aby si baliči vyberali dopravcu zásielku od zásielky. V nastaveniach organizácie, v sekcii **Preferred Shipping**, môžete napísať jednoduché pravidlá: *pre túto krajinu — alebo túto krajinu a tieto PSČ — použi tohto prepravcu*. Pravidlo môže platiť pre všetky vaše shopy, alebo len pre niektoré.

Každé pravidlo môže byť mäkké alebo záväzné:

- Bežné pravidlo urobí z prepravcu **návrh**: obrazovka expedície ho predvyberie, ale tím môže stále zvoliť iného.
- Pravidlo označené ako **important** prepravcu pre dané destinácie **uzamkne**. Obrazovka expedície nedovolí baličovi ticho zvoliť niekoho iného — obísť zámok môže len supervízor expedície alebo administrátor organizácie, a aj tí najprv dostanú varovanie, pretože odoslanie zásielky nesprávnym dopravcom môže znamenať, že zákazníkovi bola naúčtovaná nesprávna cena dopravy.

Počítajú sa len aktívni prepravcovia: pravidlo mieriace na prepravcu, ktorého ste medzitým vyradili, jednoducho prestane platiť.

## Ako sa robí voľba pri expedícii

Keď sa dodací list dostane do kroku odoslania, aiku určí svoj návrh v tomto poradí:

1. **Prvá je voľba zákazníka.** Ak objednávka nesie prepravcu, ktorého si zákazník uzamkol, ten istý prepravca je uzamknutý aj na dodacom liste.
2. Inak, ak objednávka už má prepravcu, alebo jej prepravná zóna používa vždy len jedného dopravcu, navrhne sa práve ten.
3. Inak sa skontrolujú vaše pravidlá **Preferred Shipping** voči krajine a PSČ doručenia — návrh sa objaví predvybraný, uzamknutý, ak bolo pravidlo označené ako important.

Ak sa nič nezhoduje, tím vyberá zo zoznamu prepravcov ako obvykle. V oboch prípadoch prepojený prepravca vytlačí vlastný štítok a manuálny si vyžiada sledovacie číslo.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zobraziť alebo pridať prepravcov:</b> váš sklad → <b>Dispatching → Shippers</b> → <b>Create Shipper</b>. Vyradení dopravcovia sú na karte <b>Inactive</b>.</li>
<li><b>Nastaviť preferovaných prepravcov:</b> vaša organizácia → <b>Settings</b> → <b>Preferred Shipping</b> → pridajte pravidlá podľa krajiny a PSČ; zaškrtnite <b>important</b> na uzamknutie.</li>
<li><b>Použiť ich:</b> v kroku odoslania na dodacom liste je navrhnutý prepravca predvybraný — potvrďte ho, prepojení dopravcovia si štítok vytlačia sami.</li>
</ul>
</aside>
