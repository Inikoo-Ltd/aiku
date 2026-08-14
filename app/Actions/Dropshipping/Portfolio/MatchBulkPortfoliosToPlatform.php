<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Allegro\Product\GetAllegroListedSkus;
use App\Actions\Dropshipping\Allegro\Product\MatchPortfolioToCurrentAllegroProduct;
use App\Actions\Dropshipping\Ebay\Product\GetEbayListedSkus;
use App\Actions\Dropshipping\Ebay\Product\MatchPortfolioToCurrentEbayProduct;
use App\Actions\Dropshipping\Shopify\Product\GetShopifyListedSkus;
use App\Actions\Dropshipping\Shopify\Product\MatchPortfolioToCurrentShopifyProduct;
use App\Actions\Dropshipping\Tiktok\Product\GetTiktokListedSkus;
use App\Actions\Dropshipping\Tiktok\Product\MatchPortfolioToCurrentTiktokProduct;
use App\Actions\Dropshipping\WooCommerce\Product\GetWooListedSkus;
use App\Actions\Dropshipping\WooCommerce\Product\MatchPortfolioToCurrentWooProduct;
use App\Actions\OrgAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class MatchBulkPortfoliosToPlatform extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public string $jobQueue = 'dropshipping-long';

    public string $commandSignature = 'dropshipping:match-existing-listings {customerSalesChannel}';

    /**
     * Links portfolios to the listings the seller already has on their shop, matched by SKU.
     * A portfolio whose SKU is not listed there is left alone.
     *
     * @return array{matched: int, ignored: int}
     *
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData = []): array
    {
        $listedSkus = $this->getListedSkus($customerSalesChannel);

        if (blank($listedSkus)) {
            return ['matched' => 0, 'ignored' => 0];
        }

        $matched = 0;
        $ignored = 0;

        $this->getPortfoliosToMatch($customerSalesChannel, $modelData)
            ->chunkById(500, function ($portfolios) use ($customerSalesChannel, $listedSkus, &$matched, &$ignored) {
                foreach ($portfolios as $portfolio) {
                    $platformProductId = $this->findPlatformProductId($portfolio, $listedSkus);

                    if (!$platformProductId) {
                        $ignored++;

                        continue;
                    }

                    $this->dispatchMatch($customerSalesChannel, $portfolio, $platformProductId);

                    $matched++;
                }
            });

        return ['matched' => $matched, 'ignored' => $ignored];
    }

    /**
     * @return array<string, string>
     *
     * @throws \Exception
     */
    private function getListedSkus(CustomerSalesChannel $customerSalesChannel): array
    {
        $user = $customerSalesChannel->user;

        if (!$user) {
            return [];
        }

        return match ($customerSalesChannel->platform?->type) {
            PlatformTypeEnum::EBAY => GetEbayListedSkus::run($user),
            PlatformTypeEnum::SHOPIFY => GetShopifyListedSkus::run($user),
            PlatformTypeEnum::WOOCOMMERCE => GetWooListedSkus::run($user),
            PlatformTypeEnum::TIKTOK => GetTiktokListedSkus::run($user),
            PlatformTypeEnum::ALLEGRO => GetAllegroListedSkus::run($user),
            default => []
        };
    }

    private function dispatchMatch(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio, string $platformProductId): void
    {
        match ($customerSalesChannel->platform?->type) {
            PlatformTypeEnum::EBAY => MatchPortfolioToCurrentEbayProduct::dispatch($portfolio, [
                'platform_product_id' => $platformProductId
            ]),
            PlatformTypeEnum::SHOPIFY => MatchPortfolioToCurrentShopifyProduct::dispatch($portfolio, [
                'shopify_product_id' => $platformProductId
            ]),
            PlatformTypeEnum::WOOCOMMERCE => MatchPortfolioToCurrentWooProduct::dispatch($portfolio, [
                'platform_product_id' => $platformProductId
            ]),
            PlatformTypeEnum::TIKTOK => MatchPortfolioToCurrentTiktokProduct::dispatch($portfolio, [
                'platform_product_id' => $platformProductId
            ]),
            PlatformTypeEnum::ALLEGRO => MatchPortfolioToCurrentAllegroProduct::dispatch($portfolio, [
                'platform_product_id' => $platformProductId
            ]),
            default => null
        };
    }

    /**
     * Portfolios already linked and live on the platform have nothing left to match
     */
    private function getPortfoliosToMatch(CustomerSalesChannel $customerSalesChannel, array $modelData): HasMany
    {
        return $customerSalesChannel
            ->portfolios()
            ->with('item')
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('platform_product_id')
                    ->orWhere('platform_status', false);
            })
            ->when(
                Arr::get($modelData, 'portfolios'),
                fn ($query, $portfolioIds) => $query->whereIn('id', $portfolioIds)
            );
    }

    /**
     * Bundles go up to the platform under the product code rather than the portfolio SKU,
     * so both are worth looking for.
     *
     * @param  array<string, string>  $listedSkus
     */
    private function findPlatformProductId(Portfolio $portfolio, array $listedSkus): ?string
    {
        $candidateSkus = array_filter([
            $portfolio->sku,
            $portfolio->item?->code
        ]);

        foreach ($candidateSkus as $candidateSku) {
            $platformProductId = $listedSkus[Str::lower($candidateSku)] ?? null;

            if ($platformProductId) {
                return $platformProductId;
            }
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'portfolios'   => ['sometimes', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        self::dispatch($customerSalesChannel, $this->validatedData);
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        if (!$customerSalesChannel) {
            $command->error('Customer sales channel not found');

            return 1;
        }

        $result = $this->handle($customerSalesChannel);

        $command->info("Matched {$result['matched']} portfolios, ignored {$result['ignored']}");

        return 0;
    }
}
