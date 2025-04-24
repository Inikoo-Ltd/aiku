<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-55m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Shopify;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Shopify\Product\HandleApiProductToShopify;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaProductShopify extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(ShopifyUser $shopifyUser, array $modelData)
    {
        DB::transaction(function () use ($shopifyUser, $modelData) {
            foreach (Arr::get($modelData, 'products') as $product) {
                $portfolio = StorePortfolio::run($shopifyUser->customer, [
                    'product_id' => $product,
                    'platform_id' => Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first()->id,
                ]);

                HandleApiProductToShopify::run($shopifyUser, [$portfolio->id]);
            }
        });
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($shopifyUser, $this->validatedData);
    }
}
