<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Dropshipping\Client;

use App\Actions\Dropshipping\WooCommerce\Clients\FetchRetinaCustomerClientFromWooCommerce;
use App\Actions\Retina\Dropshipping\Client\Traits\WithGeneratedShopifyAddress;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class FetchRetinaFulfilmentCustomerClientFromWooCommerce extends RetinaAction
{
    use WithGeneratedShopifyAddress;

    /**
     * @throws \Throwable
     */
    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        FetchRetinaCustomerClientFromWooCommerce::run($wooCommerceUser);
    }


    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->platform->type !== PlatformTypeEnum::WOOCOMMERCE) {
            $validator->errors()->add('platform', 'The platform type must be WooCommerce.');
        }

    }


    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {

        $this->initialisation($request);
        /** @var WooCommerceUser $wooCommerceUser */
        $wooCommerceUser = $customerSalesChannel->user;
        $this->handle($wooCommerceUser);
    }
}
