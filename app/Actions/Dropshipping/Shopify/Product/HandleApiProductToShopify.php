<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\UploadProductToShopifyProgressEvent;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\ShopifyUserHasProduct;
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

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, array $attributes): void
    {
        $shopifyReadyUploadProducts = ShopifyUserHasProduct::where('shopify_user_id', $shopifyUser->id)
            ->whereIn('portfolio_id', Arr::get($attributes, 'portfolios'))->get();

        $portfolios = $shopifyUser
            ->customer->portfolios()
            ->whereIn('id', $shopifyReadyUploadProducts->pluck('portfolio_id')->toArray())
            ->get();

        $totalProducts = $portfolios->count();
        $uploaded      = 0;
        foreach ($portfolios->chunk(10) as $portfolioChunk) {
            $client   = $shopifyUser->api()->getRestClient();

            $variants = [];
            $images   = [];
            foreach ($portfolioChunk as $portfolio) {
                $product = $portfolio->item;
                foreach ($product->productVariants as $variant) {
                    $existingOptions = Arr::pluck($variants, 'option1');

                    if (!in_array($variant->name, $existingOptions)) {
                        $variants[] = [
                            "option1"      => $variant->name,
                            "price"        => $variant->price,
                            "barcode"      => $variant->slug,
                            "weight"      => $variant->gross_weight / 1000,
                            "weight_unit"      => "kg",
                            "inventory_management"      => "shopify"
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
                        "id"           => $product->id,
                        "title"        => $product->name,
                        "body_html"    => $product->description,
                        "vendor"       => $product->shop->name,
                        "product_type" => $product->family?->name,
                        "images"       => $images,
                        "variants"     => $variants,
                        "options"      => [
                            "name"   => "Variants",
                            "values" => Arr::pluck($variants, "option1")
                        ]
                    ]
                ];

                $response =  $client->request('POST', '/admin/api/2024-04/products.json', $body);

                if ($response['errors']) {
                    throw ValidationException::withMessages(['Internal server error, please wait a while']);
                }

                $productShopify = Arr::get($response, 'body.product');

                $inventoryVariants = [];
                foreach (Arr::get($productShopify, 'variants') as $variant) {
                    $variant['available_quantity'] = $product->available_quantity;
                    $inventoryVariants[] = $variant;
                }

                HandleApiInventoryProductShopify::run($shopifyUser, $inventoryVariants);

                $uploaded++;

                $this->update($portfolio->shopifyPortfolio, [
                    'shopify_product_id' => Arr::get($productShopify, 'id')
                ]);

                $this->update($portfolio, [
                    'data' => []
                ]);

                UploadProductToShopifyProgressEvent::dispatch($shopifyUser, $totalProducts, $uploaded);
            }
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
