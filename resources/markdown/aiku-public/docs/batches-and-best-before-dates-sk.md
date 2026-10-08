---
title: Dávky a dátumy minimálnej trvanlivosti
summary: Ako aiku vie, ktorá dávka každého produktu je na ktorom regáli a kedy expiruje, čo robí každý tím, aby to sedelo, a kde vidieť, čo čoskoro expiruje.
date: 2026-10-08
source_date: 2026-10-08
tags: warehouse, batches, best-before, goods in, picking, production, reports
category: warehouse
---

<aside class="tldr">
Každá dávka, ktorá príde na regál, nesie svoj kód a dátum minimálnej trvanlivosti a každé vyskladnenie ju zase odoberie, takže aiku vždy vie, ktoré dávky sú na ktorom regáli. Väčšina je automatická. Príjem tovaru zapíše kód a dátum zo štítku pri dodávkach od dodávateľov, výroba zaznamená trvanlivosť toho, čo vyrába, a sklad raz označí zásobu, ktorá už na regáloch bola. Potom <b>Inventory → Batch Codes</b> a export SKO ukazujú, čo čoskoro expiruje.
</aside>

## Ako dávka cestuje

Dávka je kód a dátum minimálnej trvanlivosti pre jedno SKO. Zásoba nesie svoju dávku od príchodu až po odchod:

- **Dodávky od dodávateľov:** príjem zaznamená dávku pri kontrole a naskladnením ide na regál. Pozrite [Vystavenie objednávky a prevzatie tovaru](/docs/raising-a-purchase-order-and-receiving-the-goods-sk).
- **Naša výroba:** hotový pracovný príkaz dostane dávku pri naskladnení, s dátumom zadaným pre dávku alebo vypočítaným z trvanlivosti artefaktu. Pozrite [Ukladanie hotovej výroby](/docs/putting-away-finished-production-sk).
- **Dodávky od partnerskej organizácie:** dávky, ktoré partner vyskladnil, prídu už vyplnené vo vašej dodávke. Pozrite [Nákup od partnera](/docs/buying-from-a-partner-sk).
- **Vyskladňovanie:** každé vyskladnenie vezme dávku s najskorším dátumom na danej lokácii a podľa potreby sa rozdelí medzi dávky. Pozrite [Vyskladnenie a balenie dodacieho listu](/docs/picking-and-packing-a-delivery-note-sk).
- **Vrátený tovar a zrušené vyskladnenia** sa vrátia na regál ako dávky, ktoré boli vyskladnené.

Zásoba bez zaznamenanej dávky sa považuje za najstaršiu na regáli, takže sa použije ako prvá.

## Čo robí každý tím

| Kto | Čo urobiť | Ako často |
|---|---|---|
| Príjem tovaru | Pri **kontrole** stlačte **+ Batch** pod skontrolovaným množstvom a zapíšte kód dávky a dátum zo štítku. Ak riadok prišiel vo viacerých dávkach, rozdeľte ho cez **Add batch**. | Každá dodávka od dodávateľa |
| Príjem, dodávky od partnerov | Potvrďte už vyplnené dávky, opravte ich, ak tovar hovorí inak. | Každá dodávka od partnera |
| Vyskladňovači | Nič, iba ak ste vzali inú dávku, než aká je zobrazená: vtedy ju zmeňte pri vyskladnení. | Keď sa to stane |
| Výroba | Vyplňte trvanlivosť každého artefaktu (alebo celej rodiny naraz). Bez nej vyrobený tovar dostane dávku, ale nie dátum. | Raz, potom pri nových artefaktoch |
| Sklad | Označte zásobu, ktorá už je na regáloch: na stránke **Batch Codes** pri SKO, **Count batches**, spočítajte každú lokáciu podľa toho, čo je vytlačené, a uložte. Začnite potravinami, arómami a kozmetikou. | Raz pre každé SKO |
| Zodpovedný za rodinu skladových položiek | Zapnite **Batch tracked** (stránka úpravy rodiny) tam, kde na dátume záleží. Ich riadky príjmu upozorňujú, kým každé SKO nemá dávku. | Raz pre rodinu |

Nič nikdy neblokuje: tovar bez vytlačeného kódu sa dá prijať aj vyskladniť. Iba sa v prehľadoch zobrazí ako *bez dávky*, a to je signál na opravu.

## Kde vidieť, čo čoskoro expiruje

- **Inventory → Batch Codes** zobrazuje dávky na regáloch, najskorší dátum ako prvý, so zostávajúcimi SKO, počtom lokácií a počtom zostávajúcich dní: oranžovo do 90 dní, červeno po expirácii. Dávka bez dátumu je označená.
- Stránka **Batch Codes** pri SKO ukazuje to isté pre dané SKO a tam ho spočítate dávku po dávke.
- Export SKO (**Inventory → SKOs**, export) pridáva pre každé SKO najskorší dátum na regáloch, už expirované SKO, SKO s expiráciou do 30 a do 90 dní a SKO bez dávky.
- PDF dodacieho listu uvádza pod každou položkou dávku, dátum a množstvo.

Viac o lokáciách a inventúrach: [Skladové oblasti, lokácie a zásoby](/docs/warehouse-areas-locations-and-stock-sk).

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Dávky, ktoré čoskoro expirujú:</b> váš sklad → <b>Inventory → Batch Codes</b>.</li>
<li><b>Zadanie dávok pri príjme:</b> dodávka → <b>Items</b> → <b>+ Batch</b> pod skontrolovaným množstvom.</li>
<li><b>Označenie zásoby, ktorá už je na regáloch:</b> <b>Inventory → SKOs</b> → SKO → <b>Batch Codes</b> → <b>Count batches</b>.</li>
<li><b>Zapnutie sledovania dávok:</b> <b>Goods → Families</b> → rodina → upraviť → <b>Batch tracked</b>.</li>
<li><b>Export dátumov po SKO:</b> <b>Inventory → SKOs</b> → export.</li>
</ul>
</aside>
