---
title: Tlač SKO a jednotkových štítkov
summary: Vytlačte štítok, ktorý ide na produkt, aj ten, ktorý ide na škatuľu, priamo zo stránky SKO alebo z riadku dodacieho listu, na kotúč, ktorý už kupujete, alebo 27 na hárok A4.
date: 2026-09-23
source_date: 2026-09-23
tags: warehouse, inventory, labels, barcodes, printing
category: warehouse
---

<aside class="tldr">
SKO vie vytlačiť <b>dva rôzne štítky</b>. <b>Jednotkový štítok</b> (unit label) ide na produkt, ktorý napokon drží zákazník, takže nesie pôvod, výrobcu, hmotnosť a adresu vašej firmy a je postavený okolo jednotkového EAN13. <b>SKO štítok</b> (SKO label) ide na vonkajšiu škatuľu, takže hovorí, čo je v škatuli a koľko kusov v nej je, písmom čitateľným z niekoľkých metrov v uličke. Ktorýkoľvek z nich otvoríte zo stránky SKO alebo z riadku dodacieho listu, zaškrtnete, čo na ňom chcete, vyberiete veľkosť štítka a vytlačíte jeden alebo hárok s 27 kusmi.
</aside>

## Dva štítky

Nie sú to tie isté štítky v dvoch veľkostiach. Sú pre dvoch rôznych čitateľov.

| | Jednotkový štítok | SKO štítok |
| --- | --- | --- |
| Ide na | produkt | vonkajšiu škatuľu |
| Číta ho | ten, kto ho napokon drží v ruke | ktokoľvek, kto prejde uličkou |
| Čiarový kód | jednotkový EAN13 | SKO kód, cez celú šírku |
| Môže niesť | obrázok, krajinu pôvodu, výrobcu, hmotnosť, vlastný text, podpis účtu | obrázok, vlastný text |
| Veľkosti | sedem | štyri |

Jednotkový štítok je ten s drobným písmom. Uvádza, odkiaľ tovar pochádza a kto ho vyrobil, udáva hmotnosť a podpisuje organizáciu pod tým, pretože presne na to štítok na produkte slúži. SKO štítok toto všetko vynecháva: kód je biely na čiernom, počet a názov sú pod ním a čiarový kód ide cez celú šírku štítka.

## Tlač jedného štítka

Otvorte SKO a nájdite čiarové kódy. Kliknite na ktorýkoľvek z nich — SKO kód alebo jednotkový EAN13 — a panel štítka sa otvorí na tom kóde. Ak sa dá vytlačiť len jeden z dvoch, panel sa otvorí na ňom; ak sa dajú oba, hore sa objaví malý prepínač **SKO / Unit** a môžete medzi nimi prechádzať bez zatvorenia panela.

Z dodacieho listu je to rýchlejšie. Každý riadok v záložke **Items** má vedľa kódu malú ikonu PDF. Stlačte ju a otvorí sa rovnaký panel pre dané SKO bez toho, aby ste opustili dodací list.

## Výber toho, čo na štítku bude

Každý prvok je zaškrtávacie políčko a predvolene je zaškrtnuté to, čo predvolene tlačila Aurora: všetko, pre čo má SKO hodnotu, okrem podpisu účtu a vlastného textu, ktoré zostávajú vypnuté, kým si ich nevyžiadate.

**Políčko, ktoré sa nedá stlačiť, znamená, že SKO tam nemá čo vytlačiť.** Podržte nad ním myš a povie vám čo — *This item has no image* (nemá obrázok), *This item has no country of origin* (nemá krajinu pôvodu), *This item has no manufacturer* (nemá výrobcu), *This item has no weight* (nemá hmotnosť). To hovoria údaje SKO, nie štítok: doplňte pole na trade unit a políčko ožije. Funguje to tak, aby nikto nevytlačil štítok s dierou.

**Vlastný text** (custom text) je voľný text len pre túto tlač. Neukladá sa k SKO. Použite ho na to, čo sa medzi tlačami mení a nepatrí do žiadneho záznamu.

## Veľkosti a hárky

Veľkosti sú tie isté štítkové materiály, aké ponúkala Aurora, na milimeter presne, takže kotúč kúpený pre starý systém tlačí rovno aj v tomto.

| | Ponúkané veľkosti |
| --- | --- |
| Jednotkový štítok | 63 × 29,6, 63,5 × 29,6, 70 × 29,7, 70 × 30, 125 × 37, 130 × 60, 140 × 90 |
| SKO štítok | 63 × 29,6, 63,5 × 29,6, 70 × 29,7, 130 × 60 |

SKO štítok sa ponúka v štyroch veľkostiach, pretože nesie oveľa menej, a tieto štyri sa hodia na škatuľu.

**Layout** (rozloženie) je buď jeden štítok narezaný na vlastnú veľkosť, alebo **A4 27 labels (EU30161)** — tri vedľa seba a deväť pod sebou na bežnom hárku A4. Hárok je vysekaný na 63,5 × 29,6, takže keď ho zvolíte, výber veľkosti ustúpi a oznámi to. Všetkých 27 je ten istý štítok; hárok slúži na tlač série jedného SKO, nie na miešanú stranu.

Písmo, čiarový kód aj obrázok sa škálujú podľa zvolenej veľkosti štítka namiesto toho, aby boli pevné, takže 140 × 90 zaplní svoj priestor a 63 × 29,6 zostane čitateľný.

## Keď sa štítok nevytlačí

Jednotkový štítok je postavený okolo svojho čiarového kódu, takže SKO bez jednotkového EAN13 ho vytlačiť nedokáže. Panel to oznámi a tlačidlo PDF zošedne. Najprv priraďte SKO kód EAN — jednotkový EAN13 má na stránke SKO vlastný editor — a štítok sa vytlačí.

SKO štítok je zhovievavejší. Vytlačí sa aj pre škatuľu, ktorá ešte nemá pridelený čiarový kód; jednoducho sa vytlačí bez neho. Pozrite [Kontrola SKO čiarových kódov skenerom](/docs/checking-sko-barcodes-with-a-scanner), ako dostať vonkajší kód na správne SKO.

## Odkiaľ pochádza text

Na štítku sa nič nepíše ručne okrem vlastného textu. Každý riadok sa číta zo záznamu, takže opraviť štítok znamená opraviť záznam.

| Na štítku | Pochádza z |
| --- | --- |
| Kód a názov | kód SKO a názov jeho trade unit |
| **6x** pred názvom | koľko kusov je zabalených v škatuli |
| *Imported from … by …* | krajina pôvodu trade unit a vaša organizácia |
| *Manufactured by …* | GPSR výrobca trade unit |
| Hmotnosť | marketingová hmotnosť trade unit |
| Blok s adresou | adresa a telefón vašej organizácie |
| Obrázok | prvá trade unit, ktorá má hlavný obrázok |

Ak SKO obsahuje viac trade units, ktoré sa v niektorom z týchto údajov nezhodujú, riadok sa vynechá namiesto hádania: štítok uvádzajúci jednu krajinu pre škatuľu s tovarom z dvoch by bol nesprávny, nielen neúplný.
