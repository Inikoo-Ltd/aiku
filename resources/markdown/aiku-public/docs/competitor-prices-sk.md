---
title: Ceny konkurentov
summary: Ako aiku číta ceny z webových stránok vašich konkurentov, páruje ich produkty s vašimi hlavnými produktmi a ukazuje, kde ste lacnejší alebo drahší, a čo kontrolovať na dashboarde Competitors.
date: 2026-11-06
source_date: 2026-11-06
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

**Jazyk ich webu** (**Language of their website**) sa zistí sám pri čítaní ich dossieru a dá sa zmeniť cez **Edit**. Ich produkty sa párujú a ich web sa prehľadáva s názvami vašich produktov v tomto jazyku, prevzatými z vášho obchodu v tomto jazyku, takže španielsky konkurent sa porovnáva s vašimi španielskymi názvami.

Pre obchod na Shopify alebo WooCommerce, ktorý ukazuje svoje ceny, to stačí: aiku si jeho verejný katalóg nájde samo a prečíta ho nasledujúce ráno o 04:00 UTC, každý produkt a každú veľkosť balenia s cenou. Na stránke konkurenta potom pri *Prices read from* stojí **Shopify catalogue** alebo **WooCommerce catalogue**.

Pri akejkoľvek inej webovej stránke vyplňte jedno z polí v **Where to read prices** (Odkiaľ čítať ceny):

- **Product feed link** (odkaz na produktový feed): CSV alebo textový súbor so všetkými ich produktmi a cenami, ktorý dávajú svojim obchodným zákazníkom. Najlepšia možnosť; číta sa každé ráno.
- **Search link** (odkaz na vyhľadávanie): vyhľadajte na ich webe ľubovoľné slovo, skopírujte odkaz na stránku s výsledkami a na miesto slova vložte `{query}`. aiku potom každý týždeň vyhľadáva na ich webe hlavné produkty, ktoré majú otvorený cenový tip. Ak odkaz pri webe s prihlásením necháte prázdny alebo zadaný odkaz vedie na stránku bez hľadaných slov, aiku použije odkaz na vyhľadávanie, ktorý web o sebe zverejňuje, ak ho má; inak sa pri konkurentovi zobrazí **The search link does not search**.
- **Login page** (prihlasovacia stránka), **login email** a **password**: iba keď ceny ukazujú prihláseným obchodným zákazníkom. Heslo sa ukladá zašifrované a už sa nezobrazí; pri úprave ho nechajte prázdne, ak ho chcete zachovať. aiku sa pri každom vyhľadávaní prihlási nanovo.

## Čo ukazuje dashboard

**Masters › Competitors** sa otvorí na dashboarde:

- **Competitors**: koľko sa prečítalo bez problémov, koľko zlyháva a prečo (web sa nedal otvoriť, zablokoval nás, zlyhalo prihlásenie alebo odkaz na vyhľadávanie nevyhľadáva), s poslednou chybou jasnými slovami a kedy bolo posledné čítanie.
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

Pár označený *Similar, not the same item* (podobný, nie rovnaká položka) sa ponechá ako nápoveda a do porovnania cien sa nezapočíta, kým naň neklikneš na **začiarknutie**: to znamená, že ide o rovnakú položku, a odvtedy sa započíta.

## Stránka konkurenta

Kliknite na názov konkurenta v zozname alebo na dashboarde. Hlavička ukazuje jeho logo, web, platformu, na ktorej beží jeho obchod (Shopify, WooCommerce a podobne), hlavný obchod, komu predáva, menu, ako sa čítajú jeho ceny, kedy sa ceny naposledy čítali a kedy sa naposledy obnovil dossier. Nasledujú štyri karty:

- **Dossier**, predvolená karta: spravodajský report o konkurentovi, ktorý sa raz týždenne vytvára z jeho verejných stránok (pozri nižšie).
- **Matches**: jeho páry s rovnakou kontrolou fajkou a krížikom.
- **Products read**: všetko, čo sa z neho prečítalo, s cenou, cenou pred zľavou, keď ju obchod ukazuje, výškou zľavy, jednotkami a odkazom. Filter **On sale now** nechá iba produkty so zľavou.
- **Policies**: text každej stránky o doprave, vrátení tovaru a obchodných podmienkach, ktorá sa čítala pre dossier, každá s odkazom, aby sa údaj z dossieru dal overiť na stránke, z ktorej pochádza.

**Edit** otvorí rovnaký formulár ako pri pridávaní, bez hlavného obchodu.

### Dossier

Každý týždeň aiku v prehliadači otvorí domovskú stránku konkurenta a jeho stránky o doprave, vrátení tovaru a obchodných podmienkach, presne ako návštevník, bez prihlásenia. Urobí snímku domovskej stránky, prečíta, čo firma o sebe zverejňuje, a AI z toho vytiahne údaje, každý s odkazom na stránku, z ktorej pochádza, a napíše krátky prehľad. Dossier sa vytvorí aj pri pridaní konkurenta a vždy, keď kliknete na **Refresh dossier** (trvá niekoľko minút; vráťte sa na stránku neskôr).

Karta ukazuje zhora nadol:

- **Briefing**: štyri až šesť viet o tom, kto sú, ako ceňujú oproti nám, ako veľmi sa spoliehajú na akcie, a o ich doprave a podmienkach, s jednoriadkovým zaradením na trhu.
- **What changed**: čo sa líši od predchádzajúceho obnovenia, s dátumami: hranica dopravy zadarmo, ceny alebo dodacie lehoty dopravy, minimálna objednávka, lehota na vrátenie, akcia alebo kupón, ktoré sa objavili alebo skončili, spôsoby platby, telefón, email alebo adresa, platforma, počet produktov, ktorý sa zmenil o viac ako 10 %, alebo podiel produktov v akcii, ktorý sa zmenil o viac ako 10 bodov. Skoršie zmeny nasledujú ako krátka časová os. Prvý dossier uvedie, že zatiaľ nie je s čím porovnávať.
- **Screenshot** ich domovskej stránky; kliknutím ho zväčšíte.
- **Identity**: názov firmy, telefóny, emaily, adresa, krajina a sociálne siete tak, ako ich zverejňuje ich web.
- **Key numbers**: počet produktov, cenové rozpätie, podiel produktov teraz v akcii, priemerná zľava, potvrdené páry a medián rozdielu oproti nám.
- **Site-wide offers**: bannery, kupónové kódy, zľavy za objem alebo po úrovniach a zľavy za odber newslettera, každá s odkazom na zdrojovú stránku, plus oznamy na ich domovskej stránke.
- **Share of products on sale, last 90 days**: čiara z denných čítaní katalógu, takže obchod s vysokou cenníkovou cenou, ktorý je stále v akcii, je tak aj vidieť.
- **Shipping and terms**: doprava zadarmo od, ceny dopravy, kam dodávajú, dodacie lehoty, minimálna objednávka, vrátenie tovaru a platby, každé s odkazom na stránku, ktorá to uvádza. *Not found on their pages* znamená, že prečítané stránky to neuvádzajú.
- **Most discounted right now**: ich produkty s najväčšou zľavou pri poslednom čítaní katalógu.

Údaj pochádza iba z prečítaných stránok: AI má pokyn povedať *not found*, nie hádať. Keď web prehliadač zablokuje alebo neodpovedá, dossier si ponechá posledný obsah a hlavička ukáže chybu.

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
