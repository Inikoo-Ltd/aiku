<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCrmStats;
use App\Actions\Comms\Email\SendCustomerWelcomeEmail;
use App\Actions\Comms\Email\SendNewCustomerNotification;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\OrgAction;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;

class RegisterPreCustomer extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Customer
    {
        $password      = Arr::pull($modelData, 'password');

        data_set($modelData, 'registered_at', now());
        data_set($modelData, 'status', CustomerStatusEnum::PRE_REGISTRATION);

        $customer = StoreCustomer::make()->action($shop, $modelData);

        $webUser = StoreWebUser::make()->action($customer, [
            'username'     => Arr::get($modelData, 'email'),
            'email'        => Arr::get($modelData, 'email'),
            'password'     => $password,
            'is_root'      => false,
        ]);

        dd($webUser);


        // SendCustomerWelcomeEmail::run($customer);

        // SendNewCustomerNotification::run($customer);

        // ShopHydrateCrmStats::run($shop);

        // auth('retina')->login($webUser);

        return $customer;
    }



    public function rules(): array
    {
        return [
            'email'              => [
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
            'contact_address'    => ['required', new ValidAddress()],
            'password'           =>
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
    public function action(Shop $shop, array $modelData): Customer
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }


    public string $commandSignature = 'pre_register_test';

    public function asCommand($command)
    {
        $f = Shop::find(6);

        $country = Country::where('code', 'ID')->first();
        $addressData = [
            'address_line_1'        => 'Jl. Raya Kuta No. 123',
            'address_line_2'        => null,
            'postal_code'           => '80361',
            'locality'              => 'Kuta',
            'country_code'          => $country->code,
            'country_id'            => $country->id
        ];

        $this->handle($f, [
            'email'     => 'preTest@gmail.com',
            'password'  => 'test',
            'contact_address' => $addressData,
        ]);
    }

}
