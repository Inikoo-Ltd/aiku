<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sept 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * AROMA moved to aiku while orders submitted in Aurora were still in the warehouse. Those are
 * finished in Aurora and fetched afterwards, so they are locked against aiku staff until then.
 * Orders born in aiku, and anything already dispatched or cancelled, are left alone.
 */
class LockAromaOrdersInProcessInAurora
{
    use AsAction;

    public string $commandSignature = 'aroma:lock_orders_in_process_in_aurora {--live : Actually write, otherwise dry run}';

    public function handle(Shop $shop, bool $live, ?Command $command = null): array
    {
        $orders = Order::where('shop_id', $shop->id)
            ->whereNotNull('source_id')
            ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::DISPATCHED, OrderStateEnum::CANCELLED])
            ->orderBy('id')
            ->get();

        $deliveryNotes = DeliveryNote::where('shop_id', $shop->id)
            ->whereNotNull('source_id')
            ->whereNotIn('state', [DeliveryNoteStateEnum::DISPATCHED, DeliveryNoteStateEnum::CANCELLED])
            ->whereIn('id', DB::table('delivery_note_order')->whereIn('order_id', $orders->pluck('id'))->select('delivery_note_id'))
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $command?->line(sprintf('order %s  %s', $order->reference, $order->state->value));
        }
        foreach ($deliveryNotes as $deliveryNote) {
            $command?->line(sprintf('delivery note %s  %s', $deliveryNote->reference, $deliveryNote->state->value));
        }

        if ($live) {
            Order::whereIn('id', $orders->pluck('id'))->update(['handled_in_aurora' => true]);
            DeliveryNote::whereIn('id', $deliveryNotes->pluck('id'))->update(['handled_in_aurora' => true]);
        }

        return [$orders->count(), $deliveryNotes->count()];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shop = Shop::where('code', 'AROMA')->firstOrFail();
        $live = (bool)$command->option('live');

        [$numberOrders, $numberDeliveryNotes] = $this->handle($shop, $live, $command);

        $command->info(sprintf(
            '%d orders and %d delivery notes %s',
            $numberOrders,
            $numberDeliveryNotes,
            $live ? 'locked' : 'would be locked (dry run, add --live)'
        ));

        return 0;
    }
}
