<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleApiProductToShopify extends RetinaAction
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
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        $variants = [];
        $images = [];
        foreach ($portfolios as $portfolio) {
            $product = $portfolio->item;
            foreach ($product->productVariants as $variant) {
                $existingOptions = Arr::pluck($variants, 'option1');

                if (!in_array($variant->name, $existingOptions)) {
                    $variants[] = [
                        "option1" => $variant->name,
                        "price" => $variant->price,
                        "barcode" => $variant->slug,
                        "weight" => $variant->gross_weight / 1000,
                        "weight_unit" => "kg",
                        "inventory_management" => "shopify"
                    ];
                }
            }

            try {
                foreach ($product->images as $image) {
                    $images[] = [
                        "attachment" => $image->getBase64Image()
                    ];
                }
            } catch (\Exception $e) {
                // do nothing
            }

            $body = [
                "product" => [
                    "id" => $product->id,
                    "title" => $product->name,
                    "body_html" => $product->description,
                    "vendor" => $product->shop->name,
                    "product_type" => $product->family?->name,
                    "images" => $images,
                    "variants" => $variants,
                    "options" => [
                        "name" => "Variants",
                        "values" => Arr::pluck($variants, "option1")
                    ]
                ]
            ];

            RequestApiStoreProductToShopify::dispatch($shopifyUser, $product, $portfolio, $body);
        }
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array']
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $customerSalesChannel->user;
        $this->initialisation($request);

        $this->handle($shopifyUser, $this->validatedData);
    }
}
