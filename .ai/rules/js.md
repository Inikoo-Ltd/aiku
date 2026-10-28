---
paths:
  - 'resources/js/**'
---

# Js

## Buttons are white with an icon; the icon carries the colour
Raul, 11 Oct 2026: no filled or coloured buttons (grey, theme or PrimeVue severity fills), in dialogs and confirmations included. Every button has an icon and a white/transparent background with a border: `@/Components/Elements/Buttons/Button.vue` with `type="tertiary"`. Meaning goes in the icon colour through the `#icon` slot: red (`text-red-500`, fal fa-times) for cancel, green (`text-green-600`, fal fa-check) for confirm, amber warning for a destructive confirm.
PrimeVue ConfirmDialog renders its own filled buttons: give it a `#container` slot that draws these buttons (`resources/js/Pages/Grp/Procurement/StockDelivery.vue` is the reference), set `group` on every `confirm.require` so the page's dialog takes it, and a fixed width (~28rem) so long messages wrap instead of stretching.

## Codes and references never wrap
Product codes, SKO codes and PO/delivery references never break across lines in tables or lists: render them with `whitespace-nowrap` (a `#cell(code)` slot when the standard Table would otherwise wrap them). A code split at its hyphen, like "ACEBL-BOX-" / "01", reads as two codes. Let the name or description column wrap instead.

## Dialog headers are compact; table headers never wrap
Raul, 16 Oct 2026: PrimeVue Dialog never uses its default big title and big circled (x): small grey semibold title (text-sm), tight header padding and a small borderless close button (h-7 w-7, grey icon, light grey hover). This is global, in the dialog `css` of `MyPreset` (`resources/js/app-grp.js`): the selector must be `.p-dialog .p-dialog-header .p-dialog-close-button.p-button` with `padding: 0` and no outline or ring on `:focus-visible`, because PrimeVue's button rules otherwise win (a 28x40 oval) and the dialog focuses the close button on open (the dark ring). Do not add per-page `pt` for it.
Table column headers stay on one line: `whitespace-nowrap` on the `<thead>` of hand-built tables ("UNIT / COST" split over two lines is wrong). Let the name/description column wrap instead.

## No toast notifications: errors show where the user saved
Raul, 28 Oct 2026: we stop using toasts (`notify()` from @kyvg/vue3-notification) in new UI. An error is shown inside the modal, or right under the control the user clicked save on (Inertia onError / form.errors into a ref, red text-xs). Success shows in place: the dialog closes, the row appears, a Copy button reads "Copied". Convert any toast in a file you touch.

## Money is always shown in currency format
28 Oct 2026 (HELP-4369): an amount that has a currency is never printed as a bare number or as number + code ("169 INR", "12.5 EUR"), and never through an ad hoc `Intl.NumberFormat` without `style: "currency"`. Format it with `useLocaleStore()` from `@/Stores/locale`: `currencyFormat(code, amount)` for prices and totals (2 decimals), `currencyFormatUnitCost(code, amount)` for supplier unit costs and proposed prices, which are stored with 4 decimals (1,569 supplier products have costs like 0.0825 that `currencyFormat` would round to 0.08). In a translated string pass the formatted amount as one placeholder (`ctrans("current price :price", { price })`), not separate `:price :currency`. Money inputs take the currency too: PrimeVue `InputNumber` with `mode="currency"` (`currencyInputOptions(code)` for 2-decimal prices). When the backend only sends a summary string with a price in it, send the amount and currency code as fields and format them in the component.

## Whispers on shared presence channels never ask everyone to answer
INI-151 (28 Oct 2026): a whisper on grp.live.users reaches every tab of every colleague, all through one soketi process. A whisper that makes every tab reply (requestCurrentPage, sendTo<id> on joining) costs tabs² frames per (re)connection: ~700 staff tabs pushed 100 GB/h and froze soketi 1–17 s in UK hours, stalling every ShouldBroadcastNow (chat replies). A tab announces only itself (otherIsNavigating), with announcedUser() fields only, never ...auth.user. Data a newcomer needs from everyone comes from the server, not from replies. Nothing listened to staffTyping either: don't whisper without a listener.
