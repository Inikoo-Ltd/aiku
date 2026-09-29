---
title: Chyby pri nahrávaní do obchodu: naša vina, alebo ich?
date: 2026-09-29
source_date: 2026-09-29
summary: Keď dropshippingový zákazník hovorí, že jeho produkty sa nedajú nahrať na eBay, Shopify, WooCommerce, TikTok Shop, Wix alebo Allegro, najprv si v aiku prečítajte odpoveď platformy. Väčšinou ide o vlastné pravidlo platformy a zákaznícky návod už hovorí, čo má urobiť.
tags: dropshipping, crm, sales channels, ebay, shopify, woocommerce, tiktok, wix, allegro, tickets
category: crm
help_routes: grp.org.shops.show.crm.customers.show.customer_sales_channels.index, grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios
---

<aside class="tldr">
Keď sa zákazníkove produkty nedajú nahrať do jeho obchodu, aiku si drží presnú odpoveď, ktorú dala platforma. Otvorte zákazníkov kanál, potom <b>Portfolios</b> &rarr; <b>Logs</b> a prečítajte si <b>Response</b>. Ak sa správa týka zákazníkovho účtu, limitov, pravidiel, kategórií alebo jeho vlastnej webstránky, je to <b>pravidlo platformy</b>: opraviť to môže iba zákazník a zákaznícky návod na našej webstránke mu povie ako. Pošlite mu ten návod. Ticket zakladajte iba vtedy, keď sa správa týka nás alebo našich produktových dát, alebo keď nič nevysvetľuje.
</aside>

## Najprv si prečítajte odpoveď

1. Otvorte zákazníka, potom záložku <b>Channels</b>. Každý kanál ukazuje svoj stav: <b>closed</b>, alebo zelené a červené ikony pre <b>App installed ok</b>, <b>Exist in platform</b> a <b>Platform status</b>. Podržte myš nad ikonou a prečítajte si, čo znamená.
2. Kliknite na kanál, potom <b>Portfolios</b>. Produkty sú vypísané s rovnakými ikonami. Červený krížik pri <b>Platform status</b> znamená, že produkt nie je živý v obchode.
3. Otvorte záložku <b>Logs</b>. Každé nahratie, aktualizácia skladu a nový pokus je riadok so <b>Status</b>: <b>Done</b>, <b>In progress</b> alebo <b>Failed</b>. Stĺpec <b>Response</b> je to, čo povedala platforma. Kliknutím na ikonu kódu, <b>See the answer of the platform</b>, si prečítate celú odpoveď.

Produkt bez riadku v <b>Logs</b> ešte nebol odoslaný. To nie je chyba: zákazník ho pridal, ale nestlačil <b>Create new product</b>, alebo čaká na rad.

## Čia je to chyba?

<b>Platformy</b>, keď odpoveď hovorí o:

- limite: počet položiek za mesiac, skúšobná lehota, počet produktov;
- zákazníkovom účte: neoverený, nie predajný účet, neaktívny, obmedzený;
- zákazníkových nastaveniach na platforme: dopravné alebo vratné pravidlá, sklady, podmienky reklamácií, kategórie, o ktoré musí požiadať;
- zákazníkovej vlastnej webstránke, ktorá je nedostupná, pomalá, alebo nás blokuje (WooCommerce a Wix);
- produkte, ktorý už v jeho obchode existuje s rovnakým SKU alebo čiarovým kódom.

Toto nemôžeme zmeniť my ani inžinieri. Zákazník to musí urobiť na platforme.

<b>Naša</b>, keď odpoveď hovorí o:

- produktových dátach, ktoré vlastníme my: chýbajúca hmotnosť, chýbajúca značka alebo typ, príliš malý obrázok, chýbajúci čiarový kód, ktorý zákazník nevie doplniť;
- samotnom aiku, alebo správe, ktorá nás menuje;
- riadku <b>Failed</b> bez akejkoľvek správy;
- rovnakej chybe, ktorá sa naraz objaví u mnohých zákazníkov tej istej platformy.

<b>Nové pripojenie nevynuluje pravidlá platformy.</b> Zmazanie kanála a jeho opätovné pripojenie, alebo zmena typu účtu, nič nezmenia, ak limit alebo obmedzenie sedí na zákazníkovom účte. Nový kanál s tým istým účtom narazí na tú istú stenu.

## eBay

Návod na poslanie: <b>managing-products-on-ebay</b>, a <b>connecting-ebay</b> pri problémoch s pripojením.

- <b>"This listing would cause you to exceed the number of items you can list"</b> alebo <b>"… the amount you can list this month"</b>: zákazníkov predajný limit na eBay. Zvýšiť ho môže iba eBay, na ebay.co.uk/help/selling/listings/selling-limits. Business účet ho sám od seba nezvýši.
- <b>"invalid data in the associated fulfilment policy"</b>: dopravné pravidlo, ktoré si zvolili, nemá žiadnu dopravnú službu. Opravia si to v eBay.
- <b>Seller account not finished</b>: dokončia registráciu predajcu v eBay.
- <b>"not allowed to revise an ended item"</b> alebo <b>"This Offer is not available"</b>: inzerát na eBay skončil. Stlačia <b>Create new product</b>.
- <b>"improper words" alebo "in violation of eBay policy"</b>: vlastná kontrola eBay. Odpovedať vie iba eBay.
- <b>Overseas Warehouse Block Policy</b>: požiadajú eBay o schválenie.
- <b>Naša:</b> <b>"The item specific Brand is missing"</b> (alebo Type, Item Length, Item Width) a <b>"custom values for Size are no longer supported"</b>. Založte ticket s kódom produktu.

## Shopify

Návody na poslanie: <b>managing-products-on-shopify</b>, <b>connecting-shopify</b>, <b>shopify-fulfilment-location</b>.

- <b>Channel not connected yet</b>: aplikácia nebola nainštalovaná. Stlačia <b>Click here to install</b> a potom <b>Install</b> v Shopify.
- <b>"No Shopify location, the AW fulfilment service is not installed"</b>: to isté, inštalácia nebola dokončená.
- <b>A product with the same SKU already exists</b>, <b>"No variant on Shopify matches this sku"</b>, <b>"More than one variant … has the sku"</b>: produkty v ich obchode. SKU si zosúladia alebo opravia v Shopify.
- <b>"Throttled"</b>, <b>HTTP 502 alebo 504</b>: Shopify bol vyťažený. Skúsia to znova neskôr.
- <b>Produkty sa v Shopify zobrazujú ako vypredané</b>: lokalita <b>aiku-</b> chýba v ich shippingovom profile.
- <b>Naša:</b> <b>"You need to add option values"</b>, keď chcú napojiť existujúce varianty. Na to musíme zapnúť možnosť u nás, takže založte ticket.

## WooCommerce

Návody na poslanie: <b>managing-products-on-woocommerce</b>, <b>connecting-woocommerce</b>.

Takmer každá chyba WooCommerce je zákazníkova webstránka: nedostupná, pomalá, v údržbe, alebo blokuje naše servery firewallom, bezpečnostným pluginom alebo Cloudflare. Správa problém pomenuje a kde to pomôže, uvádza naše IP adresy pre ich hostingovú spoločnosť.

- <b>Webová stránka namiesto dát, 503, timeout, prázdna odpoveď</b>: ich stránka. Skontrolujú, či sa otvára, a požiadajú svoj hosting, aby nás povolil.
- <b>SKU alebo GTIN už existuje</b>: produkt v ich obchode, niekedy v koši WooCommerce.
- <b>Could not save the product images</b>: ich priečinok na nahrávanie. Opravuje ich hosting.
- <b>Not allowed to create products</b> alebo <b>rejected the credentials</b>: kľúče stratili oprávnenie alebo boli zmazané. Znova sa pripoja.
- <b>The store no longer accepts our keys</b>: stránka kanála ukáže jantárový box s odkazom na opätovné pripojenie a tlačidlom <b>Copy</b>. Pošlite zákazníkovi ten odkaz. Autorizovať znova môže iba majiteľ obchodu.

## TikTok Shop

Návody na poslanie: <b>connecting-tiktok-shop</b>, <b>tiktok-shop-warehouse</b>, <b>tiktok-shop-shipping-template</b>.

- <b>Shop probation period</b> alebo <b>probation tier</b>: nové obchody TikTok môžu vystaviť iba pár produktov. Správa hovorí koľko. Počkajú, alebo odstránia produkty, ktoré nepredávajú.
- <b>Requires an active seller account</b>, <b>category qualification</b>, <b>certifications</b>, <b>manufacturer is required</b>, <b>requires a return warehouse</b>: všetko sa nastavuje v TikTok Seller Center.
- <b>Incorrect price</b>: ich predajná cena je mimo toho, čo TikTok povoľuje. Zmenia ju v <b>My Products</b>.
- <b>No warehouse matches 0</b> alebo <b>no warehouse yet</b>: pridajú predvolený sklad v Seller Center a potom stlačia <b>Save</b> na kanáli.
- <b>Naša:</b> <b>product_weight</b> prišlo ako <b>0</b>, obrázok pod <b>300 x 300 pixelov</b>, a <b>"the warehouse does not belong to this shop"</b>. Založte ticket s kódom produktu.

## Wix

Návody na poslanie: <b>uploading-products-to-wix</b>, <b>connecting-wix</b>.

- <b>"AW Connect isn't supported with your site"</b>: Wix odmietne skôr, než sa dostaneme k našej aplikácii. Wix Stores musí byť nainštalovaný a na novšom katalógu.
- <b>Wix Stores is not installed</b>, <b>channel not connected yet</b>: stlačia <b>Try to reconnect</b> a nainštalujú na tú istú stránku.
- <b>No pictures</b>, <b>out of stock</b>: návod ich tým prevedie.

## Allegro

Návody na poslanie: <b>syncing-products-to-allegro</b>, <b>connecting-allegro</b>.

- <b>"You do not have any Complaints Terms"</b>, <b>inactive or unverified account</b>: nastavia si v Allegro.
- <b>Missing mandatory parameters</b>, <b>no matching category</b>: kategória potrebuje detaily, ktoré nemáme. Zvolia iný produkt, alebo vytvoria ponuku v Allegro a <b>Match</b>-nú ju.
- <b>Channel not connected</b>: prístup do Allegro vypršal. Stlačia <b>Reconnect</b>.
- <b>Naša:</b> <b>"No shipping price list set"</b>, ktorá zostáva aj po opätovnom pripojení toho istého účtu.

## Posielanie návodu

Zákaznícke návody sú na zákazníkovej vlastnej webstránke, na <b>/docs/</b> a názve návodu: <b>aw-dropship.com</b> pre UK, <b>aw-dropship.eu</b> pre Európu, <b>aw-dropship.es</b> pre Španielsko. Napríklad <b>https://www.aw-dropship.com/docs/managing-products-on-ebay</b>.

Odpoveď, ktorá funguje:

> The message comes from eBay, not from us: your eBay account has a monthly listing limit. Only eBay can raise it, here: https://www.ebay.co.uk/help/selling/listings/selling-limits?id=4107. Our guide explains this and the other eBay messages: https://www.aw-dropship.com/docs/managing-products-on-ebay

Povedzte, od koho správa je, čo majú urobiť, a dajte odkaz. Zákazník nemusí čakať, kým to počuje od inžiniera.

## Keď predsa len založíte ticket

Založte ho z chatu (pozri [Založenie ticketu z chatu](/docs/raising-a-ticket-from-a-chat-sk)) a priložte:

- odkaz na zákazníkov kanál v aiku;
- kód produktu;
- text <b>Response</b> z <b>Logs</b>, skopírovaný, nie prepísaný;
- čo ste už overili v zákazníckom návode.

Ticket s odpoveďou platformy v ňom sa dá vybaviť za pár minút. Ticket, ktorý hovorí "chyba pri nahrávaní", začína tým, že inžinier prejde tie isté kroky.

<aside class="wayfinder"><strong>Kde kliknúť v aiku</strong>
<ul>
<li><b>Stav kanála:</b> zákazník &rarr; <b>Channels</b> &rarr; ikony na riadku kanála.</li>
<li><b>Odpoveď platformy:</b> zákazník &rarr; <b>Channels</b> &rarr; kanál &rarr; <b>Portfolios</b> &rarr; <b>Logs</b> &rarr; <b>Response</b>, alebo ikona kódu pre celú odpoveď.</li>
<li><b>Odkaz na opätovné pripojenie WooCommerce:</b> stránka kanála &rarr; jantárový box &rarr; <b>Copy</b>.</li>
<li><b>Poslať produkty znova:</b> <b>Portfolios</b> &rarr; <b>Force Sync</b>.</li>
<li><b>Zákaznícke návody:</b> zákazníkova webstránka &rarr; <b>/docs/</b> a názov návodu.</li>
</ul>
</aside>
</content>
</invoke>
