---
title: Prebalenie finalizovaného dodacieho listu
summary: Čo sa stane, keď sa finalizovaný dodací list rozbalí a zabalí znova, prečo sa vráti rovno do stavu finalizovaný, a čo robiť, keď to aiku odmietne, lebo vychystané množstvá už nesedia s faktúrou.
date: 2026-09-15
source_date: 2026-09-15
tags: dispatch, packing, invoices, accounting
category: dispatch
---

<aside class="tldr">
<b>Sklad:</b> finalizovaný dodací list môžete stále vziať späť cez <b>Unpack</b> (rozbaliť), ak treba niečo opraviť. Keď ho zabalíte znova, vráti sa rovno do stavu <b>Finalised</b> (finalizovaný), a rovnako aj jeho objednávka. Potom stlačte <b>Dispatch</b> ako obvykle.<br>
<b>Ak aiku odmietne zabaliť:</b> vychystané množstvá už nesedia s tými na faktúre. Buď vráťte vychystávku na hodnoty z faktúry, alebo požiadajte účtovníctvo o refundáciu rozdielu, a potom zabaľte znova.
</aside>

## Prečo je finalizovaný dodací list iný

Finalizácia dodacieho listu robí dve veci naraz: označí list ako pripravený na odoslanie a vytvorí z toho, čo bolo vychystané, **faktúru** objednávky. Od toho okamihu je faktúra záznamom o tom, za čo zákazník platí.

Finalizovaný list sa dá stále vziať späť cez **Unpack** (rozbaliť), napríklad aby ste otvorili krabicu a skontrolovali alebo opravili jej obsah. List aj jeho objednávka sa vrátia do stavu **Packing** (balenie). Faktúra zostáva presne taká, aká bola.

## Opätovné balenie

Keď sa list zabalí znova, cez **Set as packed** (nastaviť ako zabalené) alebo naskenovaním poslednej položky, nezastaví sa v stave **Packed** (zabalený). Keďže jeho objednávka už má faktúru, list aj objednávka idú rovno späť do stavu **Finalised** (finalizovaný). Faktúra, sumy objednávky a prípadná už zaznamenaná zásielka zostávajú nedotknuté.

Odtiaľ list ukazuje svoje tlačidlo **Dispatch** (expedovať) a pokračujete rovnako ako pri každom inom finalizovanom liste.

## Keď aiku odmietne zabaliť

Pred návratom listu do stavu finalizovaný aiku skontroluje každý riadok: aktuálne vychystané množstvo musí sedieť s množstvom na faktúre. Ak sa niektorý riadok počas rozbalenia vychystal inak, balenie sa odmietne so správou, ktorá uvedie faktúru a odkáže na túto stránku. List zostáva v stave **Packing**; nič iné sa nemení.

Návrat do stavu finalizovaný by v takom prípade poslal zákazníkovi niečo iné, než za čo mu bola vystavená faktúra, takže najprv musí nastať jedno z tohto:

- **Vychystávka bola chyba.** Vráťte list o krok späť (**Undo packing**, potom **Undo set as picked**), vychystajte riadky znova na fakturované množstvá a zabaľte.
- **Naozaj ide menej tovaru.** Požiadajte účtovníctvo o vystavenie **refundácie** na faktúre za položky, ktoré sa neodošlú. Keď je refundácia hotová, zabaľte list znova. Pozrite [Faktúry, platby a refundácie](/docs/invoices-payments-and-refunds-sk).
- **Naozaj ide viac tovaru.** Nepridávajte to na tento list. Vychystajte tu fakturované množstvá a požiadajte zákaznícky servis, aby dal ďalší tovar na novú objednávku.

<aside class="wayfinder"><strong>Kde v aiku kliknúť</strong>
<ul>
<li><b>Nájsť list:</b> váš sklad → <b>Dispatching → Delivery notes</b> → záložka <b>Packing</b> (po rozbalení) alebo záložka <b>Finalised</b> (po opätovnom zabalení).</li>
<li><b>Rozbaliť a zabaliť znova:</b> otvorte list → <b>Unpack</b>, potom <b>Set as packed</b>, keď je krabica pripravená. Potom <b>Dispatch</b>.</li>
<li><b>Vrátiť sa k vychystávaniu:</b> na liste v stave <b>Packing</b> použite <b>Undo packing</b>; na liste v stave <b>Picked</b> použite <b>Undo set as picked</b>.</li>
<li><b>Skontrolovať faktúru:</b> vaša organizácia → <b>Accounting → Invoices</b> → otvorte faktúru uvedenú v správe → záložky <b>Transactions</b> a <b>Refunds</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Aké oprávnenia potrebujete</strong>
Rozbalenie a balenie vyžadujú prístup k dispatchingu pre daný sklad. Refundáciu vystavuje účtovníctvo s prístupom k faktúram organizácie.
</aside>
