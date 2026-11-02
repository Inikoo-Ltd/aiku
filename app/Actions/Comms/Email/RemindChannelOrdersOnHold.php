<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderCancellationReasonEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The on-hold notice goes out once, at import. A quarter of one account's channel orders were left
 * unpaid month after month (HELP-3116), so a single notice is not enough: by default this sends it
 * again once, on day 7. A shop that opts in (HELP-4426) instead sends a reminder and a final reminder
 * on its own days, the final one giving the date to pay by, and cancels the order on its cancel day.
 *
 * Each order is reminded exactly once per stage, on the calendar day it reaches the threshold, by
 * selecting that one day rather than everything older. Only orders that were sent the final reminder are
 * cancelled, so the standing pile from before a shop opted in stays CS's to chase.
 */
class RemindChannelOrdersOnHold
{
    use AsAction;

    public const int REMIND_AFTER_DAYS = 7;

    public string $commandSignature = 'orders:remind_channel_orders_on_hold';
    public string $commandDescription = 'Remind unpaid channel orders, and cancel the ones given a final reminder where the shop opted in';

    /**
     * @return array{reminded: array<int, int>, warned: array<int, int>, cancelled: array<int, int>} order ids
     */
    public function handle(?Command $command = null): array
    {
        $result = ['reminded' => [], 'warned' => [], 'cancelled' => []];

        foreach (Shop::where('type', ShopTypeEnum::DROPSHIPPING)->get() as $shop) {
            if (!$shop->cancelsUnpaidChannelOrders()) {
                foreach ($this->ordersCrossing($shop, self::REMIND_AFTER_DAYS)->pluck('orders.id') as $orderId) {
                    SendChannelOrderOnHoldEmail::dispatch($orderId);
                    $result['reminded'][] = $orderId;
                }

                continue;
            }

            $result['cancelled'] = array_merge($result['cancelled'], $this->cancelWarnedOrders($shop));

            foreach ($this->ordersCrossing($shop, $shop->unpaidChannelOrderSetting('reminder_day'))->pluck('orders.id') as $orderId) {
                SendChannelOrderOnHoldEmail::dispatch($orderId, SendChannelOrderOnHoldEmail::REMINDER);
                $result['reminded'][] = $orderId;
            }

            /** The email job marks the order once the final reminder is sent; the mark also keeps a re-run from warning it twice */
            foreach ($this->ordersCrossing($shop, $shop->unpaidChannelOrderSetting('final_reminder_day'))->whereNull('orders.data->final_payment_reminder_at')->pluck('orders.id') as $orderId) {
                SendChannelOrderOnHoldEmail::dispatch($orderId, SendChannelOrderOnHoldEmail::FINAL_REMINDER);
                $result['warned'][] = $orderId;
            }
        }

        $command?->info(count($result['reminded']).' reminded, '.count($result['warned']).' warned, '.count($result['cancelled']).' cancelled');

        return $result;
    }

    /**
     * The final reminder names the day before the cancel day as the last day to pay, and days are
     * calendar days, so this always runs after that day has ended. An order is only cancelled once
     * its final reminder was sent, before today.
     *
     * @return array<int, int>
     */
    protected function cancelWarnedOrders(Shop $shop): array
    {
        $cancelled = [];

        $orders = $this->unpaidChannelOrders($shop)
            ->whereNotNull('orders.data->final_payment_reminder_at')
            ->where('orders.data->final_payment_reminder_at', '<', now()->startOfDay()->toIso8601String())
            ->whereDate('orders.submitted_at', '<=', now()->subDays($shop->unpaidChannelOrderSetting('cancel_after_days'))->toDateString())
            ->get();

        foreach ($orders as $order) {
            try {
                CancelOrder::make()->action($order, [
                    'cancellation_reason' => OrderCancellationReasonEnum::UNPAID_PAST_DEADLINE,
                    'cancellation_notes'  => 'Not paid by the date given in the final payment reminder',
                ]);
                $cancelled[] = $order->id;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $cancelled;
    }

    protected function ordersCrossing(Shop $shop, int $days): Builder
    {
        return $this->unpaidChannelOrders($shop)
            ->whereDate('orders.submitted_at', now()->subDays($days)->toDateString());
    }

    /** An order we do not deliver to is held for its address, not for payment: paying would not send it out. */
    protected function unpaidChannelOrders(Shop $shop): Builder
    {
        return Order::query()
            ->where('orders.shop_id', $shop->id)
            ->where('orders.state', OrderStateEnum::SUBMITTED)
            ->payNotSettled()
            ->whereNull('orders.data->banned_delivery')
            ->whereHas('platform', fn ($query) => $query->whereNot('type', PlatformTypeEnum::MANUAL));
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
