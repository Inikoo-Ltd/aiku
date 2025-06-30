<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Magento\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteProductFromMagento extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(?Portfolio $portfolio, bool $forceDelete = false, bool $fromWebhook = false): null|int|WooCommerceUser
    {
        /** @var MagentoUser $magentoUser */
        $magentoUser = $portfolio->customerSalesChannel->user;

        if (!$portfolio) {
            return null;
        }

        if (!$fromWebhook && $portfolio->platform_product_id) {
            $magentoUser->deleteProduct($portfolio->platform_product_id);
        }

        return null;
    }
}
