---
title: Keď WhatsApp nefunguje
summary: Príznaky, na ktoré ľudia skutočne narazia — nič neprichádza do inboxu, šablóny zamrznuté na pending, kampaň, ktorá sa zastavila alebo sa nedokončí — s príčinou a tým, čo zmeniť.
date: 2026-09-10
source_date: 2026-09-10
tags: marketing, whatsapp, campaigns
category: marketing
series: WhatsApp
order: 3
---

<aside class="tldr">
Za všetkým, čo WhatsApp robí, stoja dve nastavovacie stránky — Meta app <b>organizácie</b> a telefónne číslo <b>obchodu</b> — a väčšina problémov je prázdne pole na jednej z nich. Začnite tam. Ak sa všetko zastavilo naraz a nikto nič nemenil, takmer vždy ide o vypršaný access token. Nastavenie je popísané v <a href="/docs/connecting-whatsapp-to-your-shop-sk">pripojení WhatsApp k vášmu obchodu</a>.
</aside>

## Do inboxu nič neprichádza, keď na číslo napíšem

Prichádzajúce správy sa buď k aiku vôbec nedostávajú, alebo sa dostávajú a sú odmietnuté. Prejdite tieto body v poradí — sú zoradené podľa toho, ako často sa ukážu byť príčinou.

1. **Je webhook v Meta stále overený?** WhatsApp → Configuration. Ak sa tam zobrazuje chyba, callback adresa alebo verify token sú zle.
2. **Je prihlásené pole `messages`?** Overenie webhooku a prihlásenie na jeho polia sú v Meta dva samostatné kroky a je ľahké urobiť prvý a zabudnúť na druhý.
3. **Má obchod správne Phone Number ID?** Prichádzajúca správa sa priraďuje k obchodu podľa tohto čísla. Zlé alebo prázdne znamená, že správa príde a nemá kam ísť.
4. **Má organizácia App Secret?** Bez neho je každá prichádzajúca správa odmietnutá skôr, než sa vôbec prečíta.

Ak sú všetky štyri v poriadku, požiadajte svojho vývojára o kontrolu logov. Vie od seba rozlíšiť príčiny 3 a 4, čo zvonka nie je možné.

## Šablóny zostávajú na "pending", hoci ich Meta schválila

Meta verdikt sa k aiku nedostáva, alebo sa nedá priradiť k obchodu. Skontrolujte dve veci.

Najprv, či je v Meta prihlásené **message_template_status_update**. Je to samostatná voľba oddelená od `messages` a zvyčajne práve táto chýba.

Potom, či má obchod vyplnené **WABA ID**. Verdikty šablón sa priraďujú k obchodu podľa business accountu, nie podľa telefónneho čísla, takže obchod môže prijímať správy bezchybne a pritom prichádzať o každý verdikt. Práve preto je tento príznak taký mätúci: všetko ostatné funguje.

Medzitým **Refresh** na šablóne si vyžiada aktuálny stav priamo od Meta a šablónu odblokuje.

## "WhatsApp is not configured for this shop"

Obchod nemá vyplnené **Phone Number ID**. Shop settings → Chat → vyplňte polia WhatsApp Connection.

Toto sa objaví pri pokuse o odoslanie, nie počas zostavovania, a preto môže kampaň vyzerať úplne pripravená a v poslednom kroku ju to odmietne. Je to zámerné: nastavenia obchodu nie sú súčasťou pripravenosti kampane, inak by zmena jedného nastavenia obchodu naraz zneplatnila každú kampaň v obchode.

## Kampaň zostala zaseknutá na "Sending"

Kampaň sa odosiela v dávkach po 50 a hlási Sent až keď je hotová každá dávka, takže jedna dávka sa jednoducho nedokončila.

Najprv jej dajte čas — veľká cieľová skupina si vyžiada chvíľu a reporty o doručení prichádzajú aj po tom, čo odišla posledná správa. Ak sa hodinu nič nehýbe, obráťte sa na vývojára: existujú príkazy na dotlačenie zvyšných dávok a uzavretie kampane, a zvyčajne to znamená, že sa zastavili pozadové workery aiku, nie že by bolo niečo zle so samotnou kampaňou.

## Kampaň skončila rovno v stave "Stopped"

Keď nastal jej naplánovaný čas, nedalo sa ju odoslať, takže sa skončila namiesto toho, aby tam zostala vyzerať, že sa práve chystá odísť.

Stránka kampane povie prečo. Bude to jedno z týchto: nevybraná šablóna, žiadni príjemcovia, alebo chýbajúce WhatsApp pripojenie obchodu. Opravte to a pošlite novú kampaň — zastavená sa neobnoví.

## Cieľová skupina je oveľa menšia než môj zoznam zákazníkov

Zväčša to vôbec nie je chyba.

Predvolene kampaň ide len zákazníkom, ktorí sa **prihlásili** na WhatsApp newsletter, čo je zo začiatku malý zlomok zoznamu a časom rastie. K tomu sa čísla, na ktoré WhatsApp nevie doručiť — chýbajúca predvoľba, emailová adresa napísaná do poľa pre telefón — vyradia bez toho, aby sa počítali.

Ak naozaj chcete širšiu cieľovú skupinu, pridajte pri zostavovaní skupinu Contacted alebo Customers a najprv si prečítajte upozornenie v [odosielaní WhatsApp kampane](/docs/sending-a-whatsapp-campaign-sk).

## Veľa správ zlyhalo s "Missing" niečoho

Šablóna má personalizované prázdne miesto a tým zákazníkom chýba hodnota, ktorou by sa vyplnilo, takže sa vynechali namiesto toho, aby dostali správu s dierou.

Buď doplňte chýbajúce pole na tých záznamoch zákazníkov, alebo použite šablónu, ktorej prázdne miesta vie vyplniť každý príjemca. Šablónu s prázdnym miestom nemožno odoslať za žiadnych okolností — WhatsApp ju rovno odmietne.

## Šablóna sa neobjavuje v selektore kampaní

Buď ešte nie je schválená, alebo nie je v kategórii **Marketing**. Potrebné je oboje. Kategória sa volí pri vytvorení šablóny a neskôr sa nedá zmeniť, takže Utility šablónu treba prepísať ako Marketing a znova odoslať na schválenie.

## Všetko sa zastavilo naraz a nikto nič nemenil

Deväťkrát z desiatich ide o vypršaný access token. Meta predvolene ponúkne dočasný 24-hodinový token a je ľahké ho pri nastavovaní omylom uložiť. Vygenerujte trvalý token pre system usera a vložte ho do nastavení organizácie pod **Meta-configuration → Access Key**.

Ďalší kandidáti, zoradení podľa pravdepodobnosti: bol zmazaný Meta system user, ktorému token patril, číslo bolo obmedzené Meta po nahláseniach alebo blokovaniach, alebo sa app prepla späť do development módu.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Polovica organizácie:</b> vaša organizácia → <b>Settings</b> → <b>Meta-configuration</b>.</li>
<li><b>Polovica obchodu:</b> váš obchod → <b>Settings</b> → <b>Chat</b> → polia WhatsApp Connection.</li>
<li><b>Odblokovať šablónu:</b> váš obchod → <b>Chat → WhatsApp templates</b> → otvorte ju → <b>Refresh</b>.</li>
<li><b>Zistiť, prečo sa kampaň zastavila:</b> otvorte kampaň z <b>Marketing → Whatsapp Campaigns</b>; dôvod je na jej stránke.</li>
</ul>
</aside>
