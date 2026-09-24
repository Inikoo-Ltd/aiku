---
title: Ako sa nastavujú predajné ceny dropshippingového zákazníka
summary: Odkiaľ pochádza cena v My Products zákazníka, čo naozaj robí cenové pravidlo +100% alebo +200%, prečo zmena pravidla neprepočíta už pridané produkty a ako ich zákazník prepočíta.
date: 2026-09-23
source_date: 2026-09-23
tags: dropshipping, crm, prices, shopify, sales channels
category: crm
help_routes: grp.org.shops.show.crm.customers.show.customer_sales_channels.
---

<aside class="tldr">
Predajná cena dropshippingového zákazníka je <b>naša odporúčaná cena (RRP) plus cenové pravidlo, ktoré si zákazník nastavil na danom predajnom kanáli</b>. Pravidlo sa použije <b>v momente pridania produktu</b> do kanála. Neskoršia zmena pravidla sa už pridaných produktov nedotkne, takže v jednom kanáli môžu byť produkty s rôznou prirážkou. <b>+100% znamená dvojnásobok RRP, +200% trojnásobok.</b> Existujúce produkty si zákazník opraví sám cez <b>Edit Price</b> v My Products.
</aside>

## Odkiaľ pochádza cena

Každý produkt má našu **RRP**, odporúčanú predajnú cenu. Je to východiskový bod pre všetkých dropshippingových zákazníkov a pre všetkých je rovnaká.

Každý predajný kanál zákazníka (obchod Shopify, obchod WooCommerce, manuálny obchod a pod.) môže mať vlastné **cenové pravidlo**:

- **Percento** — percento nad RRP.
- **Pevná suma** — suma peňazí nad RRP.
- **Bez pravidla** — produkt má cenu RRP.

Keď zákazník pridá produkt do kanála, aiku vezme RRP, použije pravidlo kanála a to je cena zobrazená v **My Products**. Tá istá cena sa pošle do jeho obchodu Shopify alebo iného pri nahraní produktu.

Nákupná cena, ktorú nám zákazník platí, sa do výpočtu nezapočítava. Pravidlo sa pripočítava k RRP, nie k nákupnej cene.

## Čo naozaj znamená +100% a +200%

Percento sa **pripočíta** k RRP. Mnohí ho čítajú ako násobok, ale nie je to tak:

| Pravidlo | Cena | Príklad s RRP 16,18 |
|---|---|---|
| bez pravidla alebo +0% | RRP | 16,18 |
| +50% | 1,5 × RRP | 24,27 |
| +100% | 2 × RRP | 32,36 |
| +200% | 3 × RRP | 48,54 |

V porovnaní s nákupnou cenou vyzerá skok ešte väčší: produkt s nákupnou cenou 7,17 a RRP 16,18 sa pri pravidle +200% predáva za 48,54, takmer sedemnásobok nákupnej ceny.

## Zmena pravidla neprepočíta to, čo už v kanáli je

Pravidlo sa použije **v momente pridania produktu**. Ak zákazník pravidlo neskôr zmení:

- produkty pridané **odvtedy** dostanú nové pravidlo;
- produkty, **ktoré už v kanáli sú**, si ponechajú cenu, ktorú dostali.

Zákazník, ktorý pridá produkty, zmení pravidlo a pridá ďalšie, preto uvidí v jednom kanáli rôzne prirážky. Niektoré produkty sú na úrovni RRP, lebo boli pridané skôr, než existovalo akékoľvek pravidlo, iné sú na 2× alebo 3× podľa pravidla platného v ten deň. To je bežná príčina hlásení „prirážka nie je jednotná". Naše RRP sú v poriadku; ceny sú presne také, aké určilo pravidlo v danom čase.

## Ako si zákazník prepočíta ceny

Zákazník to robí sám vo svojom účte:

1. Otvorí **My Products** v predajnom kanáli.
2. Označí produkty na prepočet (označí všetky pre celý kanál).
3. Klikne na **Edit Price**.
4. V časti **Price Mapping** zvolí **± % over live RRP** (alebo pevnú sumu) a zadá prirážku, napríklad **100** pre dvojnásobok RRP.
5. Uloží. Nové ceny sa vypočítajú z dnešnej RRP a pošlú sa do jeho obchodu.

Takto prepočítané produkty sa odvtedy považujú za produkty s vlastnou cenou.

## Keď zákazník hlási „nesprávnu RRP"

1. Otvorte zákazníka, prejdite na jeho predajné kanály a poznačte si cenové pravidlo každého z nich.
2. Porovnajte niekoľko produktov: RRP na produkte a cenu v kanáli. Presný násobok RRP (2×, 3×) znamená pravidlo, nie chybu.
3. Vysvetlite pravidlo a tabuľku vyššie a odkážte ho na **Edit Price** na prepočet.

Ak cena nie je presným násobkom RRP a žiadne pravidlo ju nevysvetľuje, založte tiket s kódmi produktov a názvom kanála.

<aside class="wayfinder">

### Kde kliknúť v aiku

- **Zobraziť kanály zákazníka a jeho produkty** — **CRM → Customers**, otvorte zákazníka a potom **Channels**.
- **Skontrolovať našu RRP** — otvorte produkt v katalógu obchodu.

### Kde klikne zákazník

- **Nastaviť cenové pravidlo** — nastavenia jeho predajného kanála, **Pricing Policy**. Zobrazuje príklad RRP a cenu, ktorá z neho vznikne.
- **Prepočítať už pridané produkty** — **My Products**, označiť produkty, **Edit Price**.

### Potrebné oprávnenia

- Prezeranie zákazníkov a ich kanálov patrí k bežnej práci zákazníckeho servisu v danom obchode. Ceny na kanáloch zákazníka si určuje zákazník; nemeníme ich zaňho.

</aside>
