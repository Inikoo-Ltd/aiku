<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ReAuthorizeRetinaWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:authorize-woo {customer} {name} {url}';

    public function handle(WooCommerceUser $wooCommerceUser): string
    {
        $endpoint = '/wc-auth/v1/authorize';
        $params = [
            'app_name' => 'AW Connect',
            'scope' => 'read_write',
            'user_id' => $wooCommerceUser->id,
            'return_url' => route('retina.dropshipping.customer_sales_channels.index'),
            'callback_url' => route('webhooks.woo.callback')
        ];

        return Arr::get($wooCommerceUser, 'settings.credentials.store_url').$endpoint.'?'.http_build_query($params);
    }

    public function jsonResponse(string $url): string
    {
        return $url;
    }
}
