---
title: Navrhovanie a tlač etikiet
summary: Zostavte hárok etikiet A4 na artefakte: nahrajte grafiku, nastavte mriežku, vložte kód šarže, dátum expirácie a čiarový kód, ktorý sa dá naskenovať, a potom ho publikujte, aby ho výroba mohla tlačiť.
date: 2026-09-25
source_date: 2026-09-25
tags: production, crafts, labels, printing
category: production
---

<aside class="tldr">
<b>Label</b> (etiketa) patrí k jednému artefaktu a popisuje celý hárok A4: grafiku, na ktorej stojí, koľko etikiet sa zmestí na stránku, a niekoľko textov, ktoré sa menia s každou výrobnou dávkou - kód šarže, dátum expirácie, čiarový kód. Navrhnete ju raz v editore, <b>publish</b>ujete (publikujete) ju, a odtiaľ ju každý, kto vyrába daný artefakt, tlačí priamo z nástenky <b>To produce</b>. Kým nie je publikovaná, je to koncept a vidíte ju iba vy.
</aside>

## Kde etikety žijú

Otvorte artefakt a vyberte záložku <b>Labels</b> (etikety). Všetko o etiketách daného artefaktu sa deje tam: zoznam toho, čo bolo navrhnuté, a editor, v ktorom navrhujete.

Stoja za zmienku ešte dve miesta. <b>Crafts → Labels</b> zobrazuje každú etiketu v továrni s artefaktom, ku ktorému patrí, takže etiketu nájdete, aj keď si pamätáte produkt, ale nie artefakt, pod ktorý spadá. A nástenka crafts má box <b>Labels</b>, ktorý ukazuje, koľko ich existuje a koľko je publikovaných - druhé číslo je to, na ktorom záleží, pretože nepublikovaná etiketa je pre výrobu neviditeľná.

## Koncept, navrhnuté, publikované

Etiketa je v jednom z troch stavov, a odznak na jej riadku hovorí, v ktorom.

| Stav | Čo to znamená |
| --- | --- |
| **Raw** (surové) | Ešte sa jej nikto nedotkol v editore. Zvyčajne etiketa, ktorá prišla z importu. |
| **Processed** (spracované) | Otvorené a upravené. Vaše na prácu, pre výrobu neviditeľné. |
| **Published** (publikované) | Živé. Dá sa vytlačiť z nástenky To produce. |

<b>Raw takmer vždy znamená importované.</b> Väčšina etikiet v aiku sa tu nenavrhovala - boli prevzaté hromadne zo zložiek grafických PDF súborov uložených inde, jeden súbor na etiketu, priradený k artefaktom podľa kódu. Importovaná etiketa dorazí tak, že celá A4 je jedna etiketa, nesúca svoju grafiku a nič viac: žiadna mriežka, žiadny kód šarže, žiadny dátum expirácie, žiadny čiarový kód. To je verný záznam toho, čím starý súbor bol, nie nedokončený návrh.

Takže Raw etiketa je zvyčajne úplne dobrá grafika, ktorá čaká, kým sa niekto rozhodne, či na ňu treba niečo dotlačiť. Otvorte ju, doplňte, čo výroba potrebuje, a uloženie ju presunie do Processed. Aj etiketa, ktorú vytvoríte sami, je Raw, kým ju neotvoríte a neuložíte druhýkrát, takže Raw nečítajte ako "pokazené".

Iba publikovaná etiketa sa dá vytlačiť mimo editora. To je celý zmysel publikovania, a preto tlačidlo <b>Publish</b> nesie malý červený bod, kým ho nestlačíte.

Úprava publikovanej etikety ju <b>ne</b>vráti do konceptu. Uložte zmeny a zostane publikovaná, čo znamená, že zmena je živá hneď po uložení - užitočné, keď opravujete preklep, o čom treba rozmýšľať, keď ju prekresľujete celú. Ak chcete publikovanú etiketu vyradiť z obehu, stlačte <b>Unpublish</b>: vráti sa do Processed a prestane byť tlačiteľná, a nič iné sa na nej nestratí.

## Začatie etikety

<b>New label</b> (nová etiketa) otvorí editor. Najprv jej dajte meno - meno je to, ako ju znova nájdete, a <b>Save</b> (uložiť) zostáva vypnuté, kým meno nemá.

<b>Save as new</b> (uložiť ako novú) uloží otvorený návrh ako samostatnú etiketu, čo je rýchly spôsob vytvoriť variant bez zásahu do originálu.

## Grafika pod ňou

<b>Background artwork</b> (grafika na pozadí) prijíma obrázok alebo PDF, do 8 MB. JPG, PNG, GIF a WebP fungujú všetky.

<b>PDF je lepšia voľba</b>, ak ho máte. Vkladá sa ako vektorová grafika, takže text v ňom zostáva ostrý pri akejkoľvek tlačovej veľkosti a zostáva vybrateľný v hotovom hárku - obrázok sa naťahuje, aby sa vošiel, a nedokáže ani jedno. Ak bol váš PDF uložený v novšom formáte, než aikuina čítačka zvládne, prekonvertuje sa automaticky; dozviete sa o tom iba, ak konverzia zlyhá, a potom je oprava uložiť ho znova ako PDF 1.4.

Grafika sa uchováva pri etikete. Otvorte etiketu o šesť mesiacov a stále sa tlačí proti grafike, s ktorou bola navrhnutá, bez toho, aby ste čokoľvek nahrávali znova.

<b>Canvas rotation</b> (otočenie plátna) otočí grafiku na etikete po štvrťotočkách. Použite to, keď bol súbor nakreslený naboku - otočí obrázok, nie stránku.

## Mriežka

<b>Columns</b> (stĺpce) a <b>Rows</b> (riadky) rozhodujú, koľko etikiet sa zmestí na hárok A4, až 20 na šírku a 30 na výšku. <b>Page margin</b> (okraj stránky) je prázdny okraj okolo celého hárku a <b>Gap</b> (medzera) je priestor medzi etiketami. Pod poľami riadok hovorí, koľko etikiet to znamená a aké veľké je každá v milimetroch, a zmení sa na červený, ak sa mriežka na stránku prestane zmestiť.

<b>Show cutting guides</b> (zobraziť rezacie vodiace čiary) vytlačí okolo každej etikety čiarkovanú čiaru, aby sa dali orezať od seba.

Ak je váš súbor s grafikou <em>už</em> celý hárok etikiet - návrh, ktorý má mriežku nakreslenú v sebe - zaškrtnite <b>Artwork already contains the grid</b> (grafika už obsahuje mriežku). Celá A4 sa stane jednou etiketou, poľa mriežky sa vypnú a texty pokladáte priamo na obrázok. V tomto režime duplikujete každý text a kladiete kópiu na každú etiketu, ktorú obrázok má, pretože aiku ich už za vás neopakuje.

## Texty, ktoré sa menia s každou výrobou

Pod <b>Texts</b> (texty) sú tri tlačidlá, a každé pridá jeden riadok na hárok:

- <b>Batch code</b> (kód šarže) - príde vyplnený ako kód artefaktu a dnešný dátum. Artefakty ešte nemajú svoj vlastný kód šarže, takže toto je rozumná náhrada, ktorú máte upraviť.
- <b>Expiry date</b> (dátum expirácie) - príde ako rok od dnes, na rovnakom základe.
- <b>Barcode</b> (čiarový kód) - príde ako čiarový kód uložený na SKU artefaktu, a ak vonkajší chýba, použije čiarový kód jednotky. Ak SKU nemá vôbec žiadny čiarový kód, dostanete prázdny riadok na vyplnenie.

Každý z nich je len text, ktorý môžete prepísať. Vyberte riadok a môžete zmeniť jeho obsah, veľkosť, farbu, tučnosť a otočenie. Kapátko odoberie farbu z obrazovky, čo je jednoduchý spôsob, ako sa napasovať na farbu už v grafike.

## Čiarové kódy, ktoré sa dajú skutočne naskenovať

Čiarový kód sa tlačí ako skutočné pruhy s číslicami pod nimi, nie ako číslo, a pruhy sú vykreslené vektorovo, takže zostávajú ostré nezávisle od toho, ako sa hárok vytlačí.

<b>Symbology</b> (symbolika) sa vyberie za vás pri pridaní čiarového kódu: trinásť číslic sa stane <b>EAN13</b>, čokoľvek iné <b>CODE 128</b>. Môžete ju zmeniť, a oplatí sa vedieť, prečo sa tie dve líšia. EAN13 je strohý štandard - presne trinásť číslic končiacich správnou kontrolnou číslicou - a aiku odmietne čokoľvek iné, než aby vytlačila pruhy, ktoré sa naskenujú ako iné číslo, než je pod nimi napísané. CODE 128 prijíma aj písmená, a preto vonkajší čiarový kód končiaci písmenom skončí v ňom.

Ak text nemožno vykresliť vo vybranej symbolike, editor to oznámi červeno a hárok sa nevygeneruje, kým sa to neopraví. Žltá poznámka sa zobrazí, keď je čiarový kód užší než 20 mm: stále sa vytlačí, ale ručné skenery pod touto hranicou mávajú problémy, takže ak má etiketa miesto, rozšírte ho.

<b>Digits below</b> (číslice pod kódom) sa dajú vypnúť, ak grafika číslo už tlačí sama.

## Umiestňovanie všetkého

Náhľad zobrazuje celý hárok. Prvá etiketa je zvýraznená obrysom a je jediná, na ktorú ťaháte veci - všetko, čo tam urobíte, sa skopíruje na každú ďalšiu etiketu na hárku, čo je to, čo robí hárok päťdesiatich etikiet stojaci za navrhnutie iba raz.

Text presunieme ťahaním. Malý štvorček na vybranom texte ho zmení veľkosť: veľkosť písma pri texte, veľkosť pruhov pri čiarovom kóde. <b>Snap to</b> (prichytiť k) položí text presne do rohu, hrany alebo stredu bez naťahovania. Zväčšite tlačidlami nad náhľadom, alebo skočte pomocou <b>Whole page</b> (celá stránka) a <b>Edited label</b> (upravovaná etiketa).

<b>Highlight texts</b> (zvýrazniť texty) stlmí grafiku a položí texty na kontrastnú plochu. Ovplyvňuje to iba to, čo tu vidíte - do PDF sa to nedostane - a je to najrýchlejší spôsob nájsť malý riadok tmavého textu na tmavom pozadí.

## Tlač

<b>Download PDF</b> (stiahnuť PDF) vygeneruje hárok tak, ako stojí, uložený alebo nie, takže si môžete priložiť skúšobnú tlač k skutočným etiketám ešte pred tým, ako sa k čomukoľvek zaviažete.

Keď je etiketa publikovaná, výroba ju tlačí z dráhy <b>Preparing</b> (pripravuje sa) nástenky To produce, a hlavička editora nesie aj odkaz <b>Published PDF</b>. Nástenka tlačí vlastný kód šarže a dátum expirácie danej výroby, zadaný pri presune výroby do <b>Preparing</b>. Etiketa, ktorá tlačí kód šarže alebo dátum expirácie, sa z nástenky nedá vytlačiť, kým ich jej výroba nemá, a výrobu nemožno pripraviť bez nich.

## Čo sa oplatí vedieť

- <b>Zoznam vám na prvý pohľad povie veľa.</b> Každý riadok nesie miniatúru grafiky, mriežku a malé ikony pre to, ktoré z kódu šarže, dátumu expirácie a čiarového kódu daná etiketa tlačí. Etiketa bez ikon nič meniace sa netlačí - čo je v poriadku pri jednoduchom obale a varovanie pri čomkoľvek, čo potrebuje číslo šarže.
- <b>Grafika PDF ukazuje svoju skutočnú veľkosť v centimetroch.</b> Ak etiketa na stránke vypadá zle, veľkosť na riadku to zvyčajne vysvetlí: grafika nemá veľkosť, ktorú ste si mysleli.
- <b>Úprava publikovanej etikety je živá po uložení.</b> Nestojí pred ňou žiadna kópia konceptu.
- <b>Vymazanie etikety sa nedá vrátiť.</b> Rozloženie a jeho prepojenie s grafikou zmiznú s ňou. Súbor s grafikou samotný zostáva na artefakte.
- <b>Importovaná etiketa nemá žiadny premenlivý text zámerne.</b> Tlačí presne grafiku, s ktorou prišla. Ak výroba potrebuje na nej číslo šarže, je to rozhodnutie, ktoré niekto urobí v editore, nie niečo, čo import pokazil.
- <b>Kód šarže a dátum expirácie na návrhu sú príklady.</b> Iba náhľady v editore ich zobrazujú: nástenka a agenti vždy tlačia vlastné hodnoty danej výroby. Voľba <b>Save on the label</b> (uložiť na etiketu) pri príprave výroby zmení dátum expirácie, od ktorého začínajú ďalšie výroby.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Navrhnúť etiketu:</b> vaša organizácia → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → otvorte artefakt → záložka <b>Labels</b> → <b>New label</b>.</li>
<li><b>Každá etiketa v továrni:</b> <b>Crafts</b> → <b>Labels</b>, alebo box <b>Labels</b> na nástenke crafts.</li>
<li><b>Iba publikované:</b> počet <b>Published</b> na tomto boxe, alebo odznaky <b>State</b> nad zoznamom.</li>
<li><b>Skúšobná tlač:</b> otvorte etiketu → <b>Download PDF</b>.</li>
<li><b>Tlač naozaj:</b> <b>Operations</b> → <b>To produce</b> → dráha <b>Preparing</b>.</li>
<li><b>Vyradiť z obehu:</b> otvorte ju → <b>Unpublish</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Potrebné oprávnenia</strong>
<ul>
<li>Pracovné pozície sa nastavujú na zázname zamestnanca pod Human Resources a nesú s sebou práva.</li>
<li>Vidieť etikety a stiahnuť hárok: výrobná pozícia pre danú továreň, alebo supervízor organizácie.</li>
<li>Navrhovanie, publikovanie a odpublikovanie: právo <b>research and development</b> danej továrne, alebo supervízor organizácie.</li>
<li>Čiarový kód pochádza z SKU, ktoré sa upravuje v sklade pod <b>Inventory</b>, nie tu.</li>
</ul>
</aside>