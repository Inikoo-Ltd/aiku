<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class AuthCheckCreateTiktokChannel extends RetinaAction
{
    use asAction;
    use WithActionUpdate;

    public function handle(Customer $customer): CustomerSalesChannel
    {
        $platform = Platform::where('type', PlatformTypeEnum::TIKTOK->value)->first();

        $customerSalesChannel = CustomerSalesChannel::where('customer_id', $customer->id)
            ->where('platform_id', $platform->id)
            ->whereIn('state', [CustomerSalesChannelStateEnum::AUTHENTICATED, CustomerSalesChannelStateEnum::READY])
            ->first();

        if (!$customerSalesChannel) {
            throw ValidationException::withMessages(['message' => __('No authenticated TikTok channel found.')]);
        }

        $customerSalesChannel = CheckTiktokChannel::run($customerSalesChannel->user);

        if (!$customerSalesChannel->can_connect_to_platform) {
            throw ValidationException::withMessages(['message' => __('Your TikTok channel is not authorized yet. Please contact support.')]);
        }

        return $customerSalesChannel;
    }

    public function asController(ActionRequest $request): CustomerSalesChannel
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }
}
