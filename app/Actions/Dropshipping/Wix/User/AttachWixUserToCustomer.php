<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\User;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\WixUser;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Claims an instance that was recorded before we knew whose it was, and gives it the sales
 * channel it would have got had the customer been known at install time.
 */
class AttachWixUserToCustomer
{
    use AsAction;

    public function handle(WixUser $wixUser, Customer $customer): WixUser
    {
        $wixUser->update([
            'customer_id'     => $customer->id,
            'group_id'        => $customer->group_id,
            'organisation_id' => $customer->organisation_id,
        ]);

        if (!$wixUser->customer_sales_channel_id) {
            $platform = Platform::where('group_id', $customer->group_id)
                ->where('type', PlatformTypeEnum::WIX->value)
                ->first();

            $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
                'platform_user_type' => class_basename($wixUser),
                'platform_user_id'   => $wixUser->id,
                'reference'          => $wixUser->name,
                'state'              => CustomerSalesChannelStateEnum::AUTHENTICATED
            ]);

            $wixUser->update(['customer_sales_channel_id' => $customerSalesChannel->id]);
        }

        return $wixUser->fresh();
    }
}
