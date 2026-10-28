<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CacheShopifyListedProducts implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';
    public int $jobTries = 1;
    public int $jobTimeout = 900;

    public function getJobUniqueId(int $shopifyUserId): string
    {
        return (string) $shopifyUserId;
    }

    public function handle(int $shopifyUserId): void
    {
        $shopifyUser = ShopifyUser::find($shopifyUserId);

        if ($shopifyUser) {
            GetShopifyListedProducts::cacheListedProducts($shopifyUser);
        }
    }
}
