<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Actions\Comms\Email\SendPreOrderUpdateEmail;
use App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

/**
 * Daily: balance reminders on the shop's reminder days; pre-orders whose balance is still unpaid
 * after the shop's limit are cancelled keeping the deposit, their goods back on sale; and
 * back-orders whose purchase order now lands after the promised dispatch get the new dates by
 * email, with their cancellation options.
 */
class ProcessPreOrders
{
    use AsAction;

    public string $commandSignature = 'pre_orders:process';

    public function handle(): array
    {
        $done = ['reminders' => 0, 'cancelled' => 0, 'slipped' => $this->moveSlippedBackOrders()];

        foreach (PreOrder::where('state', PreOrderStateEnum::BALANCE_REQUESTED)->with('shop')->get() as $preOrder) {
            try {
                $daysSinceRequest = (int) $preOrder->balance_requested_at->copy()->startOfDay()->diffInDays(now()->startOfDay());
                $shop             = $preOrder->shop;

                if ($daysSinceRequest >= (int) $shop->preOrderSetting('balance_cancel_after_days')) {
                    CancelPreOrder::run($preOrder, PreOrderCancellationReasonEnum::BALANCE_NOT_PAID);
                    $done['cancelled']++;
                } elseif (!$preOrder->balance_second_reminder_sent_at && $daysSinceRequest >= (int) $shop->preOrderSetting('balance_second_reminder_day')) {
                    $preOrder->update(['balance_second_reminder_sent_at' => now()]);
                    SendPreOrderUpdateEmail::dispatch($preOrder, SendPreOrderUpdateEmail::BALANCE_REMINDER);
                    $done['reminders']++;
                } elseif (!$preOrder->balance_first_reminder_sent_at && $daysSinceRequest >= (int) $shop->preOrderSetting('balance_first_reminder_day')) {
                    $preOrder->update(['balance_first_reminder_sent_at' => now()]);
                    SendPreOrderUpdateEmail::dispatch($preOrder, SendPreOrderUpdateEmail::BALANCE_REMINDER);
                    $done['reminders']++;
                }
            } catch (\Throwable $e) {
                Sentry::captureException($e);
            }
        }

        return $done;
    }

    private function moveSlippedBackOrders(): int
    {
        $slipped = 0;

        foreach (PreOrder::where('state', PreOrderStateEnum::WAITING_FOR_GOODS)->where('has_back_order', true)->with('shop')->get() as $preOrder) {
            try {
                $productIds = $preOrder->order->transactions()->where('model_type', 'Product')->pluck('model_id')->all();
                $latestEta  = collect(GetProductIncomingStock::make()->earliestEtaByProduct($productIds))->max();

                if (!$latestEta || !$preOrder->estimated_dispatch_to || Carbon::parse($latestEta)->lte($preOrder->estimated_dispatch_to)) {
                    continue;
                }

                UpdatePreOrderDispatchDates::run(
                    $preOrder,
                    $latestEta,
                    Carbon::parse($latestEta)->addWeeks((int) $preOrder->shop->preOrderSetting('dispatch_range_weeks'))->toDateString()
                );
                $slipped++;
            } catch (\Throwable $e) {
                Sentry::captureException($e);
            }
        }

        return $slipped;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $done = $this->handle();
        $command->info("Reminders sent: {$done['reminders']}, cancelled: {$done['cancelled']}, new dispatch dates: {$done['slipped']}");

        return 0;
    }
}
