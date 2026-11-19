---
title: WhatsApp notifikácie zákazníkom
summary: Posielajte informácie o objednávkach a pripomienky cez WhatsApp popri e-mailoch: čo posiela každý WhatsApp outbox, ako ho zapnúť a prečo správa nemusí odísť.
date: 2026-11-18
source_date: 2026-11-18
tags: whatsapp, notifications, outboxes, orders
category: help-desk
series: WhatsApp
order: 4
help_routes: grp.org.shops.show.dashboard.comms.customer_notification_outboxes, grp.org.shops.show.dashboard.comms.whatsapp_outboxes
---

<aside class="tldr">
<b>WhatsApp outbox</b> posiela jeden druh notifikácie zákazníkovi, napríklad Order dispatched (Objednávka odoslaná), cez WhatsApp. Funguje samostatne, popri e-maile s rovnakým názvom. Zapnete ho takto: priraďte mu <b>schválenú šablónu Utility</b> a prepnite ho na <b>Active</b> (Aktívny). Dostanú ju len zákazníci, ktorí <b>súhlasili s WhatsApp správami</b> a majú telefónne číslo, na ktoré vieme poslať.
</aside>

## Kde ich nájdete

V obchode choďte do **Comms → Customer Notifications** (Notifikácie zákazníkom) a otvorte kartu **WhatsApp**. Každý riadok je jeden outbox:

| Stĺpec | Čo vám povie |
| --- | --- |
| Template | WhatsApp šablónu, ktorú posiela, alebo *No template* (Bez šablóny) |
| Status | **Active**, **Not sending** alebo **Off** (pozri nižšie) |
| Sent 1w / Failed 1w | Odoslané a neúspešné správy za posledných 7 dní |

Kliknutím na riadok outbox otvoríte.

## Čo posiela každý outbox

| Outbox | Kedy posiela |
| --- | --- |
| Order confirmation | Keď je objednávka zadaná |
| Order dispatched | Keď je objednávka odoslaná, so sledovaním každého balíka |
| Order payment reminder | Keď objednávka na bankový prevod stále nie je zaplatená |
| Out of stock in order notification | Každých pár hodín, o produktoch v objednávke, ktoré sa minuli |
| Out of stock notification | Raz denne, keď je produkt, na ktorý sa zákazník pýtal, znova na sklade |
| Pre-order update | Keď sa predobjednávka zmení: doplatok, nový dátum odoslania, zrušenie |
| Credit balance notification for customer | Keď sa zmení kredit zákazníka |
| Chat notification to customer | Keď personál odpovie v chate a zákazník už odišiel z webu |
| Review reminder | Raz denne, stanovený počet dní po odoslaní |
| Gold reward reminder | Raz denne, stanovený počet dní po poslednej faktúre zákazníka |

**WhatsApp a e-mail sú oddelené.** Vypnutie e-mailu nezastaví WhatsApp správu a naopak. Ak sú zapnuté obe, zákazník dostane obe.

## Zapnutie outboxu

Každý outbox začína ako **Off** (Vypnutý). Zapnete ho takto:

1. **Nechajte si schváliť šablónu.** Napíšte ju v **Chat → WhatsApp templates** (WhatsApp šablóny) v kategórii **Utility** a odošlite. Meta ju zvyčajne schváli do pár minút.
2. **Otvorte outbox** a vyberte šablónu v **WhatsApp template**. Vybrať sa dajú len schválené šablóny. **Preview** (Náhľad) ukazuje, čo si zákazník prečíta.
3. **Vyplňte načasovanie** pri outboxoch, ktoré ho potrebujú:
   - *Out of stock in order notification:* **Hours between runs** (Hodiny medzi behmi).
   - *Review reminder:* **Days after dispatch** (Dni po odoslaní).
   - *Gold reward reminder:* **Days after the last invoice** (Dni po poslednej faktúre).
4. **Zapnite Active.**

aiku vám nedovolí zapnúť outbox bez schválenej šablóny Utility a jej načasovania. Pri uložení vám povie, čo chýba.

### Ako napísať šablónu, ktorá zostane schválená

- **Použite kategóriu Utility.** Šablónu Marketing tu použiť nemožno.
- **Píšte vecne**, ako na účtenke. Reklamné slová ("špeciálna ponuka", "nenechajte si ujsť") môžu spôsobiť, že Meta presunie šablónu do Marketing. Vtedy outbox prestane posielať a ukáže **Not sending** (Neposiela).
- **Používajte značky** v hranatých zátvorkách, napríklad `[Customer First Name]`, `[Order Number]`, `[Order Total]`, `[Delivery Address]` alebo `[Tracking Parcels]`. aiku ich vyplní pre každého zákazníka.
- **Schválenú šablónu nemožno upraviť.** Ak chcete zmeniť text, napíšte novú šablónu, nechajte ju schváliť a potom ju vyberte v outboxe.

## Kto správy dostane

Zákazník dostane WhatsApp notifikáciu, len keď platí **všetko** toto:

- Obchod má **pripojené WhatsApp číslo**. Ak nie, stránka to hore oznámi a nič sa nepošle. Pozrite [pripojenie WhatsApp k vášmu obchodu](/docs/connecting-whatsapp-to-your-shop-sk).
- Outbox je **Active** so schválenou šablónou Utility.
- Zákazník **súhlasil s WhatsApp správami**. Zaškrtne to pri checkoute alebo na nástenke svojho účtu.
- Zákazník má **telefónne číslo, ktoré WhatsApp vie použiť**: svoje WhatsApp číslo, ak ho uviedol, inak hlavné telefónne číslo.

Zákazníci, ktorí nesúhlasili, sa potichu preskočia. To je očakávané, nie chyba.

## Čo znamená stav

| Stav | Čo znamená |
| --- | --- |
| Active | Zapnutý a posiela |
| Not sending | Zapnutý, ale šablóna už nie je schválená šablóna Utility. Vyberte inú. |
| Off | Vypnutý. Nič sa neposiela. |

## Kontrola toho, čo odišlo

Po otvorení outboxu uvidíte tri karty:

- **Overview** (Prehľad): nastavenia, náhľad šablóny a čísla. **Sent** znamená odovzdané WhatsAppu, **Delivered** že správa došla do telefónu a **Read** že ju zákazník otvoril. Zákazníci si môžu potvrdenia o prečítaní vypnúť, takže Read je minimum.
- **Sent messages** (Odoslané správy): každá správa so zákazníkom, objednávkou a výsledkom.
- **Bulk runs** (Hromadné behy): pri outboxoch, ktoré posielajú podľa plánu (vypredané, recenzie, gold reward), jeden riadok za každý beh.

Každá správa sa zobrazí aj vo **WhatsApp chate** zákazníka. Neúspešná správa tam zostane tiež, aj s dôvodom, aby ste videli, čo zákazníkovi ušlo.

## Keď správa neodišla

| Čo vidíte | Prečo a čo robiť |
| --- | --- |
| Pre zákazníka vôbec nič | Nesúhlasil, alebo nemá použiteľné telefónne číslo. Skontrolujte jeho záznam zákazníka. |
| Failed: the template has more variables than are set up | Niektoré značky šablóny nie sú priradené. Otvorte šablónu a priraďte každú premennú. |
| Pomlčka (-) v správe | aiku nemal hodnotu pre túto značku, napríklad objednávka ešte bez sledovania. Správa aj tak odíde. |
| Stav hovorí Not sending | Meta presunula šablónu do Marketing, alebo už nie je schválená. Vyberte schválenú šablónu Utility. |
| Failed s chybou WhatsApp | WhatsApp ju odmietol. Pozrite [keď WhatsApp nefunguje](/docs/when-whatsapp-is-not-working-sk). |

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zobraziť všetky WhatsApp outboxy:</b> váš obchod → <b>Comms → Customer Notifications</b> → karta <b>WhatsApp</b>.</li>
<li><b>Zapnúť jeden:</b> otvorte outbox → vyberte <b>WhatsApp template</b> → ak sa pýta, vyplňte načasovanie → zapnite <b>Active</b>.</li>
<li><b>Napísať alebo zmeniť šablónu:</b> váš obchod → <b>Chat → WhatsApp templates</b>. Použite kategóriu Utility.</li>
<li><b>Pozrieť, čo odišlo:</b> otvorte outbox → <b>Sent messages</b>.</li>
</ul>
</aside>
