---
title: Which tax does this order pay?
summary: Selling across Europe and the UK means every order has to answer one question before a price is final — and the answer depends on two addresses, a postcode, and whether a VAT number is real. How aiku validates tax numbers against VIES and HMRC (with retries, rate limits and an audit trail), and the decision tree that turns a shipping address into a tax category, Canaries and Madeira included.
date: 2026-08-03
tags: tax, accounting, vies, ordering, europe
---

A B2B order from a shop in one EU country to a customer in another is zero‑rated *if* the customer's VAT number is valid — and standard‑rated if it is not. A parcel to Tenerife is outside EU VAT even though Tenerife is Spain. A parcel to Madeira pays a different Portuguese rate than Lisbon. Monaco is France for this purpose. A Spanish sole trader on the surcharge regime pays an extra 5.2% on top of IVA. And in the UK, after Brexit, the question is simply "are both addresses in the UK?".

aiku sells from shops in several of these countries to customers in all of them, so every order resolves a **tax category** before its totals mean anything. This note is how.

## First: is the tax number real?

A customer gives us a VAT number. We store it with a *type* (EU VAT, UK VAT, other), a *status* (valid, invalid, not applicable, unknown), how it was validated (online, basic format check, manually by staff), and when it was last checked.

**EU numbers go to VIES**, the European Commission's SOAP service. It is free, authoritative, and not always up. So the check is built for the real world: a ten‑second timeout; a basic format check first so we never call it for "1234"; **re‑checks scheduled at 2, 24 and 48 hours** after a failure in case the failure was VIES's, not the number's; a cap of twenty online checks per hour per shop so a bulk import cannot get us throttled; and a custom audit entry on every status change so an accountant can see *why* this customer was zero‑rated on that date. A number that VIES rejects is marked invalid with the timestamp, and the customer's future orders are rated accordingly until someone fixes the number.

**UK numbers go to HMRC's** check‑VAT API, behind OAuth client credentials, when the integration is enabled for the shop; otherwise a format check. Same statuses, same audit.

Staff can override — mark a number valid manually with a reason — because registries lag and customers do not wait. The override is visible as such.

## Then: which category?

With the number known, the order's tax category is a function of the shop's country, the billing address, the delivery address, the number, and one flag. The tree, as it actually runs:

```
shop in GB:
    both addresses in the UK            → UK standard
    otherwise                           → outside scope

shop in the EU:
    customer in the shop's own country:
        ES + Canaries/Ceuta/Melilla
          (postcodes 35, 38, 51, 52)    → outside scope
        ES + surcharge customer         → IVA + recargo de equivalencia
        otherwise                       → that country's standard rate
    both addresses in the EU
      and VAT number valid              → intra‑EU reverse charge (0%)
    delivery to Monaco                  → French VAT
    delivery to Madeira (90–94)         → PT reduced regional rate
    delivery to Azores (9x)             → PT reduced regional rate
    delivery elsewhere in the EU        → destination country's standard rate
    otherwise                           → outside scope
```

Every leaf is a row in the `tax_categories` table — a rate, a label, a country, a type, an active flag — not a constant in code. When a rate changes, a row changes. When a new regime appears (the Spanish surcharge was one), it is a new row plus one branch.

The tree is deliberately explicit rather than clever. Tax rules are lists of exceptions; a "general" algorithm that derives them is a place for a bug to hide, and the branch that says *Madeira* in plain text is the one an accountant can read and agree with.

## The parts that talk to each other

- The category lives on the **order** and, since this summer, [per line](/blog/tax-per-line-not-per-order): the order's category says *which country's regime*; the line's preset says *which rate inside it* (standard, food, …). Reverse charge and outside‑scope override the line to zero, as they should.
- Changing a customer's VAT status **re‑rates their open baskets**, not their invoices. The past is frozen; the future follows the number.
- Invoices carry both the category and the number that justified it, so a zero‑rated invoice can always show the validated number it relied on.

## What we learned

Treat tax‑number validation as an *operation with a history*, not a boolean: status, method, timestamp, audit, retry. Write the tax tree as the list of exceptions it really is, one readable branch per regime, with the rates in data. And test it with real addresses — a Tenerife postcode, a Funchal postcode, a Monaco delivery — against the real database, because that is where the next exception will be found.
