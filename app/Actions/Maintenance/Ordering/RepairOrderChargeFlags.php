<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairOrderChargeFlags
{
    use AsAction;

    public string $commandSignature = 'repair:order_charge_flags {--commit : Persist the fixes, dry run otherwise}';

    private const array FLAG_BY_CHARGE_TYPE = [
        ChargeTypeEnum::PREMIUM->value   => 'is_premium_dispatch',
        ChargeTypeEnum::INSURANCE->value => 'has_insurance',
        ChargeTypeEnum::PACKING->value   => 'has_extra_packing',
    ];

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $commit = (bool)$command->option('commit');

        $rows = [];
        foreach (self::FLAG_BY_CHARGE_TYPE as $chargeType => $flag) {
            $orderIds = $this->ordersMissingFlag($chargeType, $flag)->pluck('orders.id');
            $deliveryNoteIds = DB::table('delivery_note_order')
                ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_order.delivery_note_id')
                ->whereIn('delivery_note_order.order_id', $orderIds)
                ->whereNotIn('delivery_notes.state', ['dispatched', 'cancelled'])
                ->where(fn (Builder $query) => $query->where("delivery_notes.$flag", '!=', true)->orWhereNull("delivery_notes.$flag"))
                ->pluck('delivery_notes.id');

            $rows[] = [$chargeType, $flag, $orderIds->count(), $deliveryNoteIds->count()];

            if ($commit) {
                DB::table('orders')->whereIn('id', $orderIds)->update([$flag => true]);
                DB::table('delivery_notes')->whereIn('id', $deliveryNoteIds)->update([$flag => true]);
            }
        }

        $command->table(['Charge', 'Flag', 'Orders', 'Delivery notes'], $rows);
        if (!$commit) {
            $command->warn('Dry run: re-run with --commit to persist');
        }

        return 0;
    }

    private function ordersMissingFlag(string $chargeType, string $flag): Builder
    {
        return DB::table('orders')
            ->join('transactions', 'transactions.order_id', '=', 'orders.id')
            ->join('charges', 'charges.id', '=', 'transactions.model_id')
            ->where('transactions.model_type', 'Charge')
            ->where('transactions.state', '!=', TransactionStateEnum::CANCELLED->value)
            ->where('charges.type', $chargeType)
            ->whereNotIn('orders.state', [OrderStateEnum::DISPATCHED->value, OrderStateEnum::CANCELLED->value])
            ->where(fn (Builder $query) => $query->where("orders.$flag", '!=', true)->orWhereNull("orders.$flag"))
            ->distinct();
    }
}
