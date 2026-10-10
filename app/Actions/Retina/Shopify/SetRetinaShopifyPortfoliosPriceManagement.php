<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 11:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Shopify;

use App\Actions\Dropshipping\Shopify\Product\SetShopifyPortfolioPriceManagement;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetRetinaShopifyPortfoliosPriceManagement extends RetinaAction
{
    use WithRetinaCustomerOwnedRouteModels;

    /**
     * Each price sent to Shopify takes about a second, so a large selection switched on goes to the queue.
     */
    public const int SYNC_LIMIT = 20;

    /**
     * @return array{changed: int, queued: int, failed: list<string>}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): array
    {
        $managedByUs = (bool) Arr::get($modelData, 'managed_by_us');

        $portfolios = $customerSalesChannel->portfolios()
            ->whereIn('id', Arr::get($modelData, 'portfolios', []))
            ->whereRaw("coalesce(settings->>'shopify_variant_adopted', 'false') = 'true'")
            ->get();

        if ($managedByUs && $portfolios->count() > self::SYNC_LIMIT) {
            foreach ($portfolios as $portfolio) {
                SetShopifyPortfolioPriceManagement::dispatch($portfolio, true);
            }

            return ['changed' => 0, 'queued' => $portfolios->count(), 'failed' => []];
        }

        $failed = [];
        foreach ($portfolios as $portfolio) {
            [$done, $message] = SetShopifyPortfolioPriceManagement::run($portfolio, $managedByUs);

            if (!$done) {
                $failed[] = $portfolio->item_code.': '.$message;
            }
        }

        return ['changed' => $portfolios->count() - count($failed), 'queued' => 0, 'failed' => $failed];
    }

    public function rules(): array
    {
        return [
            'portfolios'    => ['required', 'array'],
            'portfolios.*'  => ['required', 'integer'],
            'managed_by_us' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{changed: int, queued: int, failed: list<string>}
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array
    {
        $this->initialisation($request);

        $result = $this->handle($customerSalesChannel, $this->validatedData);

        if ($result['failed'] !== []) {
            throw ValidationException::withMessages([
                'managed_by_us' => __('Saved, but Shopify did not accept the price of :products', ['products' => implode('; ', $result['failed'])])
            ]);
        }

        return $result;
    }
}
