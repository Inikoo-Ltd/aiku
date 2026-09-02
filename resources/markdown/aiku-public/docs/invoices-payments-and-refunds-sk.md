---
title: Faktúry, platby a refundácie
summary: Nájdite akúkoľvek faktúru, zistite, či bola uhradená, zaznamenajte proti nej platbu a pochopte, ako sa vystavuje refundácia a kam idú peniaze.
date: 2026-09-01
source_date: 2026-09-01
tags: accounting, invoices, payments, refunds
category: accounting
---

<aside class="tldr">
Každý predaj sa nakoniec stane <b>faktúrou</b>. Obrazovka organizácie **Accounting → Invoices** ich všetky vypíše, ukáže, či je každá uhradená, a umožní vám otvoriť ju a vidieť jej riadky, platby a akékoľvek refundácie proti nej. Prichádzajúce peniaze sa zaznamenávajú ako <b>payment</b> proti <b>platobnému účtu</b>; odchádzajúce peniaze sa zaznamenávajú ako <b>refund</b>, čo je samo osebe zvláštny druh faktúry.
</aside>

## Zoznam faktúr

Otvorte svoju organizáciu a potom **Accounting → Invoices**. Každý riadok zobrazuje **Reference** faktúry, **Customer**, ku ktorému patrí, **Date**, jej stav **Payment**, a sumy **Net** a **Total**. Môžete vyhľadávať, zoraďovať podľa ktoréhokoľvek z týchto stĺpcov a filtrovať medzi dátumami.

Obchod si drží vlastný pohľad na tie isté informácie: v nástenke obchodu, pod **Invoices**, nájdete faktúry daného obchodu, so samostatnými zoznamami pre **uhradené** a **neuhradené** faktúry, plus jeden pre faktúry, ktoré boli odvtedy vymazané.

Zoznam má tiež záložku **Invoices** a záložku **Refunds**, takže môžete priamo prepnúť na stranu refundácií bez opustenia obrazovky.

## Otvorenie faktúry

Kliknutím na referenciu faktúru otvoríte. Hore nájdete jej záložky:

- **Transactions** — riadky, ktoré tvoria faktúru: tovar, poplatky, doprava a podobne.
- **Payments** — každá platba prijatá voči tejto faktúre.
- **Refunds** — akékoľvek refundácie vystavené z tejto faktúry.
- **Email** — e-maily, ktoré aiku odoslalo ohľadom tejto faktúry.
- **History** — záznam o tom, čo sa s faktúrou dialo a kedy.
- **Attachments** — akékoľvek priložené súbory.

Odtiaľto si tiež môžete stiahnuť faktúru vo formáte **PDF**, a ak vaša organizácia túto možnosť zapne, aj vo formáte **Omega**, ktorý sa používa pre niektoré účtovné exporty.

## Typy faktúr

Faktúra je vždy jedného z dvoch typov: bežná **Invoice**, alebo **Refund**. Refundácia nie je samostatný druh záznamu — je to faktúra, ktorej typ je nastavený na Refund, prepojená späť na pôvodnú faktúru, ktorú opravuje. Otvorenie refundácie zo zoznamu faktúr vás vezme priamo na jej vlastnú stránku refundácie namiesto obyčajnej stránky faktúry.

## Stav platby

Každá faktúra nesie stav platby, ktorý vidíte na prvý pohľad v stĺpci **Payment**:

- **Unpaid** — proti nej sa zatiaľ neuhradilo nič alebo nedosť.
- **Paid** — faktúra bola vyrovnaná.
- **Unknown payment status** — používa sa len pri veľmi starých faktúrach (staršie ako tri roky), ktoré nemajú žiadnu históriu platieb, takže aiku to skutočne nevie povedať ani jedným smerom.

## Zaznamenanie platby

Platby žijú vo vlastnej oblasti **Payments** v **Accounting** a dajú sa spustiť aj z **platobného účtu** zákazníka. Vytvorenie platby (**New payment**) si vyžiada referenciu, zákazníka a platobné detaily, a vždy sa robí voči konkrétnemu platobnému účtu.

Keď sa platba uloží, aiku zistí, akým spôsobom bola uhradená: ak platba prišla s detailmi karty, peňaženky alebo schémy, aiku zaznamená typ peňaženky alebo platby ako **method** a schému karty ako **sub method**; inak sa vráti k typu samotného platobného účtu. Úspešná platba je prepojená s faktúrou cez jej vlastnú záložku **Payments** a zoznam platieb — či už sa pozeráte na celú organizáciu, obchod, platobný účet, alebo jednu faktúru — vždy ukazuje **Status**, **Reference**, **Payment Account**, **Type**, **Method**, **Amount** a **Date** platby.

## Platobné účty a poskytovatelia platobných služieb

**Platobný účet** je miesto, odkiaľ alebo kam sa platba skutočne odoberá — patrí **poskytovateľovi platobných služieb**, spoločnosti, ktorá platbu spracúva (napríklad kartová brána). Každý poskytovateľ platobných služieb, ktorého má vaša organizácia pripojený, má vlastnú stránku so zoznamom svojich platobných účtov, a otvorením účtu sa zobrazia platby a obchody, ktoré ho používajú.

## Refundácie

Refundácia sa vystavuje z faktúry, ktorú opravuje, a zdieľa jej referenciu s príponou `-refund-` (pokiaľ nastavenia vášho obchodu nezapnú samostatnú číselnú radu pre refundácie). Keď sa refundácia vytvorí, začína na nule a je označená ako **in process**, kým sa buduje — jej sumy sa stanú konečnými až po dokončení refundácie.

Refundácia môže vrátiť peniaze rôznymi spôsobmi, ponúkanými ako dve možnosti pri odosielaní:

- **Refund money to customer's credit balance** — suma sa pripočíta ku kreditnému zostatku zákazníka namiesto vrátenia na kartu alebo účet.
- **Refund money to payment method of the invoice** — suma sa vráti voči konkrétnej pôvodnej platbe, cez platobný účet, z ktorého bola pôvodne odobraná.

Keď sa refundácia spracúva online cez vášho poskytovateľa kartových platieb, aiku počká, kým poskytovateľ potvrdí, že refundácia skutočne prebehla úspešne, než aktualizuje **total refund** pôvodnej platby a označí ju ako refundovanú; ak poskytovateľ úspech nepotvrdí, refundácia sa neprijme.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Vidieť všetky faktúry:</b> vaša organizácia → <b>Accounting → Invoices</b>. Prepínajte medzi záložkami <b>Invoices</b> a <b>Refunds</b> hore.</li>
<li><b>Vidieť faktúry obchodu:</b> nástenka obchodu → <b>Invoices</b> → uhradené, neuhradené alebo vymazané faktúry.</li>
<li><b>Otvoriť jednu faktúru:</b> kliknite na jej referenciu, aby ste videli záložky Transactions, Payments, Refunds, Email, History a Attachments.</li>
<li><b>Zaznamenať platbu:</b> vaša organizácia → <b>Accounting → Payments</b> → <b>New payment</b> (alebo z vlastnej záložky Payments platobného účtu).</li>
</ul>
</aside>

<aside class="permissions"><strong>Aké oprávnenia potrebujete</strong>
Aby sa vám sekcia <b>Accounting</b> vôbec zobrazila v navigácii, potrebujete oprávnenie zobrazovať účtovníctvo organizácie. Vytváranie alebo úprava platby či platobného účtu vyžaduje oprávnenie upravovať účtovníctvo organizácie.
</aside>
