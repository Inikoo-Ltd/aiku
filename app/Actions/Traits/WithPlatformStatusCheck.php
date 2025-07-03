<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 May 2025 16:58:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\WooCommerceUser;

trait WithPlatformStatusCheck
{
    public function checkStatus(CustomerSalesChannel $customerSalesChannel): ?string
    {
        $status = 'connected';
        if ($customerSalesChannel->user instanceof ShopifyUser) {
            $settings = $customerSalesChannel->user->settings ?? [];
            if (empty($settings) && empty($settings['webhook'])) {
                $status = 'not-connected';
            }
        } elseif ($customerSalesChannel->user instanceof WooCommerceUser) {
            $settings = $customerSalesChannel->user->settings ?? [];

            if (empty($settings['credentials']) or empty($settings['webhooks'])) {
                $status = 'not-connected';
            }
        }

        return $status;
    }
}
