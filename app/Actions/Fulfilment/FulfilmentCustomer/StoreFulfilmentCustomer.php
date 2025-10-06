<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\OrgAction;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreFulfilmentCustomer extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Fulfilment $fulfilment, array $modelData): FulfilmentCustomer
    {
        data_set($fulfilmmentCustomerModelData, 'pallets_storage', in_array('pallets_storage', $modelData['interest']));
        data_set($fulfilmmentCustomerModelData, 'items_storage', in_array('items_storage', $modelData['interest']));
        data_set($fulfilmmentCustomerModelData, 'dropshipping', in_array('dropshipping', $modelData['interest']));

        $customer = StoreCustomer::make()->action($fulfilment->shop, $modelData);

        UpdateFulfilmentCustomer::run($customer->fulfilmentCustomer, $fulfilmmentCustomerModelData);

        return $customer->fulfilmentCustomer;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'reference'                => ['sometimes', 'string', 'max:16'],
            'state'                    => ['sometimes', Rule::enum(CustomerStateEnum::class)],
            'status'                   => ['sometimes', Rule::enum(CustomerStatusEnum::class)],
            'contact_name'             => ['nullable', 'string', 'max:255'],
            'company_name'             => ['nullable', 'string', 'max:255'],
            'email'                    => [
                'nullable',
                'string',
                'max:255',
                'exclude_unless:deleted_at,null',
                new IUnique(
                    table: 'customers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'phone'                    => ['nullable', 'max:255'],
            'identity_document_number' => ['nullable', 'string'],
            'contact_website'          => ['nullable', 'string', 'max:255'],
            'contact_address'          => ['required', new ValidAddress()],
            'delivery_address'         => ['sometimes', 'required', new ValidAddress()],
            'interest'                 => ['sometimes', 'required'],
            'timezone_id'              => ['nullable', 'exists:timezones,id'],
            'language_id'              => ['nullable', 'exists:languages,id'],
            'data'                     => ['sometimes', 'array'],
            'source_id'                => ['sometimes', 'nullable', 'string'],
            'created_at'               => ['sometimes', 'nullable', 'date'],
            'deleted_at'               => ['sometimes', 'nullable', 'date'],
            'password'                 =>
                [
                    'sometimes',
                    'required',
                    app()->isLocal() || app()->environment('testing') ? null : Password::min(8)
                ],

        ];
    }

    public function htmlResponse(FulfilmentCustomer $fulfilmentCustomer): Response
    {
        return Inertia::location(route('grp.org.fulfilments.show.crm.customers.show', [
            'organisation'       => $fulfilmentCustomer->organisation->slug,
            'fulfilment'         => $fulfilmentCustomer->fulfilment->slug,
            'fulfilmentCustomer' => $fulfilmentCustomer->slug
        ]));
    }

    /**
     * @throws \Throwable
     */
    public function asController(Fulfilment $fulfilment, ActionRequest $request): FulfilmentCustomer
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Fulfilment $fulfilment, array $modelData): FulfilmentCustomer
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($fulfilment, $modelData);

        return $this->handle($fulfilment, $this->validatedData);
    }

}
