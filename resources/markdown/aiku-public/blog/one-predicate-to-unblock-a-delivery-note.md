---
title: One predicate to unblock a delivery note
summary: A warehouse note that is "blocked" with nothing blocking it is the worst kind of bug — invisible until a picker is standing there with no button. We replaced four cached flags and six readers with two generated columns, one predicate and an alarm.
date: 2026-08-21
tags: warehouse, postgres, reliability
---

A delivery note moves through the warehouse in states: queued, handling, handling‑blocked, packed, finalised, dispatched. *Handling‑blocked* exists for a good reason — an item is waiting on stock, or on a decision from customer service — and the screen hides the "continue" buttons until the block is cleared.

Every few weeks someone reported a note stuck in handling‑blocked with no visible reason and no way out. Each time we found the culprit, added a clearer or an `if`, and the next one came from a different direction.

## Flag rot

The note's items carried four cached booleans: `is_dirty`, `is_handled`, `has_waiting_warehouse`, `has_waiting_crm`. Fifteen code paths wrote them. Six different places read them, each with its own idea of what "blocked" meant. Add a new write path that forgets one flag, or a new reader with a slightly different condition, and you get a note whose flags say "blocked" while its quantities say "fine".

Patching readers is a treadmill. The only end is to have nothing stored that can rot.

## Generated columns

The two `has_waiting_*` flags are now **generated columns** in PostgreSQL:

```sql
ALTER TABLE delivery_note_items
  ADD COLUMN has_waiting_warehouse boolean
  GENERATED ALWAYS AS (quantity_waiting_warehouse > 0) VIRTUAL;
```

They cannot be written — an `UPDATE` that tries is an error, which is the point. On PostgreSQL 18, `VIRTUAL` makes this a metadata‑only change; on 17 it is `STORED`, which rewrites the table and took six minutes on our CI fixture. Worth it once.

`quantity_required` can now only change through a single action, and that action recalculates the picked totals. One writer, no forgetting.

## One predicate

Everything that decides whether a note may move forward calls the same method:

```php
public function hasBlockingItems(): bool
{
    return $this->items()
        ->where(fn ($q) => $q
            ->where('has_waiting_warehouse', true)
            ->orWhere('has_waiting_crm', true)
            ->orWhere(fn ($q) => $q
                ->where('is_dirty', true)
                ->where('is_handled', false)
                ->whereColumn('quantity_picked', '<=', 'quantity_required')))
        ->exists();
}
```

Every gate — moving to picked, starting packing, undoing packing, packing by scan, resuming the note on screen — asks that one question. Moving a note *into* handling‑blocked is refused if nothing blocks it. Some packing gates became stricter as a result; that was deliberate.

## An alarm, because write paths get forgotten

An hourly job sweeps notes that have sat in handling‑blocked for more than thirty minutes. For each it recalculates and, if nothing blocks, moves it on — and raises a warning in error tracking that says, in effect, *a write path forgot*. The sweep is not the fix; the warning tells us where the next fix goes.

## The rule we now follow

Never add a new reader of these flags with its own condition; call `hasBlockingItems()`. Never write `has_waiting_*`; the database will stop you. If the sweep warning fires, find the writer — do not just re‑run the sweep.

It has been quiet since.
