<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Models\Dropshipping\WixUser;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchWixProducts
{
    use AsAction;

    /**
     * Products the seller already has on their Wix site, for picking one to match a portfolio to.
     *
     * @return array<int, array{id: string, name: string|null, sku: string|null, code: string|null, image: string|null, images: array<int, array{src: string}>, price: float|null}>
     */
    public function handle(?WixUser $wixUser, string $query = '', int $offset = 0, int $limit = 50): array
    {
        if (!$wixUser instanceof WixUser) {
            return [];
        }

        return $wixUser->catalog()->searchProducts($query, $offset, $limit);
    }
}
