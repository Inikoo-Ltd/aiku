---
title: Nastavovanie cieľov pre produkty vo vývoji
summary: Ako aiku krok za krokom sleduje produkty platené ako vývoj, navrhne cieľ na hodinu, keď má krok dosť behov, požiada schvaľovateľov, aby ho prijali, upravili alebo nechali produkt vo vývoji, a vypíše, čo mešká.
date: 2026-11-10
source_date: 2026-11-10
tags: production, targets, development, payroll
category: production
---

<aside class="tldr">
Produkt prepnutý na <b>In development</b> nemá cieľ, takže jeho remeselníci sú platení bez bonusu. Aiku zaznamenáva, ako dlho trvá každý jeho krok, a keď má krok dosť behov (predvolene 5 relácií od 2 remeselníkov a 4 hodiny), navrhne ako cieľ medián na hodinu. Schvaľovatelia dostanú jednu úlohu so zoznamom pripravených produktov a rozhodnú v <b>Factory → Crafts → Development → Paid as development</b>: <b>Save targets</b> (podľa návrhu alebo upravené) alebo <b>Keep on development</b> s dôvodom. Keď má cieľ každý krok platený za kus, produkt sa už neplatí ako vývoj.
</aside>

## Kde to nájdete

Otvorte **Factory → Crafts → Development** a kartu **Paid as development**. Zobrazí každý produkt so zapnutým **In development** a tieto údaje:

- **Status**: **Overdue** (pripravený na ciele a vyrábaný v posledných 4 týždňoch), **Ready, not run lately** (pripravený, ale nevyrábaný v posledných 4 týždňoch, preto sa nepočíta ako meškajúci), **Collecting** (zatiaľ nie dosť behov) alebo **Kept on development** (niekto sa rozhodol počkať; po prejdení myšou uvidíte prečo).
- **On development since**: kedy bolo **In development** naposledy zapnuté.
- **Recipe changed**: kedy sa naposledy pridal alebo odobral krok receptu. Produkty, ktorých recept sa od prvého zaznamenania nezmenil, ukazujú pomlčku.
- **Last run**: posledná relácia odpracovaná na produkte.
- **Steps**: pri každom kroku relácie, remeselníci a hodiny doteraz započítané a sadzba na hodinu, ktorú dávajú. Zatrhnutie znamená, že krok je pripravený na cieľ; kroky s cieľom ho ukazujú, kroky platené za hodinu ho nepotrebujú.

Meškajúce produkty sú na začiatku zoznamu. Meškajú, kým sa niekto nerozhodne.

## Ako sa počíta navrhnutý cieľ

- Každý krok sa počíta samostatne. Relácie na rovnakom kroku (rovnaká úloha, rovnaký štítok, rovnaký počet jednotiek na artefakt) všetkých produktov v rodine sa počítajú spolu, takže nová farba sviečky sa učí od ostatných.
- Počítajú sa len relácie od chvíle, keď recept naposledy získal alebo stratil krok: práca sa zmenila, takže meranie začína odznova.
- Prvá relácia každého remeselníka na kroku sa vynecháva ako zácvičná. Rovnako relácie kratšie ako 10 minút, relácie, ktoré stále čakajú na kontrolu časov po tom, čo ostali bežať po konci zmeny, a zjavné odľahlé hodnoty (oveľa rýchlejšie alebo pomalšie než ostatné).
- Krok je pripravený, keď zvyšok tvorí aspoň 5 relácií od aspoň 2 remeselníkov s celkom aspoň 4 hodinami.
- Navrhnutý cieľ je medián na hodinu z týchto relácií. Vedľa neho vidíte strednú polovicu a celý rozsah, aby ste videli, ako sa remeselníci líšia.

Výrobný manažér alebo účtovník môže tieto čísla zmeniť cez **Settings** na karte: relácie na krok, rôzni remeselníci, hodiny celkom, najkratšia relácia a po koľkých týždňoch bez behov už produkt nemešká.

## Úloha na posúdenie

V **Settings** výber **Who reviews** určuje ľudí, ktorí dostanú úlohu (výrobný manažér a účtovník). Aiku každé ráno skontroluje produkty vo vývoji. Keď sú niektoré pripravené, schvaľovatelia dostanú jednu úlohu, **Set targets for products paid as development**, s riadkom na produkt a navrhnutými sadzbami. Produkty, ktoré budú pripravené neskôr, sa pridajú do tej istej úlohy. Riadok sa zatrhne sám, keď sa o produkte rozhodne alebo keď už nečaká (zložený z vývoja alebo s cieľmi nastavenými ručne), a úloha sa zatvorí, keď je hotový každý riadok.

Ak v **Who reviews** nikto nie je vybraný, úloha sa neodošle; karta na to upozorní.

## Rozhodnutie

Stlačte **Review** pri produkte. Pri každom kroku uvidíte navrhnutý cieľ, započítané relácie a tie, ktoré sa vynechali (aj prečo), a na porovnanie ciele podobných krokov v továrni. Potom:

- **Save targets**: políčko cieľa každého pripraveného kroku sa vyplní navrhnutou sadzbou. Ponechajte ju, aby ste ju prijali, alebo napíšte inú sadzbu, aby ste ju upravili. Kroky, ktoré ešte nie sú pripravené, ostávajú prázdne; aj pre ne môžete napísať cieľ. Ciele platia od dnes, takže už odpracované relácie ostávajú tak, ako boli zaplatené.
- **Keep on development**: napíšte prečo (napríklad prichádza nová forma). Produkt ostáva vo vývoji a vráti sa na posúdenie, keď jeho kroky získajú znova toľko nových relácií.

Keď má cieľ každý krok platený za kus, **In development** sa vypne a produkt sa odvtedy platí podľa svojich cieľov. Ak nejaký krok cieľ ešte nemá, produkt ostáva vo vývoji, kým ho nebude mať.
