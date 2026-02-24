<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:58 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ReviveInActiveWooChannel extends OrgAction
{
    use asAction;
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        DB::transaction(function () use ($customerSalesChannel) {
            $this->update($customerSalesChannel, [
                'status' => CustomerSalesChannelStatusEnum::OPEN
            ]);

            $reviveWooUser = WooCommerceUser::where('customer_sales_channel_id', $customerSalesChannel->id)->restore();

            if ($reviveWooUser) {
                CheckWooChannel::run($customerSalesChannel->user);
            } else {
                throw ValidationException::withMessages(['message' => __('Unable to revive WooCommerce channel')]);
            }
        });
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        $this->handle($customerSalesChannel);
    }
}
