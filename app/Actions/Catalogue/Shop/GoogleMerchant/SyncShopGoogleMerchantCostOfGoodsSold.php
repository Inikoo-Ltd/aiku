<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\GoogleMerchant;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Services\GoogleMerchant\GoogleMerchantClient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Sentry;
use Throwable;

class SyncShopGoogleMerchantCostOfGoodsSold
{
    use AsAction;

    public string $commandSignature = 'google-merchant:sync-cogs {shop? : The shop slug, every connected shop if omitted}';

    /**
     * @return array{sent: int, unchanged: int, no_cost: int, failed: int, error: string|null}
     */
    public function handle(Shop $shop): array
    {
        $client = GoogleMerchantClient::forShop($shop);

        if (!$client) {
            throw new RuntimeException('Google Merchant Center is not connected or has no Merchant ID.');
        }

        $dataSource = Arr::get($shop->settings, 'google_merchant.data_source') ?: $this->storeDataSource($shop, $client);

        $costs = Product::where('products.shop_id', $shop->id)
            ->join('master_assets', 'master_assets.id', '=', 'products.master_product_id')
            ->where('master_assets.effective_cost', '>', 0)
            ->pluck('master_assets.effective_cost', 'products.code')
            ->mapWithKeys(fn ($cost, $code) => [strtolower($code) => (float) $cost]);

        $result = ['sent' => 0, 'unchanged' => 0, 'no_cost' => 0, 'failed' => 0, 'error' => null];
        $rates  = [];

        foreach ($client->products() as $merchantProduct) {
            $cost         = $costs->get(strtolower($merchantProduct['offerId']));
            $currencyCode = data_get($merchantProduct, 'productAttributes.price.currencyCode', $shop->currency->code);

            if (!array_key_exists($currencyCode, $rates)) {
                $currency               = Currency::firstWhere('code', $currencyCode);
                $rates[$currencyCode] = $currency ? GetCurrencyExchange::run($shop->group->currency, $currency) : null;
            }

            if (!$cost || !$rates[$currencyCode]) {
                $result['no_cost']++;
                continue;
            }

            $costOfGoodsSold = [
                'amountMicros' => (string) (int) round(round($cost * $rates[$currencyCode], 2) * 1_000_000),
                'currencyCode' => $currencyCode,
            ];

            if (data_get($merchantProduct, 'productAttributes.costOfGoodsSold') == $costOfGoodsSold) {
                $result['unchanged']++;
                continue;
            }

            try {
                $client->insertProductInput($dataSource, [
                    'offerId'           => $merchantProduct['offerId'],
                    'contentLanguage'   => $merchantProduct['contentLanguage'],
                    'feedLabel'         => $merchantProduct['feedLabel'],
                    'productAttributes' => ['costOfGoodsSold' => $costOfGoodsSold],
                ]);
                $result['sent']++;
            } catch (Throwable $exception) {
                $result['failed']++;
                $result['error'] ??= $merchantProduct['offerId'].': '.$exception->getMessage();
            }
        }

        $this->storeLastSync($shop, $result);

        return $result;
    }

    private function storeDataSource(Shop $shop, GoogleMerchantClient $client): string
    {
        $dataSource = $client->createSupplementalDataSource('Aiku cost of goods sold');

        $shop->refresh();
        $settings = $shop->settings;
        data_set($settings, 'google_merchant.data_source', $dataSource);
        $shop->update(['settings' => $settings]);

        return $dataSource;
    }

    /**
     * @param array<string, int|string|null> $result
     */
    private function storeLastSync(Shop $shop, array $result): void
    {
        $shop->refresh();
        $settings = $shop->settings;
        data_set($settings, 'google_merchant.last_sync', [...$result, 'at' => now()->toIso8601String()]);
        $shop->update(['settings' => $settings]);
    }

    public function asCommand(Command $command): int
    {
        $shops = $command->argument('shop')
            ? Shop::where('slug', $command->argument('shop'))->get()
            : Shop::whereNotNull('settings->google_merchant->refresh_token')->get();

        $exitCode = 0;

        foreach ($shops as $shop) {
            try {
                $result = $this->handle($shop);
                $command->info("$shop->slug: sent {$result['sent']}, unchanged {$result['unchanged']}, without cost {$result['no_cost']}, failed {$result['failed']}");
            } catch (Throwable $exception) {
                Sentry::captureException($exception);
                $this->storeLastSync($shop, ['sent' => 0, 'unchanged' => 0, 'no_cost' => 0, 'failed' => 0, 'error' => $exception->getMessage()]);
                $command->error("$shop->slug: {$exception->getMessage()}");
                $exitCode = 1;
            }
        }

        return $exitCode;
    }
}
