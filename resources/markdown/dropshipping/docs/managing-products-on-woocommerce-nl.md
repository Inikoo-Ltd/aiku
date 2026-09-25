---
title: Producten beheren op WooCommerce
summary: Voeg onze producten toe aan uw WooCommerce-kanaal, maak ze aan in uw winkel of koppel ze aan producten die u al verkoopt, houd de voorraad bij, en herstel uploadfouten.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, producten, uploaden, koppelen, sku, voorraad
category: products
series: woocommerce
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Open uw WooCommerce-kanaal en ga naar <b>Mijn producten</b>. Druk op <b>Add products</b> en kies de producten die u wilt verkopen. Stuur ze dan elk naar uw winkel: <b>Create new product</b> maakt een nieuw product aan in WooCommerce, en <b>Match with this product</b> koppelt het aan een product dat u al in uw winkel heeft. Een groen vinkje betekent dat het product live is en wij de voorraad bijhouden.
</aside>

## Uw productlijst openen

1. Open <b>Kanalen</b> in het menu en klik op de naam van uw WooCommerce-winkel.
2. Druk op het kanaaldashboard op <b>View all</b> onder <b>Products</b>. De pagina <b>Mijn producten</b> opent.

Toont de pagina <b>Your channel is not connected yet to the platform</b>, herstel dan eerst de koppeling. Zie [Uw WooCommerce-winkel koppelen](connecting-woocommerce).

## Producten aan uw lijst toevoegen

1. Druk op <b>Add products</b>. Het venster <b>Select products to be added to shop</b> opent.
2. Zoek op naam of code. U kunt ook een hele <b>Department</b>, <b>Sub-department</b> of <b>Family</b> kiezen in plaats van losse producten.
3. Vink de producten aan die u wilt. Druk op <b>Add</b>. De knop toont hoeveel u heeft geselecteerd.

De producten staan nu in uw lijst, maar nog niet in uw WooCommerce-winkel. U moet ze eerst aanmaken of matchen.

U kunt ook meerdere producten tegelijk toevoegen vanuit een spreadsheet met de uploadknop naast <b>Add products</b> (<b>Import from xlsx file</b>). Heeft u producten in een ander kanaal, dan kunt u met de knop <b>⋮</b> <b>Clone portfolio from channel</b> gebruiken.

<!-- screenshot: het venster Select products to be added to shop met een paar aangevinkte producten en de knop Add -->

## Producten naar uw winkel sturen

Elk product heeft een kolom <b>Woo Commerce product</b>. Wat u daar ziet, hangt af van het product:

- <b>Create new product</b>: maakt een nieuw product aan in uw WooCommerce-winkel, met de naam, beschrijving en prijs uit uw productlijst, en onze afbeeldingen, SKU, barcode, gewicht, afmetingen en voorraad.
- <b>Match with this product</b>: wij vonden een product in uw winkel met dezelfde SKU, of een vergelijkbare naam. Controleer of het de juiste is en druk erop om de twee te koppelen. Gebruik dit wanneer u het product al verkoopt en geen tweede exemplaar wilt.
- <b>Choose another product from your shop</b> (wanneer wij een mogelijke match vonden) of <b>Match it with an existing product in your shop</b> (wanneer wij niets vonden): opent een lijst met de producten in uw winkel. Zoek het product, selecteer het en druk op <b>Link ... to selected item on your platform</b>.

Lukt het, dan toont het product een groen vinkje en de naam van uw WooCommerce-product. Vanaf dan houden wij de voorraad ervan bij. Om het later aan een ander WooCommerce-product te koppelen, drukt u op <b>Change linked listing</b>.

Wanneer u een product matcht, koppelen wij het alleen en werken wij de voorraad bij. Wij wijzigen niet de naam, beschrijving, prijs of afbeeldingen die u al in WooCommerce heeft.

<!-- screenshot: rijen van Mijn producten met Create new product, Match with this product, en een groen vinkje op een gekoppeld product -->

### Meerdere producten tegelijk

- Vink meerdere producten in de lijst aan. Boven de lijst verschijnen knoppen: <b>Create New</b> stuurt ze allemaal als nieuwe producten, <b>Match</b> koppelt ze aan producten in uw winkel met dezelfde SKU.
- Staan sommige producten nog niet in uw winkel, dan ziet u <b>You have ... products not synced yet</b>. Druk op <b>Upload all as new product</b> om ze allemaal aan te maken, of op <b>Match all with default product</b> om elk product met dezelfde SKU in uw winkel te koppelen.

Grote uploads lopen op de achtergrond en tonen een voortgangsvenster. U kunt blijven werken terwijl ze lopen.

Matchen zoekt naar onze SKU, of onze productcode, in uw winkel. Hoofdletters en kleine letters maken geen verschil. Zijn uw SKU's anders dan de onze, gebruik dan <b>Match it with an existing product in your shop</b> en kies het product zelf.

## Wat wij naar WooCommerce sturen

- Naam, beschrijving en prijs uit uw productlijst.
- Onze afbeeldingen, SKU en barcode (als GTIN, UPC, EAN of ISBN).
- Gewicht in de eenheid die uw winkel gebruikt, en afmetingen wanneer wij die hebben.
- Land van oorsprong en ingrediënten als productattributen, en links naar productdocumenten in de beschrijving.
- De voorraad die u kunt verkopen. Producten die te koop zijn, worden gepubliceerd. Producten die niet op voorraad zijn, binnenkort komen of nog niet klaar zijn, worden als concept opgeslagen.

Wij kiezen geen categorie voor u. Nieuwe producten komen zonder categorie binnen, dus voeg zelf uw eigen categorieën toe in WooCommerce.

## Voorraad en prijzen

Wij sturen voorraadwijzigingen automatisch naar uw winkel. Druk bovenaan <b>Mijn producten</b> op <b>Update Stock</b> om de huidige voorraad van al uw producten nu naar dit kanaal te sturen.

In <b>Manage Sales Channel</b> kunt u wijzigen hoe voorraad wordt getoond:

- <b>Stock Update</b>: zet automatische voorraadupdates aan of uit.
- <b>Max Quantity To Advertise</b>: het hoogste voorraadaantal dat wij in uw winkel tonen, ook wanneer wij er meer hebben.
- <b>Stock Threshold</b>: zodra onze voorraad tot dit aantal daalt, toont het product als niet op voorraad in uw winkel.

Uw <b>Pricing Policy</b> in <b>Manage Sales Channel</b> bepaalt de prijs van producten die u vanaf nu toevoegt. Dit verandert niets aan producten die al in uw lijst staan. Om hun prijzen te wijzigen, vinkt u ze aan en drukt u op <b>Edit Price</b>.

## Producten verwijderen

Er zijn drie manieren om een product te verwijderen. Kies zorgvuldig, want de doodshoofdknop verwijdert het product ook uit uw WooCommerce-winkel.

- De doodshoofdknop op een productrij vraagt om bevestiging en verwijdert het product dan uit uw lijst en, als het gekoppeld is, permanent uit uw WooCommerce-winkel. Het gaat niet naar de WooCommerce-prullenbak.
- <b>Unlink & Delete</b> (nadat u producten aanvinkt) verwijdert de aangevinkte producten uit uw lijst, maar houdt ze in uw WooCommerce-winkel. Ze zijn niet meer gekoppeld, dus wij stoppen met het bijwerken van hun voorraad.
- <b>Unlink</b> (nadat u producten aanvinkt) houdt de producten in uw lijst en in WooCommerce, maar verbreekt de koppeling. Wij stoppen met het bijwerken van hun voorraad. U kunt ze later opnieuw matchen.

Verwijdert u een gekoppeld product zelf in WooCommerce, dan verwijderen wij het ook uit uw lijst.

Producten die wij niet meer verkopen tonen een rood teken. <b>This product line has been discontinued. Please remove this item</b> betekent dat u het uit uw winkel moet verwijderen. <b>This product line is currently not for sale</b> betekent dat u het op dit moment niet kunt uploaden.

## Controleren wat er gebeurd is

Open het tabblad <b>Logs</b> (het klokicoontje rechts van de tabbladen) om elke upload te zien, of deze gelukt is, en het bericht dat uw winkel terugstuurde.

De kolom <b>Status</b> toont drie vinkjes per product: <b>Has valid platform product id</b>, <b>Exist in platform</b> en <b>Platform status</b>. Drie groene vinkjes betekenen dat het product gekoppeld en live is.

## Als er iets misgaat

Mislukt een upload, dan toont de productrij het bericht van uw winkel en een korte tip. De meest voorkomende:

- <b>The store answered with a web page instead of data</b>, een 503-fout, een time-out, of een leeg antwoord: uw website ligt plat, is te traag, staat in onderhoudsmodus, of blokkeert ons. Dit is het meest voorkomende uploadprobleem. Controleer of uw site opent in de browser, vraag uw hostingbedrijf onze servers toe te staan, en upload daarna opnieuw.
- <b>A product with this SKU already exists in your store</b>, <b>Invalid or duplicated SKU</b>, of <b>product with SKU ... already present in the lookup table</b>: uw winkel heeft al een product met deze SKU. Wanneer u op <b>Create new product</b> drukt, proberen wij zelf aan dat product te koppelen. Blijft het bericht staan, matcht het product dan met de hand met <b>Match it with an existing product in your shop</b>. Vindt u het product niet in uw winkel, kijk dan in de WooCommerce-prullenbak: een verwijderd product houdt de SKU vast totdat u het definitief verwijdert.
- <b>Invalid or duplicated GTIN</b>: een ander product in uw winkel gebruikt al dezelfde barcode (GTIN, UPC, EAN of ISBN). Match met dat product, of verwijder de barcode van het andere product in WooCommerce, en upload daarna opnieuw.
- <b>Your store could not save the product images</b>: de WordPress-uploadmap is niet schrijfbaar. Vraag uw hostingbedrijf de mapmachtigingen te herstellen, en upload daarna opnieuw.
- <b>The account connected to your store is not allowed to create products</b> (of te bewerken of te lezen): de sleutels hebben geen <b>Read/Write</b>-machtiging. Verbind het kanaal opnieuw met een beheerdersaccount.
- <b>Your store rejected the credentials</b>: de sleutels zijn verwijderd of gewijzigd in WooCommerce. Druk op <b>Try to reconnect</b> op de kanaalpagina.
- <b>This product no longer exists in your store</b>: het product is verwijderd in WooCommerce. Maak het opnieuw aan of match het met een ander product.
- De knop <b>Add products</b> ontbreekt: uw winkel antwoordde de laatste keer dat wij het probeerden niet, dus hebben wij het kanaal gepauzeerd. Controleer of uw website online is. De knop komt terug zodra wij uw winkel weer bereiken.

Problemen op uw eigen website, zoals dat deze plat ligt, traag is of ons blokkeert, en productregels die u zelf in WooCommerce instelt, kunnen alleen door u of uw hostingbedrijf worden opgelost.
