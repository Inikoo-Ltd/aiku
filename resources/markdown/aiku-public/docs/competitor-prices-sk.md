---
title: Ceny konkurentov
summary: Ako aiku číta ceny z webových stránok vašich konkurentov, páruje ich produkty s vašimi hlavnými produktmi a ukazuje, kde ste lacnejší alebo drahší, a čo kontrolovať na dashboarde Competitors.
date: 2026-11-05
source_date: 2026-11-05
tags: masters, pricing, products, catalogue
category: shop
---

<aside class="tldr">
V <b>Masters › Competitors</b> (Konkurenti) aiku poviete, ktoré webové stránky konkurentov má sledovať. aiku si ich produkty a ceny prečíta samo, spáruje ich s vašimi hlavnými produktmi a ukáže, <b>kde ste lacnejší, približne rovnakí alebo drahší</b>, na jednotku a v mene skupiny. AI potvrdí páry, v ktorých si je istá, a <b>zvyšok vám dá na kontrolu</b> s fajkou alebo krížikom. Potvrdené ceny sa ukazujú aj v <a href="/docs/price-tips-sk">cenových tipoch</a>.
</aside>

## Pridanie konkurenta

Prejdite do **Masters › Competitors** a kliknite na **Add competitor** (Pridať konkurenta). Potrebujete:

- **hlavný obchod** (master shop), ktorého produktom konkuruje;
- **názov** a **webovú stránku**;
- komu **predáva**: zákazníkom (ich ceny sa porovnávajú s vaším RRP) alebo predajcom (porovnávajú sa s vašou cenou);
- **menu** ich cien.

Pre Shopify obchod to stačí: aiku si jeho verejný katalóg nájde samo a prečíta ho nasledujúce ráno o 04:00 UTC, každý produkt a každú veľkosť balenia s cenou. Na stránke konkurenta potom pri *Prices read from* stojí **Shopify catalogue**.

Pri akejkoľvek inej webovej stránke vyplňte jedno z polí v **Where to read prices** (Odkiaľ čítať ceny):

- **Product feed link** (odkaz na produktový feed): CSV alebo textový súbor so všetkými ich produktmi a cenami, ktorý dávajú svojim obchodným zákazníkom. Najlepšia možnosť; číta sa každé ráno.
- **Search link** (odkaz na vyhľadávanie): vyhľadajte na ich webe ľubovoľné slovo, skopírujte odkaz na stránku s výsledkami a na miesto slova vložte `{query}`. aiku potom každý týždeň vyhľadáva na ich webe hlavné produkty, ktoré majú otvorený cenový tip.
- **Login page** (prihlasovacia stránka), **login email** a **password**: iba keď ceny ukazujú prihláseným obchodným zákazníkom. Heslo sa ukladá zašifrované a už sa nezobrazí; pri úprave ho nechajte prázdne, ak ho chcete zachovať.

## Čo ukazuje dashboard

**Masters › Competitors** sa otvorí na dashboarde:

- **Competitors**: koľko sa prečítalo bez problémov, koľko zlyháva a prečo (web sa nedal otvoriť, zablokoval nás alebo zlyhalo prihlásenie), s poslednou chybou jasnými slovami a kedy bolo posledné čítanie.
- **Matches to review**: koľko párov si AI nebola istá. Kliknutím ich prejdete.
- **Reviewed by staff this week** a **Confirmed this week**, každé oproti minulému týždňu.
- Jedna karta na hlavný obchod: pri koľkých potvrdených produktoch ste **lacnejší** (cheaper), **približne rovnakí** (v rozmedzí 5 %) alebo **drahší** (dearer), medián rozdielu a podiel aktívnych hlavných produktov, ktoré majú aspoň jednu potvrdenú cenu konkurenta.
- **Confirmed matches per week** za posledných 12 týždňov.
- **Where they are cheapest** (kde sú najlacnejší) a **Where we are cheapest** (kde sme najlacnejší): desať potvrdených párov s najväčším rozdielom v každom smere, s odkazom na hlavný produkt.
- Konkurenti zoradení podľa potvrdených párov, s mediánom rozdielu, **Products read**, posledným čítaním a stavom.

Kým nepridáte prvého konkurenta, dashboard vysvetlí, čo robiť.

## Kontrola párov

Po každom čítaní AI porovná každý ich produkt s vašimi. Pár, v ktorom si je istá, sa **potvrdí sám** (označený *auto*); ostatné čakajú v **Prices to review**. Každý riadok ukazuje váš produkt, vašu cenu za jednotku, ich produkt s odkazom, ich cenu za jednotku v mene skupiny a rozdiel: červený, keď sú lacnejší, zelený, keď sú drahší.

Kliknite na **fajku**, keď je pár správny, a na **krížik**, keď nie je. Zamietnuté páry sa už nezobrazia. Voľby hore prepínajú medzi **To review**, **Confirmed**, **Rejected** a **All**; voľba **Confirmed** slúži zároveň ako porovnanie cien, zoraditeľné podľa rozdielu.

Pár označený *Similar, not the same item* (podobný, nie rovnaká položka) sa ponechá ako nápoveda, ale do porovnania cien sa nikdy nezapočíta.

## Stránka konkurenta

Kliknutím na názov konkurenta v zozname alebo na dashboarde uvidíte jeho hlavný obchod, web, komu predáva, menu, ako sa čítajú jeho ceny, či sa aiku prihlasuje, jeho stav a poslednú chybu, a dve karty:

- **Matches**: jeho páry s rovnakou kontrolou fajkou a krížikom;
- **Products read**: všetko, čo sa z neho prečítalo, s cenou, jednotkami a odkazom.

**Edit** otvorí rovnaký formulár ako pri pridávaní, bez hlavného obchodu.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Dashboard:</b> <b>Masters</b> → <b>Competitors</b>.</li>
<li><b>Pridať konkurenta:</b> <b>Masters</b> → <b>Competitors</b> → <b>Add competitor</b>.</li>
<li><b>Skontrolovať páry:</b> <b>Masters</b> → <b>Competitors</b> → <b>Prices to review</b> → fajka alebo krížik na každom riadku.</li>
<li><b>Porovnanie cien:</b> <b>Masters</b> → <b>Competitors</b> → <b>Prices to review</b> → <b>Confirmed</b>.</li>
<li><b>Jeden konkurent:</b> <b>Masters</b> → <b>Competitors</b> → <b>Competitors</b> → kliknite na jeho názov.</li>
</ul>
</aside>

<aside class="permissions"><strong>Oprávnenia, ktoré potrebujete</strong>
<p>Konkurenti sú na úrovni skupiny spolu s hlavnými obchodmi. Na zobrazenie dashboardu, zoznamov a stránok konkurentov potrebujete prístup k masters na úrovni skupiny a editačný prístup k masters na pridanie alebo úpravu konkurenta a na kontrolu párov.</p>
</aside>
