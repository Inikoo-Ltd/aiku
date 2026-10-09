---
title: Prvé prihlásenie agenta
summary: Pre nákupnú spoločnosť — ako vytvoriť jediný účet, ktorý potrebuje obstarávací agent na začiatok práce v aiku, po ktorom sa už o svojich ľudí stará sám.
date: 2026-09-30
source_date: 2026-10-08
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 1
---

<aside class="tldr">
Agenti sú v aiku organizácie, rovnako ako obchod, a ich ľudia sa prihlasujú presne ako váš vlastný personál. Vytvoríte <b>jednu</b> osobu v agentskej organizácii s pozíciou <b>Agent → Manager</b> a používateľským menom a heslom. Odvtedy si táto osoba pridáva svojich kolegov sama; jej strana je v <a href="/docs/adding-staff-to-your-agent-organisation-sk">pridávanie personálu do vašej agentskej organizácie</a>.
</aside>

## Ako fungujú prihlásenia agentov

Každý obstarávací agent je organizácia typu *agent*. Keď sa niekto z tejto organizácie prihlási, vidí len svoju vlastnú organizáciu a len svoju prácu:

- menu **Procurement**: **Purchase Orders**, ktoré mu posielajú vaše organizácie, po jednej pre každého z jeho dodávateľov, so zálohami, ktoré na ne platí, **Stock Deliveries**, ktoré vám posiela, jeho vlastní **Suppliers** a jeho dodávateľská **Inbox**;
- menu **HR**, pre jeho vlastných ľudí;
- **Tickets**, na žiadosť o pomoc u vášho helpdesku.

Nikdy nevidí skupinové menu, vaše obchody, vašich zákazníkov, vaše účty ani ostatných agentov. Ak takú stránku otvorí podľa adresy, zobrazí sa mu stránka **Forbidden**.

Nikto nemá prihlásenie, kým mu ho niekto nedá, a prvé musí prísť od vás. Potom vlastníctvo prechádza na agenta.

## Vytvorenie prvého agentského používateľa

Potrebujete práva HR edit v agentskej organizácii; administrátori skupiny ich majú pre každú organizáciu.

1. Prepnite sa na agentskú organizáciu pomocou prepínača organizácií v hornej časti stránky.
2. Prejdite na **HR → Employees** a stlačte **Create Employee**.
3. V sekcii **Employment** vyplňte povinné polia. **Worker number** a **alias** musia byť jedinečné len v rámci danej agentskej organizácie, takže krstné meno osoby postačí pre obe. Nastavte stav na **Working**.
4. V sekcii **Job**, pod **Position**, vyberte **Agent → Manager**. Toto je ten jeden krok, ktorý zmení bežného zamestnanca na niekoho, kto môže viesť prácu agenta, vrátane pridávania a odoberania ostatných ľudí. Ak ho vynecháte, prihlási sa do prázdnej obrazovky. Personálu agentov nedávajte **Organisation Administrator**: otvára účtovníctvo, sklady a nastavenia organizácie, ktoré agenti nepoužívajú.
5. V sekcii **User credentials** zadajte **username**, s ktorým sa bude prihlasovať, a počiatočné **password**. aiku ho pri prvom prihlásení donúti zvoliť si nové heslo, takže toto potrebuje vydržať len dovtedy, kým mu ho odovzdáte.
6. Uložte.

Pošlite mu adresu aplikácie, username a počiatočné heslo cez akýkoľvek kanál, ktorý s daným agentom už používate. To je všetko, čo potrebuje.

## Agenti, ktorí mali prihlásenie v Aurore

Agenti, ktorí už mali prihlásenie v starom systéme, si ponechávajú svoje username a heslo a ich prvé prihlásenie do aiku tiché skonvertuje staré heslo. Ich účet bol prenesený s pozíciou **Organisation Administrator**: otvorte ich zamestnanca, stlačte **Edit**, zaškrtnite **Agent → Manager** a odškrtnite **Organisation Administrator**, aby videli pohľad agenta.

## Ak sa agent sám zamkne von

Práva HR edit v agentskej organizácii vám ostávajú, takže vždy môžete otvoriť agentovho zamestnanca cez **HR → Employees**, prejsť na jeho používateľa a nastaviť nové heslo, alebo vytvoriť druhého manažéra rovnakým spôsobom ako prvého. Manažéri agentov nemôžu zmeniť prihlásenie sami, takže resety hesiel pre ktorýkoľvek z ich ľudí prichádzajú k vám.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Vytvorenie prvého agentského používateľa:</b> prepínač organizácií → agentská organizácia → <b>HR → Employees</b> → <b>Create Employee</b> → Position <b>Agent → Manager</b> → vyplňte <b>User credentials</b>.</li>
<li><b>Reset zamknutého agenta:</b> agentská organizácia → <b>HR → Employees</b> → daná osoba → jej používateľ → <b>Edit</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké práva potrebujete</strong>
<ul>
<li>Vytvorenie používateľa v agentskej organizácii vyžaduje práva <b>HR edit</b> v danej organizácii. Administrátori skupiny ich majú všade.</li>
<li>Nastavenie nového hesla agentovi vyžaduje práva systémovej administrácie, ktoré agenti nikdy nemajú.</li>
</ul>
</aside>
