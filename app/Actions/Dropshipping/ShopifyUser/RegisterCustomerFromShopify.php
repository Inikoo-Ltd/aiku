<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\ShopifyUser;

use App\Actions\CRM\Customer\ApproveCustomer;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Fulfilment\FulfilmentCustomer\RegisterFulfilmentCustomer;
use App\Actions\Fulfilment\RentalAgreement\StoreRentalAgreement;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RegisterCustomerFromShopify extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(ShopifyUser $shopifyUser, Fulfilment $fulfilment): FulfilmentCustomer
    {
        data_set($modelData, 'group_id', $fulfilment->group_id);
        data_set($modelData, 'organisation_id', $fulfilment->organisation_id);
        data_set($modelData, 'contact_name', $shopifyUser->name);
        data_set($modelData, 'company_name', $shopifyUser->name);
        data_set($modelData, 'email', $shopifyUser->email);
        data_set($modelData, 'password', $shopifyUser->name);
        data_set($modelData, 'phone', '81234567890');
        data_set($modelData, 'contact_address', $fulfilment->shop->address->toArray());
        data_set($modelData, 'interest', ['dropshipping', 'items_storage', 'pallets_storage']);
        data_set($modelData, 'product', 'unknown');
        data_set($modelData, 'shipments_per_week', 'unknown');
        data_set($modelData, 'size_and_weight', 'unknown');

        $customerExists = Customer::where('email', $shopifyUser->email)->first();
        $fulfilmentCustomer = $customerExists?->fulfilmentCustomer;

        if (!$fulfilmentCustomer) {
            $fulfilmentCustomer = RegisterFulfilmentCustomer::make()->action($fulfilment, $modelData);
            ApproveCustomer::run($fulfilmentCustomer->customer);
            StoreRentalAgreement::make()->action($fulfilmentCustomer, [
                'billing_cycle' => RentalAgreementBillingCycleEnum::MONTHLY,
                'state' => RentalAgreementStateEnum::ACTIVE
            ]);
        }

        StoreCustomerSalesChannel::make()->action($fulfilmentCustomer->customer, Platform::where('type', PlatformTypeEnum::SHOPIFY->value)->first(), []);

        $this->update($shopifyUser, [
            'customer_id' => $fulfilmentCustomer->customer_id,
            'organisation_id' => $fulfilmentCustomer->organisation_id,
            'group_id' => $fulfilmentCustomer->group_id
        ]);

        return $fulfilmentCustomer;
    }
}
