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
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ProcessUnregisterCustomerTiktokUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): void
    {
        $decodedData = json_decode(base64_decode(Arr::get($modelData, 'tiktok_code')), true);
        $tiktokUserId = Arr::get($decodedData, 'tiktok_user_id');

        if(! $tiktokUserId) {
            return;
        }

        $tiktokUser = TiktokUser::find($tiktokUserId);

        if(! $tiktokUser) {
            return;
        }

        $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $tiktokUser->platform_id, [
            'platform_user_type' => class_basename($tiktokUser),
            'platform_user_id' => $tiktokUser->id,
            'reference' => $tiktokUser->name,
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED
        ]);
        $tiktokUser->updateQuietly([
            'customer_sales_channel_id' => $customerSalesChannel->id,
            'customer_id' => $customer->id
        ]);
    }
}
