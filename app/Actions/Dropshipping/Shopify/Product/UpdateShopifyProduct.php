<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 08:28:03 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\HasBucketAttachment;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Helpers\Media;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Sentry;

class UpdateShopifyProduct extends RetinaAction
{
    use WithActionUpdate;
    use HasBucketAttachment;

    public function handle(Portfolio $portfolio): array
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $portfolio->customerSalesChannel->user;
        $website = $portfolio->customerSalesChannel?->shop?->website;

        $client = $shopifyUser->getShopifyClient(true); // Get GraphQL client

        if (!$client) {
            Sentry::captureMessage("Failed to initialize Shopify GraphQL client");

            return [false, 'Failed to initialize Shopify GraphQL client'];
        }

        // Check if product already exists in Shopify
        if (!$portfolio->platform_product_id) {
            $errorMessage = 'Product not found in Shopify. Please create the product first.';
            UpdatePortfolio::run($portfolio, [
                'errors_response' => [$errorMessage]
            ]);

            return [false, $errorMessage];
        }

        /** @var Product $product */
        $product = $portfolio->item;

        try {
            // GraphQL mutation to update only the product description
            $mutation = <<<'MUTATION'
            mutation productUpdate($input: ProductInput!) {
              productUpdate(input: $input) {
                product {
                  id
                  title
                  handle
                  descriptionHtml
                  productType
                  vendor
                  tags
                }
                userErrors {
                  field
                  message
                }
              }
            }
            MUTATION;

            // Build custom attributes from attachments
            $customAttributes = [];
            $tradeUnitAttachments = Arr::get($this->getAttachmentData($product), 'public', []);
            foreach ($tradeUnitAttachments as $key => $tradeUnitAttachment) {
                /** @var Media|null $attachment */
                $attachment = Arr::get($tradeUnitAttachment, 'attachment');

                if ($attachment) {
                    $customAttributes[] = [
                        'id' => (string)$attachment->id,
                        'name' => '<strong>' . Arr::get($tradeUnitAttachment, 'label') . '</strong>',
                        'option' => '<a href="https://' . $website?->domain . '/attachment/'.$attachment->ulid.'/download' . '">' .
                            Arr::get($tradeUnitAttachment, 'label') . '</a>'
                    ];
                }
            }

            // Build attachment links HTML
            $attachmentLinks = '';
            foreach ($customAttributes as $attr) {
                $attachmentLinks .= $attr['name'] . ': ' . $attr['option'] . '<br>';
            }

            $description = '<br><br>' . $attachmentLinks;

            // Prepare variables for the mutation - only updating descriptionHtml
            $variables = [
                'input' => [
                    'id'              => $portfolio->platform_product_id,
                    'descriptionHtml' => $product->description.' '.$product->description_extra . ' ' .$description,
                ]
            ];

            // Make the GraphQL request
            $response = $client->request($mutation, $variables);

            if (!empty($response['errors']) || !isset($response['body'])) {
                $errorMessage = 'Error in API response: '.json_encode($response['errors']);
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
                Sentry::captureMessage("Product update failed: ".$errorMessage);

                return [false, $errorMessage];
            }

            $body = $response['body']->toArray();

            // Check for user errors in the response
            if (!empty($body['data']['productUpdate']['userErrors'])) {
                $errors       = $body['data']['productUpdate']['userErrors'];
                $errorMessage = 'User errors: '.json_encode($errors);
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => [$errorMessage]
                ]);
                Sentry::captureMessage("Product update failed: ".$errorMessage);

                return [false, $errorMessage];
            }

            // Get the updated product
            $updatedProduct = $body['data']['productUpdate']['product'] ?? null;

            if (!$updatedProduct) {
                UpdatePortfolio::run($portfolio, [
                    'errors_response' => ['No product data in response']
                ]);
                Sentry::captureMessage("Product update failed: No product data in response");

                return [false, 'No product data in response'];
            }

            // Clear any previous errors
            UpdatePortfolio::run($portfolio, [
                'errors_response' => null
            ]);

            // Format the response
            return [true, $this->formatProductResponse($updatedProduct)];
        } catch (Exception $e) {
            Sentry::captureException($e);
            UpdatePortfolio::run($portfolio, [
                'errors_response' => [$e->getMessage()]
            ]);

            return [false, $e->getMessage()];
        }
    }

    private function formatProductResponse(array $product): array
    {
        return [
            'id'           => $product['id'],
            'title'        => $product['title'],
            'handle'       => $product['handle'],
            'body_html'    => $product['descriptionHtml'],
            'vendor'       => $product['vendor'] ?? null,
            'product_type' => $product['productType'] ?? null,
        ];
    }

    public function getCommandSignature(): string
    {
        return 'shopify:product:update {portfolio_id}';
    }

    public function asCommand(Command $command): void
    {
        /** @var Portfolio $portfolio */
        $portfolio = Portfolio::find($command->argument('portfolio_id'));

        if (!$portfolio) {
            $command->error("Portfolio not found");

            return;
        }

        $customerSalesChannel = $portfolio->customerSalesChannel;

        if (!$customerSalesChannel) {
            $command->error("Customer sales channel not found for this portfolio");

            return;
        }

        $shopifyUser = $customerSalesChannel->user;

        if (!$shopifyUser) {
            $command->error("Shopify user not found for this customer sales channel");

            return;
        }

        if (!$portfolio->platform_product_id) {
            $command->error("Product not found in Shopify. Please create the product first.");

            return;
        }

        $command->info("Updating product description in Shopify for portfolio #$portfolio->id...");

        [$status, $result] = $this->handle($portfolio);

        if (!$status) {
            $command->error("Failed to update product description in Shopify");
            $command->error("Errors:");
            $command->error(implode("\n", $portfolio->errors_response ?? []));

            return;
        }

        // Display the product data in a table format
        $command->info("Product description updated successfully!");
        $command->table(['Field', 'Value'], [
            ['ID', $result['id']],
            ['Title', $result['title']],
            ['Handle', $result['handle']],
            ['Vendor', $result['vendor'] ?? 'N/A'],
            ['Product Type', $result['product_type'] ?? 'N/A'],
        ]);
    }
}
