<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Email;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The on-hold notice goes out once, at import. A quarter of one account's channel orders were left
 * unpaid month after month (HELP-3116), so a single notice is not enough: this nudges again, once,
 * when an order has sat unpaid for a while.
 *
 * Each order is reminded exactly once, on the day it crosses the threshold, by selecting the
 * one-day window rather than everything older. The standing pile of old orders is CS's to chase
 * and must not be re-mailed every morning.
 */
class RemindChannelOrdersOnHold
{
    use AsAction;

    public const int REMIND_AFTER_DAYS = 7;

    public string $commandSignature = 'orders:remind_channel_orders_on_hold';
    public string $commandDescription = 'Send a second on-hold notice for channel orders that have sat unpaid for a week';

    /**
     * @return array<int, int> ids of the orders reminded
     */
    public function handle(?Command $command = null): array
    {
        $crossedAfter  = now()->subDays(self::REMIND_AFTER_DAYS + 1);
        $crossedBefore = now()->subDays(self::REMIND_AFTER_DAYS);

        $orderIds = Order::query()
            ->where('orders.state', OrderStateEnum::SUBMITTED)
            ->payNotSettled()
            ->whereBetween('orders.submitted_at', [$crossedAfter, $crossedBefore])
            ->whereHas('shop', fn ($query) => $query->where('type', ShopTypeEnum::DROPSHIPPING))
            ->whereHas('platform', fn ($query) => $query->whereNot('type', PlatformTypeEnum::MANUAL))
            ->pluck('orders.id')
            ->all();

        foreach ($orderIds as $orderId) {
            SendChannelOrderOnHoldEmail::dispatch($orderId);
        }

        $command?->info(count($orderIds).' channel orders reminded');

        return $orderIds;
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
