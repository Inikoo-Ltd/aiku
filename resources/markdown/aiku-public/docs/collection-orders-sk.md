---
title: Objednávky na osobný odber
summary: Čo sa mení, keď si zákazník objednávku vyzdvihne sám namiesto doručenia, čo sa objaví na faktúre a kde opraviť adresu, ktorá chýba alebo je nesprávna.
date: 2026-09-10
source_date: 2026-09-10
tags: orders, invoices, collection, accounting, crm
category: orders
help_routes: grp.org.shops.show.ordering.orders.show, grp.org.accounting.invoices
---

<aside class="tldr">
<b>Objednávka na osobný odber</b> je taká, ktorú si zákazník vyzdvihne sám. Nemá poplatok za dopravu ani doručovaciu adresu; namiesto toho nesie adresu, kde si tovar vyzdvihne. Na faktúre táto adresa nahrádza doručovaciu adresu, pod nadpisom <b>Collection address</b>. Ak zákazník nemá na účte žiadnu adresu, objednávka sa zastaví pred skladom, kým ju niekto nedoplní.
</aside>

## Čím je objednávka na osobný odber iná

Bežná objednávka sa odosiela: má doručovaciu adresu, je ocenená s poplatkom za dopravu a dodací list ide von s prepravcom. Objednávka na osobný odber nič z toho nemá. Tovar sa stále vychystá a zabalí ako obvykle, ale zákazník príde k nám, takže na objednávke nie je riadok s dopravou a nie je čo tlačiť ako doručovaciu adresu.

Namiesto toho nesie objednávka **adresu odberu** — miesto, kde si zákazník tovar vyzdvihne. Pochádza z obchodu, takže všetky objednávky na odber v tom obchode ukazujú na rovnaké miesto, pokiaľ nie je nastavené inak.

## Čo ukazuje faktúra

Faktúra za osobný odber má rovnaké dve políčka ako každá iná faktúra, ale pravé sa mení:

- **Billing address** — pochádza z účtu zákazníka. Na túto adresu je faktúra vystavená a práve tá je rozhodujúca pre daň.
- **Collection address** — adresa, odkiaľ bol tovar vyzdvihnutý. Nahrádza políčko s doručovacou adresou.

Prázdne riadky sa nikdy netlačia, takže adresa s chýbajúcimi časťami ukáže len to, čo má.

Faktúra je pevný doklad. Adresa odberu sa na ňu uloží v okamihu vystavenia, takže neskoršia zmena adresy odberu v obchode **neprepíše** už existujúce faktúry — tie naďalej ukazujú adresu, ktorá platila v čase odberu. Faktúry vystavené predtým, ako sa adresa začala ukladať, nemajú čo ukázať, takže v ich políčku je jednoducho **Collection**.

## Keď zákazník nemá adresu

Účet môže skončiť úplne bez adresy. Vtedy sa objednávka **zastaví pred skladom**: nevytvorí sa dodací list, objednávka zostane v zozname odoslaných a do skladovej poznámky pribudne upozornenie, že treba adresu. Nič sa nevychystá a nič sa nevyfakturuje, kým sa to nevyrieši.

Riešením je doplniť adresu k **zákazníkovi**, nie k objednávke. Otvorte zákazníka, doplňte adresu a objednávka pokračuje do skladu sama. Doplnenie k zákazníkovi zároveň znamená, že jeho ďalšia objednávka bude v poriadku bez toho, aby na to niekto musel myslieť.

Zamestnanec, ktorý vytvára objednávku v systéme, ju bez fakturačnej adresy vôbec neodošle. Zákazník objednávajúci na webe sa do pokladne bez adresy dostať môže — jeho platba prebehne normálne a zachytí to až zastavenie pred skladom, takže nikomu nie sú peniaze najprv strhnuté a objednávka potom odmietnutá.

## Oprava adresy

Sú to dve rôzne úlohy a používajú dve rôzne obrazovky.

**Pre všetky budúce faktúry** — zmeňte adresu odberu v obchode. Otvorte obchod, choďte do **Settings** a upravte pole **Collection address**. Odvtedy sa faktúry za odber v tomto obchode vystavujú s novou adresou. Existujúce faktúry zostanú nedotknuté, o to práve ide.

**Pre jednu už vystavenú faktúru** — otvorte faktúru a kliknite na ceruzku pri políčku s adresou. Upravuje sa tým len **fakturačná** adresa, teda tá z účtu zákazníka. Adresa odberu sa ručne nepíše; berie sa z obchodu.

<aside class="wayfinder">

### Kde v aiku kliknúť

- **Zistiť, či je objednávka na odber** — otvorte objednávku; objednávka na odber má namiesto doručovacej adresy adresu odberu a nemá riadok s dopravou.
- **Nájsť zastavenú objednávku** — zoznam objednávok organizácie, skupina **Submitted**. Dôvod je v skladovej poznámke objednávky.
- **Doplniť adresu zákazníkovi** — otvorte zákazníka cez **CRM → Customers** a upravte jeho adresu.
- **Zmeniť, odkiaľ si zákazníci odoberajú** — otvorte obchod, potom **Settings**, a upravte **Collection address**.
- **Opraviť fakturačnú adresu na jednej faktúre** — otvorte faktúru, kliknite na ceruzku pri adrese, upravte, **Save**.

### Aké oprávnenia potrebujete

- Úprava nastavení obchodu vrátane adresy odberu vyžaduje **organisation admin** alebo **shop admin**.
- Ceruzka pri adrese na faktúre sa zobrazí len **accounting supervisorom** v danej organizácii.
- Doplnenie adresy zákazníkovi patrí do bežnej práce zákazníckeho servisu v danom obchode.

</aside>
