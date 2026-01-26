<?php

/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-16h-32m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Client;

use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerClient;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use App\Traits\SanitizeInputs;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaCustomerClient extends RetinaAction
{
    use SanitizeInputs;

    public function handle(CustomerClient $customerClient, array $modelData): CustomerClient
    {
        $customerClient = UpdateCustomerClient::make()->action(
            $customerClient,
            $modelData
        );

        return $customerClient;
    }

    public function prepareForValidation(ActionRequest $request)
    {
        $this->setSanitizeFields([
            'contact_name',
            'company_name',
            'phone',
            'address',
        ]);
        $this->sanitizeInputs();
    }

    public function rules(): array
    {
        return [
        'contact_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
        'company_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
        'email'          => ['sometimes', 'nullable', 'email'],
        'phone'          => ['sometimes', 'nullable', new Phone()],
        'address'        => ['sometimes', new ValidAddress()],
        'status'         => ['sometimes', 'boolean'],
        ];
    }

    public function asController(CustomerClient $customerClient, ActionRequest $request)
    {
        $this->enableSanitize();
        $this->initialisation($request);

        $this->handle($customerClient, $this->validatedData);
    }


}
