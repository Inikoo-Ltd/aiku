---
title: Typy dochádzkových zariadení
summary: Na čo slúži každý typ dochádzkového zariadenia, ako sa cez neho zamestnanci reálne pípajú príchod a odchod, a ako vybrať to správne pre vaše pracovisko.
date: 2026-08-31
source_date: 2026-08-31
tags: hr, clocking
category: hr
series: Clocking in and out
order: 1
---

<aside class="tldr">
<em>Dochádzkové zariadenie</em> je čokoľvek, čoho sa zamestnanci dotknú, aby povedali "som tu" a "odchádzam" — tablet pri dverách, vytlačený QR kód na stene, vyhradené zariadenie. Každé zariadenie žije v jednom <b>pracovisku</b> a má <b>typ</b>, ktorý určuje, ako sa cez neho pípa. Štyri bežné typy — <b>PIN</b>, <b>Barcode Scanner</b>, <b>Camera QR</b> a <b>QR Code</b> — nepotrebujú nič viac než tablet alebo vlastné telefóny zamestnancov. Každý zaznamenaný príchod automaticky pristane vo výkaze pracovného času daného zamestnanca.
</aside>

## Prečo vôbec zariadenia?

aiku nechce vedieť len *že* niekto pípol príchod — chce vedieť *kde* a *ako*. Naviazanie každého pípnutia na konkrétne zariadenie na konkrétnom pracovisku znamená, že výkazy vedia odlíšiť dvere skladu od dverí kancelárie, a zariadenie môžete vyradiť bez toho, aby ste stratili históriu, ktorú zaznamenalo. Preto je prvým krokom vždy "vytvoriť zariadenie v aiku", aj keď je tým "zariadením" len laminovaný QR kód.

## Bežné typy

Toto sú štyri typy, ktoré si viete sami vytvoriť zo zoznamu dochádzkových zariadení, a žiadny z nich nepotrebuje špeciálny hardvér.

### PIN

Zdieľaný tablet pri vstupe zobrazuje klávesnicu. Každý zamestnanec má svoj krátky osobný PIN; zadá ho a je zapípaný na príchod — zadá ho znova neskôr a je zapípaný na odchod. Zamestnanci si svoj PIN vidia na stránke **Employee Clocking** v aiku.

Hodí sa, keď: chcete čo najjednoduchšie zdieľané zariadenie a zamestnancom sa dá dôverovať, že si PIN nebudú zdieľať.

### Barcode Scanner

Rovnaký zdieľaný tablet, ale namiesto písania zamestnanec naskenuje svoj osobný čiarový kód — zobrazený na jeho vlastnej stránke **Employee Clocking**, takže môže žiť na obrazovke telefónu alebo byť vytlačený na preukaze. Prácu odvedie lacná USB alebo Bluetooth čítačka čiarových kódov pripojená k tabletu.

Hodí sa, keď: zamestnanci už nosia preukazy, alebo chcete, aby pípnutie trvalo jedno pípnutie namiesto štyroch ťuknutí.

### Camera QR Scanner

Opäť zdieľaný tablet, no tentokrát prácu odvedie vlastná **kamera** tabletu: zamestnanec podrží QR kód zo svojej stránky Employee Clocking na telefóne, kamera ho prečíta a je hotovo. Netreba žiadny ďalší skener.

Hodí sa, keď: chcete rýchle skenovacie pípanie a nemáte nič okrem tabletu.

### QR Code

Tento typ obráti logiku: namiesto toho, aby zdieľané zariadenie čítalo kód zamestnanca, **vlastný telefón zamestnanca** číta kód na stene. Pre zariadenie vytlačíte jeden alebo viac QR kódov a nalepíte ich pri dverách; zamestnanec naskenuje jeden telefónom, čo otvorí stránku Employee Clocking v aiku, a — prihlásený ako on sám — zapíše si príchod alebo odchod.

QR Code zariadenie má dva triky, ktoré ostatné nemajú:

- **Overovanie polohy.** Môžete vyžadovať, aby telefón zdieľal polohu, a prijímať pípnutia len v okruhu (v metroch) od bodu, ktorý vyberiete na mape — aby si nikto nezapípal "v kancelárii" z gauča.
- **Politiky pípania.** Každé zariadenie môže niesť pravidlá, ktoré označia pípnutie zamestnanca ako **onsite**, **remote** alebo **hybrid**, takže práca z domu môže byť povolená pre niektorých ľudí bez toho, aby ste vypli kontrolu polohy pre všetkých.

Hodí sa, keď: nemáte voľný tablet, zamestnanci majú telefóny, alebo ľudia pracujú cez viacero dverí a lokalít.

## Ako vybrať

- Jedny dvere, jeden voľný tablet → na začiatok **PIN**; keď omrzí písanie, prejdite na **Camera QR**.
- Zamestnanci nosia preukazy alebo vlastníte skener → **Barcode Scanner**.
- Žiadne voľné zariadenie → **QR Code** na stene a nech prácu urobia telefóny.
- Potrebujete si byť istí, že sú ľudia fyzicky na mieste → **QR Code** so zapnutým overovaním polohy.

Nech si vyberiete čokoľvek, nič nie je konečné: typ zariadenia určuje, ako ľudia pípajú, no všetky pípnutia skončia na tom istom mieste — vo výkaze zamestnanca — a k tomu istému pracovisku môžete kedykoľvek pridať druhé zariadenie iného typu.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Zobraziť vaše zariadenia:</b> vaša organizácia → <b>Human Resources → Clocking machines</b>. Každý riadok ukazuje typ, pracovisko a či je jeho kiosk zapnutý.</li>
<li><b>Zariadenia jedného pracoviska:</b> <b>Human Resources → Working place</b> → otvorte pracovisko → <b>Clocking machines</b>.</li>
<li><b>Kde zamestnanci nájdu svoj PIN, čiarový kód alebo QR:</b> stránka <b>Employee Clocking</b> — každý zamestnanec vidí len tie metódy, ktoré vaše zariadenia povoľujú.</li>
</ul>
<strong>Permissions you need</strong>
<ul>
<li>Zobrazenie zariadení vyžaduje prístup Human Resources <b>view</b>; vytváranie alebo úprava vyžaduje prístup Human Resources <b>edit</b>.</li>
</ul>
</aside>
