<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Dispatching\DeliveryNote\CalculateDeliveryNoteTotalAmounts;
use App\Actions\Dispatching\DeliveryNoteItem\SyncDeliveryNoteItemsRequiredPickQuantity;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Repairs orders whose delivery note item quantities were rewritten by a product
 * composition change after picking had already started, which then inflated the
 * transaction amounts through the picked/required ratio (e.g. gb561282, BBUG-01 ×12).
 *
 * For each product transaction of the given orders: the required quantity is recomputed
 * from today's composition scaled back to the pack that was sold, the picked quantity is
 * re-expressed in the same terms preserving the picked fraction, and the transaction
 * amounts are restored to historic price × quantity ordered with the discount factor kept.
 */
class RepairCompositionCorruptedPickingQuantities
{
    use AsAction;

    public string $commandSignature = 'maintenance:repair_composition_corrupted_picking_quantities {orders* : Order slugs} {--commit : Persist the fixes, dry run otherwise}';

    public function asCommand(Command $command): int
    {
        $commit = (bool)$command->option('commit');
        $sync   = SyncDeliveryNoteItemsRequiredPickQuantity::make();

        foreach ($command->argument('orders') as $orderSlug) {
            $order = Order::where('slug', $orderSlug)->first();
            if (!$order) {
                $command->getOutput()->writeln("Order $orderSlug not found");
                continue;
            }

            $touchedDeliveryNotes = [];

            foreach ($order->transactions()->where('model_type', 'Product')->get() as $transaction) {
                if ($transaction->quantity_ordered + $transaction->quantity_bonus == 0) {
                    continue;
                }

                $factor = $transaction->current_discount_factor
                    ?? ($transaction->gross_amount != 0 ? $transaction->net_amount / $transaction->gross_amount : 1);

                $expectedGross = round($transaction->historicAsset->price * $transaction->quantity_ordered, 2);
                $expectedNet   = round($expectedGross * $factor, 2);
                if ($transaction->is_gift) {
                    $expectedGross = 0;
                    $expectedNet   = 0;
                }

                $itemFixes = [];
                foreach ($transaction->deliveryNoteItems as $deliveryNoteItem) {
                    if (in_array($deliveryNoteItem->deliveryNote->state, [
                        DeliveryNoteStateEnum::DISPATCHED,
                        DeliveryNoteStateEnum::CANCELLED,
                        DeliveryNoteStateEnum::FINALISED,
                    ])) {
                        continue;
                    }

                    $expectedRequired = $sync->getQuantityRequired($deliveryNoteItem->orgStock, $deliveryNoteItem);
                    if (is_null($expectedRequired)) {
                        continue;
                    }

                    $requiredWrong = abs($expectedRequired - (float)$deliveryNoteItem->quantity_required) > 0.000001;
                    $overPicked    = (float)$deliveryNoteItem->quantity_picked > $expectedRequired + 0.000001;

                    if (!$requiredWrong && !$overPicked) {
                        continue;
                    }

                    $pickedFraction = $deliveryNoteItem->quantity_required != 0
                        ? min(1, $deliveryNoteItem->quantity_picked / $deliveryNoteItem->quantity_required)
                        : 0;

                    $itemFixes[] = [
                        'item'     => $deliveryNoteItem,
                        'required' => $expectedRequired,
                        'picked'   => round($expectedRequired * $pickedFraction, 6),
                    ];
                }

                /*
                 * Mid-picking amounts are legitimately picked-quantity-based (zero for
                 * unpicked, fractional for partial), so only inflation above the ordered
                 * amount — the corrupted picked/required ratio — is repaired.
                 */
                $amountsWrong = $transaction->gross_amount - $expectedGross > 0.005;

                if (!$amountsWrong && empty($itemFixes)) {
                    continue;
                }

                $command->getOutput()->writeln(sprintf(
                    '%s %s | gross %s -> %s | net %s -> %s',
                    $order->slug,
                    $transaction->asset->code,
                    $transaction->gross_amount,
                    $expectedGross,
                    $transaction->net_amount,
                    $expectedNet
                ));
                foreach ($itemFixes as $fix) {
                    $command->getOutput()->writeln(sprintf(
                        '    dni %d | required %s -> %s | picked %s -> %s',
                        $fix['item']->id,
                        $fix['item']->quantity_required,
                        $fix['required'],
                        $fix['item']->quantity_picked,
                        $fix['picked']
                    ));
                }

                if (!$commit) {
                    continue;
                }

                DB::transaction(function () use ($transaction, $itemFixes, $expectedGross, $expectedNet, $amountsWrong, &$touchedDeliveryNotes) {
                    foreach ($itemFixes as $fix) {
                        $fix['item']->update([
                            'quantity_required'                   => $fix['required'],
                            'original_quantity_required'          => $fix['required'],
                            'quantity_picked'                     => $fix['picked'],
                            'composition_dirty_at'                => null,
                            'composition_dirty_quantity_required' => null,
                        ]);
                        $touchedDeliveryNotes[$fix['item']->delivery_note_id] = $fix['item']->deliveryNote;
                    }

                    if ($amountsWrong) {
                        $transaction->update([
                            'gross_amount'   => $expectedGross,
                            'net_amount'     => $expectedNet,
                            'org_net_amount' => $expectedNet * $transaction->org_exchange,
                            'grp_net_amount' => $expectedNet * $transaction->grp_exchange,
                        ]);
                    }
                });
            }

            if ($commit) {
                CalculateOrderTotalAmounts::run($order->refresh(), false, false);
                foreach ($touchedDeliveryNotes as $deliveryNote) {
                    CalculateDeliveryNoteTotalAmounts::run($deliveryNote);
                }
                $command->getOutput()->writeln("$orderSlug repaired");
            }
        }

        if (!$commit) {
            $command->getOutput()->writeln('Dry run, nothing persisted (use --commit)');
        }

        return 0;
    }
}
