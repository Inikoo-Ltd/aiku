---
title: Vybavenie položiek, ktoré sklad nemohol vychystať
summary: Keď skladník nenájde položku, objednávka čaká na zákaznícky servis. Rozhodnite o každom riadku v Waiting for CRM a zistite, čo sa vráti na zostatok zákazníka.
date: 2026-09-29
source_date: 2026-09-29
tags: orders, picking, out of stock, balance, customer service
category: crm
help_routes: grp.org.shops.show.ordering.backlog.waiting_items
---

<aside class="tldr">
Keď skladník položku nenájde, sám ju neoznačí ako nedostupnú: pošle riadok na zákaznícky servis s poznámkou a objednávka sa zobrazí ako <b>Waiting</b> (čaká). Otvorte <b>Orders backlog</b> (front objednávok), kliknite na červené číslo na políčku <b>Waiting</b> a o každom riadku rozhodnite v <b>Waiting for CRM</b>: <b>Don't pick</b> (nevychystať), <b>Replace</b> (nahradiť) alebo <b>Send back to waiting warehouse</b> (vrátiť späť skladu). Až keď je riadok označený ako <b>Don't pick</b>, stránka objednávky zobrazí jeho hodnotu pod <b>Marked out of stock so far</b> a to, čo sa má vrátiť na zostatok zákazníka.
</aside>

## Čo sa deje na sklade

Skladník príde na miesto a položka tam nie je, alebo jej nie je dosť. Namiesto hádania pošle riadok na zákaznícky servis a zvyčajne pridá poznámku, napríklad "Out of stock" (nie je na sklade) alebo "Product on this location?" (je produkt na tomto mieste?).

Od tej chvíle:

- Dodacia listina aj objednávka sa zobrazujú ako <b>Waiting</b> (môžete to vidieť aj ako handling blocked, vychystávanie zablokované).
- Na stránke objednávky krátky riadok ukazuje, koľko bolo vychystaných oproti tomu, koľko bolo objednaných, napríklad <b>2</b> preškrtnuté a <b>0</b>.
- Riadok **ešte nie je** označený ako nedostupný. Nič sa nerozhodlo, takže sa nezobrazuje žiadna suma na vrátenie zákazníkovi.

Produkt môže stále ukazovať napríklad <b>Stock: 16 available</b> (skladom: 16 dostupných). To je číslo z evidencie. Skladník bol pri regáli, takže platí jeho hlásenie; evidencia sa opraví, keď sa miesto skontroluje.

## Kde nájsť riadky na rozhodnutie

1. Choďte do svojho obchodu → <b>Orders</b> (objednávky) → <b>Backlog</b> (front).
2. Na políčku <b>Waiting</b> kliknite na malé červené číslo. Počíta objednávky s položkami, ktoré čakajú na vás.
3. Stránka <b>Waiting for CRM</b> zobrazuje po jednom riadku pre každú dodaciu listinu, s odkazom na jej objednávku.

Každý riadok zobrazuje SKO, koľko kusov čaká, produkt s cenou bez a s DPH a poznámku skladníka. Popisok <b>Still on picking</b> (ešte sa vychystáva) znamená, že skladník ešte pracuje na zvyšku danej dodacej listiny.

## Rozhodovanie o každom riadku

<b>Don't pick</b> (nevychystať, červené tlačidlo s lebkou). Položka sa neodošle a zákazníkovi sa za ňu neúčtuje. Použite ho, keď položka naozaj nie je k dispozícii. Keď na dodacej listine nič iné nečaká, sklad môže s objednávkou hneď pokračovať.

<b>Replace</b> (nahradiť). Pošlite namiesto nej iný produkt, napríklad tú istú položku v inej farbe. Časť balenia môžete nahradiť prepínačom rozbitého skla pod <b>Quantity to replace</b> (množstvo na výmenu); pozrite [Zmena časti SKO na objednávke](/docs/changing-part-of-an-sko-on-an-order-sk). Čo nenahradíte, ostáva čakať na CRM.

<b>Send back to waiting warehouse</b> (vrátiť späť skladu). Vráťte riadok skladu s poznámkou, keď si myslíte, že položka by tam mala byť: iné miesto, práve prišla dodávka, alebo nech sa skladník pozrie ešte raz.

Ak by sa to mal zákazník dozvedieť, kontaktujte ho pred rozhodnutím, aby rozhodnutie zodpovedalo tomu, čo chce.

### Keď je riadok súčasťou súpravy

Produkt zložený z viacerých dielov, napríklad soľná lampa so žiarovkou a káblom, sa vracia podľa hodnoty chýbajúceho dielu: chýbajúca lampa vráti hodnotu lampy, nie tretinu produktu.

Ak má produkt zapnuté **Sold only as a complete set** (predáva sa len ako celá súprava) na stránke **Composition** (zloženie) svojho hlavného produktu, alebo na vlastnej, ak nenasleduje diely hlavného produktu, ostatné diely sa nikdy neodošlú samostatne. Po <b>Don't pick</b> na jednom diele zostáva objednávka <b>Waiting</b>, kým sklad nevráti ostatné diely na miesto a nestlačí <b>Parts put back</b> (diely vrátené na miesto) na dodacom liste. Zákazníkovi sa potom vráti hodnota celého produktu.

## Čo zobrazuje stránka objednávky

Na stránke objednávky sa v platobnom rámčeku zobrazia dva ďalšie riadky, hneď ako je aspoň jeden riadok označený <b>Don't pick</b>, kým je objednávka ešte na sklade:

- <b>Marked out of stock so far</b> (zatiaľ označené ako nedostupné): hodnota s DPH všetkého, čo je na danej objednávke označené ako nevychystané.
- <b>Expected back to balance when picking finishes</b> (očakávané vrátenie na zostatok po dokončení vychystávania): koľko z toho, čo zákazník už zaplatil, sa vráti na jeho zostatok.

Tieto riadky sa **nezobrazujú**, kým je riadok stále v <b>Waiting for CRM</b>. Ak sa pozeráte na objednávku, ktorá je <b>Waiting</b>, a suma tam nie je, skontrolujte najprv <b>Waiting for CRM</b>: riadok čaká na vaše rozhodnutie.

Celková suma objednávky ostáva pri zadanej hodnote, kým sa vychystávanie nedokončí. Potom sa vystaví faktúra za to, čo sa naozaj odoslalo, a na už zaplatenej objednávke sa suma za neodoslané položky sama vráti na zostatok zákazníka.

## Pred založením ticketu

Ak sa na čakajúcej objednávke zdá, že suma chýba alebo nesedí, skontrolujte najprv, či riadok stále nie je v <b>Waiting for CRM</b>. Opýtajte sa kolegu alebo manažéra, prípadne si prehľadajte tieto príručky. Ticket založte, keď je niečo skutočne pokazené.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Nájsť riadky čakajúce na vás:</b> váš obchod → <b>Orders</b> → <b>Backlog</b> → červené číslo na políčku <b>Waiting</b> → <b>Waiting for CRM</b>.</li>
<li><b>Položka naozaj nie je k dispozícii:</b> <b>Don't pick</b> na riadku.</li>
<li><b>Poslať niečo iné:</b> <b>Replace</b> na riadku → vyberte produkt a množstvo → <b>Save</b>.</li>
<li><b>Nech sa sklad pozrie ešte raz:</b> <b>Send back to waiting warehouse</b> → pridajte poznámku → <b>Confirm</b>.</li>
<li><b>Zobraziť hodnotu nedostupného tovaru:</b> otvorte objednávku → platobný rámček → <b>Marked out of stock so far</b>.</li>
</ul>
</aside>
</content>
