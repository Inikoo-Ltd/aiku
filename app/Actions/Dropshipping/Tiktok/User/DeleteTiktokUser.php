<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\TiktokUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteTiktokUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser): void
    {

        UpdateCustomerSalesChannel::run($tiktokUser->customerSalesChannel, [
            'status' => CustomerSalesChannelStatusEnum::CLOSED
        ]);


        $tiktokUser->delete();
    }

    public function asController(TiktokUser $tiktokUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($tiktokUser);
    }
}
