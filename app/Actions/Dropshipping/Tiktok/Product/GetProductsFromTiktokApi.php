<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetProductsFromTiktokApi extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'tiktok:import-products {tiktokUser}';

    /**
     * @throws \Throwable
     */
    public function handle(TiktokUser $tiktokUser): void
    {
        $products = $tiktokUser->getProducts([
            'status' => 'ACTIVATE'
        ], [
            'page_size' => 100
        ]);

        $shopType = $tiktokUser->customer->shop->type;
        $products = Arr::get($products, 'data.products');

        foreach ($products as $product) {
            DB::transaction(function () use ($product, $tiktokUser, $shopType) {
                $storedItem = StoredItem::where('fulfilment_customer_id', $tiktokUser->customer->fulfilmentCustomer->id)
                    ->where('reference', Str::slug($product['title']))->first();
                $storedItemTiktok = $storedItem?->tiktokPortfolio;

                if ($shopType === ShopTypeEnum::FULFILMENT && !$storedItemTiktok) {
                    if (!$storedItem) {
                        $storedItem = StoreStoredItem::make()->action($tiktokUser->customer->fulfilmentCustomer, [
                            'reference' => Str::slug($product['title']),
                            'name' => $product['title']
                        ]);
                    }

                    $portfolio = $storedItem->portfolio;
                    if (!$portfolio) {
                        $portfolio = StorePortfolio::make()->action($tiktokUser->customer, [
                            'stored_item_id' => $storedItem->id,
                            'type' => PortfolioTypeEnum::TIKTOK
                        ]);
                    }

                    $tiktokUser->products()->updateOrCreate([
                        'productable_id' => $storedItem->id,
                        'productable_type' => class_basename($storedItem),
                        'tiktok_user_id' => $tiktokUser->id,
                        'tiktok_product_id' => $product['id']
                    ], [
                        'tiktok_user_id' => $tiktokUser->id,
                        'productable_type' => class_basename($storedItem),
                        'productable_id' => $storedItem->id,
                        'tiktok_product_id' => $product['id'],
                        'portfolio_id' => $portfolio->id
                    ]);

                    UpdateStoredItem::run($storedItem, [
                        'state' => StoredItemStateEnum::SUBMITTED,
                        'total_quantity' => Arr::get($product, 'skus.0.inventory.quantity', 0)
                    ]);
                }
            });
        }
    }

    public function asCommand(Command $command)
    {
        $tiktokUser = TiktokUser::find($command->argument('TiktokUser'));

        $this->handle($tiktokUser);
    }

    public function asController(TiktokUser $tiktokUser): void
    {
        $this->handle($tiktokUser);
    }
}
