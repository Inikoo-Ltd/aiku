<?php

/*
 * author Arya Permana - Kirin
 * created on 07-03-2025-08h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\CRM\Customer\RegisterCustomer;
use App\Actions\RetinaAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class RegisterRetinaDropshippingCustomer extends RetinaAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Customer
    {
        return RegisterCustomer::make()->action($shop, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function htmlResponse(): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return Redirect::route('retina.login.show');
    }

    public function rules(): array
    {
        return [
            'contact_name'             => ['required', 'string', 'max:255'],
            'company_name'             => ['required', 'string', 'max:255'],
            'email'                    => [
                'required',
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
            ],
            'phone'                    => ['required', 'max:255'],
            'contact_address'          => ['required', new ValidAddress()],
            'password'                 =>
                [
                    'sometimes',
                    'required',
                    app()->isLocal() || app()->environment('testing') ? null : Password::min(8)
                ],

        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): Customer
    {
        $this->registerDropshippingInitialisation($shop, $request);
        return $this->handle($shop, $this->validatedData);
    }
}
