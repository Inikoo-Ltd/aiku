<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\Catalogue\Product;
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

    /**
     * @throws \Throwable
     */
    public function handle(ShopifyUser $shopifyUser, array $modelData): void
    {

        DB::transaction(function () use ($shopifyUser, $modelData) {

            $portfolios = [];
            $response = $shopifyUser->api()->getRestClient()->request('GET', '/admin/api/2024-04/products.json');

            $products = collect(Arr::get($response, 'body.products', []));
            $productCodes = $products->flatMap(function ($product) {
                return collect(Arr::get($product, 'variants'))->pluck('barcode');
            })->filter()->values();

            foreach (Arr::get($modelData, 'items') as $product) {
                $product = Product::find($product);

                $modelData = [];
                if (in_array($product->slug, $productCodes->toArray())) {
                    $modelData = [
                        'data' => [
                            'shopify' => [
                                'status' => 'duplicated',
                                'barcode' => $product->slug
                            ]
                        ]
                    ];
                }

                $portfolio = StorePortfolio::run(
                    $shopifyUser->customerSalesChannel,
                    $product,
                    $modelData
                );

                $shopifyUser->products()->attach($product, [
                    'shopify_user_id' => $shopifyUser->id,
                    'product_type' => class_basename($product),
                    'product_id' => $product->id,
                    'portfolio_id' => $portfolio->id
                ]);

                $portfolios[] = $portfolio->id;
            }

            if ($shopifyUser->customerSalesChannel->state !== CustomerSalesChannelStateEnum::READY) {
                UpdateCustomerSalesChannel::run($shopifyUser->customerSalesChannel, [
                    'state' => CustomerSalesChannelStateEnum::PORTFOLIO_ADDED
                ]);
            }

            HandleApiProductToShopify::dispatch($shopifyUser, [
                'portfolios' => $portfolios
            ])->onQueue('urgent');
        });
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        $this->initialisationFromShop($shopifyUser->customer->shop, $request);

        $this->handle($shopifyUser, $this->validatedData);
    }
}
