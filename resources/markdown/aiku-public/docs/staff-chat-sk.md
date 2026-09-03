---
title: Rozhovor s kolegami v internom chate
summary: Lišta správ na pravej strane každej obrazovky aiku - napíšte kolegovi, opýtajte sa CRM alebo skladu na konkrétnu objednávku či dodací list jedným ťuknutím a rozhodnite, kto tieto otázky dostáva.
date: 2026-09-02
source_date: 2026-09-02
tags: crm, dispatch, hr, chat, messaging
category: crm
---

<aside class="tldr">
Interný chat je na to, aby sa ľudia pracujúci v aiku rozprávali medzi sebou. Je úplne oddelený od zákazníckeho chatu na vašom webe: zákazníci ho nikdy nevidia a nikdy sa k nim nedostane. Skladníci ho používajú z tabletov, aby sa dostali k zákazníckemu servisu bez odchodu od baliaceho stola, a zákaznícky servis ním odpovedá. Ak vám prišla správa s názvom <b>CRM · </b><i>referencia objednávky</i>, ktorú ste nečakali, preskočte na <a href="#who-receives">Kto dostáva "Ask CRM"</a>: to je nastavenie, ktoré riadi váš obchod.
</aside>

## Kde sa nachádza

Každá obrazovka v aiku má vpravo úzku **lištu správ**. Kliknutím ju rozbalíte. Ukazuje, kto je **práve online**, váš **tím** a otvorené **správy**, s červeným počítadlom pre všetko neprečítané. Na telefóne alebo tablete sa konverzácia otvorí ako celoobrazovkový hárok s veľkými tlačidlami; na počítači ako malé okno v spodnej časti stránky a **Open full view** vás prenesie na celostránkovú verziu so zoznamom konverzácií vľavo.

Lišta má dole aj položku **Customer chats** pre tých, ktorí sú nastavení ako chatoví agenti. To je webový chat opísaný v článku [Rozhovor so zákazníkmi v Chate](/docs/customer-chat-sk), čo je iná vec.

## Písanie kolegovi

Otvorte lištu, nájdite osobu v **Everyone online** alebo **Find a coworker…** a kliknite na jej meno. Kolega je ktokoľvek, kto pracuje v organizácii, ku ktorej máte prístup aj vy. Napíšte a stlačte Enter. Je tam všetko, čo od messengera čakáte: vloženie snímky obrazovky alebo priloženie obrázka, odpoveď na konkrétnu správu, reakcia emoji, odoslanie GIFu a spomenutie niekoho cez **@**, aby sa mu správa zobrazila s farebným označením.

Dve veci sú špecifické pre aiku:

- **Rýchle odpovede.** Nad poľom na písanie sú tlačidlá ako **Done**, **Help!**, **Call me**, **OK**, **Thanks**. Jedno ťuknutie slovo odošle. Existujú preto, lebo balič v rukaviciach s tabletom by nemal musieť písať. Vaša skupina môže zoznam zmeniť.
- **Automatický preklad.** Každá správa sa preloží do jazyka, ktorý každý účastník používa v aiku. Vy ju čítate vo svojom, oni vo svojom a ktokoľvek môže ťuknúť na **original** a vidieť, čo bolo naozaj napísané. Nikto nemusí písať po anglicky.

Váš zoznam **My team** je tých pár ľudí, s ktorými hovoríte najviac. Pridajte ich cez **Add to my team** a ostanú navrchu lišty so svojou bodkou prítomnosti. Online znamená, že osoba má aplikáciu otvorenú a za poslednú štvrťhodinu niečo urobila; oranžová znamená nečinná.

## Otázka pre CRM alebo sklad k objednávke

Toto tvorí väčšinu prevádzky interného chatu. Niektoré obrazovky majú tlačidlo, ktoré otvorí konverzáciu presne o tom dokumente, so správnymi ľuďmi už vnútri:

- Stránka **dodacieho listu** (delivery note): **Ask CRM**.
- Stránka **objednávky**: **Ask warehouse** a **Ask CRM**.
- Stránka **picking session**: **Ask CRM**.
- Keď dopravca odmietne vytvoriť prepravný štítok, box zásielky zobrazí chybu samotného dopravcu a tlačidlo **Ask CRM about this**. Ťuknutím sa dopravca a chyba zapíšu do CRM konverzácie dodacieho listu za vás, bez písania.

Konverzácia je pomenovaná podľa svojho dokumentu, napríklad **CRM · AFR26782**, aby každý videl, o čom je, ešte pred otvorením. Opätovné stlačenie tlačidla neskôr sa vráti do tej istej konverzácie namiesto vytvorenia novej.

Čerstvú konverzáciu nevidí nikto okrem vás, kým sa neodošle prvá správa, takže omylom otvorená konverzácia nikoho neobťažuje. Ak na druhej strane nikto nie je (prázdny zoznam príjemcov a nikto s danou rolou), aiku vám to povie namiesto toho, aby poslalo správu do prázdnej miestnosti.

<h2 id="who-receives">Kto dostáva "Ask CRM" a "Ask warehouse"</h2>

Príjemcovia sú zoznam, ktorý spravujete vy, nie každý, kto bol niekedy pracovníkom zákazníckeho servisu. Každý obchod má vlastné zoznamy, pretože o zákazníkov obchodu sa stará tím toho obchodu:

1. Otvorte **Settings** obchodu a nájdite sekciu **Staff chat**.
2. **Ask CRM goes to** je hlavný zoznam. **Ask CRM backup** sa použije, keď nikto z hlavného zoznamu práve nie je aktívny.
3. **Ask warehouse goes to** a **Ask warehouse backup** fungujú rovnako pre otázky opačným smerom.

**Settings** organizácie majú tiež sekciu **Staff chat**, ale len pre skladové zoznamy: sú predvolené, keď obchod nemá vlastné. Ask CRM je vždy per obchod.

Pravidlá, ktorými sa aiku riadi po stlačení tlačidla:

- Ak je niekto z hlavného zoznamu aktívny, pridajú sa len aktívni.
- Inak aktívni ľudia zo záložného zoznamu.
- Ak nie je aktívny nikto z ani jedného zoznamu, pridajú sa všetci z oboch zoznamov a otázka počká na prvého, kto sa vráti.
- Ak obchod nemá žiadne zoznamy, otázka ide všetkým, ktorí majú roly zákazníckeho servisu pre daný obchod (alebo skladové roly pre daný sklad).

Takže ak sa skladová otázka dostane k niekomu, kto by ju dostať nemal, náprava je v Settings obchodu, nie v chate. Odstráňte ho zo zoznamu, alebo dajte obchodu zoznam, ak žiadny nemal.

## Zatváranie a poriadok

Zatvorenie konverzácie cez **X** ju archivuje len pre vás; sama sa vráti, keď do nej niekto znova napíše. V skupinovej konverzácii môžete použiť aj **Leave this conversation for now**. Nič sa nemaže.

Interné chaty sú pracovné rozhovory, nie súkromné. HR a správcovia organizácie ich môžu čítať cez **Human Resources → Staff chat**, kde sú konverzácie so správami. Stránka sysadmina ukazuje počty a kto s kým píše, nikdy text.

## Prispôsobenie

V **My profile** si môžete nastaviť **Chat nickname**, ktorý nahradí vaše meno v lište, a vybrať farebnú tému chatu. Správcovia skupiny môžu upraviť rýchle odpovede v **Settings → Staff chat** skupiny, jednu na riadok.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Napísať niekomu:</b> lišta správ vpravo → <b>Everyone online</b> alebo <b>Find a coworker…</b> → jeho meno → napíšte → Enter.</li>
<li><b>Opýtať sa na dokument:</b> otvorte dodací list, objednávku alebo picking session → <b>Ask CRM</b> / <b>Ask warehouse</b>.</li>
<li><b>Vybrať, kto tieto otázky dostáva:</b> vaša organizácia → váš obchod → <b>Settings → Staff chat</b> → <b>Ask CRM goes to</b>, <b>Ask CRM backup</b>, <b>Ask warehouse goes to</b>, <b>Ask warehouse backup</b>. Organizácia → <b>Settings → Staff chat</b> drží skladové predvolené nastavenie.</li>
<li><b>Zmeniť rýchle odpovede:</b> skupina <b>Settings → Staff chat → Quick replies</b>.</li>
<li><b>Prezývka a farby:</b> váš avatar → <b>My profile</b> → <b>Chat nickname</b>; <b>Settings</b> → téma chatu.</li>
<li><b>Čítať interné konverzácie ako HR:</b> organizácia → <b>Human Resources → Staff chat</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Potrebné oprávnenia</strong>
<ul>
<li><b>Posielať správy:</b> ktorékoľvek prihlásenie do aiku. Dosiahnete kohokoľvek, kto pracuje v organizácii, ku ktorej máte prístup aj vy.</li>
<li><b>Tlačidlá Ask CRM / Ask warehouse:</b> ktokoľvek, kto môže otvoriť dodací list, objednávku alebo picking session.</li>
<li><b>Upravovať zoznamy príjemcov:</b> ktokoľvek, kto môže upravovať nastavenia obchodu alebo organizácie.</li>
<li><b>Upravovať rýchle odpovede:</b> správca skupiny.</li>
<li><b>Čítať interné chaty iných:</b> HR alebo správca organizácie.</li>
</ul>
</aside>
