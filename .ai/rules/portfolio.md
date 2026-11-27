---
paths:
  - app/Actions/Retina/Dropshipping/Portfolio/ZentradaWebApi.php
---

# Portfolio

## Zentrada feed sells packs by the piece, price rounded up
HELP-4923 (25 Nov 2026), reverses HELP-3530. Zentrada pulls https://www.awgifts.eu/json/zentrada-web-api (shop EU) about 4x a week and imports it straight away, so every deploy goes live. quantity_of_units_per_package = products.units (whole numbers only, else 1), MOQ 1, net_price_per_unit = pack price / units rounded UP to the cent (integer cents, piecePriceInCents), so Zentrada's pack total is never below what aiku invoices. RRP and weights are per piece; stock is products.available_quantity (whole packs). Products with no stock, no web image, a code ending in EXP (Tomas, HELP-4923: only EXP, UK-plug and other suffixed variants stay) or exclusive_for_customer_id are left out, and every row carries activ_until = today + OFFER_VALID_DAYS, because Zentrada keeps products missing from a later feed. promotion_discount is always sent, as 0 when unset (an empty column keeps the old discount running). Promotion and up to 3 volume tiers live in shops.settings.zentrada (promotion_discount, tier_N_quantity, tier_N_discount), set from Edit shop > Zentrada feed. Text fields allow only br/strong and lose phone numbers, emails and links (restrictedHtml). shop_product_categoryID1-3 stay empty until Zentrada confirms which category system the account uses.

## Zentrada GPSRIsRequired is always 1 or 2, never empty or 0
HELP-4960 (27 Nov 2026). Zentrada rejects the whole row when GPSRIsRequired is not 0, 1 or 2 (errorlog_437077: 100 products not uploaded because we sent it empty). Per csv_shop_vorlage: 1 = subject to GPSR (we have gpsr_warnings or gpsr_manual), 2 = no special instructions needed (still needs producer address + EU email, which we always send). Never send 0: it deletes every GPSR field Zentrada stores, including the producer and contact.
