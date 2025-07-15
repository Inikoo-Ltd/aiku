<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:25:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Events\UploadProductToShopifyProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Sentry;

/**
 * UploadPortfolioToShopify
 *
 * This action is responsible for uploading a portfolio to Shopify as a product.
 * It handles the entire process of creating or updating a product in Shopify,
 * including product details, images, variants, and inventory management.
 *
 * The process includes:
 * 1. Extracting product data from the portfolio
 * 2. Converting images to base64 for Shopify API
 * 3. Checking if the product already exists in Shopify
 * 4. Creating a new product if it doesn't exist
 * 5. Updating inventory information
 * 6. Updating the portfolio with Shopify product ID
 * 7. Handling errors and exceptions
 *
 * This action runs within a database transaction to ensure data integrity.
 * It also dispatches progress events to track the upload process.
 */
class UploadPortfolioToShopify extends RetinaAction
{
    use WithActionUpdate;

    /**
     * The queue where the job should be processed
     */
    public string $jobQueue = 'shopify';

    /**
     * The number of seconds to wait before retrying the job
     */
    public int $jobBackoff = 5;

    /**
     * Handle the upload of a portfolio to Shopify
     *
     * @param  ShopifyUser  $shopifyUser  The Shopify user account to use for API access
     * @param  Portfolio  $portfolio  The portfolio to upload to Shopify
     * @param  array  $body  Optional additional data for the request body
     *
     * @throws \Exception If there's an error during the upload process
     * @throws \Throwable If there's an error during the transaction
     */
    public function handle(ShopifyUser $shopifyUser, Portfolio $portfolio, array $body = []): void
    {
        // Wrap everything in a transaction to ensure data integrity
        DB::transaction(function () use ($shopifyUser, $portfolio, $body) {
            try {
                $images = [];

                // Get the product associated with this portfolio
                /** @var Product $product */
                $product = $portfolio->item;

                // Convert all product images to base64 for Shopify API
                foreach ($product->images as $image) {
                    $images[] = [
                        "attachment" => $image->getBase64Image()
                    ];
                }

                // Prepare the product data for Shopify API
                $body = [
                    "product" => [
                        "id"           => $product->id,
                        "title"        => $portfolio->customer_product_name,
                        "handle"       => $portfolio->platform_handle,
                        "body_html"    => $portfolio->customer_description,
                        "vendor"       => $product->shop->name,
                        "product_type" => $product->family?->name,
                        "images"       => $images,
                        "variants"     => [
                            [
                                // Format price to 2 decimal places
                                "price"                => number_format($portfolio->customer_price, 2, '.', ''),
                                "sku"                  => $product->code,
                                "barcode"              => $product->barcode,
                                "inventory_management" => "shopify",
                                "inventory_policy"     => "deny", // Don't allow orders when out of stock
                                "weight"               => $product->marketing_weight,
                                "weight_unit"          => "g",
                                "cost"                 => $product->price,
                            ]
                        ]
                    ]
                ];


                // Check if the product already exists in Shopify
                $shopifyProduct = GetShopifyProductFromPortfolio::run($shopifyUser, $portfolio);

                if ($shopifyProduct == null) {
                    $shopifyProduct = $this->storeShopifyProduct($shopifyUser, $portfolio, $body);
                }


                // Update inventory levels in Shopify
                UpdateShopifyProductInventoryLevels::dispatch($product, $shopifyUser, $shopifyProduct);

                // Update our portfolio with the Shopify product ID
                UpdatePortfolio::run($portfolio, [
                    'platform_product_id' => Arr::get($shopifyProduct, 'id')
                ]);

                // Dispatch event to notify about upload progress
                UploadProductToShopifyProgressEvent::dispatch($shopifyUser, $portfolio);
            } catch (\Exception $e) {
                Sentry::captureMessage($e->getMessage());

                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$e->getMessage()]
                ]);


                $portfolio->refresh();
                UploadProductToShopifyProgressEvent::dispatch($shopifyUser, $portfolio);
            }
        });
    }


    private function storeShopifyProduct(ShopifyUser $shopifyUser, Portfolio $portfolio, array $body = [])
    {

        $client         = $shopifyUser->getShopifyClient();
        try {
            // Make API request to create product in Shopify
            $response = $client->request('POST', '/admin/api/2025-07/products.json', $body);

            if ($response['errors']) {
                // Log API errors in model
                $portfolio = UpdatePortfolio::run($portfolio, [
                    'errors_response' => [Arr::get($response, 'body')]
                ]);

                // Log error to Sentry for monitoring
                Sentry::captureMessage("Product upload failed: ".$portfolio->errors_response);
                return null;
            } else {
                // Extract product data from successful response
                return Arr::get($response, 'body.product');
            }
        } catch (Exception $e) {
            // Log exception to Sentry
            Sentry::captureMessage($e->getMessage());

            // Try to find the product again in case it was created despite the exception
            return GetShopifyProductFromPortfolio::run($shopifyUser, $portfolio);

        }
    }

}
