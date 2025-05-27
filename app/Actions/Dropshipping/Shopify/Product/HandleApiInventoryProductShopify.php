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
        $locationId = Arr::get($locations, 'body.locations.0.id');

        foreach ($productVariants as $variant) {
            $response = $client->request('POST', '/admin/api/2025-04/inventory_levels/set.json', [
                'location_id' => $locationId,
                'inventory_item_id' => Arr::get($variant, 'inventory_item_id'),
                'available' => Arr::get($variant, 'available_quantity', 100)
            ]);

            if (Arr::get($response, 'status') !== 200) {
                \Sentry\captureMessage(Arr::get($response, 'body', 'Unknown error'));
            }
        }
    }
}
