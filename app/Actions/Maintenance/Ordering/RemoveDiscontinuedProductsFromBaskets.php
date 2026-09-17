<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sept 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveDiscontinuedProductsFromBaskets
{
    use AsAction;

    public string $commandSignature = 'orders:remove_discontinued_from_baskets {shop} {--live : Actually delete, otherwise dry run}';

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, bool $live, ?Command $command = null): int
    {
        $lines = Transaction::query()
            ->select('transactions.*')
            ->join('orders', 'orders.id', 'transactions.order_id')
            ->join('products', 'products.id', 'transactions.model_id')
            ->where('transactions.model_type', 'Product')
            ->where('orders.shop_id', $shop->id)
            ->where('orders.state', OrderStateEnum::CREATING)
            ->where('products.state', ProductStateEnum::DISCONTINUED)
            ->orderBy('transactions.id')
            ->with('model:id,code', 'order:id,reference')
            ->get();

        foreach ($lines as $line) {
            $command?->line(sprintf('%s  %s x %s', $line->order->reference, $line->model->code, (float)$line->quantity_ordered));
            if ($live) {
                DeleteTransaction::make()->action($line);
            }
        }

        return $lines->count();
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $live = (bool)$command->option('live');

        $count = $this->handle($shop, $live, $command);

        $command->info(sprintf('%d basket lines with discontinued products %s', $count, $live ? 'removed' : 'would be removed (dry run, add --live)'));

        return 0;
    }
}
