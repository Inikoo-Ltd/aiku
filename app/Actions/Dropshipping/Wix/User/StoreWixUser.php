<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\User;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\WixUser;

class StoreWixUser extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): WixUser
    {
        $platform = Platform::where('group_id', $customer->group_id)
            ->where('type', PlatformTypeEnum::WIX->value)
            ->first();

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);

        /** @var WixUser $wixUser */
        $wixUser = $customer->wixUsers()->create($modelData);

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => class_basename($wixUser),
            'platform_user_id'   => $wixUser->id,
            'reference'          => $wixUser->name,
            'name'               => $wixUser->name,
            'state'              => CustomerSalesChannelStateEnum::AUTHENTICATED
        ]);

        $wixUser->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);

        return $wixUser;
    }

    public function rules(): array
    {
        return [
            'wix_instance_id'        => ['required', 'string'],
            'wix_site_id'            => ['sometimes', 'nullable', 'string'],
            'name'                   => ['required', 'string'],
            'email'                  => ['sometimes', 'nullable', 'email'],
            'site_url'               => ['sometimes', 'nullable', 'string'],
            'access_token'           => ['sometimes', 'nullable', 'string'],
            'access_token_expire_in' => ['sometimes', 'nullable'],
        ];
    }

    public function action(Customer $customer, array $modelData): WixUser
    {
        $this->asAction = true;
        $this->initialisationActions($customer, $modelData);

        return $this->handle($customer, $this->validatedData);
    }
}
