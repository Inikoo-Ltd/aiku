<?php

namespace App\Actions\Maintenance\Masters\MasterProduct;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
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
     * Hydrates master_prices/master_rrps from child products, following the master shop's
     * price_exchanges config: major currencies are sourced from a real product in a shop of that
     * currency (live-rate fallback between majors only), minor currencies are always derived from
     * their configured major using the official exchange rate — never live market rates.
     *
     * @param Collection<string, Shop> $majorShops currency code => shop, first entry is the base major
     * @param array<string, array<string, float|null>> $majorExchanges [fromCode][toCode] => live rate (majors only)
     */
    public function handle(MasterAsset $masterAsset, Collection $majorShops, array $majorExchanges, array $priceExchanges, bool $dryRun = false, ?Command $command = null): void
    {
        $baseProducts = $masterAsset
            ->products
            ->whereIn('shop_id', $majorShops->pluck('id'))
            ->sortBy(fn ($product) => [$product->state == ProductStateEnum::DISCONTINUED, $product->id])
            ->groupBy('shop_id')
            ->map->first();

        if ($baseProducts->isEmpty()) {
            $command?->info("Master Asset: [{$masterAsset->code}] Skipped, no product in any major currency shop");

            return;
        }

        $sourceCurrencyCode = $majorShops->keys()->first(fn (string $code) => $baseProducts->has($majorShops[$code]->id));
        $sourceProduct      = $baseProducts->get($majorShops[$sourceCurrencyCode]->id);

        $prices = [];
        $rrps   = [];

        foreach ($majorShops as $currencyCode => $shop) {
            $product = $baseProducts->get($shop->id);
            $rate    = $product ? 1 : $majorExchanges[$sourceCurrencyCode][$currencyCode] ?? null;

            if (!$rate) {
                continue;
            }

            $price = formatPrice($rate, ($product ?? $sourceProduct)->price);
            if ($price > 0) {
                $prices[$currencyCode] = ['value' => $price, 'independent' => false];
            }

            $rrp = formatPrice($rate, ($product ?? $sourceProduct)->rrp);
            if ($rrp > 0) {
                $rrps[$currencyCode] = ['value' => $rrp, 'independent' => false];
            }
        }

        foreach ($priceExchanges as $currencyCode => $exchangeData) {
            if ($exchangeData['is_major'] ?? false) {
                continue;
            }

            $majorCode = $exchangeData['major'] ?? null;
            $exchange  = $exchangeData['exchange'] ?? null;
            if (!$majorCode || !$exchange) {
                continue;
            }

            $fractionDigits = (int)($exchangeData['fraction_digits'] ?? 2);

            if ($majorPrice = data_get($prices, "$majorCode.value")) {
                $prices[$currencyCode] = ['value' => formatPrice($majorPrice, $exchange, $fractionDigits), 'independent' => false];
            }
            if ($majorRRP = data_get($rrps, "$majorCode.value")) {
                $rrps[$currencyCode] = ['value' => formatPrice($majorRRP, $exchange), 'independent' => false];
            }
        }

        $prices = $this->mergePreservingIndependents($masterAsset->master_prices, $prices);
        $rrps   = $this->mergePreservingIndependents($masterAsset->master_rrps, $rrps);

        $modelData = [
            'master_prices' => $prices,
            'master_rrps'   => $rrps
        ];

        $baseCurrencyCode = GetMasterShopCurrenciesRate::baseCurrencyCode($priceExchanges) ?? $majorShops->keys()->first();

        if ($price = data_get($prices, "$baseCurrencyCode.value")) {
            $modelData['price'] = $price;
        }

        if ($rrp = data_get($rrps, "$baseCurrencyCode.value")) {
            $modelData['rrp'] = $rrp;
        }

        if (!$dryRun) {
            $masterAsset->updateQuietly($modelData);
        }

        $expected       = count($priceExchanges);
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

    /**
     * Rehydration starts from the stored record so currencies it cannot recompute survive,
     * and hand-maintained entries (independent === true) are never touched.
     *
     * @param array<string, array{value: mixed, independent?: bool}> $computed
     * @return array<string, array{value: mixed, independent?: bool}>
     */
    protected function mergePreservingIndependents(mixed $existing, array $computed): array
    {
        $merged = is_array($existing) ? $existing : [];

        foreach ($computed as $currencyCode => $entry) {
            if (data_get($merged, "$currencyCode.independent") === true) {
                continue;
            }

            $merged[$currencyCode] = $entry;
        }

        return $merged;
    }

    public string $commandSignature = 'repair:master_asset_hydrate_prices {master_shop} {--dry-run : Compute and report without writing}';

    public function asCommand(Command $command): int
    {
        $masterShop = MasterShop::where('slug', $command->argument('master_shop'))->firstOrFail();
        $dryRun     = (bool) $command->option('dry-run');

        if ($dryRun) {
            $command->warn('DRY RUN: no changes will be written');
        }

        $priceExchanges = $masterShop->price_exchanges ?? [];
        $majorCodes     = collect($priceExchanges)
            ->filter(fn (array $exchangeData) => $exchangeData['is_major'] ?? false)
            ->keys();

        if ($majorCodes->isEmpty()) {
            $command->error("Master shop [{$masterShop->slug}] has no major currencies in price_exchanges, configure them first");

            return 1;
        }

        $shopsByCurrency = Shop::where('master_shop_id', $masterShop->id)
            ->with('currency')
            ->orderBy('id')
            ->get()
            ->unique('currency_id')
            ->keyBy(fn (Shop $shop) => $shop->currency->code);

        $unconfigured = $shopsByCurrency->keys()->diff(array_keys($priceExchanges));
        if ($unconfigured->isNotEmpty()) {
            $command->warn('Shop currencies not in price_exchanges, skipped: '.$unconfigured->join(', '));
        }

        $majorShops = $majorCodes
            ->mapWithKeys(fn (string $code) => [$code => $shopsByCurrency->get($code)])
            ->filter();

        if ($majorShops->isEmpty()) {
            $command->error("Master shop [{$masterShop->slug}] has no shops in any major currency");

            return 1;
        }

        $command->info('Majors (from price_exchanges): '.$majorShops->keys()->join(', ').' | Minors: '.collect($priceExchanges)->filter(fn ($exchangeData) => !($exchangeData['is_major'] ?? false))->keys()->join(', '));

        $majorExchanges = [];
        foreach ($majorShops as $fromCode => $fromShop) {
            foreach ($majorShops as $toCode => $toShop) {
                if ($fromCode === $toCode) {
                    continue;
                }

                $rate = GetCurrencyExchange::run($fromShop->currency, $toShop->currency);
                if (!$rate) {
                    $command->warn("No live exchange rate $fromCode → $toCode, those major prices will be left out when no product exists");
                }

                $majorExchanges[$fromCode][$toCode] = $rate;
            }
        }

        MasterAsset::where('master_shop_id', $masterShop->id)
            ->with('products')
            ->orderBy('id')
            ->chunkById(250, function ($chunks) use ($majorShops, $majorExchanges, $priceExchanges, $dryRun, $command) {
                foreach ($chunks as $masterAsset) {
                    $this->handle($masterAsset, $majorShops, $majorExchanges, $priceExchanges, $dryRun, $command);
                }
            });

        return 0;
    }
}
