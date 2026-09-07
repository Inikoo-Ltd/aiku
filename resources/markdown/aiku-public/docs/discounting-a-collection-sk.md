---
title: Zľava na kolekciu
summary: Ako spustiť percentuálnu zľavu na každý produkt v jednej kolekcii, v jednom alebo viacerých obchodoch, a ako v košíku overiť, že ju dostanú len tie produkty.
date: 2026-09-02
source_date: 2026-09-02
tags: discounts, offers, collections
category: shop
series: Collections and collection offers
order: 3
---

<aside class="tldr">
<b>Shop Offer</b> (ponuka obchodu) bežne zľavuje každý produkt v košíku. Ak pri jej vytváraní vyberiete kolekciu, zľavuje len produkty v tejto kolekcii, v ľubovoľnom množstve, na dátumy, ktoré nastavíte. Je to nástroj na "20 % zľava na všetko vyrobené v jednej krajine" alebo "15 % zľava na letnú policu". O tom, kto zľavu dostane, rozhoduje kolekcia, preto ju postavte najprv, viď <a href="/docs/shop-collections-sk">Kolekcie obchodu</a>. Pre policu zdieľanú viacerými obchodmi ju postavte raz ako hlavnú kolekciu, viď <a href="/docs/master-collections-sk">Hlavné kolekcie</a>, potom vytvorte jednu ponuku pre každý obchod.
</aside>

## Vytvorenie ponuky

Otvorte obchod, choďte na **Offers → Campaigns**, otvorte **Shop offers** a stlačte **Create Shop Offer** (vytvoriť ponuku obchodu). Vyplňte:

- **Offer name** (názov ponuky), čo zákazník vidí na riadku košíka, preto ho píšte pre neho.
- **Select offer type** (vyberte typ ponuky): nechajte **All Orders** (všetky objednávky). **By minimum amount** (podľa minimálnej sumy) spôsobí, že zľava počká, kým košík dosiahne určitú sumu, čo pri propagácii kolekcie zvyčajne nechcete.
- **Only products in collection** (len produkty v kolekcii): napíšte časť názvu alebo kódu kolekcie a vyberte ju. Ak necháte prázdne, ponuka zľavuje celý košík.
- **Discount** (zľava), percento.
- **Offer Duration** (trvanie ponuky): **Permanent** (trvalá), alebo **Interval** so začiatkom a koncom. Tlačidlá **1 day** až **7 day** vám dátumy vyplnia.

Uložte, dostanete sa na stránku ponuky, už **Active**, ak je dátum začiatku dnešný. Kód ponuky je zložený z kódu kampane, kódu obchodu a kódu kolekcie, takže ponuku na kolekciu ľahko spoznáte v zozname.

Zopakujte v každom obchode, ktorý propagáciu beží. Ponuky patria obchodu, takže propagácia pre celú skupinu je jedna ponuka na obchod, každá ukazujúca na kópiu tej istej hlavnej kolekcie v danom obchode.

## Čo sa deje v košíku

Zakaždým, keď sa košík zmení, aiku ho preceňuje. Pri zľave na kolekciu si v danom momente prečíta zoznam produktov kolekcie a zľavu uplatní na riadky, ktorých produkt je v nej. Produkty pridané do kolekcie po spustení ponuky dostávajú zľavu odvtedy, produkty odstránené ju strácajú.

Jedna zľava na riadok. Produkt, ktorý už má väčšiu zľavu, z ponuky na rodinu alebo z vlastnej úrovne odmien zákazníka, si ponechá tú väčšiu. Zľava na kolekciu sa nikdy nepridáva navrch a nikdy neuberie lepšiu cenu. Zákazník na úrovni odmien 25 % teda na produkte z kolekcie uvidí 25 %, nie 45 % a nie 20 %.

Záložka **Orders** (objednávky) na stránke ponuky vypisuje každú objednávku, ktorá ju použila, a tržby ponuky vychádzajú z týchto riadkov. To je číslo, ktoré uvediete, keď propagácia sľubuje odviesť podiel z tržieb.

## Overte pred oznámením

Použite testovacieho zákazníka bez úrovne odmien.

1. Pridajte produkt z kolekcie. Riadok ukazuje percento a názov ponuky.
2. Pridajte produkt, ktorý nie je v kolekcii. Na tomto riadku žiadna zľava.
3. Zvýšte množstvo na riadku s produktom z kolekcie. Rovnaké percento, nič naviac.
4. Prihláste sa ako zákazník s vyššou úrovňou odmien, než je ponuka. Riadok si ponechá úroveň odmien, nie obe naraz.
5. Pozrite sa na stránku produktu na webe a na košík: produkt z kolekcie ukazuje zľavnenú cenu, ten druhý nie.

Ak produkt dostáva zľavu a nemal by, oprava je v kolekcii, nie v ponuke: odstráňte ho z kolekcie, alebo odstráňte rodinu, ktorá ho priniesla, a pridajte požadované produkty priamo.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Vytvoriť ponuku:</b> váš obchod → <b>Offers → Campaigns</b> → <b>Shop offers</b> → <b>Create Shop Offer</b> → vyplňte <b>Only products in collection</b>.</li>
<li><b>Zobraziť ponuku:</b> stránka ponuky sa otvorí po uložení; neskôr obchod → <b>Offers → Offers</b> a vyhľadajte podľa názvu.</li>
<li><b>Objednávky, ktoré ju použili:</b> stránka ponuky → záložka <b>Orders</b>.</li>
<li><b>Predčasne ukončiť:</b> stránka ponuky → upraviť → zmeniť dátum konca.</li>
<li><b>Opraviť, kto ju dostáva:</b> obchod → <b>Catalogue → Collections</b> → otvorte kolekciu → záložka <b>Products</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Oprávnenia, ktoré potrebujete</strong>
<p>Editačný prístup k zľavám daného obchodu na vytvorenie alebo zmenu ponuky, a editačný prístup k Products pre daný obchod na zmenu kolekcie, na ktorú ukazuje.</p>
</aside>
