<?php

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class CancelOrdersInClosedShop
{
    use AsAction;

    public string $commandSignature = 'ordering:cancel_orders_in_closed_shops {organisation?} {--commit : Apply the changes, otherwise dry run}';

    public function handle(Shop $shop): int
    {
        if ($shop->state != ShopStateEnum::CLOSED) {
            return 0;
        }

        $cancelled = 0;
        foreach ($this->cancellableOrders($shop)->get() as $order) {
            try {
                CancelOrder::make()->action($order);
                $cancelled++;
            } catch (Throwable) {
            }
        }

        return $cancelled;
    }

    protected function cancellableOrders(Shop $shop)
    {
        return Order::where('shop_id', $shop->id)
            ->where('state', OrderStateEnum::CREATING)
            ->where(fn ($q) => $q->where('payment_amount', '<=', 0)->orWhereNull('payment_amount'))
            ->whereDoesntHave('invoices');
    }

    public function asCommand(Command $command): int
    {
        $commit = (bool)$command->option('commit');

        $shops = Shop::where('state', ShopStateEnum::CLOSED)
            ->when($command->argument('organisation'), function ($query) use ($command) {
                $query->whereHas('organisation', fn ($q) => $q->where('slug', $command->argument('organisation')));
            })
            ->get();

        $total = 0;
        foreach ($shops as $shop) {
            $cancellable = $this->cancellableOrders($shop)->count();
            if ($cancellable > 0) {
                $command->line(($commit ? 'CANCELLING ' : 'WOULD CANCEL ')."$cancellable unpaid in-basket orders in {$shop->organisation->slug}/{$shop->slug} ({$shop->name})");
                $total += $commit ? $this->handle($shop) : $cancellable;
            }

            $flagged = Order::where('shop_id', $shop->id)
                ->whereNotIn('state', [OrderStateEnum::FINALISED, OrderStateEnum::DISPATCHED, OrderStateEnum::CANCELLED])
                ->where(fn ($q) => $q->where('payment_amount', '>', 0)->orWhere('state', '!=', OrderStateEnum::CREATING))
                ->get();
            foreach ($flagged as $order) {
                $command->warn("NEEDS REVIEW {$shop->slug} order {$order->reference}: state {$order->state->value}, payment_amount {$order->payment_amount} — cancelling would issue store credit or touch fulfilment");
            }
        }

        $command->info(($commit ? 'Cancelled' : 'Would cancel').": $total orders");

        return 0;
    }
}
