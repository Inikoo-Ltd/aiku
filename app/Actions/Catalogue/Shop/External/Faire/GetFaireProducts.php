<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class GetFaireProducts extends OrgAction
{
    public string $commandSignature = 'faire:products {shop} {min_hours?}';

    public $jobQueue = 'default-long';

    public function handle(Shop $shop, array $modelData, ?Command $command = null): void
    {
        $faireProducts = [];
        $limit         = 200;
        $page          = 1;
        $filters = [];

        if ($minHours = Arr::get($modelData, 'min_hours')) {
            $filters = [
                'updated_at_min' => now()->subHours($minHours)->toIsoString(),
            ];
        }

        UpdateShop::run($shop, [
            'last_external_shop_products_fetched_at' => now()
        ]);

        do {
            $response = $shop->getFaireProducts([
                ...$filters,
                'limit' => $limit,
                'page'  => $page
            ]);


            $fetchedProducts = Arr::get($response, 'products');

            $faireProducts = array_merge($faireProducts, $fetchedProducts ?? []);
            $command?->info("Fetched  ($page) ".count($fetchedProducts)." products, total: ".count($faireProducts));


            $page++;
        } while (count($fetchedProducts) === $limit);


        foreach ($faireProducts as $faireProduct) {

            $faireState = Arr::get($faireProduct, 'lifecycle_state');
            if (!in_array($faireState, ['PUBLISHED', 'UNPUBLISHED'])) {
                continue;
            }

            foreach ($faireProduct['variants'] as $variant) {
                $faireVariantState = Arr::get($variant, 'lifecycle_state');

                if (!in_array($faireVariantState, ['PUBLISHED', 'UNPUBLISHED'])) {
                    continue;
                }


                $faireSKU = Arr::get($variant, 'sku');
                if (!$faireSKU) {
                    continue;
                }

                $product = Product::where('shop_id', $shop->id)->where('code', $faireSKU)->first();

                if ($product) {

                    try {UpdateProduct::make()->action($product, [
                            'code'           => $faireSKU,
                            'name'           => $faireProduct['name'].' - '.$variant['name'],
                            'description'    => $faireProduct['description'],
                            'rrp'            => $this->extractFaireRetailPrices($shop, Arr::get($variant, 'prices')),
                            'price'          => $this->extractFaireCostPrices($shop, Arr::get($variant, 'prices')),
                            'units'          => $faireProduct['unit_multiplier'],
                            'marketplace_id' => $variant['id'],
                            'data'           => [
                                'faire' => $variant
                            ]
                        ], strict: false);
                    } catch (Exception $e) {
                        $command?->error("Product update failed: ".$faireProduct['name'].' - '.$variant['name'].' '.$e->getMessage());
                    }


                }else{

                    try {
                        $product=StoreProduct::make()->action($shop, [
                            'code'           => $faireSKU,
                            'name'           => $faireProduct['name'].' - '.$variant['name'],
                            'description'    => $faireProduct['description'],
                            'rrp'            => $this->extractFaireRetailPrices($shop, Arr::get($variant, 'prices')),
                            'price'          => $this->extractFaireCostPrices($shop, Arr::get($variant, 'prices')),
                            'unit'           => 'Piece',
                            'units'          => $faireProduct['unit_multiplier'],
                            'is_main'        => true,
                            'trade_config'   => ProductTradeConfigEnum::AUTO,
                            'status'         => ProductStatusEnum::FOR_SALE,
                            'state'          => ProductStateEnum::IN_PROCESS,
                            'marketplace_id' => $variant['id'],
                            'data'           => [
                                'faire' => $variant
                            ]
                        ], strict: false);
                        $command?->info("Product added: ".$product->slug);
                    } catch (Exception|Throwable $e) {
                        $command?->error("Product creation failed: ".$faireProduct['name'].' - '.$variant['name'].' '.$e->getMessage());
                    }
                }


            }
        }
    }

    public function extractFaireRetailPrices(Shop $shop, array $prices): float
    {
        $found = false;
        $rrp = Arr::get($prices, '0.retail_price.amount_minor');
        $faireCurrency = Arr::get($prices, '0.retail_price.currency');

        foreach ($prices as $price) {
            if (Arr::get($price, 'retail_price.currency') === $shop->currency->code) {
                $found = true;
                $rrp = Arr::get($price, 'retail_price.amount_minor');
            }
        }

        if(! $found) {
            $currency = Currency::where('code', $faireCurrency)->first();
            $rrp = GetCurrencyExchange::run($currency, $shop->currency);
        }

        return $rrp / 100;
    }

    public function extractFaireCostPrices(Shop $shop, array $prices): float
    {
        $found = false;
        $cost = Arr::get($prices, '0.wholesale_price.amount_minor');
        $faireCurrency = Arr::get($prices, '0.wholesale_price.currency');

        foreach ($prices as $price) {
            if (Arr::get($price, 'wholesale_price.currency') === $shop->currency->code) {
                $found = true;
                $cost = Arr::get($price, 'wholesale_price.amount_minor');
            }
        }

        if(! $found) {
            $currency = Currency::where('code', $faireCurrency)->first();
            $cost = GetCurrencyExchange::run($currency, $shop->currency);
        }

        return $cost / 100;
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)->where('engine', ShopEngineEnum::FAIRE)
            ->where('slug', $command->argument('shop'))
            ->first();

        $modelData = [
            'min_hours' => $command->argument('min_hours')
        ];

        $this->handle($shop, $modelData, $command);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);

        GetFaireProducts::dispatch($shop, $this->validatedData);
    }
}
