---
title: Práca s obrazovkou dielne
summary: Sprievodca pre remeselníka - obrazovka Moje úlohy, zoznam úloh, ktorý sa sám aktualizuje, START a DONE, a čo stlačiť, keď ste vyrobili menej, než sa žiadalo.
date: 2026-10-09
source_date: 2026-10-09
tags: production, floor, artisan
category: production
series: Ordering from partners
order: 9
---

<aside class="tldr">
Pre tých, ktorí veci vyrábajú. <b>Factory → Jobs</b> (Výroba → Úlohy) je celý váš deň na jednej obrazovke: úlohy pre vás vľavo, tá, na ktorej práve pracujete, v strede. Stlačte <b>START</b> (Štart), vyrobte to, zadajte počet, stlačte <b>DONE</b> (Hotovo). Ak ste vyrobili menej, než sa žiadalo, obrazovka sa opýta jednu vec: dokončiť úlohu tu, alebo preniesť zvyšok do novej úlohy. Nič iné vypĺňať netreba.
</aside>

## Obrazovka

Stránka je usporiadaná ako doručená pošta.

**Vľavo, vždy viditeľné:** zoznam. Hore dva počítadlá - kusy vyrobené dnes a úlohy dokončené dnes. Pod nimi <b>Your jobs</b> (Vaše úlohy), práca určená pre vás. Ak vám vaša pozícia dovoľuje vyberať si z otvoreného fondu, nasleduje časť <b>Open jobs</b> (Otvorené úlohy). Dole je <b>Finished today</b> (Dnes dokončené), čo ste už uzavreli.

**Vpravo:** to, čo ste vybrali. Kým nezačnete, sú tam detaily úlohy s veľkým tlačidlom <b>START</b>. Po spustení sa zobrazí pracovná karta s hodinami.

Zoznam sa nemusí obnovovať ručne. Keď vám plánovač niečo priradí, keď kolega začne jednu z otvorených úloh, keď vy niečo uzavriete, zoznam sa zmení sám, na každej obrazovke, ktorá danú dielňu zobrazuje.

## Jeden riadok v zozname

Každý riadok je jedna úloha na jednom pracovnom príkaze:

| Stĺpec | Význam |
| --- | --- |
| Code | výrobok, ktorý treba vyrobiť - SKO-01 |
| Name | jeho názov |
| Task · job order | krok (Production, Labelling...) a referencia pracovného príkazu |
| 0/25 | vyrobené doteraz / požadované |

Riadok so zelenou šípkou ▶ a časom je ten, na ktorom práve pracujete, a kedy ste ho začali. Oranžová poznámka pod riadkom znamená, že čaká na zmes, alebo že ho už má otvorený niekto iný.

## Ako spraviť úlohu

1. Klepnite na riadok. Vpravo sa objavia detaily.
2. Stlačte <b>START</b> (Štart). Spustia sa hodiny a riadok dostane zelenú značku.
3. Vyrobte to.
4. Zadajte počet, ktorý ste vyrobili, do poľa <b>Quantity made</b> (Vyrobené množstvo). Ak ste predák alebo vyššie, je tam aj pole <b>Rejected</b> (Zamietnuté) pre kusy, ktoré neprešli kontrolou.
5. Stlačte <b>DONE</b> (Hotovo).

Vyrobili ste všetko, čo sa žiadalo? Tým je to hotové. Úloha sa uzavrie, pracovný príkaz je dokončený a sklad dostane informáciu, že má niečo na uloženie - pozrite [Ukladanie hotovej výroby](/docs/putting-away-finished-production-sk).

## Keď ste vyrobili menej, než sa žiadalo

Zadajte skutočný počet a stlačte <b>DONE</b>. Riadok na zadávanie nahradí krátky panel: *19 hotovo · 6 zostáva* a tri tlačidlá.

- <b>Continue later</b> (Pokračovať neskôr) - úloha sa uzavrie na 19, a v zozname sa vám objaví nový pracovný príkaz na zvyšných 6, adresovaný vám. Vezmite ho zajtra, alebo keď príde materiál.
- <b>Job finished</b> (Úloha ukončená) - úloha sa uzavrie na 19 a tým to končí. Plánovač vidí pracovný príkaz, ktorý žiadal 25 a dostal 19.
- <b>Back</b> (Späť) - zmeniť číslo.

V oboch prípadoch sa úloha, na ktorej ste pracovali, uzavrie a preplatí podľa toho, čo ste vyrobili. Nič vám v zozname nezostáva rozrobené na polovicu.

## Keď ste vyrobili viac, než sa žiadalo

Zadajte skutočné číslo a stlačte **DONE**. Obrazovka upozorní na kusy nad cieľom a otvorí **Na nadvýrobu je potrebné schválenie vedúceho**.

- **Naskenovať kartu** - vedúci alebo supervízor ukáže kamere svoj osobný QR kód (ten istý, ktorým sa pípa do práce). Prijme sa hneď po načítaní. **Prepnúť kameru** prepína prednú a zadnú kameru.
- **Použiť PIN vedúceho** - ak kamera kód neprečíta, vedúci zadá svoj dochádzkový PIN.

Schváliť môže len ten, kto riadi túto výrobu, a nikdy nie vlastnú prácu. Po piatich nesprávnych kartách alebo PIN-och sa okno na 15 minút zablokuje.

Po schválení sa úloha zväčší na to, čo sa naozaj vyrobilo: sklad zaskladní všetko, čo si žiadna objednávka nepýtala ide na sklad, a suroviny sa odpíšu za celú dávku. Nasledujúce kroky tej istej úlohy sa zväčšia tiež. Stránka výrobnej zákazky ukazuje, kto vyrobil navyše, kto to schválil, ako a kedy.

## Jedna dávka pre viac riadkov

Niekedy jedna dávka slúži viac než jednej úlohe. Napríklad 100 bochníkov HCS-48 a 100 krájaných bochníkov SLHCS-48 v tej istej vôni sa spolu zmieša, naleje a vytvaruje ako 200 bochníkov.

**Spojenie (manažéri).** Klepnite na jeden z riadkov na obrazovke dielne. Pod jeho detailmi **Combine Production with other lines into one batch** zobrazí ostatné otvorené riadky čakajúce na ten istý krok. Zaškrtnite tie, ktoré patria do rovnakej dávky, a stlačte **Combine**. Riadok, ktorý je už spojený, dokončený alebo sa na ňom pracuje, sa neponúka.

**Práca na dávke (remeselníci).** Riadky sa teraz zobrazia ako jeden riadok s 🔗 a spolu uvedenými kódmi, *HCS-48 + SLHCS-48*, a súčtom, *0/200*. Detaily vypisujú každý riadok s jeho pracovným príkazom. Stlačte raz **START**, vyrobte dávku, zadajte celkový vyrobený počet - 200 - a stlačte raz **DONE**.

**Čo s tým robí Aiku.** Súčet sa rozdelí medzi riadky podľa toho, čo každému ešte zostávalo vyrobiť, v celých kusoch: 100 na HCS-48, 100 na SLHCS-48. Rovnako sa rozdelí aj čas. Každý riadok potom pokračuje svojimi vlastnými ďalšími krokmi - jeden zmršťovaním, druhý krájaním a potom zmršťovaním. Stránka pracovného príkazu ukazuje krok ako *One batch with* (Jedna dávka s) druhým riadkom.

**Mzda a ciele** sa počítajú za dávku ako celok: 200 bochníkov za hodiny, ktoré to trvalo, voči cieľu kroku. Spojenie preplatí presne toľko, koľko by preplatili samostatné dávky rovnakým tempom. Keď majú riadky pre daný krok rozdielne ciele, cieľom dávky je ten, ktorý zaberie rovnaký počet hodín ako samostatné dávky.

Vyrobiť viac, než všetky riadky žiadali, vyžaduje kartu alebo PIN vedúceho, ako je uvedené vyššie; prebytok sa tiež rozdelí medzi riadky. Manažér môže riadky znova **Separate** (Oddeliť), kedykoľvek na nich nikto nepracuje. Čo sa už spolu vyrobilo, zostáva pri každom riadku.

## Čo je dobré vedieť

- **Jedna úloha naraz.** Kým máte otvorenú úlohu, tlačidlá START sú vypnuté. Najprv ju uzavrite.
- **Vaša mzda za úlohu** sa riadi číslom v poli Quantity made, ako je vysvetlené v [Pozície vo výrobe](/docs/factory-positions-sk). Zamietnuté kusy sa nepreplácajú.
- **Open jobs** (Otvorené úlohy) sú úlohy bez priradeného remeselníka, alebo priradené niekomu inému. Vziať si takú úlohu je v poriadku, ak to vaša pozícia dovoľuje; potom je vaša, kým ju neuzavriete.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Vaša obrazovka:</b> vaša organizácia → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Začať:</b> klepnite na riadok → <b>START</b>.</li>
<li><b>Dokončiť:</b> zadajte <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Menej, než sa žiadalo:</b> <b>DONE</b> → <b>Continue later</b> alebo <b>Job finished</b>.</li>
<li><b>Jedna dávka pre viac riadkov:</b> klepnite na riadok → <b>Combine … with other lines into one batch</b> → zaškrtnite riadky → <b>Combine</b>. Vrátenie späť: klepnite na spojený riadok → <b>Separate</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Pozície sa nastavujú v karte zamestnanca v Human Resources a nesú so sebou oprávnenia.</li>
<li>Vidieť vlastné úlohy, START a DONE: pozícia <b>Operative</b> pre danú dielňu.</li>
<li>Vidieť a brať si <b>Open jobs</b>, zaznamenávať zamietnuté kusy: <b>Foreman</b>, <b>Mix preparer</b> alebo <b>Floor supervisor</b>.</li>
<li>Spájanie a oddeľovanie riadkov: manažéri, ktorí riadia dielňu, tí istí ľudia, ktorí môžu schváliť nadvýrobu.</li>
</ul>
</aside>
