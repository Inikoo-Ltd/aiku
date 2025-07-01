<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Jun 2025 13:43:12 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\DeleteWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairDiscontinueVariants
{
    use WithActionUpdate;


    public function handle(Product $product, Command $command): void
    {
        if ($product->shop->type == ShopTypeEnum::DROPSHIPPING && !$product->is_main) {

            $command->line('Discontinuing variant: ' . $product->code . ' - ' . $product->name.' '.$product->status->value);

            UpdateProduct::make()->action(
                $product,
                [
                    'status' => ProductStatusEnum::DISCONTINUED,
                    'state'  => ProductStateEnum::DISCONTINUED
                ]
            );

            if ($product->webpage) {
                DeleteWebpage::make()->action($product->webpage, forceDelete: true);
            }
        }
    }


    public string $commandSignature = 'repair:discontinue_variants';

    public function asCommand(Command $command): void
    {
        $dropshippingShops = Shop::where('type', ShopTypeEnum::DROPSHIPPING)->pluck('id')->toArray();
        Product::whereNot('is_main')->whereIn('shop_id', $dropshippingShops)->orderBy('products.id')
            ->chunk(100, function (Collection $models) use ($command) {
                foreach ($models as $model) {
                    $this->handle($model, $command);
                }
            });
    }

}
