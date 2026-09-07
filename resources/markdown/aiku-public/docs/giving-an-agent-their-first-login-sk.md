---
title: Prvé prihlásenie agenta
summary: Pre nákupnú spoločnosť — ako vytvoriť jediný účet, ktorý potrebuje obstarávací agent na začiatok práce v aiku, po ktorom sa už o svojich ľudí stará sám.
date: 2026-09-02
source_date: 2026-09-02
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 1
---

<aside class="tldr">
Agenti sú v aiku organizácie, rovnako ako obchod, a ich ľudia sa prihlasujú presne ako váš vlastný personál. Vytvoríte <b>jednu</b> osobu v agentskej organizácii s pozíciou <b>Organisation Administrator</b> a používateľským menom a heslom. Odvtedy si táto osoba pridáva svojich kolegov sama; jej strana je v <a href="/docs/adding-staff-to-your-agent-organisation-sk">pridávanie personálu do vašej agentskej organizácie</a>.
</aside>

## Ako fungujú prihlásenia agentov

Každý obstarávací agent je organizácia typu *agent*. Keď sa niekto z tejto organizácie prihlási, vidí len svoju vlastnú organizáciu: menu **Procurement** s dodávateľskými objednávkami, dodávkami tovaru a tabuľou nákupného zoznamu, ktoré sa ho týkajú, menu **HR** pre svojich vlastných ľudí a svoje nastavenia. Nikdy nevidí vaše obchody, vašich zákazníkov ani ostatných agentov.

Nikto nemá prihlásenie, kým mu ho niekto nedá, a prvé musí prísť od vás. Potom vlastníctvo prechádza na agenta.

## Vytvorenie prvého agentského používateľa

Potrebujete práva HR edit v agentskej organizácii; administrátori skupiny ich majú pre každú organizáciu.

1. Prepnite sa na agentskú organizáciu pomocou prepínača organizácií v hornej časti stránky.
2. Prejdite na **HR → Employees** a stlačte **Create Employee**.
3. V sekcii **Employment** vyplňte povinné polia. **Worker number** a **alias** musia byť jedinečné len v rámci danej agentskej organizácie, takže krstné meno osoby postačí pre obe. Nastavte stav na **Working**.
4. V sekcii **Job**, pod **Position**, vyberte **Organisation Administrator**. Toto je ten jeden krok, ktorý zmení bežného zamestnanca na niekoho, kto môže viesť organizáciu, vrátane pridávania a odoberania ostatných ľudí. Ak ho vynecháte, prihlási sa do prázdnej obrazovky.
5. V sekcii **User credentials** zadajte **username**, s ktorým sa bude prihlasovať, a počiatočné **password**. aiku ho pri prvom prihlásení donúti zvoliť si nové heslo, takže toto potrebuje vydržať len dovtedy, kým mu ho odovzdáte.
6. Uložte.

Pošlite mu adresu aplikácie, username a počiatočné heslo cez akýkoľvek kanál, ktorý s daným agentom už používate. To je všetko, čo potrebuje.

## Agenti, ktorí mali prihlásenie v Aurore

Agenti, ktorí už mali prihlásenie v starom systéme, si ponechávajú svoje username a heslo. Ich účet bol prenesený s pozíciou Organisation Administrator a ich prvé prihlásenie do aiku tiché skonvertuje staré heslo. Pre nich nemusíte robiť nič.

## Ak sa agent sám zamkne von

Práva HR edit v agentskej organizácii vám ostávajú, takže vždy môžete otvoriť agentovho zamestnanca cez **HR → Employees**, prejsť na jeho používateľa a nastaviť nové heslo, alebo vytvoriť druhého administrátora rovnakým spôsobom ako prvého.

<aside class="wayfinder"><strong>Kam kliknúť v aiku</strong>
<ul>
<li><b>Vytvorenie prvého agentského používateľa:</b> prepínač organizácií → agentská organizácia → <b>HR → Employees</b> → <b>Create Employee</b> → Position <b>Organisation Administrator</b> → vyplňte <b>User credentials</b>.</li>
<li><b>Reset zamknutého agenta:</b> agentská organizácia → <b>HR → Employees</b> → daná osoba → jej používateľ → <b>Edit</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Aké práva potrebujete</strong>
<ul>
<li>Vytvorenie používateľa v agentskej organizácii vyžaduje práva <b>HR edit</b> v danej organizácii. Administrátori skupiny ich majú všade.</li>
</ul>
</aside>
