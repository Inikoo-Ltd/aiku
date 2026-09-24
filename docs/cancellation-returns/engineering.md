# How it works, and what not to break

For engineers. Checked against the code on 22 September 2026.

## The problem it fixes

`CancelDeliveryNote` called `DeletePicking` for every picked line, and `DeletePicking` writes a
`CANCEL_PICKED` org stock movement — the stock was credited back to its location at the moment of
cancellation, while it was still physically on a trolley. aiku then offered it to the next picker.

The fix does not change what the ledger eventually records. It changes **when**, and who triggers
it: the put-away, not the cancellation.

## The flow

```
Cancel Delivery Note  (create_return = true)
        │
        ├─ DeletePicking SKIPPED ── the PICKED movement stands, stock stays booked out
        │
        ├─ delivery note → CANCELLED        (is_returned deliberately NOT set)
        │
        └─ ProcessReturnDeliveryNote(type: CANCELLATION)
                 │
                 ├─ items built from quantity_picked, not quantity_dispatched
                 └─ return delivery note in RECEIVED
                          │
                          ▼
                 RETURNING → someone sows the goods back
                          │
                          ▼
                 StoreSowing writes CANCEL_PICKED  ← the credit back, finally
                          │
                          ▼
                 RETURNED → DONE  (no invoice, no refund, no replacement)
```

The `PICKED` movement and the sowing's `CANCEL_PICKED` are a matched pair. Everything below exists
to keep that pair balanced.

## The type enum drives all of it

`ReturnDeliveryNoteTypeEnum` — `RETURN` (customer sent goods back) or `CANCELLATION` (picked, never
shipped). Existing rows default to `RETURN`, so nothing historical moved. Six places branch on it:

| Where | Cancellation does |
| --- | --- |
| `ProcessReturnDeliveryNote::afterValidator` | Expects the note in `CANCELLED`, not `DISPATCHED` |
| `ProcessReturnDeliveryNote::handle` | Filters lines on `quantity_picked`; skips `is_returned` |
| `StoreReturnDeliveryNoteItems` | `total_expected_qty` from `quantity_picked` |
| `StoreSowing` | Writes `CANCEL_PICKED` instead of `RETURN_PICKED` |
| `DeleteSowing` | Reverses with `PICKED` instead of `CANCEL_RETURN_PICKED` |
| `SetDoneReturnDeliveryNote` | Skips the invoice requirement, strips refund and replacement |

Miss `DeleteSowing` and undoing a put-away unbalances the ledger in the opposite direction. Miss
`SetDoneReturnDeliveryNote` and every cancellation strands in `RETURNED` forever, because a
cancelled order has no invoice and the guard throws *"Missing invoice detected"*.

## Do not delete the legacy branch

`CancelDeliveryNote::handle` keeps the old credit-back as its `$createReturn === false` path, and it
carries a comment saying so. It is not dead code:

- `asCommand` — `delivery_note:cancel` cancels without a return.
- `action()` — maintenance and repair callers, including `$repair = true`, which skips the picking
  loop entirely.
- `asController` — defaults to **false**, so anything not sending `create_return` keeps the old
  behaviour. Only the popup sends `true`.

Every one of those has to be migrated before the branch can go. Remove it early and cancelled stock
stops returning to its location at all.

## Guards worth knowing

**Nothing picked.** Cancelling a note where nothing was picked skips the return entirely.
`ProcessReturnDeliveryNote` refuses to create an empty return, and since the call sits inside the
cancellation transaction, without the guard the throw would roll the whole cancellation back.

**The reference.** The note is renamed to `<reference>-CANCELLED` *before* the return is raised, so
the return strips that suffix — `/-CANCELLED(-\d+)?$/`, which also catches the numbered variant —
and appends `-cancel-pick`. Without the strip you get `GB585339-CANCELLED-can`.

**Undo Pick on a cancelled note.** `is_editable` is true for any note viewed from a warehouse, so
the button renders on cancelled notes too. Clicking it after the goods were sowed back would write a
second `CANCEL_PICKED` and credit the stock twice. The undo, batch code and split buttons are hidden
once `is_returned_to_location` is set, which rides along as a subquery on `IndexDeliveryNoteItems`
rather than a query per row.

## Pickings are kept on purpose

A cancellation return does **not** delete the picking rows. Deleting via `DeletePicking` double
credits; deleting the row raw orphans the `PICKED` movement's back-reference
(`pickings.org_stock_movement_id`), which `SplitPicking`, `UpdatePicking` and
`MigratePickingReturnsToSowing` all read. The pick also genuinely happened, and `pickerPerformance`
in `ShowDispatchReports` counts picking rows — deleting them robs the picker of work they did. They
are struck through in the UI instead.

## Stats

The hydrators gained `number_return_delivery_notes_type_return` and `_type_cancellation` rather than
filtering the existing counts. Filtering would have desynced `number_return_delivery_notes_state_*`
from the lists those numbers badge — the warehouse sub-navigation and the Incoming hub widget both
show cancellations, so their counts must keep including them.

## Tests

`tests/Feature/DispatchingTest.php`, filter `HELP-2693`. Six tests: both cancel branches, the
empty-pick guard, the reference, the `CANCEL_PICKED`/`PICKED` ledger pair, and the
`is_returned_to_location` flag flipping only once the return is walked back.
