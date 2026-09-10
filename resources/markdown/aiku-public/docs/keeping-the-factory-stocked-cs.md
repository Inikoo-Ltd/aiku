---
title: Udržování zásob v továrně
summary: Stránka To restock - které artefakty dojdou první, jak dlouho továrně trvá cokoli vyrobit, a jak dostat vlastní doplňovací práci na nástěnku To produce.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, planning
category: production
series: Ordering from partners
order: 11
---

<aside class="tldr">
Pro osobu, která plánuje týden továrny. <a href="/docs/fulfilling-partner-orders-cs">To produce</a> (K výrobě) odpovídá na otázku <i>co si někdo vyžádal</i>. <b>To restock</b> (K doplnění) odpovídá na tu druhou: <i>co nám dojde, ať už si o to někdo řekl nebo ne</i>. Seřadí všechno, co továrna vyrábí, podle toho, kolik dní pokrytí zbývá, měřeno vůči tomu, jak dlouho této továrně skutečně trvá něco vyrobit, a umožní vám poslat to, co stojí za výrobu, na nástěnku To produce.
</aside>

## Dodací lhůta je měřítko

Každý koš na této stránce se měří v **dodacích lhůtách**, ne ve dnech. Dodací lhůta je průměrný počet dní od zahájení práce v dílně do jejího návratu do skladu, spočítaný z vlastních výrobních příkazů této továrny za poslední rok. Pokud je hotových výrobních příkazů méně než pět, není co měřit, a aiku místo toho použije odhad — sedm dní, pokud někdo na továrně nenastaví jiné číslo — a u čísla napíše *odhad*.

Proto koše čtou takto, jak čtou. Artefakt se čtyřmi dny pokrytí není v ohrožení v továrně, která práci otočí za dva dny; v té, které to trvá týden, je už ztracený.

## Koše

| Koš | Co znamená |
| --- | --- |
| Out of stock (Vyprodáno) | na regálu nic není |
| Doomed (Odsouzeno) | dojde dřív, než by mohlo dorazit cokoli zahájené dnes |
| Critical (Kritické) | dojde do dvou dodacích lhůt |
| Danger (Nebezpečí) | dojde do tří dodacích lhůt |
| Watch (Sledovat) | dojde do čtyř dodacích lhůt |
| Covered (Pokryto) | pokrytí na víc než čtyři dodací lhůty |
| Dead stock (Mrtvý sklad) | hodnota na regálu a žádné použití |
| Never made yet (Nikdy nevyrobeno) | artefakt bez záznamu o skladu |

Každý koš nese tři čísla: kolik artefaktů je v něm, kolik je **already in hand** (už se s nimi pracuje) a kolik je **untouched** (nedotčených). Already in hand znamená, že se tím už někdo zabývá — otevřený řádek na nástěnce To produce, nebo výrobní příkaz v dílně. Untouched je počet, na kterém je třeba zapracovat.

Kliknutím na koše zvolíte, co ukazuje seznam níže. Otevírá se na **Out of stock, Doomed a Critical**, což je upřímný ranní seznam.

## Pruhy

Pod koši je stejná práce rozložená do čtyř pruhů:

- **To do** (K vyřízení) — artefakty v koších, které jste zvolili, se kterými se ještě nic neudělalo. Naléhavé první. Každý řádek nese kód skladu, co je na regálu, dny pokrytí, rodinu artefaktu, kdo ho obvykle vyrábí, a počet **units** (kusů), pro které by se výrobní příkaz vystavil: doporučené objednací množství přepočtené na kusy a zaokrouhlené nahoru na celou dávku. Cokoli, co si partner už vyžádal, je vynecháno — to je práce stránky To produce, ne této.
- **Queued** (Ve frontě) — řádky, které už čekají na nástěnce To produce, ať přišly od partnera nebo odsud.
- **Producing** (Vyrábí se) — řádky s výrobním příkazem v dílně, s jeho číslem a řemeslníkem.
- **Restocked** (Doplněno) — co se za poslední dva týdny vrátilo z dílny, abyste viděli, že stránka funguje.

## Posílání práce na nástěnku

Zaškrtněte řádky v **To do** a stiskněte tlačítko pro zařazení do fronty. Každý se stane řádkem na nástěnce To produce bez partnera a bez zákazníka za sebou: prostě práce, kterou továrna dluží sama sobě. Odtud se plánuje, přiřazuje a vyrábí přesně jako partnerský řádek, a z nástěnky zmizí, jakmile se hotové zboží uloží na místo.

Řádek se přeskočí, a stránka to řekne, pokud je stejný sklad na nástěnce už otevřený. Stejnou věc nelze zařadit do fronty dvakrát stisknutím tlačítka dvakrát.

## Co je dobré vědět

- **Položky On Demand tu nejsou.** Artefakt, jehož SKO je označené *On Demand* (Na vyžádání), se vyrábí, až je o něj požádáno, a nemá žádné pokrytí, které by mu mohlo dojít.
- **Mrtvý sklad je otázka, ne úkol.** Hodnota, která leží bez použití, si obvykle žádá rozhovor s obchodem, ne výrobní příkaz.
- **Stránka se počítá vždy znovu.** Nic se neukládá, nic se nemusí uklízet, a koš se sám vyprázdní, jakmile zboží dorazí.
- **Dny pokrytí vycházejí ze stejné prognózy**, kterou používá zbytek aiku, viz [Jak aiku předpovídá, co vám dojde](/docs/how-aiku-predicts-what-you-run-out-of).

<aside class="wayfinder"><strong>Kam kliknout v aiku</strong>
<ul>
<li><b>Stránka:</b> vaše organizace → <b>Factory</b> (Továrna) → <b>To restock</b> (K doplnění).</li>
<li><b>Změnit, co pruhy ukazují:</b> klikněte na dlaždice košů nahoře.</li>
<li><b>Zařadit vlastní práci do fronty:</b> zaškrtněte řádky v <b>To do</b> → tlačítko fronty → objeví se na <b>To produce</b>.</li>
<li><b>Nastavit odhadovanou dodací lhůtu</b>, dokud je historie tenká: ve vlastním nastavení továrny; jakmile je hotovo pět výrobních příkazů, naměřené číslo převezme vládu samo.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li>Pozice se nastavují na kartě zaměstnance v sekci Human Resources a nesou s sebou příslušná práva.</li>
<li>Zobrazení stránky: pozice <b>Production operative</b> (dělník) pro danou továrnu, nebo výše.</li>
<li>Zařazování práce do To produce: pozice <b>Production floor supervisor</b> (vedoucí výroby) pro danou továrnu, nebo supervizor organizace.</li>
</ul>
</aside>
