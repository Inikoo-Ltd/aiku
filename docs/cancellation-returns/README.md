# Cancellation returns

Goods picked for an order that was cancelled before it ever shipped. They are on a trolley, not on
the shelf, and someone has to walk them back. Shipped in HELP-2693.

| Document | Audience |
| --- | --- |
| This page | Warehouse and management. What changed when you cancel a delivery note. |
| [engineering.md](engineering.md) | Engineers. The stock ledger, the legacy path, what not to delete. |

## What changed

**Cancel Delivery Note** now opens a confirmation with a **Create Return** checkbox, ticked by
default.

**Ticked** — the delivery note is cancelled and a return delivery note is raised for everything that
was picked. The stock stays booked out until someone actually walks it back through
*Returning to Locations*, exactly like a customer return.

**Unticked** — the old behaviour. Every picked line is credited straight back to its location the
moment you cancel.

## Why it was worth changing

Cancelling used to credit the stock back immediately, while the goods were still sitting on the
trolley. For the minutes or hours until someone tidied up, aiku believed the stock was on the shelf
and offered it to the next picker, who then could not find it. Tomas raised it as *"we need to put
the items fast into locations"*; the deeper problem was that the system had already claimed they
were there.

## Where to look in the app

- **The popup** — any delivery note not yet dispatched, *Cancel Delivery Note*.
- **The return** — warehouse → Goods in → Returns. A cancellation is referenced
  `<delivery note>-cancel-pick`, against `-ret` for a real customer return.
- **The cancelled note** — its pickings are struck through and labelled **Returned** once the goods
  are back. Until then they read as live, because they are.

## How it differs from a customer return

A cancellation never shipped and was never invoiced, so there is nothing to refund and nothing to
replace. It finishes at **Done** without either. A customer return still behaves exactly as before.

Return counts in reporting separate the two, so cancellations do not inflate a customer's return
rate. The warehouse state badges deliberately count both, because both are work on the floor.

## One thing to know

Notes cancelled **before** this shipped were credited back the old way and have no return. Nothing
was lost — the stock is on the shelf in aiku, and physically back too if the floor tidied up. There
is nothing to backfill.
