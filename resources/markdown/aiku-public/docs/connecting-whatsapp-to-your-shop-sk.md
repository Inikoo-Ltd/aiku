---
title: Pripojenie WhatsApp k vášmu obchodu
summary: Čo si pripraviť z Meta, na ktorú z dvoch nastavovacích stránok každá hodnota patrí, a ako pred spoľahnutím sa na to overiť, že spojenie funguje.
date: 2026-11-03
source_date: 2026-11-03
tags: marketing, whatsapp, shop
category: help-desk
series: WhatsApp
order: 1
---

<aside class="tldr">
WhatsApp potrebuje nastavenia na <b>dvoch miestach</b>, a práve to takmer každého zaskočí. Meta app — access key, app id, app secret — patrí na nastavenia <b>organizácie</b>. Telefónne číslo a business account patria na nastavenia <b>obchodu</b>. Obchod, ktorý má vyplnenú len svoju polovicu, vyzerá v aiku všade pripojený a zlyhá až vo chvíli, keď sa pokúsi niečo odoslať. Vyplňte obe, potom skontrolujte tri veci na konci tejto stránky. A ak sa číslo používa v aplikácii <b>WhatsApp Business app</b> na telefóne, nemažte ho z aplikácie: pripojte ho cez <b>Connect WhatsApp Business app</b>, ako je popísané nižšie.
</aside>

## Čo vlastne pripájate

WhatsApp nie je jeden účet. Je to **Meta app**, ktorú vlastní organizácia, plus **WhatsApp Business Account** — každý mu hovorí WABA — a **telefónne číslo** pod ním, ktoré patria obchodu.

Pripravte si týchto päť hodnôt z Meta dashboardu, kým začnete. Všetko potom je už len vyplnenie dvoch formulárov.

| Hodnota | Kde ju nájdete v Meta |
| --- | --- |
| App ID | App dashboard, hore |
| App secret | App dashboard → Settings → Basic |
| Access token | Business settings → System users → Generate token |
| WABA ID | WhatsApp → API Setup |
| Phone number ID | WhatsApp → API Setup, pod číslom |

**Access token musí byť trvalý.** Meta ponúkne najprv dočasný token a ten po 24 hodinách vyprší — na druhý deň všetko prestane fungovať bez akéhokoľvek varovania a bez zjavnej príčiny. Toto je zďaleka najčastejšia chyba pri nastavovaní.

## Číslo, ktoré už je v aplikácii WhatsApp Business app

Bežné nastavenie prijme len číslo, ktoré v aplikácii WhatsApp Business app nie je, preto zvyčajná rada znie najprv zmazať účet v aplikácii. **Nerobte to.** Zmazaním sa pôvodná WhatsApp registrácia čísla zruší nadobro a každý zákazník, ktorý s ním niekedy chatoval, potom vidí "This person is no longer on WhatsApp" (Táto osoba už nie je na WhatsApp) a nemá kam písať. Znova sa dostane k správe, až keď si kontakt zmaže a uloží nanovo, napíše z WhatsApp Web, alebo mu najprv napíšete vy. Stalo sa to obchodom, ktoré boli presunuté týmto spôsobom.

Číslo v aplikácii sa pripája cez **Connect WhatsApp Business app** (Pripojiť aplikáciu WhatsApp Business), čo Meta nazýva Coexistence. Číslo ostane v aplikácii na telefóne obchodu a aiku ho číta aj píše tiež:

- **Odpovede napísané na telefóne sa objavia v aiku** ako odpovede obchodu v konverzácii zákazníka, takže chat, na ktorý sa odpovedalo z telefónu, nezostane v inboxe čakať ako nezodpovedaný. Zatvorenú konverzáciu znova neotvoria.
- **História chatov sa prenesie jednorazovo.** Počas prihlásenia sa telefón opýta, či ju zdieľať. Ak súhlasíte, skopíruje sa až šesť mesiacov individuálnych chatov ako zatvorené konverzácie, už prečítané. Obrázky a súbory v nej prídu ako poznámka, že sa niečo zdieľalo, nie ako samotný súbor. Skupinové chaty sa nikdy nekopírujú.
- **Hovory zvonia len na telefóne.** WhatsApp hovory na číslo sa v aiku prijať nedajú.
- **Aplikáciu WhatsApp Business app na tom telefóne otvorte aspoň raz za 13 dní.** Inak Meta číslo od aiku odpojí.
- **Kampane odchádzajú o niečo pomalšie.** Meta povoľuje takémuto číslu 20 správ za sekundu.

Takto sa dá pripojiť len číslo, ktoré je v aplikácii práve teraz. Číslo, ktoré už bolo presunuté na bežné nastavenie, sa späť prepnúť nedá.

Pripojenie:

1. **Meta-configuration** (Meta konfigurácia) organizácie potrebuje okrem App ID a App Secret aj **Embedded Signup configuration ID**. Požiadajte svojho vývojára: vytvára sa v Meta app dashboarde.
2. V nastaveniach obchodu pod **Chat** zapnite **Enable WhatsApp Channel**. Pod **Phone Number Status** (Stav telefónneho čísla) stlačte **Connect WhatsApp Business app**.
3. Otvorí sa okno Meta. Vyberte firmu, potom zvoľte možnosť pripojiť číslo z aplikácie WhatsApp Business. Zobrazí sa QR kód: naskenujte ho aplikáciou WhatsApp Business na telefóne obchodu a ak chcete históriu chatov, súhlaste s jej zdieľaním.
4. Keď sa okno zatvorí, aiku si samo vyplní Phone Number ID, WABA ID a Phone Number a stav ukáže **Coexistence**. Nie je čo overovať ani registrovať: číslo už registrované je.

Urobte to naraz. O históriu chatov sa dá požiadať len raz, do jedného dňa od pripojenia, a aiku o ňu žiada ako súčasť pripojenia.

## Ako povedať Meta, kam posielať správy

V dashboarde Meta app, pod **WhatsApp → Configuration**, nastavte callback adresu na vašu doménu aiku s pripojeným `/webhooks/whatsapp` a verify token. Verify token sa musí zhodovať s nastavením na strane aiku, takže si ho **najprv vyžiadajte od svojho vývojára** a vložte presne to, čo vám dá — je to zmena nastavenia z ich strany, nie zmena kódu.

Stlačte **Verify and save**. Meta ihneď zavolá aiku, takže ak sa sťažuje, buď adresa alebo token sú zle.

Potom otvorte **Manage** vedľa webhook polí a prihláste sa presne na dve:

- **messages** — všetko, čo vám zákazník pošle, plus potvrdenia o doručení a prečítaní.
- **message_template_status_update** — Meta verdikt po dokončení preskúmania šablóny.

Prihlásiť sa na menej niečo konkrétne pokazí: bez prvej neprídu žiadne správy vôbec, a bez druhej vaše šablóny navždy zobrazujú "pending" aj potom, čo ich Meta schválila.

Ak ktorýkoľvek obchod na Meta app organizácie používa číslo z aplikácie WhatsApp Business app, prihláste sa aj na **smb_message_echoes** (odpovede napísané na telefóne), **history** (chaty skopírované pri pripojení) a **smb_app_state_sync** (kontakty z telefónu, ktoré aiku nepoužíva). Obchodov s bežným nastavením sa to netýka.

## Polovica organizácie

Otvorte nastavenia **vašej organizácie** a nájdite **Meta-configuration**.

- **Access Key** — access token. Podpisuje ním všetko, čo aiku posiela. Bez neho neodíde nič.
- **App ID** — použije sa pri nahrávaní vzorového obrázka, voči ktorému sa šablóna posudzuje.
- **App Secret** — dokazuje, že prichádzajúca správa naozaj pochádza z Meta a nie od niekoho, kto sa za Meta vydáva.

Každá organizácia používa vlastnú Meta app, takže sa nie je na čo spoľahnúť namiesto toho. Prázdne tu znamená prázdne: aiku si nastavenia inej organizácie nepožičia.

## Polovica obchodu

Otvorte nastavenia **obchodu** a prejdite na **Chat**. Zapnite **Enable WhatsApp Channel**, čím sa odkryjú ďalšie tri polia.

- **Phone Number ID** — z ktorého čísla odosielate, a podľa čoho aiku pozná, že prichádzajúca správa patrí tomuto obchodu.
- **WABA ID** — potrebné pre všetko okolo šablón.
- **Phone Number** — samotné číslo, zapísané tak, ako by ho videl zákazník. Toto slúži len na zobrazenie; objaví sa v náhľade kampane.

## Overenie, že to funguje

Tri kontroly, v tomto poradí. Každá závisí od predchádzajúcej, takže zlyhanie vám povie, kde hľadať.

1. **Meta prijala webhook.** Povedala vám to, keď ste stlačili Verify and save.
2. **Správy prichádzajú.** Pošlite WhatsApp správu na dané číslo z vlastného telefónu. Do pár sekúnd by sa konverzácia mala objaviť v chat inboxe obchodu. Ak sa nič neobjaví, prichádzajúce správy sa k aiku nedostávajú — pozrite [keď WhatsApp nefunguje](/docs/when-whatsapp-is-not-working-sk).
3. **Šablóny sa synchronizujú.** Otvorte **Chat → WhatsApp templates** a stlačte **Sync**. Malo by sa objaviť všetko, čo je už schválené v Meta. Ak tlačidlo hlási chybu, WABA ID alebo access token sú zle.

Keď prejdú všetky tri, obchod môže viesť konverzácie a posielať kampane.

## Skôr než niečo odošlete

**Šablóny nie sú voliteľné.** WhatsApp nedovolí firme otvoriť konverzáciu voľným textom. Každá kampaň a každá odpoveď odoslaná viac ako 24 hodín po tom, čo vám zákazník naposledy napísal, musí použiť šablónu, ktorú Meta vopred schválila. Píšte a odosielajte ich cez **Chat → WhatsApp templates** a počítajte s tým, že preskúmanie trvá od pár minút po deň.

**Šablóna pre kampane musí byť v kategórii Marketing.** Šablóny v kategóriách Utility alebo Authentication sú v konverzáciách úplne použiteľné, no v selektore kampaní sa nikdy nezobrazia. Rozhoduje o tom Meta, nie aiku, a kategóriu nemožno po vytvorení šablóny zmeniť.

**Mať telefónne číslo neznamená mať povolenie niekomu poslať reklamu.** Zákazníci sa prihlasujú na WhatsApp newsletter pri registrácii a pri checkoute, a predvolene kampane idú len tým, ktorí to urobili. [Odosielanie WhatsApp kampane](/docs/sending-a-whatsapp-campaign-sk) sa venuje tejto voľbe.

## Oplatí sa niekde si zapísať

- Ktorému Meta system userovi patrí access token. Keď niekto tohto usera nakoniec zmaže, WhatsApp prestane fungovať a nikto si nespomenie prečo.
- Kto vo firme schvaľuje znenie šablóny pred odoslaním do Meta. Zamietnutá šablóna znamená pomalý kolobeh.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Polovica organizácie:</b> vaša organizácia → <b>Settings</b> → <b>Meta-configuration</b> → Access Key, App ID, App Secret.</li>
<li><b>Polovica obchodu:</b> váš obchod → <b>Settings</b> → <b>Chat</b> → zapnite <b>Enable WhatsApp Channel</b>, potom Phone Number ID, WABA ID a Phone Number.</li>
<li><b>Číslo v aplikácii WhatsApp Business app:</b> vaša organizácia → <b>Settings</b> → <b>Meta-configuration</b> → Embedded Signup configuration ID; potom váš obchod → <b>Settings</b> → <b>Chat</b> → <b>Phone Number Status</b> → <b>Connect WhatsApp Business app</b>.</li>
<li><b>Overiť, že správy prichádzajú:</b> váš obchod → <b>Chat → Inbox</b>, po odoslaní správy na číslo z vlastného telefónu.</li>
<li><b>Overiť šablóny:</b> váš obchod → <b>Chat → WhatsApp templates</b> → <b>Sync</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Oprávnenia, ktoré potrebujete</strong>
<ul>
<li>Nastavenia organizácie aj nastavenia obchodu sú obrazovky pre administrátora. Ak na organizácii nevidíte <b>Meta-configuration</b>, nemáte na toto práva a budete potrebovať niekoho, kto ich má.</li>
</ul>
</aside>
