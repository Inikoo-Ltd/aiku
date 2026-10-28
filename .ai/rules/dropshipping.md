---
paths:
  - 'app/Actions/Dropshipping/**'
---

# Dropshipping

## Bundles: derive the whole from the parts, every time

A bundle is one `Product` (`is_bundle`) whose trade units stand in for all of its components.
Everything downstream — available quantity, gross weight, the SKU on the platform variant,
picking — is read off those trade units, so a component missing there is a component missing
from the customer's order. Three ways it goes wrong, all seen in the wild:

- Two components made of the same trade unit. `SyncProductTradeUnits` assigns per trade unit
  (`$tradeUnits[$id] = ...`), so a list carrying the same id twice keeps the last and silently
  drops the rest. Callers must hand it ONE row per trade unit with the quantities already
  added up — it will not do that for you, and this is true of every caller, not just bundles.
- A component that is itself several trade units. The bundle holds
  `component_pivot_quantity * bundle_quantity` of it, not `bundle_quantity`.
- Recomputing from whatever the request happened to mention. After items are created,
  requantified or deleted, read the trade units back off the bundle's CURRENT items
  (`UpdateBundle::getCurrentBundleTradeUnits`). Merging per-branch partials leaves the
  product describing an older component set.

Portfolio `sku` is a snapshot taken at `StorePortfolio` time. Anything that changes a
bundle's components must recompute it via `WithPortfolioSKU::getSKU`, or the platform keeps
selling the old component set. Use `App\Actions\Dropshipping\Bundle\WithBundleTradeUnits`
rather than re-deriving this mapping.

## Platform listed-product payloads share one shape

Every `Get*ListedProducts` / `Search*Products` action feeding the "match to an existing
product" picker must return `id`, `name`, `slug`, `code`, `images` (and `sku_list` where the
platform has variants). The pickers render `item.name` / `item.code` and fall back to the
literal strings "no name" / "no code" — a platform that returns its own vocabulary
(Shopify's `title` / `handle`) renders an entire list of blanks.

## An action returning `[bool, string]` has a result you must check

The Shopify product/variant actions report failure by return value, not by throwing. Calling
one and discarding what it returns turns a rejected push into a 2xx and a "synchronised"
toast, which is indistinguishable to the user from the feature not existing. Propagate it —
`ValidationException` for a retina request — or state in the caller why swallowing is right.

## Shopify: bulk Edit Price sends the price only
Retina bulk Edit Price → Save & Publish (`UpdateAndUploadRetinaBulkPortfolioPriceToCurrentChannel` → `UpdateAndUploadRetinaPortfolioToCurrentChannel::handle` → `updateShopifyChannel`) sends `UpdateShopifyProductVariant`: `price` and `compareAtPrice` (`price` alone for an adopted, matched variant; nothing at all for an adopted variant whose price the merchant manages). `UpdateShopifyProduct` (title and description) runs only when `customer_product_name` or `customer_description` changed, and the bulk path never sets either. Other platforms differ: the same `handle` calls `UpdateEbayOffer` with `withTitle: true` on eBay, and `UpdateWooProduct` / `UpdateWixProduct` on WooCommerce / Wix. Keep `resources/markdown/dropshipping/docs/product-descriptions-after-connecting*.md` in line with this.

## Bulk Unlink & Delete ends the listing on the platform
The Retina `bulk.unlink` route (`UnlinkAndDeleteBulkRetinaPortfolio`) runs `DeletePortfolio` for each portfolio (queued above `SYNC_LIMIT`). Since HELP-3409 it no longer unlinks first, so `platform_product_id` is still set and `DeletePortfolio` acts on the platform: WooCommerce force-deletes the product, Magento, Wix and Amazon delete it, eBay withdraws the offer, Shopify deactivates the inventory at the aiku- location (`DeactivateShopifyProduct`, the product stays). UI copy and guides must not say the listing stays. `bulk.unlink_only` (`BulkUnlinkRetinaPortfolio` → `UnlinkRetinaPortfolio`) is the one that keeps the platform listing and only clears the link. A bundle's portfolio (`is_bundle`, `bundle_id`) is created only by `StoreBundle`, one per bundle, and nothing rebuilds it, so `DeletePortfolio` runs `DeleteBundle` for it (PRE-10): a bundle never outlives its portfolio. The bulk dialog in `Retina/Dropshipping/Portfolios.vue` and `removing-products*.md` say per platform what happens to the listing.

## eBay: never send a description to a customer's listing
Nobody authorised us to change descriptions in customers' eBay shops (PRE-27, Raul). `UpdateEbayOffer::pushPriceAndTitle` never PUTs the offer: the price goes alone through `bulk_update_price_quantity`, which also works on offers eBay holds without a `listingDescription` (the full `PUT offer` refuses those with 25016), and a Retina title save only changes `product.title` on the inventory item (`updateInventoryItemTitle`). Never add a description fallback, not our snapshot and not the product name. `StoreEbayProduct` still writes a description, both when it creates a listing and when it re-uploads onto an offer that already exists, possibly PUBLISHED (after `bulk.unlink_only`): that is a customer-triggered upload, but nothing else may write one.

## Shopify: a fulfilment order notification can arrive before the request is readable
Shopify can send `fulfillment_order_notification` up to a second before `assignedFulfillmentOrders` lists the request (PRE-34: notified at 10:02:54, `sentAt` 10:02:55Z). The read the webhook triggers then finds nothing, and the order shipped. One immediate read is not enough: the webhook queues `RetrieveShopifyCancelledOrders` again after `RETRY_SECONDS`, and `shopify:sweep-cancellation-requests` runs every 30 min. `CancelOrder` has no guard and refunds every time it runs, so cancellation answers are serialised per store with a lock, and the order state is read only after the lock is taken. To tell the two notification kinds apart in NightOwl, `nightowl_requests_v2.headers_z` is raw deflate (zlib wbits=-15): content-length 30 = FULFILLMENT_REQUEST, 31 = CANCELLATION_REQUEST.
