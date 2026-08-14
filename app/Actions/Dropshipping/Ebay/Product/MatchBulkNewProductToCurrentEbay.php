<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class MatchBulkNewProductToCurrentEbay extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public string $jobQueue = 'dropshipping-long';

    public string $commandSignature = 'dropshipping:ebay:match-existing-listings {customerSalesChannel}';

    private const int PAGE_SIZE = 50;

    private const int MAX_PAGES = 400;

    /**
     * Links portfolios to the listings the seller already has on eBay, matched by SKU.
     * A portfolio whose SKU is not listed on eBay is left alone.
     *
     * @return array{matched: int, ignored: int}
     *
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData = []): array
    {
        $ebayUser = $customerSalesChannel->user;

        if (!$ebayUser instanceof EbayUser) {
            return ['matched' => 0, 'ignored' => 0];
        }

        $listedSkus = $this->getListedSkus($ebayUser);

        if (blank($listedSkus)) {
            return ['matched' => 0, 'ignored' => 0];
        }

        $matched = 0;
        $ignored = 0;

        $this->getPortfoliosToMatch($customerSalesChannel, $modelData)
            ->chunkById(500, function ($portfolios) use ($listedSkus, &$matched, &$ignored) {
                foreach ($portfolios as $portfolio) {
                    $listedSku = $this->findListedSku($portfolio, $listedSkus);

                    if (!$listedSku) {
                        $ignored++;

                        continue;
                    }

                    MatchPortfolioToCurrentEbayProduct::dispatch($portfolio, [
                        'platform_product_id' => $listedSku
                    ]);

                    $matched++;
                }
            });

        return ['matched' => $matched, 'ignored' => $ignored];
    }

    /**
     * Portfolios already linked and live on eBay have nothing left to match
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
     * Every SKU the seller has on eBay, keyed by its lowercased form so the comparison
     * survives the casing drifting between Aiku and what was typed into eBay.
     *
     * @return array<string, string>
     *
     * @throws \Exception
     */
    private function getListedSkus(EbayUser $ebayUser): array
    {
        $listedSkus = [];
        $offset     = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $ebayUser->getProducts(self::PAGE_SIZE, $offset);

            if (Arr::has($response, 'error') || Arr::has($response, 'errors')) {
                return $listedSkus;
            }

            $inventoryItems = Arr::get($response, 'inventoryItems', []);

            foreach ($inventoryItems as $inventoryItem) {
                $sku = Arr::get($inventoryItem, 'sku');

                if ($sku) {
                    $listedSkus[Str::lower($sku)] = $sku;
                }
            }

            if (count($inventoryItems) < self::PAGE_SIZE) {
                return $listedSkus;
            }

            $offset += self::PAGE_SIZE;
        }

        return $listedSkus;
    }

    /**
     * Bundles go up to eBay under the product code rather than the portfolio SKU,
     * so both are worth looking for.
     *
     * @param  array<string, string>  $listedSkus
     */
    private function findListedSku(Portfolio $portfolio, array $listedSkus): ?string
    {
        $candidateSkus = array_filter([
            $portfolio->sku,
            $portfolio->item?->code
        ]);

        foreach ($candidateSkus as $candidateSku) {
            $listedSku = $listedSkus[Str::lower($candidateSku)] ?? null;

            if ($listedSku) {
                return $listedSku;
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

        $this->handle($customerSalesChannel, $this->validatedData);
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
