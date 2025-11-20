<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\Ebay\Traits\WithEbayApiRequest;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\EbayUserStepEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\EbayUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CheckEbayUserCreating extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithEbayApiRequest;

    public function handle(Customer $customer): EbayUser
    {
        return EbayUser::where('customer_id', $customer->id)
            ->whereNot('step', EbayUserStepEnum::COMPLETED)
            ->orderBy('updated_at', 'desc')
            ->first();
    }

    public function asController(ActionRequest $request): EbayUser
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }
}
