<?php

/*
 * author Arya Permana - Kirin
 * created on 27-03-2025-13h-33m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\CRM;

use App\Actions\CRM\Customer\UpdateCustomerAddress;
use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaCustomerAddress extends RetinaAction
{
    public function handle(Customer $customer, array $modelData): void
    {
        UpdateCustomerAddress::run($customer, $modelData);
    }

    public function rules(): array
    {
        return [
            'address'             => ['sometimes'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return false;
    }

    public function asController(Customer $customer, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customer, $this->validatedData);
    }
}
