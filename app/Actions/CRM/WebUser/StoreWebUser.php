<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateWebUsers;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateWebUsers;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebUsers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebUsers;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreWebUser extends OrgAction
{
    use WithNoStrictRules;

    private Customer $customer;
    private Customer|FulfilmentCustomer $parent;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): Webuser
    {
        data_set($modelData, 'language_id', $customer->shop->language_id, overwrite: false);
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'shop_id', $customer->shop_id);


        if (Arr::exists($modelData, 'password')) {
            $modelData['password'] = Hash::make($modelData['password']);
        }
        $webUser = DB::transaction(function () use ($customer, $modelData) {
            /** @var WebUser $webUser */
            $webUser = $customer->webUsers()->create(
                array_merge(
                    $modelData,
                    [
                        'website_id' => $customer->shop->website->id
                    ]
                )
            );
            $webUser->stats()->create();
            $webUser->refresh();

            return $webUser;
        });

        GroupHydrateWebUsers::dispatch($webUser->group)->delay($this->hydratorsDelay);
        OrganisationHydrateWebUsers::dispatch($webUser->organisation)->delay($this->hydratorsDelay);
        ShopHydrateWebUsers::dispatch($webUser->shop)->delay($this->hydratorsDelay);
        CustomerHydrateWebUsers::dispatch($webUser->customer)->delay($this->hydratorsDelay);

        return $webUser;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        if ($this->parent instanceof FulfilmentCustomer) {
            return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'contact_name' => ['sometimes', 'nullable', 'max:255'],
            'type'         => ['sometimes', Rule::enum(WebUserTypeEnum::class)],
            'auth_type'    => ['sometimes', Rule::enum(WebUserAuthTypeEnum::class)],
            'username'     => [
                'required',
                'string',
                'max:255',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->shop->website->id]
                    ]
                ),
            ],
            'is_root'      => ['required', 'boolean'],
            'data'         => ['sometimes', 'array'],
            'password'     =>
                [
                    'sometimes',
                    'required',
                    app()->isLocal() || app()->environment('testing') || !$this->strict ? Password::min(3) : Password::min(8)
                ],
            'google_id'    => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

        ];

        $emailRule = [
            $this->strict ? 'email' : 'string:500',
            'max:255',
            new IUnique(
                table: 'web_users',
                extraConditions: [
                    ['column' => 'website_id', 'value' => $this->shop->website->id]
                ]
            ),
        ];

        if ($this->customer->hasUsers()) {
            $rules['email'] = array_merge(['sometimes', 'nullable'], $emailRule);
        } else {
            $rules['email'] = $emailRule;
        }

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->shop->website) {
            abort(422, 'Website not set up');
        }

        if ($this->get('type') === null) {
            $this->fill(['type' => WebUserTypeEnum::WEB]);
        }

        if (!$this->customer->hasUsers()) {
            $this->fill(['is_root' => true]);
        } elseif ($this->get('is_root') == null) {
            $this->fill(['is_root' => false]);
        }
    }

    /**
     * @throws \Throwable
     */
    public function inFulfilmentCustomer(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Webuser
    {
        $this->parent   = $fulfilmentCustomer;
        $this->customer = $fulfilmentCustomer->customer;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer->customer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inCustomer(Customer $customer, ActionRequest $request): Webuser
    {
        $this->parent   = $customer;
        $this->customer = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function action(Customer $customer, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Webuser
    {
        if (!$audit) {
            WebUser::disableAuditing();
        }
        $this->asAction       = true;
        $this->customer       = $customer;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }


    public function htmlResponse(WebUser $webUser): Response
    {
        if ($this->parent instanceof FulfilmentCustomer) {
            return Inertia::location(route('grp.org.fulfilments.show.crm.customers.show.web_users.show', [
                'organisation'       => $webUser->organisation->slug,
                'fulfilment'         => $this->parent->fulfilment->slug,
                'fulfilmentCustomer' => $this->parent->slug,
                'webUser'            => $webUser->slug
            ]));
        } elseif (request()->user() instanceof WebUser) {
            return Inertia::location(route('retina.sysadmin.web-users.show', [
                'webUser' => $webUser->slug
            ]));
        }


        return Inertia::location(route('grp.org.shops.show.crm.customers.show.web_users.show', [
            'organisation' => $webUser->organisation->slug,
            'shop'         => $this->parent->shop->slug,
            'customer'     => $this->parent->slug,
            'webUser'      => $webUser->slug
        ]));
    }

}
