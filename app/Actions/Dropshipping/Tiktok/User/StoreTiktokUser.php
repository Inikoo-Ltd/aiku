<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\TiktokUser;

class StoreTiktokUser extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): TiktokUser
    {
        $platform = Platform::where('type', PlatformTypeEnum::TIKTOK->value)->first();


        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'platform_id', $platform->id);

        /** @var TiktokUser $tikTokUser */
        $tikTokUser = $customer->tiktokUser()->create($modelData);

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
            'platform_user_type' => class_basename($tikTokUser),
            'platform_user_id' => $tikTokUser->id,
        ]);

        $tikTokUser->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);
        return $tikTokUser;
    }

    public function rules(): array
    {
        return [
            'tiktok_id'               => ['required', 'string'],
            'name'                    => ['required', 'string'],
            'username'                => ['required', 'string'],
            'access_token'            => ['required', 'string'],
            'access_token_expire_in'  => ['required'],
            'refresh_token'           => ['required', 'string'],
            'refresh_token_expire_in' => ['required']
        ];
    }

    public function action(Customer $customer, array $modelData): TiktokUser
    {
        $this->initialisationActions($customer, $modelData);

        return $this->handle($customer, $this->validatedData);
    }
}
