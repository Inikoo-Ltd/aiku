<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SynchroniseDropshippingPortfoliosToShopify extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, array $attributes): void
    {
        $portfolios = $shopifyUser
            ->customerSalesChannel
            ->portfolios()
            ->where('status', true)
            ->whereNull('platform_product_id')
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        foreach ($portfolios as $portfolio) {
            RequestApiUploadProductToShopify::dispatch($shopifyUser, $portfolio);
        }
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array'],
            'portfolios.*' => ['required', 'integer', Rule::exists('portfolios', 'id')],
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($shopifyUser, $this->validatedData);
    }
}
