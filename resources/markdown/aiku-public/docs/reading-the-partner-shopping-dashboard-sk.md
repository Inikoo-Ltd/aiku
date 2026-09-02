---
title: Ako čítať nákupný panel partnera
summary: Obrazovka, ktorá vám hovorí, čo nakúpiť od partnera a koľko priestoru na to máte - tri karty limitov, osem košov rizikového skladu a objednávkový pipeline.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, intercompany, shopping-list, stock
category: procurement
series: Ordering from partners
order: 2
---

<aside class="tldr">
Panel je začiatkom každej nákupnej relácie. Horný riadok hovorí, koľko priestoru máte - peniaze a skladové miesto. Stred hovorí, ktoré z partnerových produktov vám čoskoro spôsobia problém, najhoršie ako prvé. Spodok hovorí, čo sa už hýbe. Nemusíte si nič pamätať; len odpovedáte na to, čo sa vás obrazovka pýta. Samotné zadanie objednávky je popísané v <a href="/docs/buying-from-a-partner-sk">Buying from a partner</a>.
</aside>

Otvorte ho na **Procurement → Partners → {partner} → Shopping**. Nahrádza starý zvyk otvoriť nákupný zoznam a snažiť sa spomenúť si, čo chýbalo.

## Tri karty hore: koľko priestoru máte

Sú to obmedzenia, nie ozdoby. Existujú preto, lebo nákupný zoznam, do ktorého môže hocikto hodiť hocičo, prestáva byť signálom - partner, ktorý dostane tisíc riadkov, nedokáže rozpoznať, ktoré dva sú núdzové.

- **Order budget used.** Hodnota vášho otvoreného zoznamu voči tomu, čo vám tento partner *skutočne dodá* v jednom objednávkovom cykle, vo mene vašej vlastnej organizácie - každé peňažné číslo na týchto obrazovkách je za vás prepočítané, takže nikdy nemusíte rozmýšľať v mene partnera. Keď je dosť histórie dodávok, meria sa z týchto dodávok; predtým je to jeden objednávkový cyklus toho, čo z ich produktov naozaj predávate. Toto číslo nezadáva nikto - ani vy, ani váš nadriadený. Keď je pruh plný, karta ukazuje **at capacity**.
- **Warehouse space.** Koľko miest je voľných z celkového počtu, s pruhom rozdeleným na to, čo je *v použití*, čo je *na ceste* v otvorených objednávkach a dodávkach, a čo by si nárokoval *tento nákupný zoznam*. Pod tým spravodlivý podiel tohto partnera: koľko z voľných miest môžu zabrať jeho úplne nové produkty. Miesta sa počítajú ako sloty - nemáme údaje o objeme, takže nepredstierame, že meriame kubické metre.
- **Karta partnera.** Jeho nameraný čas dodania **objednávka → naskladnené**, z koľkých dodávok bol nameraný (alebo že je to zatiaľ len odhad), na koľkých objednávkach mešká a o koľko, a veľkosť jeho katalógu.

## Rizikový sklad: osem košov, najhoršie ako prvé

Blok dlaždíc pokrýva celý partnerov katalóg, rozdelený podľa toho, ako dlho vydrží váš vlastný sklad. Nebezpečné úrovne sú dimenzované podľa tohto nameraného času dodania, nie podľa kalendárnych týždňov - a v tom je podstata:

- **Out of stock** - na regáli nič nie je.
- **Doomed** - stále máte sklad, ale matematicky sa minie skôr, než by mohla doraziť dodávka, aj keby ste objednali práve teraz.
- **Critical / Danger / Watch** - dôjde do dvoch, troch alebo štyroch časov dodania.
- **Covered** - v poriadku.
- **Dead stock** - nič sa nepredáva, peniaze ležia na regáli; dlaždica ukazuje, akú má hodnotu.
- **We never stocked** - partner to predáva, vy ste to nikdy nemali na sklade.

Jeden typ položky sa tu nikdy neobjaví: SKO, ktoré máte vo vlastnom inventári označené ako **On Demand**. Ich stav skladu sa nesleduje, takže "out of stock" by nič neznamenalo - panel, tabuľky košov aj Auto-fill ich vynechávajú.

Každá dlaždica odpovedá na jednu otázku: **koľko ich ešte odo mňa potrebuje?** Počet "*N* need action" ignoruje všetko, čo je už na zozname alebo už na ceste, takže postupne klesá na nulu, ako pracujete. Pod ním je ten istý počet rozdelený podľa **ranku** - najprv produkty A, s D a Z vyblednutými na konci. Dva produkty A bez skladu sú dôležitejšie ako päťsto pokrčení plecami pri ranku D, takže v tomto poradí sa pracuje.

Tri veci môžete urobiť z jednej dlaždice:

- **Kliknúť na číslo**, aby ste otvorili kôš ako tabuľku: každá položka, zoradená podľa ranku, s ich skladom, vaším skladom, kedy vám dôjde, a políčkom na množstvo, ktoré zapisuje priamo do nákupného zoznamu.
- **Kliknúť na dlaždicu alebo rank**, aby ste prezerali tie produkty v partnerovom katalógu.
- **+ fill**, aby ste otvorili Auto-fill už zúžené na daný kôš a už vygenerované - vy len upravíte a potvrdíte. Viac práce ako čarovné tlačidlo, ale oveľa viac kontroly. Keď je časť koša už na vašom zozname, tlačidlo zmení popisok na **+ fill more**, a počty vedľa neho - *N on the way · N on list* - vám povedia, koľko z dlaždice ste už vyriešili.

Na **Covered** a **Dead stock** sa namiesto toho zobrazí červené upozornenie, keď je niečo z daného koša na vašom nákupnom zozname: to je sklad, ktorý nepotrebujete. **remove** vymaže tieto riadky jedným kliknutím.

## Objednávkový pipeline

Spodný pás sleduje všetko od potreby po regál: **on shopping list → being prepared → ready to ship → in transit → arrived, booking in**. Každý stĺpec zobrazuje dodávky a počet položiek v nich; každá karta otvorí dodávku len na čítanie - sklad predávajúceho ju vlastní, kým tovar k vám nedorazí.

Karty viditeľne starnú. Po trojnásobku času dodania zožltnú (amber); po desaťnásobku sčervenajú. Stará karta je otázka pre partnera, nie číslo na obdiv. Všetko, čo naozaj mešká, sa navyše objaví v zozname **Late from this partner**, s najväčším omeškaním ako prvým, s vyznačeným "no delivery date given".

## Prečo obrazovka niekedy povie nie

Pridanie do zoznamu môže byť odmietnuté. Je to zámerné a existujú len tri dôvody:

- **Dosiahnutý limit rozpočtu** - najprv niečo odstráňte alebo znížte prioritu. Skutočná kríza sa vždy zmestí: **položky s rankom A a bez skladu sú z limitu vyňaté**, takže núdza nikdy nečaká za limitom.
- **Dno skladu** - pod 5 % voľných miest sa nepridá žiadny *nový* produkt od nikoho. Položky, ktoré už máte na sklade, dopĺňajú vlastné miesta a prechádzajú bez obmedzenia.
- **Spravodlivý podiel tohto partnera** - jeden partner môže zabrať zhruba pätinu voľných miest produktmi, ktoré ste nikdy nemali na sklade. Aj ostatní dodávatelia potrebujú priestor.

Rovnaká ochrana platí, nech pridávate kdekoľvek - ručne, hromadne, alebo z Auto-fill - takže návrh nikdy neobsahuje riadky, ktoré nemôžete potvrdiť.

## Namerané, alebo čestne označené ako odhad

Dve čísla poháňajú takmer celú túto obrazovku: čas dodania a rozpočet. Pravidlo je pre obe rovnaké. **Ak máme históriu, číslo je namerané a nedá sa upraviť.** Ak ju nemáme, povieme to na karte, a odhad je upraviteľný - ale v nastaveniach, nikdy priamo na paneli: **estimated delivery time** na produkt, buď na produkte dodávateľa, alebo v nastaveniach samotného SKO. Akonáhle existuje dosť skutočných dodávok, meranie preberá vládu a pole s odhadom zmizne. Nikto nemôže prepísať to, čo sa naozaj stalo.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Panel:</b> vaša organizácia → <b>Procurement → Partners</b> → otvorte partnera → <b>Shopping</b>.</li>
<li><b>Pracovať s košom:</b> kliknite na číslo dlaždice pre tabuľku položiek, alebo na písmeno ranku, aby ste prezerali tie produkty, alebo na <b>+ fill</b> pre zúžený návrh Auto-fill.</li>
<li><b>Vyčistiť zoznam:</b> <b>remove</b> na dlaždici Covered alebo Dead stock.</li>
<li><b>Opraviť odhad času dodania:</b> nastavenia SKO, alebo nastavenia produktu dodávateľa - len kým ešte hovorí <i>estimate</i>.</li>
</ul>
</aside>
