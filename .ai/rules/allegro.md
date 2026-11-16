---
paths:
  - 'app/Actions/Dropshipping/Allegro/**'
---

# Allegro

## Allegro: live means Allegro says ACTIVE; never create a second offer
MER-01 (14 Nov 2026). A portfolio's platform_status is true only when GET /sale/product-offers/{id} says publication.status ACTIVE (CheckAllegroPortfolio::applyOffer). Otherwise errors_response says why and data.allegro_publication keeps status/ended_by. Only a real 404 marks the offer missing (exist_in_platform false); any other API failure leaves the row as it was. Publishing is async: StoreProductToAllegro::activateOffer marks the row ACTIVATING and queues a delayed CheckAllegroPortfolio with the command id, which reads /tasks for the reason. While platform_product_id is set and exist_in_platform is true, upload re-activates that offer and never calls createOffer. The stock loop covers every offer that exists, not just live ones. The PATCH response refreshes the status, and an offer ENDED with EMPTY_STOCK is reactivated once we push stock > 0. A seller-ended offer (USER) is never reactivated by the stock push.
