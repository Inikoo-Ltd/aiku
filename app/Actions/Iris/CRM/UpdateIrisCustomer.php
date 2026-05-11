<?php

/*
 * Author: Vika Aqordi
 * Created on 11-05-2026-12h-31m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Iris\CRM;

use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class UpdateIrisCustomer extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): Customer
    {
        return UpdateCustomer::run($customer, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customer !== null;
    }

    public function rules(): array
    {
        return [
            'is_gift_opted_out' => ['required', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): Customer
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }

    public function jsonResponse(Customer $customer): array
    {
        return [
            'is_gift_opted_out' => (bool) ($customer->settings['is_gift_opted_out'] ?? false),
        ];
    }
}
