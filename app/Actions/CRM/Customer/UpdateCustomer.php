<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomers;
use App\Actions\CRM\Customer\Search\CustomerRecordSearch;
use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\Helpers\Tag\AttachTagsToModel;
use App\Actions\Helpers\TaxNumber\DeleteTaxNumber;
use App\Actions\Helpers\TaxNumber\StoreTaxNumber;
use App\Actions\Helpers\TaxNumber\UpdateTaxNumber;
use App\Actions\Ordering\Order\ResetOrderTaxCategory;
use App\Actions\Ordering\Order\UpdateOrderBillingAddress;
use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCustomers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCustomers;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Traits\WithProcessContactNameComponents;
use App\Actions\Traits\WithPrepareTaxNumberValidation;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use App\Rules\Phone;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithModelAddressActions;
    use WithNoStrictRules;
    use WithProcessContactNameComponents;
    use WithCRMEditAuthorisation;
    use WithPrepareTaxNumberValidation;

    private Customer $customer;

    public function handle(Customer $customer, array $modelData): Customer
    {
        if (Arr::has($modelData, 'contact_address')) {
            $contactAddressData = Arr::get($modelData, 'contact_address');

            Arr::forget($modelData, 'contact_address');


            if (!blank($contactAddressData)) {
                if ($customer->address) {
                    UpdateAddress::run($customer->address, $contactAddressData);
                } else {
                    $this->addAddressToModelFromArray(
                        model: $customer,
                        addressData: $contactAddressData,
                        scope: 'billing',
                        updateLocation: false,
                        canShip: true
                    );
                }
                $customer->refresh();

                /** @var Order $order */
                foreach ($customer->orders()->where('state', OrderStateEnum::CREATING)->get() as $order) {
                    $editDelivery = $order->billing_address_id == $order->delivery_address_id;

                    UpdateOrderBillingAddress::make()->action(
                        $order,
                        [
                            'address' => $customer->address->toArray()
                        ]
                    );

                    if ($editDelivery) {
                        UpdateOrderDeliveryAddress::make()->action($order, [
                            'address'       => $customer->address->toArray(),
                            'update_parent' => false
                        ]);
                    }
                }
            }

            $customer->refresh();
            data_set($modelData, 'location', $customer->address->getLocation());
        }
        if (Arr::has($modelData, 'delivery_address')) {
            $deliveryAddressData = Arr::get($modelData, 'delivery_address');
            Arr::forget($modelData, 'delivery_address');

            if ($customer->address_id != $customer->delivery_address_id) {
                UpdateAddress::run($customer->deliveryAddress, $deliveryAddressData);
            } else {
                $customer = $this->addAddressToModelFromArray(
                    model: $customer,
                    addressData: $deliveryAddressData,
                    scope: 'delivery',
                    updateLocation: false,
                    updateAddressField: 'delivery_address_id'
                );
            }
        }


        if (Arr::has($modelData, 'tax_number')) {
            if ($this->strict) {
                $taxNumberData = [];
                data_set($taxNumberData, 'number', Arr::get($modelData, 'tax_number.number'));
                data_set($taxNumberData, 'country_id', $customer->address->country_id);
                Arr::forget($modelData, 'tax_number');
            } else {
                $taxNumberData = Arr::pull($modelData, 'tax_number');
            }


            if (Arr::get($taxNumberData, 'number')) {
                if (!$customer->taxNumber) {
                    if (!Arr::get($taxNumberData, 'data.name')) {
                        Arr::forget($taxNumberData, 'data.name');
                    }

                    if (!Arr::get($taxNumberData, 'data.address')) {
                        Arr::forget($taxNumberData, 'data.address');
                    }


                    StoreTaxNumber::run(
                        owner: $customer,
                        modelData: $taxNumberData,
                        strict: $this->strict
                    );
                } else {
                    UpdateTaxNumber::run($customer->taxNumber, $taxNumberData, $this->strict);
                }
            } elseif ($customer->taxNumber) {
                DeleteTaxNumber::run($customer->taxNumber);
            }
        }
        if (Arr::hasAny($modelData, ['contact_name', 'company_name'])) {
            $contact_name = Arr::exists($modelData, 'contact_name') ? Arr::get($modelData, 'contact_name') : $customer->contact_name;
            $company_name = Arr::exists($modelData, 'company_name') ? Arr::get($modelData, 'company_name') : $customer->company_name;

            $modelData['name'] = $company_name ?: $contact_name;
        }

        if (Arr::has($modelData, 'contact_name')) {
            data_set($modelData, 'contact_name_components', $this->processContactNameComponents(Arr::get($modelData, 'contact_name')));
        }

        if (Arr::has($modelData, 'tags')) {
            AttachTagsToModel::make()->action($customer, [
                'tags_id' => Arr::pull($modelData, 'tags')
            ]);
        }

        $emailSubscriptionsData = Arr::pull($modelData, 'email_subscriptions', []);
        UpdateCustomerComms::run($customer->comms, $emailSubscriptionsData);

        $customer = $this->update($customer, $modelData, ['data', 'contact_name_components']);


        $changes = Arr::except($customer->getChanges(), ['updated_at', 'last_fetched_at']);

        if (Arr::hasAny($changes, ['contact_name', 'email'])) {
            $rootWebUser = $customer->webUsers->where('is_root', true)->first();
            if ($rootWebUser) {
                $rootWebUser->update(
                    [
                        'contact_name' => $customer->contact_name,
                        'email'        => $customer->email
                    ]
                );
            }
        }


        if (Arr::has($changes, 'state')) {
            GroupHydrateCustomers::dispatch($customer->group);
            OrganisationHydrateCustomers::dispatch($customer->organisation);
            ShopHydrateCustomers::dispatch($customer->shop);
        }


        if (Arr::hasAny($changes, ['is_re'])) {
            foreach ($customer->orders()->where('state', OrderStateEnum::CREATING)->whereNull('orders.source_id')->get() as $order) {
                $order->update(['is_re' => $customer->is_re]);
                ResetOrderTaxCategory::run($order);
            }
        }

        if (Arr::hasAny($changes, [
            'company_name',
            'contact_name',
            'email',
            'internal_notes',
            'warehouse_internal_notes',
            'warehouse_public_notes',
            'reference',
            'name',
            'state',
            'created_at',
            'location',
            'phone'
        ])) {
            CustomerRecordSearch::dispatch($customer)->delay($this->hydratorsDelay);
        }


        if (Arr::has($changes, 'email') && $customer->shop->is_aiku) {
            MatchCustomerProspects::run($customer);
        }


        return $customer;
    }

    public function rules(): array
    {
        $rules = [
            'contact_name'                                          => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'                                          => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'                                                 => [
                'sometimes',
                'nullable',
                $this->strict ? 'email' : 'string:500',
                new IUnique(
                    table: 'customers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->customer->id, 'operator' => '!=']
                    ]
                ),
            ],
            'phone'                                                 => [
                'sometimes',
                'nullable',
                $this->strict ? new Phone() : 'string:255',
            ],
            'identity_document_number'                              => ['sometimes', 'nullable', 'string'],
            'contact_website'                                       => ['sometimes', 'nullable', 'active_url'],
            'contact_address'                                       => ['sometimes', 'required', new ValidAddress()],
            'delivery_address'                                      => ['sometimes', 'nullable', new ValidAddress()],
            'delivery_address_id'                                   => ['sometimes', 'integer'],
            'timezone_id'                                           => ['sometimes', 'nullable', 'exists:timezones,id'],
            'language_id'                                           => ['sometimes', 'nullable', 'exists:languages,id'],
            'balance'                                               => ['sometimes', 'nullable'],
            'internal_notes'                                        => ['sometimes', 'nullable', 'string'],
            'warehouse_internal_notes'                              => ['sometimes', 'nullable', 'string'],
            'warehouse_public_notes'                                => ['sometimes', 'nullable', 'string'],
            'tax_number'                                            => ['sometimes', 'nullable', 'array'],
            'tags'                                                  => ['sometimes', 'array'],
            'email_subscriptions'                                   => ['sometimes', 'array'],
            'email_subscriptions.is_subscribed_to_newsletter'       => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_marketing'        => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_abandoned_cart'   => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_reorder_reminder' => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_basket_low_stock' => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_basket_reminder'  => ['sometimes', 'boolean'],
            'state'                                                 => ['sometimes', Rule::enum(CustomerStateEnum::class)],
            'is_re'                                                 => ['sometimes', 'boolean'],

        ];

        if ($this?->asAction) {
            $rules['status'] = ['sometimes', 'nullable', Rule::enum(CustomerStatusEnum::class)];
        }


        if (!$this->strict) {
            $rules['is_vip']             = ['sometimes', 'boolean'];
            $rules['as_organisation_id'] = ['sometimes', 'nullable', 'integer'];
            $rules['as_employee_id']     = ['sometimes', 'nullable', 'integer'];
            $rules['registered_at']      = ['sometimes', 'nullable', 'date'];


            $rules['phone']           = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['email']           = [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'exclude_unless:deleted_at,null',
                new IUnique(
                    table: 'customers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->customer->id, 'operator' => '!=']
                    ]
                ),
            ];
            $rules['contact_website'] = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules                    = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


    public function asController(Organisation $organisation, Customer $customer, ActionRequest $request): Customer
    {
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Customer
    {
        if (!$audit) {
            Customer::disableAuditing();
        }

        $this->asAction       = true;
        $this->customer       = $customer;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }


    public function jsonResponse(Customer $customer): CustomersResource
    {
        return new CustomersResource($customer);
    }
}
