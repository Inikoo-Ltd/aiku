---
title: Kontrola cien hlavných produktov
summary: Odkiaľ aiku berie navrhovanú cenu pri vytváraní hlavného produktu, čo znamenajú červené upozornenia na cenách, ktoré vyzerajú podozrivo, a čo robiť, keď sa zmenia obchodné jednotky produktu.
date: 2026-09-28
source_date: 2026-09-28
tags: masters, pricing, products, catalogue
category: shop
---

<aside class="tldr">
Cena hlavného produktu sa dostane do každého obchodu, ktorý ho predáva, takže jedna chyba zasiahne všetky obchody naraz. aiku teraz červenou farbou označuje dva druhy podozrivej ceny: cenu za jednotku <b>vzdialenú od zvyšku jej rodiny</b> a cenu, ktorá <b>nebola skontrolovaná po zmene obchodných jednotiek</b>. Ak vytvárate hlavné produkty alebo sa staráte o ceny, prečítajte si túto príručku.
</aside>

## Odkiaľ pochádza navrhovaná cena

Keď vytvárate hlavný produkt z obchodných jednotiek, aiku za vás doplní cenu. Spočíta náklady na **každú vybranú obchodnú jednotku** a potom uplatní bežnú prirážku hlavného obchodu. RRP sa vypočíta z tejto ceny rovnakým spôsobom.

Tento návrh je len taký dobrý, ako obchodné jednotky, ktoré ste vybrali:

- Vyberiete **jednu** obchodnú jednotku a návrh je cena jedného kusu.
- Vyberiete **viacero** obchodných jednotiek a aiku zaobchádza s produktom ako s **balíkom (bundle)** všetkých z nich. Navrhovaná cena je cena celého balíka.

Ak ste teda chceli vytvoriť jednu veľkosť odevu a vybrali ste v zozname všetky veľkosti, produkt sa nacení ako balík všetkých veľkostí. Táto cena je mnohonásobne vyššia, než zákazníci očakávajú za jeden kus.

<aside class="tip">Pred uložením vždy skontrolujte zoznam obchodných jednotiek a pole <b>Unit</b>. Ak pole ukazuje <b>bundle</b> a vy ste chceli jeden kus, vybrali ste príliš veľa obchodných jednotiek.</aside>

## Upozornenie 1: cena je vzdialená od rodiny

aiku porovnáva **cenu za jednotku** každého produktu s bežnou cenou za jednotku **ostatných produktov v tej istej rodine**. Ak produkt stojí **trojnásobok bežnej ceny alebo viac**, alebo **tretinu bežnej ceny alebo menej**, aiku zobrazí upozornenie.

- **Pri vytváraní produktu:** pod cenami sa objaví červený rámček s bežnou cenou rodiny. Pri uložení vás aiku požiada o potvrdenie.
- **Na karte Pricing:** cena sa zobrazí červenou farbou s výstražným trojuholníkom. Prejdením myšou nad trojuholníkom uvidíte, ako veľmi sa cena líši.

Upozornenie nič neblokuje. Niektoré rodiny miešajú veľmi odlišné veľkosti a veľká fľaša môže úprimne stáť päťnásobok malej. Berte to ako otázku, na ktorú treba odpovedať: je táto cena naozaj správna? Ak áno, nechajte ju tak. Ak nie, opravte ju.

Kontrola potrebuje aspoň tri ďalšie produkty v rodine, takže v malých rodinách zostáva ticho.

## Upozornenie 2: obchodné jednotky sa zmenili po nastavení ceny

Zmena obchodných jednotiek produktu (jeho zloženia) **nemení jeho cenu**. Ak bol produkt vytvorený ako balík zo 17 obchodných jednotiek a neskôr opravený na jednu, ponechá si cenu za 17 jednotiek, kým ju niekto neupraví.

Od teraz platí, že keď sa zmenia obchodné jednotky hlavného produktu a ceny sa neuložia v tej istej úprave, produkt sa označí na kontrolu. Na karte Pricing sa jeho cena zobrazí červenou farbou s výstražným trojuholníkom a tooltip uvedie, že sa zloženie zmenilo po nastavení ceny.

Označenie zmizne, akonáhle niekto **uloží ceny** daného produktu, či už jednotlivo alebo hromadnou úpravou cien. Opätovné uloženie tej istej ceny označenie tiež zruší, takže môžete potvrdiť, že cena je stále správna.

## Čo skontrolovať

1. Otvorte rodinu a prejdite na kartu **Pricing**.
2. Hľadajte ceny červenou farbou. Prejdením myšou nad trojuholníkom si prečítajte prečo.
3. Pri každej z nich skontrolujte popis obchodných jednotiek vedľa názvu. Zobrazuje každú obchodnú jednotku a jej množstvo.
4. Ak sú obchodné jednotky nesprávne, najprv ich opravte na stránke úpravy produktu a potom nastavte cenu.
5. Upravte cenu pomocou ceruzky a uložte ju. Červené označenie zmizne.

Nezabudnite ani na RRP: bola vypočítaná z tej istej nesprávnej ceny, takže ju skontrolujte v stĺpci RRP.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Ceny rodiny:</b> <b>Masters</b> → otvorte hlavný obchod → <b>Families</b> → otvorte rodinu → karta <b>Pricing</b>.</li>
<li><b>Ceny variantu:</b> otvorte rodinu → variant → karta <b>Pricing</b>.</li>
<li><b>Upraviť jednu cenu:</b> ceruzka vedľa ceny na karte Pricing.</li>
<li><b>Upraviť viac cien:</b> zaškrtnite produkty na karte Pricing a použite hromadnú úpravu cien.</li>
<li><b>Opraviť obchodné jednotky:</b> otvorte hlavný produkt → edit → composition.</li>
</ul>
</aside>

<aside class="permissions"><strong>Oprávnenia, ktoré potrebujete</strong>
<p>Hlavné obchody sú na úrovni skupiny. Na zobrazenie karty Pricing potrebujete prístup k masters na úrovni skupiny a editačný prístup na zmenu cien alebo obchodných jednotiek.</p>
</aside>
