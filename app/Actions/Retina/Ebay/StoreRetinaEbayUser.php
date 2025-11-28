<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Ebay;

use App\Actions\Dropshipping\Ebay\StoreEbayUser;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\EbayUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaEbayUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): EbayUser
    {
        return StoreEbayUser::run($customer, $modelData);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string']
        ];
    }

    public function asController(ActionRequest $request): EbayUser
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }
}
