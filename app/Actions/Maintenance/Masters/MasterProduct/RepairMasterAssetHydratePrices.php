<?php

namespace App\Actions\Maintenance\Masters\MasterProduct;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
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

    public function handle(MasterAsset $masterAsset, Shop $baseShop, Collection $currenciesRate, ?Command $command = null)
    {
        $baseProduct = $masterAsset->products->where('shop_id', $baseShop->id)->first();

        if (!$baseProduct) {
            $command?->info("Master Asset: [{$masterAsset->code}] Skipped, no base product");
            return;
        };

        $hasPrice = false;
        $hasRRP   = false;

        $updateData = [];

        if ($baseProduct->price) {
            $updateData['master_prices']    = $currenciesRate->map(
                fn ($ratio) => [
                    'value'         => formatPrice($ratio, $baseProduct->price),
                    'independent'   => false
                ]
            )->toArray();
            $hasPrice = true;
        }

        if ($baseProduct->rrp) {
            $updateData['master_rrps']    = $currenciesRate->map(
                fn ($ratio) => [
                    'value'         => formatPrice($ratio, $baseProduct->rrp),
                    'independent'   => false
                ]
            )->toArray();
            $hasRRP   = true;
        }

        UpdateMasterAsset::make()->action($masterAsset, $updateData);

        $setUpText = '';

        if ($command && $hasPrice) {
            $setUpText = '| Master Price hydrated |';
        }

        if ($command && $hasRRP) {
            $setUpText .= '| Master RRP hydrated |';
        }

        $command?->info("Master Asset: [{$masterAsset->code}] => $setUpText from $baseProduct->slug");
    }

    // Shop as base, master_shop as target
    public string $commandSignature = 'repair:master_asset_hydrate_prices {shop} {master_shop}';

    public function asCommand(Command $command)
    {
        $shopArgument       = $command->argument('shop');
        $masterShopArgument = $command->argument('master_shop');

        if (!$shopArgument || !$masterShopArgument) {
            $command->error("Unable to process, Shop and Master Shop argument must be present");
            return;
        }
        
        $baseShop       = Shop::where('slug', $shopArgument)->firstOrFail();
        $baseCurrency   = $baseShop->currency;
        
        $masterShop = MasterShop::where('slug', $masterShopArgument)->firstOrFail();
        
        $shopCurrencies = Shop::where('master_shop_id', $masterShop->id)
            ->whereNot('currency_id', $baseCurrency->id)
            ->select('currency_id')
            ->distinct()
            ->get();

        $currencies     = Currency::whereIn('id', $shopCurrencies)->get()->keyBy('id');
        $currenciesRate   = $currencies->mapWithKeys(function ($currency) use ($baseCurrency) {
            $currencyRatio = GetCurrencyExchange::run($baseCurrency, $currency);
            return [
                $currency->code => round($currencyRatio, 2)
            ];
        });

        MasterAsset::where('master_shop_id', $masterShop->id)
            ->with('products') // Eager load, this only does 1 query (SELECT * FROM PRODUCTS WHERE products.master_asset_id IN $masterAssets->ids), leave it be, it's not heavy, we'll chunk anyway
            ->orderBy('id')
            ->chunkById(250, function ($chunks) use ($baseShop, $currenciesRate, $command) {
                foreach($chunks as $masterAsset) {
                    $this->handle($masterAsset, $baseShop, $currenciesRate, $command);
                }
            });
    }
}
