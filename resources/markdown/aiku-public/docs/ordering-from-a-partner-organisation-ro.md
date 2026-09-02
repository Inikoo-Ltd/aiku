---
title: Comenzi de la o organizație parteneră
summary: De ce comerțul între organizații surori folosește o listă de cumpărături în loc de comenzi de achiziție, și cum funcționează întreaga buclă, de la nevoia listată până la stocul recepționat.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, warehouse, intercompany
category: procurement
series: Ordering from partners
order: 1
---

<aside class="tldr">
Când cumperi de la o organizație soră nu emiți o comandă de achiziție. Adaugi ce ai nevoie pe o listă de cumpărături; organizația vânzătoare o alege când poate expedia. De acolo totul curge singur: depozitul lor face picking și ambalarea, iar pe partea ta apare o livrare de stoc care intră, gata de recepționat când marfa ajunge. Dacă <em>plasezi</em> aceste comenzi, începe cu <a href="/docs/reading-the-partner-shopping-dashboard-ro">dashboard-ul de cumpărături</a> și citește <a href="/docs/buying-from-a-partner-ro">Cumpărarea de la un partener</a>; dacă <em>onorezi</em> aceste comenzi, citește <a href="/docs/fulfilling-partner-orders-ro">Lucrul cu comenzile partenerilor</a>.
</aside>

<figure><img src="/art/docs/draw-partner-shopping.svg" alt="Watercolor sketch: the buyer's shopping list card (Procurement › Partners › Shopping list, with Auto-fill) and the seller's shipping list card with ticked lines and a Send to warehouse button, a dashed arrow between them, and a truck carrying the goods to a box labelled as the incoming stock delivery" width="1200" height="750" loading="eager"><figcaption>Tu scrii lista, ei fac picking și expediază, iar pe partea ta intră o livrare de stoc.</figcaption></figure>

## De ce nu există comandă de achiziție

O comandă de achiziție are sens cu un furnizor din afară: te angajezi la cantități, ei confirmă, iar ambele părți urmăresc același document. Între organizațiile noastre, acest ceremonial stă în cale. Vânzătorul își cunoaște propriul stoc mai bine decât cumpărătorul, iar a-l obliga pe cumpărător să ghicească ce se poate expedia duce la comenzi amendate la nesfârșit.

Așa că fluxul e întors. **Cumpărătorul spune ce are nevoie**, **vânzătorul decide ce expediază și când**. Nimeni nu amendează comanda nimănui, pentru că nu există o comandă de amendat — doar o listă de nevoi deschise și un flux de expedieri împotriva ei.

## Bucla, de la un capăt la altul

1. Cumpărătorul deschide [dashboard-ul de cumpărături](/docs/reading-the-partner-shopping-dashboard-ro) ca să vadă ce se termină și cât loc mai are, apoi [adaugă ce are nevoie pe lista de cumpărături](/docs/buying-from-a-partner-ro) — manual, din catalogul partenerului, sau cu o propunere de auto-completare.
2. Vânzătorul [alege liniile pe care le poate expedia și trimite expedierea către depozitul lui](/docs/fulfilling-partner-orders-ro). Se face picking, se ambalează și se expediază ca orice altă comandă.
3. În momentul în care expedierea intră în depozitul vânzătorului, pe partea cumpărătorului apare o **livrare de stoc** care intră. Aceasta urmărește singură progresul vânzătorului — vânzătorul e sursa de adevăr până marfa ajunge.
4. Când marfa ajunge fizic, cumpărătorul o recepționează, o verifică și o plasează în locații exact ca orice livrare de la furnizor.

## Lista are un plafon, intenționat

Lista cumpărătorului nu e o cutie a dorințelor. E limitată la aproximativ un ciclu de comandă din ce partenerul chiar ne livrează, iar produsele noi sunt limitate de spațiul liber din depozit și de o cotă echitabilă din el pe partener. O listă pe care nimeni nu o poate inunda este o listă pe care vânzătorul o poate citi: când totul e pe listă, nimic nu e urgent. Articolele fără stoc și cele de rang A sunt scutite de plafon, așa că o criză reală nu așteaptă niciodată la coadă în spatele limitei.

## Bani, facturi și probleme

Nu există facturi separate de furnizor între organizații. Factura vânzătorului pentru expediere **este** documentul, iar livrarea de stoc care intră este legată de ea. Dacă ceva ajunge lipsă, deteriorat sau greșit, rezolvi asta *după* ce ai recepționat livrarea — acela e momentul în care responsabilitatea trece pe partea ta — iar orice rambursare sau credit se gestionează contra facturii legate.

## Lucruri bune de știut

- Prima dată când un vânzător face picking pentru un partener, în magazinul vânzătorului se creează un cont de client numit după organizația cumpărătoare. E normal — așa călătorește expedierea prin mecanismul obișnuit al vânzătorului.
- Picking-urile parțiale sunt normale. O linie preluată parțial lasă restul deschis pentru o expediere ulterioară; nimic nu se pierde.
- Prețurile sunt prețurile curente din magazinul vânzătorului cu discountul standard intercompany al organizației cumpărătoare aplicat, afișate în moneda proprie a cumpărătorului. Nimic nu se negociază linie cu linie; dacă înțelegerea se schimbă, se va anunța.

<aside class="wayfinder"><strong>Permisiuni de care ai nevoie</strong>
<ul>
<li><b>Vezi listele de cumpărături și de expediere:</b> acces procurement <i>view</i> (vizualizare) în organizația ta.</li>
<li><b>Adaugi linii, alegi ce expediezi, trimiți la depozit:</b> acces procurement <i>edit</i> (editare) în organizația care face acțiunea (a cumpărătorului pentru listă, a vânzătorului pentru picking și expediere).</li>
<li><b>Recepționezi marfa ajunsă:</b> acces la stocul depozitului în depozitul cumpărătorului, la fel ca la orice livrare de furnizor.</li>
<li>Îți lipsește ceva din astea? Cere administratorului tău să acorde rolul în <b>Sysadmin → Users</b> — permisiunile sunt pe organizație, așa că a le avea într-una nu se transferă în organizația parteneră.</li>
</ul>
</aside>
