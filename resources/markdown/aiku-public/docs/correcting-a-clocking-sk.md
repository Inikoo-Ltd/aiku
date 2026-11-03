---
title: Oprava dochádzky
summary: Keď je príchod alebo odchod zapísaný zle alebo chýba, zamestnanec požiada o opravu zo svojho vlastného výkazu pracovného času a HR ju schváli alebo zamietne. Schválená oprava zmení samotný výkaz.
date: 2026-11-03
source_date: 2026-11-03
tags: hr, clocking, attendance
category: hr
---

<aside class="tldr">
Zabudli ste si pípnuť odchod, alebo ste pípli neskoro, lebo zariadenie bolo obsadené? Zamestnanec požiada o <b>clocking adjustment</b> (úpravu dochádzky) zo svojej vlastnej stránky <b>Employee Clocking</b>, uvedie správne časy a dôvod. HR žiadosť vidí v <b>HR → Clocking adjustment</b> a schváli ju alebo zamietne. Schválením sa výkaz pracovného času za daný deň zmení na požadované časy; dovtedy sa nemení nič.
</aside>

## Žiadosť o opravu (zamestnanec)

Všetko je na vašej stránke **Employee Clocking**. Ktorú záložku použijete, závisí od dňa.

### Deň, ktorý už má pípnutia

1. Otvorte záložku **Timesheets** a nájdite deň.
2. Stlačte **Request correction** na konci riadka.
3. Okno **Request timesheet correction** ukazuje, čo bolo zaznamenané (**Recorded**), a začína s týmito časmi už vyplnenými. Zmeňte **Check-in time** (čas príchodu), **Check-out time** (čas odchodu) alebo oboje.
4. Napíšte dôvod do **Reason**. Je povinný.
5. Ak máte doklad, pridajte ho v **Supporting Document (Optional)**: PDF, JPG alebo PNG, najviac 3 súbory po 5 MB.
6. Stlačte **Submit Request**.

Riadok teraz namiesto tlačidla ukazuje **Correction pending**, kým HR nerozhodne.

### Deň bez akéhokoľvek pípnutia

Otvorte záložku **Clocking adjustment** a stlačte **Clocking adjustment**. Vyberte dátum v **Date**, vyplňte čas príchodu, čas odchodu alebo oboje, uveďte dôvod a odošlite.

### O čo môžete a nemôžete požiadať

- Žiadosti siahajú najviac **jeden mesiac** dozadu a nikdy nie na budúci dátum.
- Čas odchodu musí byť po čase príchodu.
- Jedna čakajúca žiadosť na deň. Pred ďalšou žiadosťou počkajte na rozhodnutie alebo ju zrušte.
- Nie na deň, ktorý pokrýva schválená neprítomnosť.

### Sledovanie žiadosti

Záložka **Clocking adjustment** zobrazuje vaše žiadosti s pôvodnými časmi, požadovanými časmi a stavom. Kým žiadosť čaká, môžete ju stiahnuť cez **Cancel request**. Ak je zamietnutá, dôvod od HR sa zobrazí červenou pod vaším.

## Rozhodnutie o žiadosti (HR)

Otvorte **HR → Clocking adjustment**. Každá žiadosť je karta: zamestnanec, deň, zaznamenané časy (**Recorded**, alebo **No clockings**, ak pípnutia nie sú) vedľa požadovaných časov (**Requested**), dôvod a prílohy. Časy sú v časovom pásme vašej organizácie, ktoré karta uvádza.

Nad kartami môžete hľadať podľa zamestnanca alebo dôvodu, vybrať stav (**All**, **Pending**, **Approved**, **Rejected**) a nastaviť dátum **From** (od) a **To** (do). **Clear filters** ich vymaže.

- **Approve** si vyžiada potvrdenie a potom zmení výkaz pracovného času za daný deň: prvý príchod a posledný odchod sa presunú na požadované časy a odpracované hodiny sa prepočítajú. Prestávky uprostred dňa ostanú tak, ako boli. Pri dni bez pípnutí sa chýbajúci príchod a odchod pridajú.
- **Reject** si vyžiada dôvod v **Reason for rejection**, ktorý zamestnanec uvidí.

Rozhodnutá žiadosť ukazuje **Processed**, s tým, kto a kedy rozhodol, a znova sa o nej rozhodnúť nedá.

### Už vyplatené týždne remeselníkov

Remeselníci na výrobnej hale dostávajú mzdu za hodiny, ktoré si odpípnu, a účtovník vypláca každý týždeň (pondelok až nedeľa) v utorok po ňom. Takže keď ten utorok začne, týždeň je **Locked for payroll**: HR už nemôže schváliť opravu za tento týždeň ani pridať, zmeniť či zmazať jeho pípnutia vo výkaze pracovného času. Môže to len účtovník a výrobný manažér a Aiku sa ich opýta na dôvod. Zmena sa zaznamená, nájdete ju v zozname **Factory** → **Artisans** → **Payroll changes**, a rozdiel v mzde sa vyplatí alebo odpočíta v nasledujúcom mzdovom behu. Aktuálny týždeň a minulý týždeň do pondelka 23:59 sa dajú opraviť ako zvyčajne. Pípnutia zamestnancov, ktorí nepracujú na výrobnej hale, sa to netýka.

### Prehľad

Záložka **Dashboard** na tej istej stránke je mesiac na jeden pohľad: riadok na zamestnanca, stĺpec na deň, so značkou pri každom dni, ktorý má žiadosť, vo farbe jej stavu. Filtrujte podľa zamestnanca alebo stavu, prechádzajte mesiacmi a kliknutím na značku zobrazíte **Adjustment Details**.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Opraviť deň, keď ste pípli:</b> <b>Employee Clocking</b> → <b>Timesheets</b> → <b>Request correction</b>.</li>
<li><b>Doplniť deň, keď ste vôbec nepípli:</b> <b>Employee Clocking</b> → <b>Clocking adjustment</b> → <b>Clocking adjustment</b>.</li>
<li><b>Stiahnuť žiadosť:</b> <b>Employee Clocking</b> → <b>Clocking adjustment</b> → <b>Cancel request</b>.</li>
<li><b>Schváliť alebo zamietnuť:</b> vaša organizácia → <b>HR → Clocking adjustment</b>.</li>
<li><b>Pozrieť si mesiac:</b> <b>HR → Clocking adjustment</b> → <b>Dashboard</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Oprávnenia, ktoré potrebujete</strong>
<ul>
<li>Na žiadosť o opravu potrebujete záznam zamestnanca prepojený s vaším prihlásením do aiku. Opraviť môžete len svoj vlastný výkaz.</li>
<li>Na zobrazenie žiadostí a prehľadu potrebujete v organizácii práva <b>HR view</b>.</li>
<li>Schvaľovanie a zamietanie potrebujú práva <b>HR edit</b>.</li>
</ul>
</aside>

Súvisiace: <a href="/docs/reading-the-hr-dashboard-sk">Ako čítať HR nástenku</a> pre dochádzku dňa a <a href="/docs/setting-up-a-clocking-machine-sk">Nastavenie dochádzkového zariadenia</a>, kde sa dozviete, odkiaľ pípnutia pochádzajú.
