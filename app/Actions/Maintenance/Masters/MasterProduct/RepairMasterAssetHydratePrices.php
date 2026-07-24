<?php

namespace App\Actions\Maintenance\Masters\MasterProduct;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterAssetHydratePrices
{
    use AsAction;

    public function handle(MasterAsset $masterAsset, Collection $baseShops, Collection $baseCurrenciesExchange, ?Command $command = null)
    {
        $baseProducts = $masterAsset
            ->products
            ->whereIn(
                'shop_id', data_get($baseShops, '*.id')
            )
            ->keyBy('shop_id');

        if (!$baseProducts) {
            $command?->info("Master Asset: [{$masterAsset->code}] Skipped, no base product");
            return;
        };

        $price  = $baseShops
            ->mapWithKeys(function ($shop) use ($baseProducts, $baseCurrenciesExchange) {
                $product = $baseProducts
                    ->get($shop->id);

                $price = 0;
                if (!$product) {
                    $baseCopy = $baseProducts->first();
                    $convert = $baseCurrenciesExchange[$baseCopy->shop_id][$shop->id];
                    $price = formatPrice($convert, $baseCopy?->price);
                } else {
                    $price = formatPrice(1, $product?->price);
                }
                
                return [
                    $shop->currency->code => [
                        'value'         => $price,
                        'independent'   => (bool) $product
                    ]
                ];
            });

        $rrp    = $baseShops
            ->mapWithKeys(function ($shop) use ($baseProducts, $baseCurrenciesExchange) {
                $product = $baseProducts
                    ->get($shop->id);

                $rrp = 0;
                if (!$product) {
                    $baseCopy = $baseProducts->first();
                    $convert = $baseCurrenciesExchange[$baseCopy->shop_id][$shop->id];
                    $rrp = formatPrice($convert, $baseCopy?->rrp);
                } else {
                    $rrp = formatPrice(1, $product?->rrp);
                }
                
                return [
                    $shop->currency->code => [
                        'value'         => $rrp,
                        'independent'   => (bool) $product
                    ]
                ];
            });

        $masterAsset->updateQuietly([
            'master_prices' => $price,
            'master_rrps'   => $rrp
        ]);

        $additionalText = '';
        if (count($price) < 8) {
            $additionalText .= "| PRICE NOT FULLY HYDRATED (count($price))";
        }
        if (count($rrp) < 8) {
            $additionalText .= "| RRP NOT FULLY HYDRATED (count($rrp))";
        }

        $command?->info("Master Asset: [{$masterAsset->code}] => Hydrated {$additionalText}");
    }

    // Shop as base, master_shop as target
    public string $commandSignature = 'repair:master_asset_hydrate_prices {master_shop}';

    public function asCommand(Command $command)
    {
        $masterShopArgument = $command->argument('master_shop');

        if (!$masterShopArgument) {
            $command->error("Unable to process, Shop and Master Shop argument must be present");
            return;
        }

        $baseShops = Shop::whereIn('slug', [
                'uk', 
                'eu', 
                'plsk', 
                'cz', 
                'hu', 
                'ro', 
                'se', 
                'ua'
            ])
            ->with('currency')
            ->get();
        
        $command->info('Preparing currency exchange (Eager Loading)');

        $baseCurrenciesExchange = [];

        foreach ($baseShops as $from) {
            foreach ($baseShops as $to) {
                if ($from->id === $to->id) {
                    continue;
                }

                $baseCurrenciesExchange[$from->id][$to->id] = GetCurrencyExchange::run($from->currency, $to->currency);
            }
        }

        $baseCurrenciesExchange = collect($baseCurrenciesExchange);

        $masterShop = MasterShop::where('slug', $masterShopArgument)->firstOrFail();

        MasterAsset::where('master_shop_id', $masterShop->id)
            ->with('products') // Eager load, this only does 1 query (SELECT * FROM PRODUCTS WHERE products.master_asset_id IN $masterAssets->ids), leave it be, it's not heavy, we'll chunk anyway
            ->orderBy('id')
            ->chunkById(250, function ($chunks) use ($baseShops, $baseCurrenciesExchange, $command) {
                foreach ($chunks as $masterAsset) {
                    $this->handle($masterAsset, $baseShops, $baseCurrenciesExchange, $command);
                }
            });
    }
}
