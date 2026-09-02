---
title: Objednávání od partnerské organizace
summary: Proč obchod mezi sesterskými organizacemi funguje přes nákupní seznam místo objednávek, a jak celý cyklus probíhá od zapsané potřeby až po naskladněné zboží.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, warehouse, intercompany
category: procurement
series: Ordering from partners
order: 1
---

<aside class="tldr">
Když nakupujete od sesterské organizace, nevystavujete objednávku. Přidáte to, co potřebujete, na nákupní seznam a prodávající organizace to vyzvedne, jakmile to bude moct odeslat. Od té chvíle vše plyne samo: jejich sklad to vychystá a zabalí a na vaší straně se objeví příchozí skladová dodávka, připravená k naskladnění, jakmile zboží dorazí. Pokud tyto objednávky <em>zadáváte</em>, začněte u <a href="/docs/reading-the-partner-shopping-dashboard-cs">nákupního dashboardu</a> a přečtěte si <a href="/docs/buying-from-a-partner-cs">Nákup od partnera</a>; pokud je <em>vyřizujete</em>, přečtěte si <a href="/docs/fulfilling-partner-orders-cs">Vyřizování partnerských objednávek</a>.
</aside>

<figure><img src="/art/docs/draw-partner-shopping.svg" alt="Watercolor sketch: the buyer's shopping list card (Procurement › Partners › Shopping list, with Auto-fill) and the seller's shipping list card with ticked lines and a Send to warehouse button, a dashed arrow between them, and a truck carrying the goods to a box labelled as the incoming stock delivery" width="1200" height="750" loading="eager"><figcaption>Vy napíšete seznam, oni ho vychystají a odešlou, na vaší straně dorazí skladová dodávka.</figcaption></figure>

## Proč tu není žádná objednávka

Objednávka dává smysl u vnějšího dodavatele: zavážete se k množstvím, on je potvrdí a obě strany sledují stejný dokument. Mezi vlastními organizacemi tento obřad jen překáží. Prodávající zná svůj vlastní sklad lépe než kupující, a nutit kupujícího hádat, co lze odeslat, vede jen k nekonečným úpravám objednávek.

Tok je proto obrácený. **Kupující řekne, co potřebuje**, **prodávající rozhodne, co a kdy odešle**. Nikdo nikomu neupravuje objednávku, protože žádná objednávka neexistuje — jen seznam otevřených potřeb a proud dodávek proti němu.

## Celý cyklus

1. Kupující otevře [nákupní dashboard](/docs/reading-the-partner-shopping-dashboard-cs), aby viděl, co dochází a kolik má prostoru, a poté [přidá, co potřebuje, na nákupní seznam](/docs/buying-from-a-partner-cs) — ručně, z partnerova katalogu, nebo pomocí návrhu z automatického doplnění.
2. Prodávající [vybere řádky, které může odeslat, a pošle zásilku do svého skladu](/docs/fulfilling-partner-orders-cs). Vychystá se, zabalí a expeduje se jako každá jiná objednávka.
3. V okamžiku, kdy zásilka vstoupí do skladu prodávajícího, se na straně kupujícího objeví příchozí **skladová dodávka**. Sama sleduje postup prodávajícího — dokud zboží nedorazí, je prodávající zdrojem pravdy.
4. Když zboží fyzicky dorazí, kupující ho přijme, zkontroluje a uloží na místa přesně jako u kterékoli dodavatelské dodávky.

## Seznam je záměrně omezený

Kupujícího seznam není přáníček. Je omezen zhruba na jeden objednávkový cyklus toho, co partner skutečně dodává, a nové produkty jsou omezeny volným místem ve skladu a spravedlivým podílem na něm na partnera. Seznam, který nikdo nemůže zaplavit, je seznam, který prodávající dokáže číst: když je na něm všechno, není nic naléhavé. Položky bez skladu a produkty ranku A jsou z limitu vyňaty, takže skutečná krize nikdy nečeká ve frontě za limitem.

## Peníze, faktury a problémy

Mezi organizacemi neexistují samostatné dodavatelské faktury. Vlastní faktura prodávajícího za zásilku **je** ten dokument a příchozí skladová dodávka je na ni navázaná. Pokud něco dorazí v menším množství, poškozené nebo špatné, řešte to *až po* přijetí dodávky — to je bod, kdy odpovědnost přechází na vaši stranu — a jakýkoli refund nebo dobropis se řeší proti té navázané faktuře.

## Co je dobré vědět

- Když prodávající vychystává pro partnera poprvé, v jeho obchodě se vytvoří zákaznický účet pojmenovaný podle kupující organizace. To je očekávané — je to způsob, jak zásilka projde jeho běžným strojem.
- Částečné vychystání je normální. Řádek vychystaný jen z části nechá zbytek otevřený pro pozdější zásilku; nic se neztrácí.
- Ceny jsou aktuální ceny prodávajícího obchodu se standardní intercompany slevou kupující organizace, zobrazené ve vlastní měně kupujícího. Nic se nevyjednává po řádcích; pokud se dohoda změní, bude to oznámeno.

<aside class="wayfinder"><strong>Jaká oprávnění potřebujete</strong>
<ul>
<li><b>Vidět nákupní a expediční seznam:</b> přístup procurement <i>view</i> ve vaší organizaci.</li>
<li><b>Přidávat řádky, vybírat a odesílat do skladu:</b> přístup procurement <i>edit</i> v organizaci, která danou akci provádí (u seznamu kupující, u vychystávání a odesílání prodávající).</li>
<li><b>Přijmout a naskladnit dorazené zboží:</b> přístup ke skladovým zásobám ve skladu kupujícího, stejně jako u kterékoli dodavatelské dodávky.</li>
<li>Něco z toho chybí? Požádejte svého administrátora o přidělení role v <b>Sysadmin → Users</b> (Správa systému → Uživatelé) — oprávnění jsou po organizacích, takže mít je v jedné organizaci se nepřenáší na její partnerskou.</li>
</ul>
</aside>
