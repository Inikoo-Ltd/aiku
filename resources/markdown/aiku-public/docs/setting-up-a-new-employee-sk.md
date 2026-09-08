---
title: Nastavenie nového zamestnanca
summary: Pridajte nového zamestnanca do aiku — jeho osobné údaje, pracovné podmienky a pracovné pozície — a ak to potrebuje, dajte mu v tom istom formulári aj vlastné prihlásenie.
date: 2026-08-31
source_date: 2026-08-31
tags: hr
category: hr
---

<aside class="tldr">
Každá osoba, ktorá pracuje vo vašej organizácii — na výplatnej páske alebo nie — má v aiku záznam <em>employee</em>. Vytvoríte ho raz, v <b>HR → Employees → Create Employee</b>, a všetko ostatné sa naň naviaže: jeho zmluva, výkazy pracovného času, dochádzkový PIN a (ak vyplníte poslednú sekciu formulára) používateľské meno a heslo, ktoré použije na prihlásenie do samotného aiku.
</aside>

## Skôr než začnete

Pripravte si tri veci: celé meno osoby, **worker number** (vaše vlastné interné referenčné číslo zamestnanca — musí byť jedinečné v rámci organizácie) a **alias** — krátku prezývku, ktorou sa na osobu odvoláva zvyšok aiku, takže zvoľte niečo rozpoznateľné ako `maria` alebo `j.smith`. Ak sa bude do aiku prihlasovať, rozhodnite sa aj o jeho používateľskom mene a počiatočnom hesle.

## Vytvorenie zamestnanca

Otvorte svoju organizáciu, prejdite na **HR → Employees** a stlačte **Create Employee**. Formulár je jedna stránka s piatimi sekciami; povinná je len hŕstka polí a k zvyšku sa môžete neskôr vrátiť cez **Edit**.

### Personal information

Tu je povinné len **Name**. Zvyšok — dátum narodenia, osobný email, telefón, domáca adresa, núdzový kontakt, identifikačný doklad a voľné poznámky — sa oplatí zaznamenať, kým máte podklady po ruke, no nič vám nebráni uložiť aj bez toho.

### Employment

Táto sekcia obsahuje povinné polia:

- **Worker Type** — je táto osoba *employee*, *volunteer*, alebo *temporal worker*? Ide o to, kým je pre organizáciu, nie o jej pracovný čas.
- **Employment Type** — *full time* alebo *part time*.
- **Worker number** a **Alias** — jedinečné referencie opísané vyššie.
- **Work email** — jeho firemná adresa, ak nejakú má. Ak mu nižšie pridelíte prihlásenie, táto adresa sa stane emailom na jeho používateľskom účte.
- **State** — vyberte **Hired** pre osobu, ktorá podpísala zmluvu, ale nastupuje k budúcemu dátumu, alebo **Working** pre osobu, ktorá už pracuje. (Neskôr v jeho pracovnom pomere sa záznam môže presunúť na *Leaving* a napokon *Left* — odchádzajúci zamestnanci sa nikdy nevymazávajú, takže ich história ostáva neporušená.)
- **Employment start at** — jeho prvý deň.

### Job

- **Job Title** — voľný text; pole navrhuje tituly už použité inde, aby vaše pomenovania zostali konzistentné.
- **Position** — toto je tá dôležitá časť. Positions sú roly zo zoznamu pracovných pozícií vašej organizácie a rozhodujú o tom, **čo daná osoba v aiku vidí a môže robiť**. Niektoré pozície platia pre celú organizáciu; iné sú vymedzené, takže si vyberiete, ktoré obchody, fulfilmenty alebo sklady daná rola pokrýva — supervízor obchodu len pre jeden obchod, picker v jednom sklade, ale nie v inom. Jedna osoba môže mať naraz viac pozícií. Ak sa bude prihlasovať, vyberajte tieto pozorne: jeho menu v aiku sa z nich zostavuje.

### Contract

Voliteľné: dátum začiatku a konca zmluvy plus jeho **annual leave days**. Ak zadáte dátum začiatku zmluvy, aiku pre neho založí riadny záznam zmluvy, ktorý neskôr nájdete v záložke **Contracts** zamestnanca — spolu s ďalšími budúcimi zmluvami, ako sa podmienky menia.

### User credentials

Ak túto sekciu necháte prázdnu, osoba v HR existuje, ale nemá prihlásenie — čo je správne pre skladový alebo predajný personál, ktorý sa dotýka len dochádzkového zariadenia. Vyplňte **Username** a **Password** a aiku vytvorí jeho používateľský účet v tej istej chvíli, ako vytvorí zamestnanca. Účet automaticky prevezme jeho meno a pracovný email a pri prvom prihlásení ho aiku vyzve, aby si zvolil vlastné nové heslo — takže to, ktoré tu zadáte vy, je len otvárač dverí, nie tajomstvo, ktoré treba chrániť navždy.

## Čo sa stane po uložení

Stlačenie save vás presunie na stránku nového zamestnanca a na pozadí sa už stalo niekoľko vecí:

- Objaví sa v zozname **HR → Employees**, vo vašich číslach o počte zamestnancov a v zoznamoch pracovných pozícií pre role, ktoré ste mu pridelili.
- Ak je jeho stav **Working**, aiku mu už vydalo **dochádzkový PIN**, takže sa môže od prvého dňa pípať na dochádzkovom zariadení. PIN si môžete pozrieť — alebo vydať nový — z jeho stránky zamestnanca.
- Jeho **výkazy pracovného času** sa začnú zbierať hneď, ako začne pípať, v záložke **Timesheets** zamestnanca.
- Ak ste vytvorili prihlásenie, môže sa hneď prihlásiť s používateľským menom a heslom, ktoré ste nastavili (heslo zmení pri prvom vstupe), a vidí presne to, čo mu jeho pozície umožňujú.

Čokoľvek ste preskočili — adresu, dátumy zmluvy, ďalšie pozície — je na jedno kliknutie vzdialené pod **Edit** na jeho stránke.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Pridať niekoho:</b> vaša organizácia → <b>HR → Employees</b> → <b>Create Employee</b>.</li>
<li><b>Doplniť alebo opraviť neskôr:</b> otvorte zamestnanca → <b>Edit</b>. Zmluvy, výkazy pracovného času a pozície majú na stránke zamestnanca vlastné záložky.</li>
<li><b>Dochádzkový PIN:</b> na stránke zamestnanca — zobrazte ho alebo tam vygenerujte nový.</li>
<li><b>Viac nových zamestnancov naraz:</b> zoznam Employees ponúka aj šablónu tabuľky na stiahnutie, vyplnenie a nahratie.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké práva potrebujete</strong>
<ul>
<li>Vytváranie a úprava zamestnancov vyžaduje práva <b>HR edit</b> v organizácii — zvyčajne HR rola alebo administrátor organizácie.</li>
</ul>
</aside>
