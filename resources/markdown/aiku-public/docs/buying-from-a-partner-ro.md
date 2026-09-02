---
title: Cumpărarea de la un partener
summary: Ghidul cumpărătorului - pornește de la dashboard-ul de cumpărături, completează lista manual, din catalogul partenerului sau cu auto-completare, și recepționează marfa la sosire.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, intercompany, shopping-list
category: procurement
series: Ordering from partners
order: 3
---

<aside class="tldr">
Pentru persoanele care <em>plasează</em> comenzile către parteneri. Ții o singură listă deschisă cu ce are nevoie organizația ta; partenerul expediază contra ei în ritmul lui. Pornește de la <a href="/docs/reading-the-partner-shopping-dashboard-ro">dashboard-ul de cumpărături</a> ca să vezi ce e în risc și cât loc ai, apoi adaugă linii manual, din catalogul lor, sau lasă auto-completarea să propună o reaprovizionare încadrată într-un buget. Ești nou în acest flux? Începe cu <a href="/docs/ordering-from-a-partner-organisation-ro">prezentarea generală</a>.
</aside>

## Pornește de la dashboard

**Procurement → Partners → {partner} → Shopping** deschide [dashboard-ul de cumpărături](/docs/reading-the-partner-shopping-dashboard-ro): ce urmează să se termine, ce e deja pe drum, și cele două limite în care trăiește lista ta — **bugetul de comandă** pentru acest partener și **spațiul de depozit** disponibil. Lucrează plăcile de risc de acolo și lista aproape se scrie singură; tot ce urmează e cum se comportă lista odată ce ești în ea.

## Lista de cumpărături

Lângă dashboard, tab-ul **Shopping list** (lista de cumpărături) ține fiecare linie deschisă.

- **Add stocks** (adaugă stocuri) deschide lista de stocuri a partenerului cu disponibilitatea lor, cum e ambalat fiecare articol, stocul tău propriu curent, și cât ai folosit în ultimele patru trimestre. Cantitățile sunt în unitățile de expediere ale vânzătorului (SKO-uri).
- Fiecare linie spune povestea stocului dintr-o privire — *stocul lor*, *stocul nostru* și când *ne terminăm* — plus suma la prețul tău de cumpărare, cu totalul articolelor deschise la baza tabelului.
- Liniile deschise sunt pe deplin ale tale: alege **priority** (prioritatea, de la scăzut la urgent) direct din dropdown-ul din tabel, sau șterge linia cu butonul de coș. Ca să schimbi o cantitate, folosește **Browse** (răsfoire) — stepper-ul aceluiași articol de acolo editează direct linia deschisă. Odată ce partenerul preia o linie, aceasta se blochează, iar starea ei îți spune unde se află.

## Răsfoirea catalogului partenerului

Lângă lista de cumpărături există un tab **Browse** (răsfoire): întregul catalog al partenerului ca un magazin, cu stoc și prețuri în timp real. Treci prin el pe **Departments** (departamente) sau **Collections** (colecții), coboară până la familii, sau tastează direct în căsuța de căutare. Fiecare fișă de produs arată prețul curent, o insignă **Their stock** (stocul lor) cu ce are disponibil partenerul, și — pentru articolele pe care le folosești — propriile tale numere: *stocul nostru*, *vânzările noastre / trimestru* și *ne terminăm în* atâtea zile (roșu când sunt două săptămâni sau mai puțin).

Două lucruri despre acel catalog merită știute. Prețurile sunt **ale tale, nu ale raftului**: prețul de listă al vânzătorului cu discountul tău intercompany deja scăzut, convertit în moneda propriei tale organizații, așa că ce citești e ce va spune factura. Și include produse pe care partenerul le-a făcut **exclusive pentru tine** — linii care nu apar niciodată în magazinul lor public, dar există pentru organizația ta. Dacă nu găsești ceva la care te așteptai, merită să întrebi; dacă găsești ceva la care nu te așteptai, probabil e al tău prin înțelegere.

Comanda se face direct pe fișă: căsuța cu cantitate **este** lista ta de cumpărături. Tastează sau apasă un număr și linia e adăugată sau actualizată pe lista deschisă; pune-l înapoi la 0 și linia dispare. Alături, un chip punctat **suggested** (sugerat) arată cantitatea pe care aiku ar comanda-o — un click completează căsuța cu ea.

Cât timp răsfoiești, lista ta de cumpărături merge alături, ca o chitanță fixată în dreapta — fiecare linie grupată pe familie, cu totalul curent — ca să știi mereu unde stă comanda. **Go to Shopping list** (mergi la lista de cumpărături) te duce înapoi la lista completă, editabilă.

<figure><img src="/art/docs/draw-partner-browse.svg" alt="Watercolor sketch of the partner catalogue browser: a search box, Departments and Collections tabs, product cards with plus buttons, and the shopping list receipt pinned on the right with its running total" width="1200" height="750" loading="lazy"><figcaption>Magazinul partenerului, cu lista ta mergând alături.</figcaption></figure>

## Auto-fill: un buget și, dacă vrei, o instrucțiune

Auto-fill (auto-completarea) există ca reaprovizionarea să nu depindă de cineva care își amintește fiecare articol. Îi dai un singur număr — un **budget** (buget), în aceeași monedă ca prețurile la care cumperi — și el construiește o propunere care încape în el:

- Se uită la fiecare articol pe care partenerul îl poate furniza și pe care chiar îl folosești, le clasează după **cât de curând te termini** (aceeași prognoză *ne terminăm în* pe care o vezi în Browse), și completează mai întâi cele mai apropiate de epuizare, fiecare la cantitatea lui recomandată de comandă.
- Fiecare linie propusă arată **motivul** ei ("Vânzările noastre/trimestru ~48 · stocul nostru 0 · ne terminăm acum"), cantitatea și costul, ca să vezi de ce e acolo. Cantitățile urmează aceeași prognoză ca și chip-urile *suggested* din Browse.
- Căsuța de **instrucțiune** e opțională și acceptă limbaj natural: *"prioritizează uleiurile esențiale, sari peste orice avem stoc pe mai mult de 8 săptămâni"*, *"concentrează-te pe lumânări, nimic sezonier"*. Un AI citește instrucțiunea ta împreună cu aceleași date de utilizare și remodelează propunerea în consecință — dar rezultatul e verificat față de realitate înainte să-l vezi: cantitățile sunt plafonate la ce are efectiv partenerul, iar totalul e forțat înapoi în bugetul tău. Dacă instrucțiunea nu poate fi urmată, primești propunerea standard.
- **Nimic nu se adaugă singur.** Propunerea e un set de linii bifate pe care le poți debifa, recantitatea sau regenera cu alt buget sau altă instrucțiune; doar **Add items to shopping list** (adaugă articolele pe lista de cumpărături) confirmă ceva.
- **Unele articole ies din joc.** Un SKO cu **Do not auto order** (nu comanda automat) activat (în ecranul de editare al SKO-ului, sub Stock Data) nu apare niciodată într-o propunere — pentru articolele pe care procurement vrea să le țină sub control manual. Îl poți comanda tot manual, din Browse sau din lista de stocuri; doar calea automată îl sare. SKO-urile marcate **On Demand** (la cerere) sunt excluse complet din shopping-ul cu partenerii.

Auto-fill poate fi deschis și deja limitat: **+ fill** pe o placă de risc din dashboard îl deschide doar pentru acel bucket, cu propunerea deja generată. Aceleași reguli — ajustezi, debifezi și confirmi; nimic nu se adaugă singur.

Un obicei bun: lucrează plăcile din dashboard de la cel mai rău caz în jos, apoi rulează Auto-fill o dată pe ciclu de reaprovizionare pentru ce a rămas, citește motivele, debifează ce nu ești de acord și adaugă restul.

## Când lista spune nu

Adăugările sunt refuzate în trei cazuri, intenționat: lista a atins **budget**-ul (bugetul) pentru acest partener (articolele de rang A și cele fără stoc sunt scutite — o urgență încape întotdeauna), depozitul e sub 5% locații libere, sau acest partener a revendicat deja cota lui echitabilă din sloturile libere cu produse pe care nu le-ai stocat niciodată. Rezolvă mesajul în loc să cauți altă cale de intrare — aceeași gardă acoperă adăugările manuale, cele în bloc și Auto-fill. [Articolul despre dashboard](/docs/reading-the-partner-shopping-dashboard-ro) explică de unde vin acele limite.

## Când marfa e pe drum

Odată ce partenerul [trimite o expediere către depozitul lui](/docs/fulfilling-partner-orders-ro), pe pagina partenerului tău, sub **Stock deliveries** (livrări de stoc), apare o **livrare de stoc** care intră. Las-o în pace cât timp spune confirmed sau dispatched — ea reflectă depozitul vânzătorului și se actualizează singură. Când cutiile ajung fizic: **receive** (recepționează), verifică și plasează în locații exact ca la orice livrare de furnizor. Orice lipsă sau daună se rezolvă după recepționare, contra facturii legate — vezi [prezentarea generală](/docs/ordering-from-a-partner-organisation-ro) pentru cum funcționează banii.

<aside class="wayfinder"><strong>Unde apeși în aiku</strong>
<ul>
<li><b>Vezi ce trebuie cumpărat:</b> organizația ta → <b>Procurement → Partners</b> → deschide partenerul → <b>Shopping</b> (dashboard-ul) → lucrează plăcile de risc.</li>
<li><b>Adaugă pe listă:</b> <b>Shopping list</b> → <b>Add stocks</b>, sau <b>Browse</b> și setează cantități pe fișele de produs, sau <b>Auto-fill</b> (sau <b>+ fill</b> pe o placă din dashboard) pentru o propunere.</li>
<li><b>Ajustează liniile deschise:</b> schimbă prioritatea sau șterge linii în tabelul listei de cumpărături; schimbă cantități din fișele de produs în <b>Browse</b>.</li>
<li><b>Ține un articol în afara auto-completării:</b> organizația ta → <b>Warehouse → Inventory</b> → deschide SKO-ul → <b>Edit SKO</b> → activează <b>Do not auto order</b>.</li>
<li><b>Urmărește și recepționează expedierea:</b> aceeași pagină de partener → <b>Stock deliveries</b> → când marfa ajunge, <b>Receive</b> → verifică → plasează în locații.</li>
</ul>
</aside>
