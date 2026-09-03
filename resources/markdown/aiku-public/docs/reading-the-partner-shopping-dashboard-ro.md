---
title: Citirea dashboard-ului de cumpărături de la parteneri
summary: Ecranul care arată ce trebuie cumpărat de la un partener și cât loc ai să cumperi — trei plăci de capacitate, un donut de acoperire a stocului cu opt găleți, și pipeline-ul de comenzi.
date: 2026-09-02
source_date: 2026-09-02
tags: procurement, intercompany, shopping-list, stock
category: procurement
series: Ordering from partners
order: 2
---

<aside class="tldr">
Acest dashboard e locul de unde pornește orice sesiune de cumpărare. Rândul de sus arată cât loc ai — bani și spațiu de depozit. Mijlocul arată care dintre produsele partenerului trebuie comandate, cele mai grave primele. Jos arată ce e deja pe drum. Nu trebuie să ții minte nimic — ecranul îți spune ce cere atenție. Plasarea comenzii propriu-zise e acoperită în <a href="/docs/buying-from-a-partner-ro">Cumpărarea de la un partener</a>.
</aside>

Deschide-l la **Procurement → Partners → {partner} → Shopping**. Folosește-l în loc să deschizi lista de cumpărături și să încerci să-ți amintești ce lipsea.

## Cele trei plăci de sus: cât loc ai

Aceste plăci sunt limite, nu decor. Există pentru că o listă de cumpărături în care oricine poate arunca orice încetează să mai însemne ceva — un partener care primește o mie de linii nu poate spune care două sunt urgente.

- **Order budget used** (buget de comandă folosit). Valoarea listei tale deschise comparată cu ce livrează efectiv acest partener într-un ciclu de comandă, afișată în moneda propriei tale organizații — fiecare cifră de bani de pe aceste ecrane e convertită pentru tine, deci nu trebuie niciodată să gândești în moneda partenerului. Dacă există suficient istoric de livrări, bugetul e măsurat din livrări reale; dacă nu, e un ciclu de comandă din ce chiar vinzi tu din produsele lor. Nimeni nu tastează acest număr — nici tu, nici managerul tău. Când bara e plină, placa spune **at capacity** (la capacitate maximă).
- **Warehouse space** (spațiu de depozit). Câte locații sunt libere din total, cu o bară împărțită în ce e *în folosință*, ce e *pe drum* pe comenzi de achiziție și livrări deschise, și ce ar ocupa *această listă de cumpărături*. Sub asta, cota echitabilă a partenerului: câte din sloturile libere pot folosi produsele lui complet noi. Locațiile sunt numărate ca sloturi — nu avem date de volum, așa că nu ne prefacem că măsurăm metri cubi.
- **Lead time** (timpul de livrare). Intitulată cu numele partenerului, această placă arată timpul lor măsurat de la **comandă → recepționare**, din câte livrări a fost măsurat (sau o notă că e încă o estimare), la câte comenzi de achiziție întârzie și cu cât, și cât de mare e catalogul lor.

## Acoperirea stocului: donutul și cele opt găleți

Această secțiune acoperă întregul catalog al partenerului, împărțit în opt găleți după cât va mai ține stocul tău propriu. Găletile riscante sunt dimensionate după timpul de livrare măsurat, nu după săptămâni de calendar — asta e ideea.

Se deschide cu un **donut chart** (grafic gogoașă): fiecare produs din catalog, câte o felie pe găleată, cu totalul în mijloc. Treci cu mausul peste o felie ca să vezi numărul și procentul; dă click pe o felie — sau pe un rând din legenda de alături — ca să răsfoiești acea găleată în catalogul partenerului. O privire îți spune dacă ziua de azi e o completare liniștită sau un exercițiu de incendiu: mult roșu înseamnă necazuri, mai ales verde înseamnă că ești bine.

Sub grafic, găletile stau în două grupuri. **Needs ordering** (necesită comandă) ține cele cinci care cer atenția ta:

- **Out of stock** (fără stoc) — nu mai e nimic pe raft.
- **Doomed** (sortit) — mai ai stoc, dar se va termina înainte să poată ajunge o livrare, chiar dacă ai comanda chiar acum.
- **Critical / Danger / Watch** (critic / pericol / atenție) — se va termina în două, trei sau patru timpi de livrare.

**Not for ordering** (nu pentru comandă) ține celelalte trei:

- **Covered** (acoperit) — bine deocamdată.
- **Dead stock** (stoc mort) — nimic nu se vinde, bani stând pe un raft; rândul arată cât valorează.
- **We never stocked** (nu am stocat niciodată) — partenerul îl vinde, dar tu nu l-ai avut niciodată.

Un fel de articol nu apare deloc aici: SKO-urile marcate **On Demand** (la cerere) în inventarul tău propriu. Stocul lor nu e urmărit, așa că "fără stoc" nu ar însemna nimic — dashboard-ul, tabelele de găleți și Auto-fill le sar pe toate.

Fiecare tile (placă) răspunde la o singură întrebare: **câte mai au nevoie de mine?** Numărul "*N* need action" (*N* necesită acțiune) ignoră tot ce e deja pe listă sau deja pe drum, așa că scade pe măsură ce lucrezi prin el. Dedesubt, același număr defalcat pe **rank** (rang) — produsele A primele, D și Z estompate la final. Două produse A fără stoc contează mai mult decât cinci sute de produse D, deci așa e ordinea de lucru.

Trei lucruri poți face dintr-o placă:

- **Click pe număr** ca să deschizi găleata ca tabel: fiecare articol, ordonat, cu stocul lor, stocul tău, când te termini, și o căsuță de cantitate care scrie direct pe lista de cumpărături.
- **Click pe numele găleții sau pe o literă de rang** ca să răsfoiești acele produse în catalogul partenerului.
- **Fill** (completează) ca să deschizi Auto-fill deja limitat la acea găleată și deja generat — doar ajustezi și confirmi. Puțin mai multă muncă decât un buton magic, dar mult mai mult control. Numerele de pe placă — *N on the way · N on list* (*N* pe drum · *N* pe listă) — arată cât din găleată ai gestionat deja.

Pe **Covered** și **Dead stock**, apare un avertisment roșu în loc, când ceva din acea găleată se află pe lista ta de cumpărături: e stoc de care nu ai nevoie. **remove** (elimină) șterge acele linii dintr-un click.

## Pipeline-ul de comenzi

Banda de jos urmărește totul de la nevoie la raft: **on shopping list → being prepared → ready to ship → in transit → arrived, booking in** (pe lista de cumpărături → în pregătire → gata de expediat → în tranzit → sosit, în recepționare). Fiecare coloană arată livrările ei și câte articole conțin; fiecare card deschide livrarea, doar pentru citire — depozitul vânzătorului o deține până marfa ajunge la tine.

Cardurile îmbătrânesc vizibil. Peste de trei ori timpul de livrare devin chihlimbarii; peste de zece ori, roșii. Un card vechi e o întrebare de pus partenerului, nu un număr de privit fix. Orice e cu adevărat întârziat apare și în lista **Late from this partner** (întârziate de la acest partener) de dedesubt, cea mai mare întârziere prima, cu "no delivery date given" (nicio dată de livrare dată) marcat.

## De ce ecranul uneori spune nu

Adăugarea pe listă poate fi refuzată. E intenționat, și există doar trei motive:

- **La plafonul de buget** — elimină sau depriorizează ceva mai întâi. O urgență reală încape întotdeauna: **articolele de rang A și cele fără stoc sunt scutite**, așa că o urgență nu așteaptă niciodată în spatele unui plafon.
- **Podeaua depozitului** — cu sub 5% din locații libere, niciun produs *nou* de la nimeni nu se mai adaugă. Articolele pe care le stochezi deja își reumplu propriile sloturi și trec liber.
- **Cota echitabilă a acestui partener** — un partener poate revendica circa o cincime din sloturile libere cu produse niciodată stocate. Și ceilalți furnizori au nevoie de loc.

Aceeași regulă se aplică peste tot unde adaugi — manual, în bloc, sau din Auto-fill — așa că o propunere nu conține niciodată linii pe care nu le poți confirma.

## Măsurat, sau etichetat cinstit ca o estimare

Două numere conduc mare parte din acest ecran: timpul de livrare și bugetul. Regula e aceeași pentru amândouă. **Dacă avem istoricul, numărul e măsurat și nu poate fi editat.** Dacă nu-l avem, placa spune asta, iar estimarea poate fi editată — dar în setări, niciodată direct pe dashboard: un **estimated delivery time** (timp de livrare estimat) per produs, pe produsul de furnizor sau pe propriile setări ale SKO-ului. Odată ce există suficiente livrări reale, măsurătoarea preia controlul și câmpul de estimare dispare. Nimeni nu poate suprascrie ce s-a întâmplat efectiv.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Dashboard-ul:</b> organizația ta → <b>Procurement → Partners</b> → deschide partenerul → <b>Shopping</b>.</li>
<li><b>Sari din donut:</b> click pe o felie, sau pe un rând din legendă, ca să răsfoiești acea găleată în catalog.</li>
<li><b>Lucrează o găleată:</b> click pe numărul de pe placă pentru tabelul de articole, o literă de rang ca să răsfoiești acele produse, sau <b>Fill</b> pentru o propunere Auto-fill limitată.</li>
<li><b>Curăță lista:</b> <b>remove</b> pe placa Covered sau Dead stock.</li>
<li><b>Corectează o estimare de timp de livrare:</b> setările SKO-ului, sau setările produsului de furnizor — doar cât timp încă spune <i>estimate</i>.</li>
</ul>
</aside>
