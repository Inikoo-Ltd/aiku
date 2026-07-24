<?php

namespace App\Actions\Maintenance\Masters\MasterProduct;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterAssetHydratePrices
{
    use AsAction;

    /**
     * @param Collection<int, Shop> $baseShops           one shop per currency, base currency first
     * @param array<int, array<int, float|null>>  $baseCurrenciesExchange  [fromShopId][toShopId] => rate
     */
    public function handle(MasterAsset $masterAsset, Collection $baseShops, array $baseCurrenciesExchange, bool $dryRun = false, ?Command $command = null): void
    {
        $baseProducts = $masterAsset
            ->products
            ->whereIn('shop_id', $baseShops->pluck('id'))
            ->sortBy(fn ($product) => [$product->state == ProductStateEnum::DISCONTINUED, $product->id])
            ->groupBy('shop_id')
            ->map->first();

        if ($baseProducts->isEmpty()) {
            $command?->info("Master Asset: [{$masterAsset->code}] Skipped, no base product");

            return;
        }

        $sourceShop    = $baseShops->first(fn ($shop) => $baseProducts->has($shop->id));
        $sourceProduct = $baseProducts->get($sourceShop->id);

        $prices = [];
        $rrps   = [];

        foreach ($baseShops as $shop) {
            $product = $baseProducts->get($shop->id);
            $rate    = $product ? 1 : $baseCurrenciesExchange[$sourceShop->id][$shop->id] ?? null;

            if (!$rate) {
                continue;
            }

            $price = formatPrice($rate, ($product ?? $sourceProduct)->price);
            if ($price > 0) {
                $prices[$shop->currency->code] = [
                    'value'       => $price,
                    'independent' => false
                ];
            }

            $rrp = formatPrice($rate, ($product ?? $sourceProduct)->rrp);
            if ($rrp > 0) {
                $rrps[$shop->currency->code] = [
                    'value'       => $rrp,
                    'independent' => false
                ];
            }
        }

        $modelData = [
            'master_prices' => $prices,
            'master_rrps'   => $rrps
        ];

        if ($eurPrice = data_get($prices, 'EUR.value')) {
            $modelData['price'] = $eurPrice;
        }

        if ($eurRRP = data_get($rrps, 'EUR.value')) {
            $modelData['rrp'] = $eurRRP;
        }

        if (!$dryRun) {
            $masterAsset->updateQuietly($modelData);
        }

        $expected       = $baseShops->count();
        $additionalText = '';
        if (count($prices) < $expected) {
            $additionalText .= '| PRICE NOT FULLY HYDRATED ('.count($prices)."/$expected)";
        }
        if (count($rrps) < $expected) {
            $additionalText .= '| RRP NOT FULLY HYDRATED ('.count($rrps)."/$expected)";
        }

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $command?->info("{$prefix}Master Asset: [{$masterAsset->code}] => Hydrated {$additionalText}");
    }

    public string $commandSignature = 'repair:master_asset_hydrate_prices {master_shop} {--dry-run : Compute and report without writing}';

    public function asCommand(Command $command): int
    {
        $masterShop = MasterShop::where('slug', $command->argument('master_shop'))->firstOrFail();
        $dryRun     = (bool) $command->option('dry-run');

        if ($dryRun) {
            $command->warn('DRY RUN: no changes will be written');
        }

        $baseShops = Shop::where('master_shop_id', $masterShop->id)
            ->with('currency')
            ->orderBy('id')
            ->get()
            ->unique('currency_id')
            ->sortBy(fn ($shop) => $shop->currency->code != 'EUR')
            ->values();

        if ($baseShops->isEmpty()) {
            $command->error("Master shop [{$masterShop->slug}] has no shops");

            return 1;
        }

        $command->info('Preparing currency exchange (Eager Loading): '.$baseShops->pluck('currency.code')->join(', '));

        $baseCurrenciesExchange = [];
        foreach ($baseShops as $from) {
            foreach ($baseShops as $to) {
                if ($from->id === $to->id) {
                    continue;
                }

                $rate = GetCurrencyExchange::run($from->currency, $to->currency);
                if (!$rate) {
                    $command->warn("No exchange rate {$from->currency->code} → {$to->currency->code}, those prices will be left out");
                }

                $baseCurrenciesExchange[$from->id][$to->id] = $rate;
            }
        }

        MasterAsset::where('master_shop_id', $masterShop->id)
            ->with('products')
            ->orderBy('id')
            ->chunkById(250, function ($chunks) use ($baseShops, $baseCurrenciesExchange, $dryRun, $command) {
                foreach ($chunks as $masterAsset) {
                    $this->handle($masterAsset, $baseShops, $baseCurrenciesExchange, $dryRun, $command);
                }
            });

        return 0;
    }
}
