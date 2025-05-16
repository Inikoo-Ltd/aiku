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
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaCustomerClient extends RetinaAction
{
    public function handle(CustomerClient $customerClient, array $modelData): CustomerClient
    {
        $customerClient = UpdateCustomerClient::make()->action(
            $customerClient,
            $modelData
        );

        return $customerClient;
    }

    public function rules(): array
    {
        $rules = [
        'contact_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
        'company_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
        'email'          => ['sometimes', 'nullable', 'email'],
        'phone'          => ['sometimes', 'nullable', new Phone()],
        'address'        => ['sometimes', new ValidAddress()],
        ];

        return $rules;
    }

    public function asController(CustomerClient $customerClient, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($customerClient, $this->validatedData);
    }

    // public function htmlResponse(CustomerClient $customerClient)
    // {
    //     return Redirect::route('retina.dropshipping.client.show', [$customerClient->ulid]);
    // }
}
