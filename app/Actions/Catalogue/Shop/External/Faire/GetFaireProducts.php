<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetFaireProducts extends OrgAction
{
    public string $commandSignature = 'faire:products {shop}';

    public $jobQueue = 'low-priority';

    public function handle(Shop $shop): void
    {
        $faireProducts = $shop->getFaireProducts([
            'limit' => 250
        ]);
        // foreach to support of faire has more than 250 products

        foreach (Arr::get($faireProducts, 'products', []) as $faireProduct) {
            foreach ($faireProduct['variants'] as $variant) {
                if (Product::where('shop_id', $shop->id)->where('code', $variant['sku'])->exists()) {
                    $product = Product::where('shop_id', $shop->id)->where('code', $variant['sku'])->first();
                    UpdateProduct::make()->action($product, [
                        'code'        => $variant['sku'],
                        'name'        => $faireProduct['name'].' - '.$variant['name'],
                        'description' => $faireProduct['description'],
                        'rrp'         => Arr::get($variant, 'prices.0.retail_price.amount_minor') / 100,
                        'price'       => Arr::get($variant, 'prices.0.wholesale_price.amount_minor') / 100,
                        'units'       => $faireProduct['unit_multiplier'],
                        'data'        => [
                            'faire' => $variant
                        ]
                    ], strict: false);

                    continue;
                }

                StoreProduct::make()->action($shop, [
                    'code'         => $variant['sku'],
                    'name'         => $faireProduct['name'].' - '.$variant['name'],
                    'description'  => $faireProduct['description'],
                    'rrp'          => Arr::get($variant, 'prices.0.retail_price.amount_minor') / 100,
                    'price'        => Arr::get($variant, 'prices.0.wholesale_price.amount_minor') / 100,
                    'unit'         => 'Piece',
                    'units'        => $faireProduct['unit_multiplier'],
                    'is_main'      => true,
                    'trade_config' => ProductTradeConfigEnum::AUTO,
                    'status'       => ProductStatusEnum::FOR_SALE,
                    'state'        => ProductStateEnum::IN_PROCESS,
                    'data'         => [
                        'faire' => $variant
                    ]
                ], strict: false);
            }
        }
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)->where('engine', ShopEngineEnum::FAIRE)
            ->where('slug', $command->argument('shop'))
            ->first();

        $this->handle($shop);
    }
}
