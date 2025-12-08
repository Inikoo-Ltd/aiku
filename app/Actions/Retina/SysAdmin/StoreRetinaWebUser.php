<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateWebUsers;
use App\Actions\RetinaAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreRetinaWebUser extends RetinaAction
{
    use WithNoStrictRules;

    protected Customer|FulfilmentCustomer $parent;

    private bool $action = false;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): Webuser
    {
        data_set($modelData, 'type', WebUserTypeEnum::WEB);
        data_set($modelData, 'language_id', $customer->shop->language_id, overwrite: false);
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'shop_id', $customer->shop_id);
        data_set($modelData, 'is_root', false);
        data_set($modelData, 'website_id', $customer->shop->website->id);

        $modelData['password'] = Hash::make($modelData['password']);

        $webUser = DB::transaction(function () use ($customer, $modelData) {
            /** @var WebUser $webUser */
            $webUser = $customer->webUsers()->create($modelData);
            $webUser->stats()->create();
            $webUser->refresh();

            return $webUser;
        });

        CustomerHydrateWebUsers::dispatch($webUser->customer);

        return $webUser;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        return $request->user()->is_root;
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($this->customer->webUsers->count() > 20) {
            $validator->errors()->add('username', 'Maximum number of web users reached');
        }

    }

    public function rules(): array
    {
        return [
            'contact_name' => ['sometimes', 'nullable', 'max:255'],
            'username' => [
                'required',
                new AlphaDashDot,
                'min:4',
                'max:255',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->webUser->website->id],
                    ]
                ),
            ],
            'password' => [
                'required',
                app()->isLocal() || app()->environment('testing') ? Password::min(3) : Password::min(8),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                new IUnique(
                    table: 'web_users',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->webUser->website->id],
                    ]
                ),
            ],

        ];

    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Webuser
    {
        $customer = $request->user()->customer;
        $this->customer = $customer;
        $this->initialisation($request);

        return $this->handle($customer, $this->validatedData);
    }

    public function action(Customer $customer, array $modelData): Webuser
    {
        $this->action = true;
        $this->initialisationFulfilmentActions($customer->fulfilmentCustomer, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    public function htmlResponse(WebUser $webUser): Response
    {
        return Inertia::location(route('retina.sysadmin.web-users.show', [
            'webUser' => $webUser->slug,
        ]));
    }
}
