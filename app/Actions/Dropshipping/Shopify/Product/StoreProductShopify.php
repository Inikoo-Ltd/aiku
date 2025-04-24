<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreProductShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(ShopifyUser $shopifyUser, array $modelData)
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();

        DB::transaction(function () use ($shopifyUser, $modelData, $platform) {
            foreach (Arr::get($modelData, 'products') as $product) {
                $portfolio = StorePortfolio::run($shopifyUser->customer, [
                    'product_id'  => $product,
                    'platform_id' => $platform->id
                ]);

                HandleApiProductToShopify::dispatch($shopifyUser, [$portfolio->id]);
            }
        });
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array']
        ];
    }

    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        $this->initialisationFromShop($shopifyUser->customer->shop, $request);

        $this->handle($shopifyUser, $this->validatedData);
    }
}
