---
title: Aká rýchla je moja stránka?
summary: Panel PageSpeed Insights na karte Performance webovej stránky hodnotí živú stránku na 100 bodov pre počítač a mobil, zobrazuje, čo skutočne zažili návštevníci za posledný mesiac, a uchováva históriu, aby bolo vidno, či nejaká zmena stránku zrýchlila.
date: 2026-09-16
source_date: 2026-09-16
tags: website, performance, seo, shop
category: marketing
---

<aside class="tldr">
Otvorte webovú stránku v aiku, kliknite na <b>Performance</b> (výkon) a prejdite na <b>PageSpeed Insights</b>. Štyri ciferníky hodnotia živú stránku na 100 bodov pre <b>Performance</b> (výkon), <b>Accessibility</b> (prístupnosť), <b>Best practices</b> (osvedčené postupy) a <b>SEO</b>, samostatne pre počítač a mobil. Zelená je 90–100, oranžová 50–89, červená pod 50. Nižšie <b>Core Web Vitals</b> je to, čo skutočne zažili návštevníci za posledných 28 dní, <b>Lab metrics</b> (laboratórne metriky) je, z čoho hodnotenie vzniklo, a <b>Score history</b> (história hodnotení) vykresľuje hodnotenia za obdobie zvolené v hornej časti karty. Meranie je Googlu, nie naše, a vždy meria publikovanú stránku, nikdy váš neuložený návrh.
</aside>

## Čo sa meria

Google meria stránku na svojich vlastných serveroch, čo znamená, že sťahuje **živú, publikovanú adresu** stránky. Z toho vyplývajú dve veci:

- Stránka, ktorá nie je publikovaná alebo ešte nemá verejnú adresu, sa nedá zmerať. Panel to tak aj napíše, namiesto zobrazenia hodnotení.
- To, na čo sa pozeráte, je stránka tak, ako ju dostane návštevník dnes, nie návrh, ktorý upravujete v dielni. Najprv publikujte, potom merajte.

Každá stránka sa meria dvakrát, raz ako telefón a raz ako počítač, a tieto dva výsledky sa nemiešajú. Na prepínanie medzi nimi použite tlačidlá <b>Desktop</b> / <b>Mobile</b> v hornej časti panelu. Mobilné hodnotenia sú takmer vždy nižšie ako počítačové: Google simuluje telefón strednej triedy na pomalom pripojení, a to je ten tvrdší, ale pravdivejší test, pretože väčšina nakupujúcich prichádza z telefónu.

## Štyri hodnotenia

Každý ciferník je znamienko na 100 bodov:

- **Performance** (výkon) — ako rýchlo sa stránka načíta a stane použiteľnou.
- **Accessibility** (prístupnosť) — ako dobre stránka funguje pre niekoho, kto používa čítačku obrazovky alebo inú asistenčnú technológiu.
- **Best practices** (osvedčené postupy) — bezpečnostné a moderné webové kontroly, ako HTTPS a chyby v prehliadači.
- **SEO** — základné kontroly, ktoré umožňujú vyhľadávačom stránku nájsť, prehľadať a pochopiť.

Farby sú vlastné Googlu pásy: **0–49 slabé**, **50–89 potrebuje zlepšenie**, **90–100 dobré**. Prejdením kurzorom alebo zameraním ciferníka sa zobrazí karta, ktorá to hodnotenie vysvetlí v jednej vete, ukáže počítačové a mobilné číslo vedľa seba, aby bolo vidno, ktoré zariadenie sťahuje výsledok dole, a znova zopakuje pásy.

Berte číslo ako smer, nie ako cieľ. Rovnaká stránka meraná dvakrát v priebehu hodiny sa môže posunúť o niekoľko bodov, pretože simulovaná sieť Googlu nie je pri každom behu úplne identická. Pokles z 88 na 84 je šum; pokles z 88 na 40 je niečo, čo ste zmenili.

## Core Web Vitals: vaši skutoční návštevníci

Blok **Core Web Vitals** sa zobrazí iba vtedy, keď má Google dosť skutočnej návštevnosti na túto stránku. Nejde o simuláciu: je to to, čo zažili ľudia, ktorí stránku skutočne navštívili za **posledných 28 dní**, získané z Chrome.

Zobrazuje sa päť meraní, každé s pruhom, ktorý rozdeľuje vaše návštevy na dobré, potrebujúce zlepšenie a slabé:

- **Largest Contentful Paint** — ako dlho trvá, kým sa na obrazovke objaví hlavná vec na stránke, obvykle veľký obrázok alebo nadpis.
- **Interaction to Next Paint** — ako dlho trvá stránke odpovedať na dotyk alebo kliknutie.
- **Cumulative Layout Shift** — koľko sa stránka pri načítaní hýbe. Toto je to, čo nakupujúci popisujú ako "posunulo sa to a stlačil som nesprávne tlačidlo".
- **First Contentful Paint** — ako dlho trvá, kým sa objaví čokoľvek.
- **Time to First Byte** — ako dlho trval nášmu serveru začiatok odpovede.

Pruhy sú dôležitejšie než hlavné číslo. Stránka môže mať prijateľný priemer, hoci pätina návštev je slabá, a táto pätina je zvyčajne jedna krajina, jeden telefón alebo jeden pomalý obrázok.

Málo navštevovaná stránka — nová rodina produktov, zriedka navštevovaná obsahová stránka — tento blok nemusí mať vôbec. To nie je chyba; Google jednoducho nebude hodnotiť pár návštev.

## Lab metrics: z čoho hodnotenie vzniklo

**Lab metrics** (laboratórne metriky) sú časovania z jediného simulovaného behu Googlu, toho, ktorý vytvoril ciferník Performance: First Contentful Paint, Largest Contentful Paint, Total Blocking Time, Cumulative Layout Shift a Speed Index. Sú to podklady za hodnotením.

Použite ich na určenie, *aký druh* pomalosti to je. Slabý Largest Contentful Paint väčšinou znamená ťažký hlavný obrázok. Slabý Total Blocking Time znamená skripty. Slabý Cumulative Layout Shift väčšinou znamená obrázok alebo banner bez vyhradeného miesta. Ak laboratórne čísla vyzerajú dobre a pruhy skutočných návštevníkov vyzerajú slabo, problém nie je v samotnej stránke, ale v tom, kto ju navštevuje a odkiaľ.

## Score history

V spodnej časti <b>Score history</b> (história hodnotení) vykresľuje jedno hodnotenie naraz za obdobie nastavené dátumami <b>From</b> a <b>To</b> v hornej časti karty Performance. Vyberte, ktoré hodnotenie, v rozbaľovacom zozname. Plná čiara je počítač, čiarkovaná je mobil.

Body sú denné, keď je rozsah do približne troch mesiacov, a týždenný priemer nad tým, aby dlhší pohľad späť zostal čitateľný. Bod existuje iba pre deň, kedy bola stránka skutočne zmeraná, takže očakávajte medzery, nie neprerušenú čiaru.

Toto je časť, na ktorú sa treba pozrieť po redizajne, po pridaní videa alebo po zámene obrázkov na stránke oddelenia. Nastavte obdobie okolo zmeny a pozrite, či sa čiara posunula.

## Meranie a opätovné meranie

Vedľa názvu panelu je čas, kedy bol aktuálny výsledok zmeraný. Výsledok sa uchováva jeden deň: prvýkrát, keď niekto otvorí kartu, sa stránka zmeria, a nasledujúcich 25 hodín každý vidí to isté meranie, namiesto toho, aby spustil nové. Toto je zámerné — Google obmedzuje, ako často sa môžeme pýtať.

Keď chcete čerstvé číslo hneď, stlačte <b>Re-measure</b> (znova zmerať). Ciferníky sivejú, panel oznámi, že meria, a výsledky obvykle prídu do minúty; panel sa sám obnoví, takže nie je potrebné ho znova načítať. Ak je Google pomalý, panel kontroluje asi tri minúty a potom vás vyzve stlačiť <b>Re-measure</b> znova.

Každé dokončené meranie, či pochádza z toho, že ste otvorili kartu, alebo z dávkového behu, sa zapíše do histórie hodnotení. Takže čím častejšie sa stránka pozerá, tým hustejšia je jej história.

## Keď nie sú žiadne hodnotenia

- **"This webpage has no publicly reachable URL to analyse"** (táto stránka nemá verejne dostupnú adresu URL na analýzu) — stránka nie je živá, alebo webová stránka ešte nemá verejnú doménu. Publikujte stránku, alebo poproste toho, kto webovú stránku nastavoval, aby dokončil nasmerovanie domény.
- **"Google could not measure this page"** (Google nemohol zmerať túto stránku) so správou pod tým — Google sa na stránku dostal a odmietol ju alebo pri nej zlyhal. Bežnými príčinami sú stránka, ktorá vracia chybu návštevníkovi, ktorý nie je prihlásený, presmerovacia slučka, alebo stránka, ktorá je príliš pomalá na to, aby sa vôbec dokončila načítavať. Najprv otvorte stránku v súkromnom okne prehliadača; ak tam nefunguje, nefunguje ani pre Google.
- **Chýba blok Core Web Vitals** — nedostatok skutočných návštevníkov za posledných 28 dní. Všetko ostatné na paneli platí ďalej.

## Čo robiť so slabým hodnotením

Väčšinu toho, čo tieto čísla hýbe, nie je v texte stránky:

- **Obrázky** sú obvyklým vinníkom. Obrovská fotka produktu zmenšená v prehliadači stojí návštevníka celý pôvodný súbor.
- **Čokoľvek vložené** — video, mapa, chat alebo widget na recenzie — je cudzí kód bežiaci na vašej stránke, a počíta sa proti vám.
- **Hodnotenia Accessibility a SEO** často strácajú body na veciach, ktoré sa rýchlo opravia z dielne: chýbajúce popisy obrázkov, chýbajúci titulok alebo popis stránky, nadpisy použité pre svoju veľkosť a nie pre svoj význam, alebo text príliš bledý voči svojmu pozadiu.

Problémy s výkonom, ktoré sú rovnaké na každej stránke webu, sú naše, nie vaše; nahláste ticket s adresou stránky a snímkou obrazovky panelu. Problémy na jednej stránke sú zvyčajne obsahom tej stránky.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Otvoriť panel:</b> vaša organizácia → váš shop → <b>Website → Webpages</b> → stránka → <b>Performance</b>, potom prejdite na <b>PageSpeed Insights</b>.</li>
<li><b>Telefón alebo počítač:</b> tlačidlá <b>Desktop</b> / <b>Mobile</b> v hlavičke panelu.</li>
<li><b>Vysvetliť hodnotenie:</b> prejdite kurzorom nad jeden zo štyroch ciferníkov.</li>
<li><b>Čerstvé meranie:</b> tlačidlo <b>Re-measure</b> vpravo v hlavičke panelu.</li>
<li><b>Zmeniť obdobie histórie:</b> dátumy <b>From</b> a <b>To</b> v hornej časti karty Performance.</li>
<li><b>Vybrať čiaru histórie:</b> rozbaľovací zoznam vedľa <b>Score history</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Dobré vedieť</strong>
<ul>
<li><b>Hodnotenia sú Googlu.</b> aiku sa pýta PageSpeed Insights a zobrazuje odpoveď; známky nepočítame my.</li>
<li><b>Meria sa iba živá stránka</b>, nikdy návrh, a nikdy stránka za prihlásením.</li>
<li><b>Jedno meranie za deň na stránku</b>, na zariadenie, pokiaľ nestlačíte <b>Re-measure</b>.</li>
<li><b>Návštevnosť a tržby pre tú istú stránku</b> sú v grafe nad týmto panelom — pozri <a href="/docs/did-my-webpage-change-work-sk">Did my webpage change work?</a></li>
</ul>
</aside>
</content>
