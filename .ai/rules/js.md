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
