<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveAuroraGrGiftsOrdersInBasket implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int|null $orderID): string
    {
        return $orderID ?? 'empty';
    }

    public function handle(int|null $orderID, Command|null $command = null): void
    {
        if (!$orderID) {
            return;
        }
        $order = Order::find($orderID);
        if (!$order) {
            return;
        }

        foreach ($order->transactions as $transaction) {
            if ($transaction->model instanceof Product and $transaction->quantity_bonus > 0) {
                $product = $transaction->model;

                if (stripos($product->code, 'GR-') === 0 || stripos($product->code, 'PI-01') === 0) {
                    $transaction->forceDelete();
                    $command?->warn('GR Product deleted '.$product->code);
                } else {
                    if (!in_array($product->code, [
                        'ArTeaP-20',
                        'ArTeaP-18',
                        'LSF-02',
                        'SSL-02',
                        'Begg-02',
                        'MSJ-14'
                    ])) {
                        $command?->info('Not GR Product in bonus '.$product->code);
                    }
                }
            }
        }
    }


    public string $commandSignature = 'orders:remove_aurora_gr_gifts {shop?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('shop')) {
            $shop     = Shop::where('slug', $command->argument('shop'))->firstOrFail();
            $shopsIds = [$shop->id];
        } else {
            $shopsIds = Shop::where('is_aiku', true)->where('type', ShopTypeEnum::B2B)->pluck('id')->toArray();
        }


        $count = Order::where('state', OrderStateEnum::CREATING)->whereIn('shop_id', $shopsIds)->count();


        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Order::where('state', OrderStateEnum::CREATING)->whereIn('shop_id', $shopsIds)->orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar, $command) {
                foreach ($models as $model) {
                    $this->handle($model->id, $command);
                    //$bar->advance();
                }
            });
    }

}
