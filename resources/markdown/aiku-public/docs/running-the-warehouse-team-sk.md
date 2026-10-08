---
title: Riadenie skladového tímu
summary: Stránka Tím v sklade — dashboard pre toho, kto riadi prevádzku (kto je práve na pracovisku, čakajúci backlog, dnešok oproti bežnému dňu, posledných 30 dní a výkon každého človeka na odpracovanú hodinu, nedokončené vychystávania a meškania) s kartou Hodiny, kde vidíte zápisy dochádzky dňa podľa ľudí, uzavriete zabudnuté odchody a pridáte alebo opravíte zápis bez zásahu HR.
date: 2026-10-07
source_date: 2026-10-07
tags: warehouse, clocking, picking, packing, performance
category: dispatch
---

<aside class="tldr">
Otvorte <b>váš sklad → Tím</b>. Karta <b>Dashboard</b> ukazuje prevádzku práve teraz (on site, clocked out, not in yet, did not clock in, on leave, day off), backlog čakajúci na vyskladnenie a balenie, súčty tímu za dnes, včera, 7 alebo 30 dní oproti predchádzajúcemu obdobiu, grafy dneška po hodinách oproti bežnému dňu a posledných 30 dní a tabuľku People zoradenú podľa položiek na odpracovanú hodinu s nedokončenými vychystávaniami a meškanými príchodmi. Karta <b>Hodiny</b> ukazuje zápisy dochádzky jedného dňa podľa ľudí, označuje príchody, ktoré nikto neuzavrel, a tu pridáte, zmeníte alebo zmažete zápis. Túto stránku vidia len skladoví supervízori a ľudia, ktorí môžu upravovať HR.
</aside>

Otvorte ju vo **vašom sklade → Tím**, posledná položka sekcie skladu v bočnom paneli.

## Kto ju vidí

Stránka je pre ľudí, ktorí riadia prevádzku skladu. **Tím** uvidíte, ak ste skladový supervízor, supervízor dispatchingu alebo supervízor príjmu tovaru pre daný sklad. Uvidíte ho aj vtedy, ak môžete v organizácii upravovať HR. Vychystávači a baliči ju nevidia.

## Kto patrí do tímu

Každý, kto ešte pracuje alebo odchádza a má pracovnú pozíciu v skladovom oddelení daného skladu: vychystávači, baliči, vychystávači výnimiek, kontrolóri zásob a supervízori. Pozícia, ktorá nie je viazaná na žiadny sklad, platí pre každý sklad. Kto pracuje len v inom sklade, v zozname nie je. Ak chcete niekoho pridať alebo odobrať, zmeňte jeho pracovné pozície v HR.

Vyskladňovanie a balenie sa k človeku priraďuje cez jeho používateľský účet. Kto nemá v aiku používateľský účet, ukazuje hodiny, ale žiadne vyskladňovanie ani balenie a tabuľka People pod jeho menom uvádza "no user".

## Karta Dashboard

Časy sa riadia časovou zónou organizácie.

### Floor now

Šesť počítadiel, jedno na stav, a pod nimi zoznam ľudí. Stlačením počítadla zobrazíte len ľudí v danom stave; opätovným stlačením uvidíte všetkých.

- **On site** — v tejto chvíli zapísaní na príchode, s časom príchodu a koľko času uplynulo.
- **Clocked out** — dnes boli v práci a už odišli, s časom odchodu a odpracovanými hodinami.
- **Not in yet** — majú dnes podľa pracovného rozvrhu (vlastného, alebo rozvrhu organizácie, ak žiadny nemajú) byť v práci a začiatok bol pred menej než 30 minútami.
- **Did not clock in** — majú dnes byť v práci, od začiatku uplynulo viac než 30 minút a nemajú žiadny zápis.
- **On leave** — dnešok pokrýva schválená neprítomnosť.
- **Day off** — dnes nie je v ich rozvrhu pracovný deň.

Znamienko **+** na konci riadka pridá človeku zápis dochádzky.

Žltý pruh nad počítadlami uvádza **príchody z predchádzajúcich dní, ktoré nikto neuzavrel** (posledných 14 dní). Kto zabudol zapísať odchod, ostáva v ten deň navždy "on site" a jeho hodiny za ten deň sú nesprávne. Stlačte meno a doplňte chýbajúci odchod; okno sa otvorí na danom dni.

### Backlog the team faces

Dodacie listy v tomto sklade, ktoré práve teraz čakajú na tím, s počtom položiek: **To pick** (nepriradené, vo fronte alebo sa vychystávajú), **Blocked**, **To pack** (vychystané alebo sa balia) a **Packed, waiting dispatch**. **Open goods out** vás zavedie na celý backlog.

### Throughput

Vyberte **Dnes**, **včera**, **7 days** alebo **30 days**. Karty aj tabuľka People sa riadia výberom; stav prevádzky a backlog sa vždy týkajú práve tejto chvíle. Každá karta ukazuje hodnotu za obdobie, rovnakú hodnotu za predchádzajúce obdobie (včera, deň predtým, predchádzajúcich 7 alebo 30 dní) a zmenu v percentách, zelenú, keď sa pohla správnym smerom, a červenú, keď nie.

- **Delivery notes picked** a **Delivery notes packed** — dodacie listy dokončené v tomto sklade ľuďmi z tímu.
- **Items handled** — vyskladnené plus zabalené položky.
- **Hours worked** — čas tímu na dochádzke podľa timesheetov, bez prestávok. Keď sa postavíte myšou nad číslo, uvidíte, koľko ľudí sa zapísalo.
- **Items per worked hour** — spracované položky vydelené odpracovanými hodinami. Kým tím neodpracuje desať minút, zostáva prázdne.
- **Short picks** — riadky vychystávania, ktoré vychystávač nemohol splniť, s podielom na všetkých riadkoch vychystávania. Nižšie je lepšie.
- **Late clock-ins** — zápisy dochádzky označené ako neskoré voči pracovnému rozvrhu. Nižšie je lepšie.

Keď sa dnes ešte nič nevychystalo ani nezabalilo, poznámka to povie a uvedie, kedy sa tak naposledy stalo.

### Grafy

- **Today by hour** — dodacie listy dokončené tímom každú hodinu dnes (stĺpce) oproti priemeru toho istého dňa v týždni za posledné štyri týždne (prerušované čiary). Na prvý pohľad vidíte, či je deň pred bežným dňom, alebo za ním.
- **Last 30 days** — dodacie listy vychystané a zabalené za deň.
- **Items per worked hour** — produktivita tímu za deň za posledných 30 dní, s odpracovanými hodinami každého dňa pod ňou, takže slabý deň s málo hodinami sa číta inak než slabý deň s plným tímom.

### People

Jeden riadok na človeka, zoradené podľa položiek na odpracovanú hodinu; stlačením hlavičky stĺpca zoradíte inak. Ľudia, ktorí nemajú v období nič zaznamenané, sú skrytí; prepínač v nadpise ich zobrazí.

- **V** a **Von** (jeden deň) sú prvý príchod a posledný odchod. "…" znamená, že je stále na pracovisku. Pri viacerých dňoch tabuľka namiesto toho ukazuje odpracované **Dni**.
- **Worked** je jeho čas na dochádzke, bez prestávok.
- **Vybrané** a **Zabalené** sú položky. Keď sa nad číslo postavíte myšou, uvidíte, koľko dodacích listov pokrýva.
- **Items/h** sú vyskladnené plus zabalené položky na odpracovanú hodinu, s pruhom vzhľadom na najlepšieho v tíme. Balič, ktorý aj vychystáva, sa meria za oboje.
- **Short picks** — riadky, ktoré nemohol vychystať. Myšou zobrazíte podiel z jeho riadkov vychystávania. Zelená 0 znamená, že vychystával bez nedokončenia.
- **Late** — koľko jeho zápisov bolo neskorých.

## Karta Hodiny

Jeden deň naraz. Pohybujte sa šípkami, výberom dátumu alebo tlačidlom **Dnes**. Riadok hore udáva, koľko ľudí sa v daný deň zapísalo, odpracované hodiny, koľko zápisov bolo neskorých a koľko pridaných ručne. Uvedení sú len ľudia so zápismi; prepínač zobrazí všetkých.

Každý človek má riadok s odpracovaným časom a časom prestávok a potom jeho zápisy v poradí: zelená šípka pre príchod, sivá pre odchod. Žltý zápis je neskorý. Keď sa postavíte myšou nad čas, uvidíte, odkiaľ pochádza (dochádzkový terminál, alebo kto ho pridal ručne) a jeho poznámku. Červené "No clock-out" pod menom znamená, že jeho posledný príchod v ten deň nikto neuzavrel.

Žltý rámček **Missing clock-outs from earlier days** uvádza každý príchod za posledných 14 dní, ktorý nikto neuzavrel. Stlačením jedného doplníte odchod v ten deň.

### Pridanie, zmena alebo zmazanie zápisu

- **Pridať** — stlačte **+** na konci zápisov človeka, **+** na riadku Dashboardu, alebo **Add clocking** hore a vyberte človeka. Skontrolujte dátum a čas (predvolene je to teraz, alebo deň, na ktorý sa pozeráte, a nemôže byť v budúcnosti), pridajte poznámku s dôvodom, napr. "zabudol zapísať príchod", a stlačte **Add clocking**. Každý zápis prepne človeka medzi príchodom a odchodom; okno vám povie, ktorý z nich to bude.
- **Zmeniť** — stlačte ceruzku pri zápise a nastavte nový čas. Zápis zostane v svojom dni; mení sa len čas a pracovný čas, ktorý otvoril alebo uzavrel, sa prepočíta.
- **Odstrániť** — stlačte kôš pri zápise a potvrďte. Pracovný čas okolo neho sa prepočíta. Nedá sa vrátiť späť.

Zápis pridaný alebo zmenený tu je rovnaký ako zápis urobený v HR: zapíše sa do timesheetu daného dňa a zaznamená sa ako vykonaný vami, s vaším menom, keď sa nad ním niekto postaví myšou.
