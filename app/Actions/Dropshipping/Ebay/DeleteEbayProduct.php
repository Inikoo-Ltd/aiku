<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 May 2026 05:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay;

use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Portfolio;
use Exception;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsObject;
use Sentry;

class DeleteEbayProduct
{
    use AsObject;

    public function handle(Portfolio $portfolio): void
    {
        /** @var EbayUser $ebayUser */
        $ebayUser = $portfolio->customerSalesChannel->user;
        if (!$ebayUser) {
            Log::error("Ebay user not found in DeleteEbayProduct");

            return;
        }

        try {
            $ebayUser->withdrawOffer($portfolio->platform_product_id);
        } catch (Exception $e) {
            Sentry::captureException($e);
        }
    }
}
