<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCrmStats;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomerInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomers;
use App\Actions\CRM\Customer\Search\CustomerRecordSearch;
use App\Actions\Fulfilment\FulfilmentCustomer\StoreFulfilmentCustomerFromCustomer;
use App\Actions\Helpers\Address\ParseCountryID;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\Helpers\TaxNumber\StoreTaxNumber;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCustomers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCustomers;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class StoreCustomer extends OrgAction
{
    use AsCommand;
    use WithModelAddressActions;
    use WithNoStrictRules;


    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Customer
    {
        $contactAddressData = Arr::get($modelData, 'contact_address', []);
        Arr::forget($modelData, 'contact_address');
        $deliveryAddressData = Arr::get($modelData, 'delivery_address', []);
        Arr::forget($modelData, 'delivery_address');
        $taxNumberData = Arr::get($modelData, 'tax_number');
        Arr::forget($modelData, 'tax_number');

        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            $modelData['is_dropshipping'] = true;
        } elseif ($shop->type == ShopTypeEnum::FULFILMENT) {
            $modelData['is_fulfilment'] = true;
        }


        if (!Arr::get($modelData, 'reference')) {
            data_set($modelData, 'reference', GetSerialReference::run(container: $shop, modelType: SerialReferenceModelEnum::CUSTOMER));
        }

        data_fill(
            $modelData,
            'status',
            ((Arr::get($shop->settings, 'registration_type', 'open') == 'approval-only') || ($shop->type === ShopTypeEnum::FULFILMENT))
                ?
                CustomerStatusEnum::PENDING_APPROVAL
                :
                CustomerStatusEnum::APPROVED
        );

        $emailSubscriptionsData = Arr::pull($modelData, 'email_subscriptions', []);

        $customer = DB::transaction(function () use ($shop, $modelData, $contactAddressData, $deliveryAddressData, $taxNumberData, $emailSubscriptionsData) {
            /** @var Customer $customer */
            $customer = $shop->customers()->create($modelData);
            $customer->stats()->create();
            $customer->comms()->create(
                array_merge(
                    $this->getCommsBaseValues(),
                    $emailSubscriptionsData
                )
            );

            if ($customer->is_fulfilment) {
                StoreFulfilmentCustomerFromCustomer::run($customer, $shop, ['source_id' => $customer->source_id]);
            }

            $customer = $this->addAddressToModelFromArray(
                model: $customer,
                addressData: $contactAddressData,
                scope: 'billing',
                canShip: true
            );
            $customer->refresh();

            if ($deliveryAddressData) {
                $customer = $this->addAddressToModelFromArray(
                    model: $customer,
                    addressData: $deliveryAddressData,
                    scope: 'delivery',
                    updateLocation: false,
                    updateAddressField: 'delivery_address_id'
                );
            } else {
                $customer->updateQuietly(['delivery_address_id' => $customer->address_id]);
            }


            if ($taxNumberData) {
                if (!Arr::get($taxNumberData, 'data.name')) {
                    Arr::forget($taxNumberData, 'data.name');
                }

                if (!Arr::get($taxNumberData, 'data.address')) {
                    Arr::forget($taxNumberData, 'data.address');
                }

                StoreTaxNumber::run(
                    owner: $customer,
                    modelData: $taxNumberData
                );
            }

            return $customer;
        });

        ShopHydrateCrmStats::dispatch($customer->shop)->delay($this->hydratorsDelay);
        ShopHydrateCustomers::dispatch($customer->shop)->delay($this->hydratorsDelay);
        ShopHydrateCustomerInvoices::dispatch($customer->shop)->delay($this->hydratorsDelay);
        GroupHydrateCustomers::dispatch($customer->group)->delay($this->hydratorsDelay);
        OrganisationHydrateCustomers::dispatch($customer->organisation)->delay($this->hydratorsDelay);

        CustomerRecordSearch::dispatch($customer);


        return $customer;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    private function getCommsBaseValues(): array
    {
        return [
            'is_subscribed_to_newsletter'        => false,
            'is_subscribed_to_marketing'         => false,
            'is_subscribed_to_abandoned_cart'    => true,
            'is_subscribed_to_reorder_reminder'  => true,
            'is_subscribed_to_basket_low_stock'  => true,
            'is_subscribed_to_basket_reminder_1' => true,
            'is_subscribed_to_basket_reminder_2' => true,
            'is_subscribed_to_basket_reminder_3' => true,

        ];
    }

    public function rules(): array
    {
        $rules = [
            'reference'                => [
                'sometimes',
                'string',
                'max:16',
                new IUnique(
                    table: 'customers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),
            ],
            'state'                    => ['sometimes', Rule::enum(CustomerStateEnum::class)],
            'status'                   => ['sometimes', Rule::enum(CustomerStatusEnum::class)],
            'contact_name'             => ['nullable', 'string', 'max:255'],
            'company_name'             => ['nullable', 'string', 'max:255'],
            'email'                    => [
                'nullable',
                $this->strict ? 'email' : 'string:500',
                new IUnique(
                    table: 'customers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'phone'                    => [
                'nullable', 'string:32'
            ],
            'identity_document_number' => ['sometimes', 'nullable', 'string'],
            'contact_website'          => ['sometimes', 'nullable', 'active_url'],
            'contact_address'          => ['required', new ValidAddress()],
            'delivery_address'         => ['sometimes', 'required', new ValidAddress()],


            'timezone_id'              => ['nullable', 'exists:timezones,id'],
            'language_id'              => ['nullable', 'exists:languages,id'],
            'data'                     => ['sometimes', 'array'],
            'registered_at'            => ['sometimes', 'nullable', 'date'],
            'internal_notes'           => ['sometimes', 'nullable', 'string'],
            'warehouse_internal_notes' => ['sometimes', 'nullable', 'string'],
            'warehouse_public_notes'   => ['sometimes', 'nullable', 'string'],

            'email_subscriptions' => ['sometimes', 'array'],
            'email_subscriptions.is_subscribed_to_newsletter' => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_marketing' => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_abandoned_cart' => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_reorder_reminder' => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_basket_low_stock' => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_basket_reminder_1' => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_basket_reminder_2' => ['sometimes', 'boolean'],
            'email_subscriptions.is_subscribed_to_basket_reminder_3' => ['sometimes', 'boolean'],


            'password' =>
                [
                    'sometimes',
                    'required',
                    app()->isLocal() || app()->environment('testing') ? Password::min(3) : Password::min(8)
                ],

        ];

        if (!$this->strict) {

            $rules['is_vip'] = ['sometimes', 'boolean'];
            $rules['as_organisation_id'] = ['sometimes','nullable', 'integer'];
            $rules['as_employee_id'] = ['sometimes','nullable', 'integer'];

            $rules['phone']           = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['email']           = [
                'nullable',
                'string',
                'max:255',
                'exclude_unless:deleted_at,null',
                new IUnique(
                    table: 'customers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ];
            $rules['contact_website'] = ['sometimes', 'nullable', 'string', 'max:255'];

            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->get('contact_name') && !$this->get('company_name') && !$this->get('email')) {
            $validator->errors()->add('company_name', 'At least one of contact_name, company_name or email must be provided');
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Customer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Customer
    {
        if (!$audit) {
            Customer::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    public string $commandSignature = 'customer:create {shop} {--contact_name=} {--company_name=} {--email=} {--phone=}  {--contact_website=} {--country=} ';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $this->asAction = true;
        $this->strict   = true;

        try {
            /** @var Shop $shop */
            $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        } catch (Exception) {
            $command->error('Shop not found');

            return 1;
        }

        $address = [
            'country_id' => $shop->country_id
        ];

        if ($command->option('country')) {
            $address['country_id'] = ParseCountryID::run($command->option('country'));
        }


        $modelData = [
            'contact_name'    => $command->option('contact_name'),
            'company_name'    => $command->option('company_name'),
            'email'           => $command->option('email'),
            'phone'           => $command->option('phone'),
            'contact_website' => $command->option('contact_website'),
            'contact_address' => $address
        ];

        $this->initialisationFromShop($shop, $modelData);

        $customer = $this->handle($shop, $this->validatedData);

        echo "Customer $customer->reference created 🎉"."\n";

        return 0;
    }

    public function htmlResponse(Customer $customer): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.customers.show', [$customer->organisation->slug, $customer->shop->slug, $customer->slug]);
    }
}
