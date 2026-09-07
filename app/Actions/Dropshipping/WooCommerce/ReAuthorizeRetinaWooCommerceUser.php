<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\WooCommerce\Traits\WithWooCommerceAuthorizationToken;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ReAuthorizeRetinaWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithWooCommerceAuthorizationToken;

    public const int STAFF_LINK_TTL_SECONDS = 7 * 24 * 3600;

    /**
     * The customer clicks this within the hour from retina; a link customer services sends by
     * email needs to survive until the customer gets round to it, hence the longer life.
     */
    public function handle(WooCommerceUser $wooCommerceUser, int $ttlSeconds = 3600): string
    {
        $token = $this->storeWooAuthorizationToken([
            'woo_commerce_user_id' => $wooCommerceUser->id
        ], $ttlSeconds);

        $params = [
            'app_name' => 'AW Connect',
            'scope' => 'read_write',
            'user_id' => $token,
            'return_url' => route('retina.dropshipping.customer_sales_channels.index'),
            'callback_url' => route('webhooks.woo.callback')
        ];

        return rtrim($wooCommerceUser->store_url, '/') . '/wc-auth/v1/authorize?' . http_build_query($params);
    }

    public function jsonResponse(string $url): string
    {
        return $url;
    }
}
