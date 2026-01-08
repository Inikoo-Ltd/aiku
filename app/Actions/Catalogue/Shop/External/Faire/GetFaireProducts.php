<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Product\StoreProduct;
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
        $products = $shop->getFaireProducts([
            'limit' => 250
        ]);

        foreach (Arr::get($products, 'products', []) as $product) {
            foreach ($product['variants'] as $variant) {
                if (Product::where('shop_id', $shop->id)->where('code', $variant['sku'])->exists()) {
                    continue;
                }

                $product = StoreProduct::make()->action($shop, [
                    'code' => $variant['sku'],
                    'name' => $product['name'] . ' - ' . $variant['name'],
                    'description' => $product['description'],
                    'rrp' => Arr::get($variant, 'prices.0.retail_price.amount_minor') / 100,
                    'price' => Arr::get($variant, 'prices.0.wholesale_price.amount_minor') / 100,
                    'unit' => 'Piece',
                    'units' => $product['unit_multiplier'],
                    'is_main' => true,
                    'trade_config' => ProductTradeConfigEnum::AUTO,
                    'status' => ProductStatusEnum::FOR_SALE,
                    'state' => ProductStateEnum::ACTIVE,
                    'data' => [
                        'faire' => $variant
                    ]
                ], strict: false);

                StoreHistoricAsset::run($product, []);

                echo $product->code . "\n";
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
