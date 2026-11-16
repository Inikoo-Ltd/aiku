---
paths:
  - 'app/Actions/Goods/**'
---

# Goods

## Trade unit dimensions are stored in metres; units is only how they are shown
INI-274 (16 Nov 2026). trade_units.marketing_dimensions (and products/master_assets copies) hold l/w/h in METRES; `units` (mm/cm/m/inch) is only the unit to display them in, and `type` picks the shape. DimensionsFormatter, PureInputDimension.vue, eBay and Shopify all read it that way. Any writer taking sizes typed in a unit must convert with DimensionsFormatter::toMetres() and always set type and units; readers that show numbers back in the unit use fromMetres(). Rows with no units/type show no dimensions on sites and feeds.
