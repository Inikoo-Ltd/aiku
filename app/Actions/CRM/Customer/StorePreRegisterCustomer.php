<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\OrgAction;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StorePreRegisterCustomer extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Customer
    {

        data_set($modelData, 'registered_at', now());
        data_set($modelData, 'status', CustomerStatusEnum::PRE_REGISTRATION);
        data_set($modelData, 'state', CustomerStateEnum::IN_PROCESS);


        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        $googleUser = Arr::pull($modelData, 'google_user', []);
        if ($googleUser) {
            data_set($modelData, 'google_id', Arr::get($googleUser, 'id'));
            data_set($modelData, 'email', Arr::get($googleUser, 'email'));
            data_set($modelData, 'google_avatar', Arr::get($googleUser, 'avatar'));
            data_set($modelData, 'contact_name', Arr::get($googleUser, 'name'));
            data_set($modelData, 'name', Arr::get($googleUser, 'name'));
        }

        $customer = StoreCustomer::make()->action($shop, $modelData);

        $password = Str::random(15);
        $webUser = StoreWebUser::make()->action($customer, [
            'username'     => Arr::get($modelData, 'email'),
            'email'        => Arr::get($modelData, 'email'),
            'password'     => $password,
            'is_root'      => true,
        ]);

        // SendCustomerWelcomeEmail::run($customer);

        // SendNewCustomerNotification::run($customer);

        // ShopHydrateCrmStats::run($shop);


        auth('retina')->login($webUser);

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
            'google_user'        => ['sometimes', 'array'],
            'google_user.id'     => ['sometimes', 'string', 'max:255'],
            'google_user.email'  => ['sometimes', 'email', 'max:255'],
            'google_user.name'   => ['sometimes', 'string', 'max:255'],
            'google_user.avatar' => ['sometimes', 'string', 'max:255'],
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
        $f = Shop::find(13);

        $this->handle($f, [
            'email'     => 'preTest@gmail.com',
        ]);
    }

}
