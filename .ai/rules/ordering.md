---
paths:
  - 'app/Actions/Ordering/**'
  - 'resources/js/Pages/Grp/Org/Ordering/**'
---

# Ordering

## Order payment box always shows the amount left to pay, also while in the warehouse
Settled after three reversals (24 Aug hid amount and Pay button, 30 Sep HELP-3596 restored Pay, 12 Oct HELP-3896 restored the amount). While an order is in_warehouse / handling / handling_blocked (`isOrderAmountsProvisional`), `NeedToPayV2` keeps BOTH the "Need to pay X of Y" line and the Pay button. What marks the figure as provisional is the grey box, the "In warehouse" heading, the "Amounts will be recalculated when picking is finished" note and the warning above Pay, never hiding the number or the button: staff add products to orders being picked and must know what to charge. The box and the Pay window read the same `box_stats.products.payment.pay_amount` (`IsOrder`), keep it that way.

## Shop charges: read the trigger in the action, not the shop settings
What a customer pays in charges is decided by the `Calculate*Charges` actions; derive it from them, never from shop settings or a charge's description (some descriptions mention exemptions the code does not apply). `shops.settings.can_collect` is written only by `FetchAuroraShop` and read by nothing. `CalculateCollectionCharges` adds the shop's active COLLECTION charge whenever the order has a `collection_address_id`, and removes it when it has none; it has no MANUAL charges-engine check. `CalculateOrderHangingCharges` (the small-order charge) returns early when `charges_engine` is MANUAL, then matches `goods_amount` against the charge's `settings.rules` (`'<;limit'` or `'>;limit'`): `<` is strict and never applies to a goods amount of 0, so an order exactly at the limit pays nothing.

## Shipping step bands are inclusive at both ends, first match wins
`CalculateOrderShipping::getPriceBlanketFromAmount` takes the first step where `from <= amount <= to` (or `to == 'INF'` and `amount >= from`), in the order the steps are stored. Weight pricing passes `estimated_weight / 1000` (kg), so with steps 0-2 and 2-5 an order of exactly 2 kg pays the 0-2 band. The same function prices "Step Order Items Net Amount" on `goods_amount`, so net-amount bands behave the same way. When no step matches (above the last bound of a zone without an INF step, or in a gap between steps) it returns `'TBC'`, never free delivery (PRE-22: dssk zones end at 30 kg and heavier orders were invoiced €0 shipping).
