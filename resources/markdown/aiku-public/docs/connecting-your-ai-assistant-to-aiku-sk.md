---
title: Pripojenie tvojho AI asistenta k aiku
summary: Pripoj ChatGPT, Claude, Gemini alebo Perplexity k aiku cez svoje vlastné prihlásenie, potom sa ho pýtaj na predaj, sklad, objednávky, zákazníkov a svoj tím a, ak to administrátor povolí, nechaj ho robiť zmeny za teba. Ako sa pripojiť, čo vie čítať, čo vie meniť, nápady na vyskúšanie a čo robiť, keď pripojenie zlyhá.
date: 2026-11-28
source_date: 2026-11-28
tags: profile, ai, access
category: profile
---

<aside class="tldr">
Administrátor ti na tvojom používateľovi zapne <b>Can connect AI assistant</b> (môže pripojiť AI asistenta). Potom pridáš aiku do svojho asistenta cez adresu <code>https://app.aiku.io/mcp/aiku</code>, klikneš na <b>Connect</b>, ak sa to pýta, prihlásiš sa do aiku a stlačíš <b>Authorize</b>. Tvoj asistent číta aiku ako ty: len obchody, organizácie a sklady, ktoré vidíš ty. Iba číta, kým mu administrátor nedá aj niektoré z oprávnení na zápis. Tvoj profil, karta <b>AI access</b>, presne ukazuje, čo vie ten tvoj, aj s príkladmi na skopírovanie.
</aside>

## Skôr než začneš

**Požiadaj administrátora, aby to zapol.** Na tvojom používateľovi pod <b>Access</b> zapne <b>Can connect AI assistant</b>. Bez toho v tvojom profile chýba karta <b>AI access</b> a stránka na pripojenie hlási, že AI asistenta nemôžeš používať, a tlačidlo <b>Authorize</b> je sivé.

**Použi svoje vlastné prihlásenie do aiku a svoj vlastný účet asistenta.** Všetko, čo tvoj asistent číta alebo mení, sa robí ako ty a zaznamená sa pod tvojím menom. Asistent kolegu vidí to, čo vidí on, nie to, čo ty, a na to, čo robíš ty, sa zapíše jeho meno.

**Skontroluj, čo vie ten tvoj.** Otvor svoj profil (klikni na svoj obrázok, potom <b>My profile</b>) a kartu <b>AI access</b>. Nájdeš v nej adresu na skopírovanie, kroky pre každého asistenta a pod <b>What your assistant can do</b> (čo vie tvoj asistent robiť) každé svoje oprávnenie, spolu s promptmi, ktoré môžeš skopírovať do asistenta.

## Pripojenie

Pripojíš sa raz. Tvoj asistent zostane pripojený, kým ho neodstrániš.

### ChatGPT

1. Vo firemnom workspace je aplikácia <b>Aiku</b> už pridaná pre všetkých: otvor ju v <b>Apps</b> a klikni na <b>Connect</b>. Na osobnom pláne choď do <b>Settings → Connectors</b>, vytvor konektor s názvom <b>Aiku</b> a s vyššie uvedenou adresou (ak možnosť vytvoriť nie je, zapni <b>Developer mode</b> pod <b>Advanced</b>).
2. Otvorí sa okno s aiku. Ak v tom prehliadači nie si prihlásený do aiku, aiku ukáže svoju prihlasovaciu stránku s poznámkou, že pripájaš svojho asistenta. Prihlás sa a ak používaš 2FA, zadaj svoj 2FA kód.
3. aiku ukáže <b>Authorize ChatGPT</b> s tvojím používateľským menom. Skontroluj, že si to ty, a stlač <b>Authorize</b>. Okno sa zatvorí.
4. V novom chate zapni aplikáciu Aiku a polož svoju otázku.

### Claude

1. V claude.ai klikni na svoje iniciály, potom <b>Settings → Connectors → Add custom connector</b>.
2. Pomenuj ho <b>Aiku</b>, vlož adresu a klikni na <b>Connect</b>.
3. Prihlás sa a stlač <b>Authorize</b> ako v krokoch 2 a 3 vyššie.

### Gemini a Perplexity

Gemini funguje iba s osobným účtom Google: <b>Settings & help → Connected apps</b>, pridaj vlastnú aplikáciu s touto adresou, potom sa prihlás a autorizuj. Perplexity (platené plány) namiesto prihlásenia žiada kľúč: vytvor ho vo svojom profile v <b>AI access → Access keys</b> a vlož ho tam. Kľúč funguje ako heslo; ak ho prestaneš používať, zruš ho na tej istej stránke.

## Čo vie čítať

Keď je <b>Can connect AI assistant</b> zapnuté, tvoj asistent odpovedá na otázky o všetkom, čo v aiku vidíš. Hovor s ním vlastným jazykom, tak ako by si sa pýtal kolegu, ktorý aiku dobre pozná.

**Predaj a produkty**

> *"Ako sa predával môj obchod minulý týždeň v porovnaní s týždňom predtým? Ktoré skupiny produktov (families) vyrástli najviac a ktoré klesli?"*

> *"Ktoré produkty sa dobre predávajú, ale zostáva z nich zásoba na menej než dva týždne?"*

> *"Pri ktorých produktoch bolo v tomto štvrťroku najviac vrátení peňazí?"*

> *"Ktoré aktívne produkty nemajú obrázok?"*

**Objednávky a zákazníci**

> *"Ukáž mi objednávku AW-123456: kde je a kedy bola odoslaná?"*

> *"Nájdi zákazníka s e-mailom customer@example.com: čo si tento rok objednal a je ešte niečo otvorené?"*

> *"Koľko zákazníkov, ktorí sa zaregistrovali v septembri, urobilo prvú objednávku?"*

**Marketing a weby**

> *"Ktoré ponuky priniesli minulý mesiac najviac objednávok a ktoré nepoužil nikto?"*

> *"Ako dopadol newsletter z minulého týždňa: otvorenia, kliknutia a objednávky?"*

> *"Čo zákazníci tento mesiac píšu v recenziách? Zhrň sťažnosti."*

**Sklad a tím**

> *"Koľko dodacích listov čaká na vychystanie v sklade a ako rýchlo sme včera balili?"*

> *"Ktorá zásoba sa nepredala šesť mesiacov a koľko stojí?"*

> *"Koľko by si účtoval každý dopravca za poslanie 4 kg balíka do Írska?"*

**Pomoc od kolegov**

> *"Vytvor tiket: tlačiareň štítkov v balení tlačí od rána prázdne štítky."*

> *"Požiadaj sklad, aby spočítal lokáciu A-12-03, a daj mi vedieť, keď to bude hotové."*

> *"Pošli vedúcemu skladu správu, že paleta pre španielskeho partnera je pripravená."*

**Verba, chat zamestnancov**

Tvoj asistent číta tvoje vlastné konverzácie vo Verbe, V·Desky, ktoré môžeš čítať, a tvoje Slack DM, a môže tam v tvojom mene poslať správu. Najprv ti ukáže, kam správa pôjde a jej presné znenie, a pošle ju až po tvojom áno. Nikdy nečíta konverzácie iných ľudí a čítaním nič neoznačí ako prečítané. Vie aj zmeniť tvoju tapetu vo Verbe a tvoje V·Desky s hviezdičkou.

> *"Čo som dnes ráno zmeškal vo Verbe? Zhrň moje neprečítané konverzácie."*

> *"Napíš do V·Desku balenia, že kuriér príde dnes o 15:00."*

<b>Super intelligence 🧠</b> je samostatné oprávnenie pre pár ľudí, ktorým sa dôveruje so všetkými údajmi firmy. Ich asistent sa môže pýtať čokoľvek na všetko v aiku, v každom obchode a organizácii, vrátane údajov, ktoré v aiku sami nevidia, a na systémové záznamy (chyby, pomalé stránky, úlohy na pozadí). Stále iba číta.

## Čo vie meniť

Každý druh zmeny má svoje oprávnenie, pod <b>Through their AI assistant</b> (cez ich AI asistenta) na tvojom používateľovi. Administrátor zapne iba tie, ktoré tvoja práca potrebuje. Stále potrebuješ rovnaké oprávnenie na úpravy v aiku: oprávnenie nikdy nedovolí tvojmu asistentovi urobiť niečo, čo by si nemohol urobiť sám.

Skôr než niečo zmení, asistent ti ukáže, čo sa zmení, a čaká na tvoje áno. Každá zmena sa uloží do záznamu <b>AI changes</b> s tvojím menom a presnými slovami, ktoré si použil, a väčšinu z nich tam môžeš vrátiť späť.

### Goods

**Can discontinue SKOs.** Pozri všetko, čo ešte od SKO závisí (zásoba, otvorené nákupné objednávky, dropshippingoví zákazníci, živé stránky, otvorené objednávky), potom ho zmeň na discontinuing, discontinued, suspended alebo späť na active a zastav alebo obnov jeho objednávanie od dodávateľa.

> *"Čo ešte závisí od SKO ABC-01, ak ho vyradím?"*

> *"Vyraď ABC-01 vo všetkých organizáciách okrem Španielska a prestaň ho objednávať od dodávateľa."*

**Can edit trade units.** Zmeň názvy, hmotnosti, zloženie, pôvod, colné a bezpečnostné kódy, ktoré sa prenesú do každého produktu predávajúceho túto trade unit, daj čiarový kód trade unit, ktorá ho nemá, a zaznamenaj obal odvážený v sklade.

> *"Nastav čistú hmotnosť ABC-01 na 250 g a jeho colný kód na 3307 41 00."*

### Catalogue

**Can change master prices.** Ulož master ceny, za ktoré predáva každý obchod nasledujúci master, a daj master produkty na predobjednávku.

> *"Ukáž master ceny skupiny Incense Cones s nákladmi a maržou, potom ich všetky zvýš o 5 %."*

**Can change website content and redirects.** Zmeň súvisiace produkty („Sells well with“) na stránkach skupín, presuň produkt do inej skupiny a presmeruj redirecty zatvorených stránok na živé stránky.

> *"Stará stránka Gift Sets je zatvorená: presmeruj jej redirecty na oddelenie Gifts."*

**Info websites.** Webmasteri skupiny môžu meniť čisto informačné weby (stránky, hlavičku, pätu). Nič sa neuloží, kým nevidíš náhľad.

> *"Na aw.gifts zmeň otváracie hodiny v päte na 9:00 až 17:00 a ukáž mi náhľad."*

### Procurement

**Can add to the manufacturing hub shopping list.** Pozri, čo objednať z hubu, od najnaliehavejšieho, a pridávaj SKO po celých dávkach. Hub vidí zoznam až vtedy, keď ho niekto odošle.

> *"Čo by sme mali tento týždeň objednať z manufacturing hubu? Pridaj desať najnaliehavejších."*

**Can place orders to the manufacturing hub, partners and suppliers.** Odošli košík hubu, zadaj záchranné objednávky partnerom a odošli nákupné objednávky dodávateľom. Objednávky sa nedajú vrátiť späť zo záznamu AI changes.

> *"Zadaj nákupnú objednávku nášmu dodávateľovi vonných tyčiniek na všetko, čoho zostáva zásoba na menej než štyri týždne."*

**Can save stock delivery costs.** Ulož náklady na dopravu, clo a ďalšie náklady skladových dodávok, ktoré rozhodujú o hodnote zásoby a o maržiach.

> *"Ktorým skladovým dodávkam ešte chýbajú náklady? Dodávka SD-1234: doprava stála 820 eur, ulož to."*

### Warehouse

**Can put SKOs on locations.** Pridaj SKO na skladové lokácie, viacero naraz. Nikdy nepresúva zásobu, nemení množstvo ani neberie SKO z lokácie.

> *"Daj týchto dvadsať SKO na lokáciu B-04-02."*

### Production

**Can set up artefacts, raw materials and recipes.** Vytváraj a upravuj artefakty, suroviny a úlohy a nahraď kroky receptu.

> *"Ukáž mi recept artefaktu ABC-01, potom pridaj po plnení krok označovania rýchlosťou 120 kusov za hodinu."*

### Customer service

**Can serve customers.** Prečítaj chaty, ktoré na teba čakajú, so všetkým, čo o nich aiku vie, a ulož návrhy odpovedí, ktoré potom odošleš ty. V chate, ktorý pomenuješ, vie aj odpovedať, zatvoriť chat, vyriešiť reklamáciu a zmeniť ešte nebalenú objednávku. Pozri [Odpovedanie zákazníkom s tvojím AI asistentom](/docs/answering-customers-with-your-ai-assistant-sk).

> *"Ktoré chaty na mňa čakajú? Otvor najstarší a napíš návrh odpovede so žiadosťou o fotky rozbitých hrnčekov."*

### Settings

**Can change who gets Ask CRM and Ask warehouse questions.** Pridaj a odober zamestnancov v týchto zoznamoch v jednom obchode alebo vo všetkých obchodoch organizácie.

> *"Pridaj nového kolegu zo zákazníckeho servisu do Ask CRM vo všetkých obchodoch organizácie."*

**Admini Verby** vyberajú, ktorým organizáciám sa každý verejný V·Desk zobrazí v zozname, alebo ho skryjú zo všetkých zoznamov. Nikto nestratí prístup a Slack sa nemení; každá zmena sa zapíše do histórie kanála s tvojím menom.

> *"Zobraz španielske V·Desky iba španielskej organizácii."*

<aside class="warning">
Oprávnenia, ktoré menia, koľko platia zákazníci, míňajú peniaze alebo menia hodnotu zásoby, sú pre ľudí, ktorí o týchto veciach rozhodujú. Skôr než o také požiadaš administrátora, opýtaj sa svojho nadriadeného.
</aside>

## Ako získať dobré odpovede

- **Povedz, ktorý obchod a ktoré dátumy.** *"Britský obchod, 1. až 30. novembra"* je lepšie než *"nedávno"*.
- **Keď chceš porovnávať, popros o tabuľku.** *"Ako tabuľku s minulým rokom vedľa."*
- **Popros ho, aby ukázal, ako na to prišiel.** *"Ktoré čísla si použil?"* Číta tie isté čísla, ktoré vidíš v aiku, takže si ich tam môžeš skontrolovať.
- **Čítaj, než povieš áno.** Pri zmene presne vypíše, čo sa zmení. Ak niečo vyzerá zle, povedz nie alebo to oprav.
- **Vie, čo vidíš ty, nič viac.** Ak povie, že obchod nevidí, nemáš k nemu prístup ani ty v aiku.

## Keď pripojenie nefunguje

- **Stránka s hláškou "This link is not complete".** Adresu na pripojenie v aiku si otvoril sám. Zavri ju, vráť sa k svojmu asistentovi a klikni tam na <b>Connect</b>.
- **Stránka s hláškou "This connect page has expired".** Už bola použitá alebo zostala otvorená príliš dlho. Zavri ju a v asistentovi klikni na <b>Connect</b> znova.
- **"You cannot use the AI assistant" a sivé tlačidlo Authorize.** <b>Can connect AI assistant</b> je na tvojom používateľovi vypnuté. Opýtaj sa administrátora.
- **Stránka na pripojenie ukazuje cudzie používateľské meno.** Tvoj prehliadač je do aiku prihlásený ako niekto iný. Odhlás sa z aiku v tom prehliadači a potom klikni na <b>Connect</b> znova.
- **Žiada sa 2FA kód.** Používaš dvojfaktorové prihlásenie, preto sa aiku pred pripojením pýta na tvoj kód. Zadaj kód z tvojej autentifikačnej aplikácie.
- **Asistent hlási "Couldn't connect to the server" po autorizácii.** Tvoj prístup bol vypnutý alebo si autorizoval ako používateľ bez prístupu. Opýtaj sa administrátora, potom odstráň Aiku zo svojho asistenta a pripoj sa znova.

Ak to stále zlyháva, vytvor tiket a napíš, ktorého asistenta si použil a čo stránka ukázala. Administrátori vidia každý pokus o pripojenie a jeho výsledok v <b>System Administration → AI analytics → Connections</b>.
