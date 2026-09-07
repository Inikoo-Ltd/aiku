<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\User;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\WixUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteWixUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(WixUser $wixUser): void
    {
        UpdateCustomerSalesChannel::run($wixUser->customerSalesChannel, [
            'status' => CustomerSalesChannelStatusEnum::CLOSED
        ]);

        $wixUser->delete();
    }

    public function asController(WixUser $wixUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($wixUser);
    }
}
