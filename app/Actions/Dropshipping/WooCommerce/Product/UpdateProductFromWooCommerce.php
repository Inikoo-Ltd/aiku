<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateProductFromWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser): \GuzzleHttp\Promise\PromiseInterface
    {
        $productId = 0;
        $body      = [
            "product" => [
                "title" => "product title"
            ]
        ];

        // return $wooCommerceUser->api()->getRestClient()->request('PUT', '/admin/api/2024-04/products/'.$productId.'.json', $body);
    }

    public function asController(Customer $customer, WooCommerceUser $wooCommerceUser): \GuzzleHttp\Promise\PromiseInterface
    {
        return $this->handle($wooCommerceUser);
    }
}
