---
title: Pozície v továrni
summary: Štyri pozície v továrni - vedúci dielne, prípravár zmesí, majster a operátor - čo môže každá z nich robiť v aiku a ako sa pozícia priraďuje osobe.
date: 2026-09-08
source_date: 2026-09-08
tags: production, hr
category: production
help_routes: grp.org.hr.employees.edit
series: Ordering from partners
order: 7
---

<aside class="tldr">
Pre manažérov a pre každého, kto sa pýta, čo mu jeho prihlásenie umožňuje. Továreň v aiku má štyri pozície, od človeka, ktorý plánuje celú dielňu, až po človeka, ktorý veci vyrába. Pozície sa priraďujú v karte zamestnanca pod <b>Human Resources</b> (ľudské zdroje), jedno zaškrtnutie na továreň, a oprávnenia z toho vyplynú automaticky. Nikto neupravuje oprávnenia ručne. Bežné obrazovky sú vysvetlené v <a href="/docs/fulfilling-partner-orders-sk">Práca so zoznamom To produce</a>, <a href="/docs/preparing-mixes-sk">Príprava zmesí</a> a <a href="/docs/working-the-floor-screen-sk">Práca s obrazovkou dielne</a>.
</aside>

## Štyri pozície

Od najvyššej po najnižšiu. Každá zahŕňa všetko, čo je pod ňou na obrazovke dielne, ale nie plánovacie právomoci tej nad ňou.

**Floor supervisor** (vedúci dielne). Riadi továreň. Vidí **To produce** so všetkými záložkami, presúva karty na Boarde a tým vytvára pracovné príkazy, uvoľňuje ich na dielňu, môže ich prijať na sklad, ak ich sklad ešte neuložil, rozhoduje, kto obvykle čo vyrába, a vedie zoznam remeselníkov. Jeden alebo dvaja ľudia na továreň.

**Mix preparer** (prípravár zmesí). Vedie zmesi a základy, na ktorých remeselníci závisia. Vidí **To produce** a záložku **Mixes**, vytvára pracovné príkazy na zmesi pretiahnutím karty k sebe, a uvoľňuje a prijíma iba pracovné príkazy adresované sebe. Na pracovné príkazy niekoho iného nesiaha. V dielni pracuje ako remeselník.

**Foreman** (majster). Drží operátorov v poriadku. Vidí všetko na dielni aj pracovné príkazy, môže upravovať položky a množstvá pracovného príkazu a môže spustiť úlohu v mene niekoho iného. Nemôže vytvárať pracovné príkazy z **To produce**, uvoľňovať ich ani meniť, kto čo vyrába. Osoba, na ktorú sa treba obrátiť, keď úloha viazne.

**Operative** (operátor). Vyrába veci. Jeho obrazovka je **Factory → Jobs**: úlohy adresované jemu, a pri každej START a DONE. Môže sa pozrieť na To produce a na Board, aby videl, čo prichádza, ale nič na nich nemôže presúvať. Zamietnutia (rejects) zaznamenáva majster alebo vyššia pozícia, nie operátor.

## Ako sa pozícia priraďuje

1. **Human Resources → Employees** (ľudské zdroje → zamestnanci), otvorte osobu, **Edit** (upraviť), potom **Job Positions (permissions)** (pracovné pozície / oprávnenia).
2. Rozbaľte organizáciu. Nájdite riadok **Production** (výroba).
3. Zaškrtnite jednu pozíciu. Ak má organizácia viac ako jednu továreň, vyberte, na ktoré továrne sa vzťahuje.
4. Uložte. Oprávnenia, aj samotná továreň, sa objavia v prihlásení osoby okamžite. Ak sekcia Factory chýba v ich bočnom paneli aj potom, požiadajte ich, aby sa odhlásili a znova prihlásili.

Osoba môže mať súčasne viacero pozícií, v továrni aj inde. Vedúci dielne, ktorý zároveň vychystáva v sklade, má jednoducho zaškrtnuté oba riadky.

## Čo je dobré vedieť

- **Dielňa je rovnaká obrazovka pre každého.** Mení sa to, ktoré úlohy sa zobrazia a ktoré tlačidlá existujú, nie rozloženie. Majster a vyššie pozície vidia aj otvorený zásobník úloh, na ktorých nie je nikto menovaný, a môžu si niektorú vziať.
- **Hotový tovar je práca skladu.** Keď je posledná úloha hotová, pracovný príkaz sa objaví pod <a href="/docs/putting-away-finished-production-sk">Dispatching → From production</a>, aby ho dispečer uložil. Na to nie je potrebná žiadna pozícia v továrni.
- **Pozície sú na úrovni továrne.** Tá istá osoba môže byť v jednej továrni vedúcim dielne a v inej operátorom.
- **Žiadna pozícia, žiadna továreň.** Niekto bez pozície Production nevidí sekciu Factory vôbec, aj keby bol vedúcim dielne inde.
- **Administrátori organizácie** majú toto všetko bez pozície v továrni.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Priradiť pozíciu:</b> vaša organizácia → <b>Human Resources</b> → <b>Employees</b> → otvorte osobu → <b>Edit</b> → <b>Job Positions (permissions)</b> → rozbaľte organizáciu → riadok <b>Production</b>.</li>
<li><b>Zistiť, čo má niekto priradené:</b> tá istá stránka zobrazuje zaškrtnutie pri každej pozícii, ktorú osoba má.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké oprávnenia potrebujete</strong>
<ul>
<li>Priraďovanie alebo zmena pozícií: Human Resources supervisor pre organizáciu, alebo organisation administrator.</li>
</ul>
</aside>
