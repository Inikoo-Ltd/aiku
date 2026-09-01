<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WixUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class DeleteProductFromWix extends OrgAction
{
    use AsAction;

    public function handle(Portfolio $portfolio): void
    {
        /** @var WixUser $wixUser */
        $wixUser = $portfolio->customerSalesChannel?->user;

        if (!$wixUser instanceof WixUser || !$portfolio->platform_product_id) {
            return;
        }

        try {
            $wixUser->deleteProduct($portfolio->platform_product_id);
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }
    }
}
