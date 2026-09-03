<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Models\Dropshipping\WixUser;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWixListedSkus
{
    use AsAction;

    /**
     * @return array<string, string> lowercased sku => wix product id
     */
    public function handle(WixUser $wixUser): array
    {
        return $wixUser->catalog()->listedSkus();
    }
}
