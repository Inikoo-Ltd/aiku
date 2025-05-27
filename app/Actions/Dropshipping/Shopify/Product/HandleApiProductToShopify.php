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
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleApiProductToShopify extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    //    public string $jobQueue = 'shopify';

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

        foreach ($portfolios as $portfolio) {
            $variants = [];
            foreach ($portfolio->item->productVariants as $variant) {
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
                $images = [];
                foreach ($portfolio->item->images as $image) {
                    $images[] = [
                        "attachment" => $image->getBase64Image()
                    ];
                }
            } catch (\Exception $e) {
                // do nothing
            }

            $body = [
                "product" => [
                    "id" => $portfolio->item->id,
                    "title" => $portfolio->item->name,
                    "body_html" => $portfolio->item->description,
                    "vendor" => $portfolio->item->shop->name,
                    "product_type" => $portfolio->item->family?->name,
                    "images" => $images,
                    "variants" => $variants,
                    "options" => [
                        "name" => "Variants",
                        "values" => Arr::pluck($variants, "option1")
                    ]
                ]
            ];

            $client = $shopifyUser->api()->getRestClient();

            $response = $client->request('POST', '/admin/api/2024-04/products.json', $body);

            if ($response['errors']) {
                throw ValidationException::withMessages(['Internal server error, please wait a while']);
            }

            $productShopify = Arr::get($response, 'body.product');

            $inventoryVariants = [];
            foreach (Arr::get($productShopify, 'variants') as $variant) {
                $variant['available_quantity'] = $portfolio->item->available_quantity;
                $inventoryVariants[] = $variant;
            }

            HandleApiInventoryProductShopify::run($shopifyUser, $inventoryVariants);

            $this->update($portfolio->shopifyPortfolio, [
                'shopify_product_id' => Arr::get($productShopify, 'id')
            ]);

            $this->update($portfolio, [
                'data' => []
            ]);
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
