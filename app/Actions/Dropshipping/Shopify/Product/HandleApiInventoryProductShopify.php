<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleApiInventoryProductShopify extends OrgAction implements ShouldBeUnique
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $jobQueue = 'shopify';

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, array $productVariants): void
    {
        $client = $shopifyUser->api()->getRestClient();

        $locations = $client->request('GET', '/admin/api/2025-04/locations.json');
        $allLocations = Arr::get($locations, 'body.locations', []);

        $targetLocation = null;
        foreach ($allLocations as $location) {
            // Skip fulfillment service locations
            if (!Arr::get($location, 'legacy', false)) {
                $targetLocation = $location;
                break;
            }
        }

        if (!$targetLocation) {
            $targetLocation = Arr::first($allLocations);
        }

        $locationId = Arr::get($targetLocation, 'id');

        \Log::info("Using location: " . Arr::get($targetLocation, 'name') . " (ID: {$locationId})");

        foreach ($productVariants as $variant) {
            $inventoryItemId = Arr::get($variant, 'inventory_item_id');

            // First, check existing inventory levels for this item
            $existingLevels = $client->request('GET', "/admin/api/2025-04/inventory_levels.json?inventory_item_ids={$inventoryItemId}");
            $currentLevels = Arr::get($existingLevels, 'body.inventory_levels', []);

            // Check if item is already active at a fulfillment service location
            $hasActiveFulfillmentLocation = false;
            foreach ($currentLevels as $level) {
                $levelLocationId = Arr::get($level, 'location_id');

                // Find the location details
                $levelLocation = collect($allLocations)->firstWhere('id', $levelLocationId);

                if ($levelLocation && Arr::get($levelLocation, 'legacy', false)) {
                    $hasActiveFulfillmentLocation = true;
                    \Log::warning("Item {$inventoryItemId} is already active at fulfillment service location: " . Arr::get($levelLocation, 'name'));
                    break;
                }
            }

            // Skip setting inventory if already active at fulfillment service
            if ($hasActiveFulfillmentLocation) {
                \Log::info("Skipping inventory update for item {$inventoryItemId} due to fulfillment service conflict");
                continue;
            }

            try {
                $response = $client->request('POST', '/admin/api/2025-04/inventory_levels/set.json', [
                    'location_id' => $locationId,
                    'inventory_item_id' => $inventoryItemId,
                    'available' => Arr::get($variant, 'available_quantity', 100)
                ]);

                if (Arr::get($response, 'status') !== 200) {
                    \Log::error("Failed to set inventory for item {$inventoryItemId}", [
                        'response' => $response,
                        'location_id' => $locationId,
                        'available' => Arr::get($variant, 'available_quantity', 100)
                    ]);

                    // Handle specific error cases
                    $errorBody = Arr::get($response, 'body', []);
                    if (str_contains(json_encode($errorBody), 'fulfillment service location')) {
                        // Try to deactivate at fulfillment locations first
                        $this->deactivateAtFulfillmentLocations($client, $inventoryItemId, $allLocations);

                        // Retry the request
                        $retryResponse = $client->request('POST', '/admin/api/2025-04/inventory_levels/set.json', [
                            'location_id' => $locationId,
                            'inventory_item_id' => $inventoryItemId,
                            'available' => Arr::get($variant, 'available_quantity', 100)
                        ]);

                        if (Arr::get($retryResponse, 'status') !== 200) {
                            \Sentry\captureMessage("Failed to set inventory after deactivating fulfillment locations: " . json_encode(Arr::get($retryResponse, 'body', [])));
                        }
                    } else {
                        \Sentry\captureMessage("Inventory update failed: " . json_encode($errorBody));
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Exception setting inventory for item {$inventoryItemId}: " . $e->getMessage());
                \Sentry\captureException($e);
            }
        }
    }

    private function deactivateAtFulfillmentLocations($client, $inventoryItemId, $allLocations): void
    {
        try {
            // Get current inventory levels
            $existingLevels = $client->request('GET', "/admin/api/2025-04/inventory_levels.json?inventory_item_ids={$inventoryItemId}");
            $currentLevels = Arr::get($existingLevels, 'body.inventory_levels', []);

            foreach ($currentLevels as $level) {
                $levelLocationId = Arr::get($level, 'location_id');
                $levelLocation = collect($allLocations)->firstWhere('id', $levelLocationId);

                // If this is a fulfillment service location, deactivate it
                if ($levelLocation && Arr::get($levelLocation, 'legacy', false)) {
                    $client->request('POST', '/admin/api/2025-04/inventory_levels/set.json', [
                        'location_id' => $levelLocationId,
                        'inventory_item_id' => $inventoryItemId,
                        'available' => 0
                    ]);

                    \Log::info("Deactivated inventory at fulfillment service location: " . Arr::get($levelLocation, 'name'));
                }
            }
        } catch (\Exception $e) {
            \Log::error("Failed to deactivate fulfillment locations: " . $e->getMessage());
        }
    }

    public function getJobUniqueId(ShopifyUser $shopifyUser, array $productVariants): int
    {
        return rand();
    }
}
