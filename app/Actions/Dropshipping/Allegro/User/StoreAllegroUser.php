<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 05 Mar 2026 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\AllegroUser;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;

class StoreAllegroUser extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): AllegroUser
    {
        $platform = Platform::where('type', PlatformTypeEnum::ALLEGRO->value)->first();

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);

        /** @var AllegroUser $allegroUser */
        $allegroUser = $customer->allegroUsers()->create($modelData);

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => class_basename($allegroUser),
            'platform_user_id' => $allegroUser->id,
            'reference' => $allegroUser->name,
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED
        ]);

        $allegroUser->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);

        return $allegroUser;
    }

    public function rules(): array
    {
        return [
            'allegro_id'              => ['required', 'string'],
            'name'                    => ['required', 'string'],
            'username'                => ['sometimes', 'string'],
            'email'                   => ['sometimes', 'nullable', 'email'],
            'access_token'            => ['required', 'string'],
            'access_token_expire_in'  => ['required'],
            'refresh_token'           => ['required', 'string'],
            'refresh_token_expire_in' => ['required'],
            'marketplace_id'          => ['nullable', 'string'],
            'auth_type'               => ['sometimes', 'string'],
        ];
    }

    public function action(Customer $customer, array $modelData): AllegroUser
    {
        $this->initialisationActions($customer, $modelData);

        return $this->handle($customer, $this->validatedData);
    }
}
