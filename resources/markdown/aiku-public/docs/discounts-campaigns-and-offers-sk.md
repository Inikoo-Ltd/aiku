---
title: Zľavy: kampane a ponuky
summary: Ako sú zľavy v obchode usporiadané do kampaní, ako sa vytvára a časuje jednotlivá ponuka a ako sa zľava napokon prejaví na objednávke.
date: 2026-09-02
source_date: 2026-09-02
tags: discounts, offers, campaigns
category: shop
---

<aside class="tldr">
Každý obchod má pevnú sadu <b>kampaní</b> — po jednej pre každý druh zľavy, ktorú aiku vie spustiť, napríklad objemové zľavy, poukazy alebo darčeky. V rámci kampane vytvárate jednotlivé <b>ponuky (offers)</b>: konkrétne pravidlo so začiatkom, koncom a odmenou, ktorú prináša. Ponuka sama prechádza malou sadou stavov — scheduled, active, finished, suspended — a keď je raz aktívna, aiku ju automaticky uplatní na zodpovedajúcu objednávku. Jednu vec je dobré vedieť ešte pred začiatkom: tlačidlo, ktoré vytvára ponuku, nie je vždy na stránke kampane — step discounts a gifts sa vytvárajú z produktu a mix &amp; match z rodiny.
</aside>

## Kampane: jedna na každý typ zľavy

Otvorte obchod a prejdite na **Offers → Campaigns**. Každý riadok je kampaň a jej typ hovorí, aký druh zľavy vie spustiť:

- **Order recursion**
- **Volume/GR discount**
- **First order**
- **Customer offers**
- **Shop offers**
- **Category offers**
- **Product offers**
- **Step offers**
- **Discretionary discounts**
- **Shipping discount**
- **Gifts**
- **Vouchers**

Zoznam ukazuje názov kampane, koľko aktuálnych ponúk obsahuje a koľkých zákazníkov a objednávok sa dotkla. Nové kampane si sami nevytvárate — obchod už má jednu od každého typu — otvoríte tú, ktorej typ zodpovedá zľave, ktorú chcete, a pridáte do nej ponuku.

Po otvorení kampane sa dostanete na jej záložku **Overview**, ktorá sumarizuje jej ponuky. Odtiaľ môžete prejsť na záložku **Offers**, kde vidíte alebo pridávate ponuky vo vnútri, a na **History**, kde vidíte, čo sa zmenilo. Kampaň typu Volume/GR discount má navyše záložku **GR Amnesty**, pre svoj vlastný druh ponuky.

Aj samotná kampaň má stav, zobrazený voči každej svojej ponuke ako skupina: **In process**, **Active**, **Finished** alebo **Suspended**.

## Ponuky: samotné pravidlo zľavy

Každá kampaň má vlastné tlačidlo na vytvorenie, pomenované podľa toho, čo vytvára — **Create Product Offer**, **Create Voucher**, **Create Gift Offer** — a každé otvorí formulár určený pre ten jeden druh zľavy. Všetky formuláre sa pýtajú na:

- **Offer name** — názov, podľa ktorého ju bude váš tím poznať.
- **Spúšťač** — zvyčajne voľbu medzi **By quantity** a **By minimum amount**, plus to, na čom ponuka visí: produkt, rodina, zákazník.
- **Zľavu** — vo väčšine formulárov percento, pri vouchers pevnú sumu, pri gifts tovar zadarmo.

Celá ponuka po vytvorení nesie aj:

- **Start date** a voliteľný **end date**. Nechajte začiatok prázdny a ponuka sa spustí okamžite.
- **Duration**: **Permanent**, teda beží od dátumu začiatku bez konca, alebo **Interval**, teda je ohraničená a dátum konca je povinný.
- Jednu alebo viac **allowances** — samotnú odmenu. Každá allowance má typ: **Percentage Off**, **Amount Off**, **Free Items**, **Gift**, **Shipping**, alebo kombináciu **Mixed**.

Spúšťač ponuky — čo musí zákazník urobiť, aby si ju zaslúžil — závisí od typu kampane, v ktorej sa nachádza. Kampaň typu product-offers sa spúšťa objednaním daného produktu alebo jeho množstva; kampaň typu category-offers sa spúšťa oddelením, pod-oddelením, rodinou alebo kategóriou; kampaň typu shop-offers sa spúšťa celkovou sumou objednávky za celý obchod; kampaň typu first-order sa spúšťa tým, že ide o prvú objednávku zákazníka; kampaň typu voucher sa spúšťa zadaním kódu poukazu; a kampaň typu shipping dáva namiesto zníženia ceny allowance na dopravu zadarmo.

## Kde sa jednotlivé ponuky naozaj vytvárajú

Pri väčšine kampaní je tlačidlo na vytvorenie priamo na stránke kampane, ale nie pri všetkých:

| Kampaň | Kde je tlačidlo | Čo je na ňom |
| --- | --- | --- |
| Product offers | stránka kampane | Create Product Offer |
| Category offers | stránka kampane, alebo záložka **Offers** rodiny | Create Category Offer |
| Shop offers | stránka kampane | Create Shop Offer |
| Customer offers | stránka kampane | Create Customer Offer |
| Vouchers | stránka kampane | Create Voucher |
| Gifts | stránka kampane, alebo záložka **Offers** produktu | Create Gift Offer |
| Shipping discount | stránka kampane | Create Discount Shipping |
| First order | stránka kampane | Create First Order Bonus |
| Step offers | záložka **Offers** produktu | Create Step Discount |
| Volume/GR discount | stránka kampane, v hlavičke | Set up Vol/GR Gift · New GR Amnesty |
| Discretionary discounts | nikde — ponuka už existuje | — |
| Order recursion | nie je sprístupnená | — |

Cesty cez katalóg sú tie, ktoré ľuďom unikajú. Otvorte rodinu, prejdite na jej záložku **Offers** a máte tam **Create Category Offer** a **Create Mix & Match Offer**; otvorte produkt a tá istá záložka nesie **Create Gift Offer** a **Create Step Discount**. Ponuky vytvorené tam sa samy zaradia do správnej kampane — mix & match začatý z produktu skončí v Product offers, začatý z rodiny skončí v Category offers — takže sa v kampani objavia aj vtedy, keď ste ju vôbec neotvorili.

## Stavy: ako prebieha život ponuky

Stav ponuky sa zobrazuje ako jeden z nasledujúcich:

- **Scheduled** — uložená s budúcim dátumom začiatku, ešte nie je live.
- **Active** — beží práve teraz; zodpovedajúce objednávky dostávajú zľavu.
- **Finished** — za svojím dátumom konca.
- **Suspended** — vypnutá členom tímu pred svojím prirodzeným koncom.

Otvorte ponuku a stránka má v titulku jej kód, ukazuje časy začiatku a konca, jej stav, jej typ a náhľad zľavy tak, ako ju uvidí zákazník. Jej záložky sú **Orders** a **Customers**, ktoré ukazujú, kto ponuku využil, a **History**.

## Ako sa zľava zobrazí na objednávke

Keď sa objednávka kvalifikuje na ponuku, odmena sa na objednávku uplatní automaticky — nepripájate ju ručne. Na objednávke každý dotknutý riadok zobrazuje **Net Amount** popri pôvodnej (hrubej) sume; tam, kde sa tieto dve líšia, bola na daný riadok uplatnená zľava, a riadok možno v prípade potreby vrátiť späť na pôvodnú sumu. Discretionary discounts — ručný, personálom uplatňovaný druh zľavy, na rozdiel od automatickej ponuky — možno navyše zapnúť, odstrániť alebo obnoviť naprieč všetkými riadkami objednávky jedinou akciou **Global discount**, s vlastným percentom a označením.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zobraziť všetky zľavy:</b> váš obchod → <b>Offers</b> v hlavnej navigácii, čo otvorí stránku <b>Insights</b>.</li>
<li><b>Prehliadať podľa typu kampane:</b> obchod → <b>Offers → Campaigns</b> → otvorte kampaň → jej záložky <b>Overview</b> a <b>Offers</b> (kampane Volume/GR majú aj <b>GR Amnesty</b>).</li>
<li><b>Prehliadať všetky ponuky naraz:</b> obchod → <b>Offers → Offers</b>, zoznam s názvom, označením, typom, dátumami začiatku a konca, objednávkami, faktúrami a tržbami.</li>
<li><b>Vytvoriť ponuku:</b> pri väčšine typov stránka kampane; pri step discounts a gifts záložka <b>Offers</b> produktu; pri category a mix &amp; match záložka <b>Offers</b> rodiny.</li>
<li><b>Zobraziť výkonnosť:</b> obchod → <b>Offers → Insights</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Oprávnenia, ktoré potrebujete</strong>
<p>Na zobrazenie kampaní a ponúk obchodu potrebujete zobrazovací prístup k zľavám daného obchodu; na ich zmenu potrebujete editačný prístup. Ak vám chýbajú tlačidlá na vytvorenie alebo úpravu, spýtajte sa administrátora svojej organizácie.</p>
</aside>
