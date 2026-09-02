---
title: Ako čítať nákupný panel partnera
summary: Obrazovka, ktorá ukazuje, čo nakúpiť od partnera a koľko priestoru na to máte - tri karty limitov, koláčový graf krytia skladu s ôsmimi košmi a objednávkový pipeline.
date: 2026-09-02
source_date: 2026-09-02
tags: procurement, intercompany, shopping-list, stock
category: procurement
series: Ordering from partners
order: 2
---

<aside class="tldr">
Tento panel je začiatkom každej nákupnej relácie. Horný riadok hovorí, koľko priestoru máte - peniaze a skladové miesto. Stred hovorí, ktoré z partnerových produktov potrebujú objednať, najhoršie ako prvé. Spodok hovorí, čo je už na ceste. Nemusíte si nič pamätať; obrazovka vám povie, čo potrebuje pozornosť. Samotné zadanie objednávky je popísané v <a href="/docs/buying-from-a-partner-sk">Buying from a partner</a>.
</aside>

Otvorte ho na **Procurement → Partners → {partner} → Shopping**. Použite ho namiesto toho, aby ste otvárali nákupný zoznam a snažili sa spomenúť si, čo chýbalo.

## Tri karty hore: koľko priestoru máte

Tieto karty sú limity, nie ozdoby. Existujú preto, lebo nákupný zoznam, do ktorého môže hocikto hodiť hocičo, prestáva niečo znamenať - partner, ktorý dostane tisíc riadkov, nedokáže rozpoznať, ktoré dva sú núdzové.

- **Order budget used.** Hodnota vášho otvoreného zoznamu voči tomu, čo vám tento partner skutočne dodá v jednom objednávkovom cykle, v mene vašej vlastnej organizácie - každé peňažné číslo na týchto obrazovkách je za vás prepočítané, takže nikdy nemusíte rozmýšľať v mene partnera. Ak je dosť histórie dodávok, rozpočet sa meria zo skutočných dodávok; ak nie, je to jeden objednávkový cyklus toho, čo z ich produktov naozaj predávate. Toto číslo nezadáva nikto - ani vy, ani váš nadriadený. Keď je pruh plný, karta ukazuje **at capacity**.
- **Warehouse space.** Koľko miest je voľných z celkového počtu, s pruhom rozdeleným na to, čo je *v použití*, čo je *na ceste* v otvorených objednávkach a dodávkach, a čo by si nárokoval *tento nákupný zoznam*. Pod tým spravodlivý podiel tohto partnera: koľko z voľných miest smú zabrať jeho úplne nové produkty. Miesta sa počítajú ako sloty - nemáme údaje o objeme, takže nepredstierame, že meriame kubické metre.
- **Lead time.** Karta s menom partnera ukazuje jeho nameraný čas dodania **order → booked in**, z koľkých dodávok bol nameraný (alebo poznámku, že je to zatiaľ len odhad), na koľkých objednávkach mešká a o koľko, a veľkosť jeho katalógu.

## Krytie skladu: koláčový graf a osem košov

Táto časť pokrýva celý partnerov katalóg, rozdelený do ôsmich košov podľa toho, ako dlho vydrží váš vlastný sklad. Rizikové koše sú dimenzované podľa nameraného času dodania, nie podľa kalendárnych týždňov - v tom je celá podstata.

Začína **koláčovým grafom**: každý produkt v katalógu, jeden výsek na kôš, s celkovým počtom v strede. Prejdite myšou po výseku pre počet a percento; kliknutím na výsek - alebo na riadok v legende vedľa neho - prezriete daný kôš v partnerovom katalógu. Jeden pohľad povie, či je dnes pokojné dopĺňanie, alebo požiar: veľa červenej znamená problém, prevažne zelená znamená, že je všetko v poriadku.

Pod grafom sú koše rozdelené do dvoch skupín. **Needs ordering** (potrebuje objednať) obsahuje päť košov, ktoré si žiadajú vašu pozornosť:

- **Out of stock** - na regáli nič nie je.
- **Doomed** - stále máte sklad, ale minie sa skôr, než by mohla doraziť dodávka, aj keby ste objednali práve teraz.
- **Critical / Danger / Watch** - dôjde do dvoch, troch alebo štyroch časov dodania.

**Not for ordering** (neobjednávať) obsahuje ostatné tri:

- **Covered** - v poriadku pre teraz.
- **Dead stock** - nič sa nepredáva, peniaze ležia na regáli; riadok ukazuje, akú má hodnotu.
- **We never stocked** - partner to predáva, ale vy ste to nikdy nemali na sklade.

Jeden typ položky sa tu nikdy neobjaví: SKO, ktoré máte vo vlastnom inventári označené ako **On Demand**. Ich stav skladu sa nesleduje, takže "out of stock" by nič neznamenalo - panel, tabuľky košov aj Auto-fill ich vynechávajú.

Každá dlaždica odpovedá na jednu otázku: **koľko ich ešte odo mňa potrebuje?** Počet "*N* need action" ignoruje všetko, čo je už na zozname alebo už na ceste, takže sa zmenšuje, ako pracujete. Pod ním je ten istý počet rozdelený podľa **rank** - najprv produkty A, s D a Z vyblednutými na konci. Dva produkty A bez skladu sú dôležitejšie ako päťsto produktov D, takže v tomto poradí sa pracuje.

Tri veci môžete urobiť z jednej dlaždice:

- **Click the number** (kliknite na číslo), aby ste otvorili kôš ako tabuľku: každá položka, zoradená podľa rank, s ich skladom, vaším skladom, kedy vám dôjde, a políčkom na množstvo, ktoré zapisuje priamo do nákupného zoznamu.
- **Click the bucket's name or a rank letter** (kliknite na názov koša alebo písmeno rank), aby ste prezerali tie produkty v partnerovom katalógu.
- **Fill**, aby ste otvorili Auto-fill už zúžené na daný kôš a už vygenerované - vy len upravíte a potvrdíte. Trochu viac práce ako čarovné tlačidlo, ale oveľa viac kontroly. Počty na dlaždici - *N on the way · N on list* - ukazujú, koľko z koša ste už vyriešili.

Na **Covered** a **Dead stock** sa namiesto toho zobrazí červené upozornenie, keď je niečo z daného koša na vašom nákupnom zozname: to je sklad, ktorý nepotrebujete. **remove** vymaže tieto riadky jedným kliknutím.

## Objednávkový pipeline

Spodný pás sleduje všetko od potreby po regál: **on shopping list → being prepared → ready to ship → in transit → arrived, booking in**. Každý stĺpec zobrazuje dodávky a počet položiek v nich; každá karta otvorí dodávku len na čítanie - sklad predávajúceho ju vlastní, kým tovar k vám nedorazí.

Karty viditeľne starnú. Po trojnásobku času dodania zožltnú (amber); po desaťnásobku sčervenajú. Stará karta je otázka pre partnera, nie číslo na obdiv. Všetko, čo naozaj mešká, sa navyše objaví v zozname **Late from this partner**, s najväčším omeškaním ako prvým, s vyznačeným "no delivery date given".

## Prečo obrazovka niekedy povie nie

Pridanie do zoznamu môže byť odmietnuté. Je to zámerné a existujú len tri dôvody:

- **At the budget cap** (na strope rozpočtu) - najprv niečo odstráňte alebo znížte prioritu. Skutočná núdzová situácia sa vždy zmestí: **položky s rank A a bez skladu sú z limitu vyňaté**, takže núdza nikdy nečaká za limitom.
- **The warehouse floor** (dno skladu) - pod 5 % voľných miest sa nepridá žiadny *nový* produkt od nikoho. Položky, ktoré už máte na sklade, dopĺňajú vlastné miesta a prechádzajú bez obmedzenia.
- **This partner's fair share** (spravodlivý podiel tohto partnera) - jeden partner môže zabrať zhruba pätinu voľných miest produktmi, ktoré ste nikdy nemali na sklade. Aj ostatní dodávatelia potrebujú priestor.

Rovnaké pravidlo platí všade, kde pridávate - ručne, hromadne, alebo z Auto-fill - takže návrh nikdy neobsahuje riadky, ktoré nemôžete potvrdiť.

## Namerané, alebo čestne označené ako odhad

Dve čísla poháňajú väčšinu tejto obrazovky: čas dodania a rozpočet. Pravidlo je pre obe rovnaké. **Ak máme históriu, číslo je namerané a nedá sa upraviť.** Ak ju nemáme, karta to povie a odhad sa dá upraviť - ale v nastaveniach, nikdy priamo na paneli: **estimated delivery time** (odhadovaný čas dodania) na produkte dodávateľa, alebo v nastaveniach samotného SKO. Akonáhle existuje dosť skutočných dodávok, meranie preberá vládu a pole s odhadom zmizne. Nikto nemôže prepísať to, čo sa naozaj stalo.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Panel:</b> vaša organizácia → <b>Procurement → Partners</b> → otvorte partnera → <b>Shopping</b>.</li>
<li><b>Preskočiť z grafu:</b> kliknite na výsek, alebo na riadok v legende, pre prezretie daného koša v katalógu.</li>
<li><b>Pracovať s košom:</b> kliknite na číslo dlaždice pre tabuľku položiek, na písmeno rank pre prezretie tých produktov, alebo na <b>Fill</b> pre zúžený návrh Auto-fill.</li>
<li><b>Vyčistiť zoznam:</b> <b>remove</b> na dlaždici Covered alebo Dead stock.</li>
<li><b>Opraviť odhad času dodania:</b> nastavenia SKO, alebo nastavenia produktu dodávateľa - len kým ešte hovorí <i>estimate</i>.</li>
</ul>
</aside>
