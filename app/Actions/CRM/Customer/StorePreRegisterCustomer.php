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
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\WithLogRequest;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
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
    use WithLogRequest;
    public function handle(Shop $shop, array $modelData): Customer
    {

        // $isWithGoogle = Arr::pull($modelData, 'is_with_google', false);
        // if ($isWithGoogle) {
        //     $googleId = Arr::pull($modelData, 'google_id');
        //     if ($googleId) {
        //         data_set($modelData, 'google_id', $googleId);
        //     }
        // }

        data_set($modelData, 'registered_at', now());
        data_set($modelData, 'status', CustomerStatusEnum::PRE_REGISTRATION);
        data_set($modelData, 'state', CustomerStateEnum::REGISTERED);

        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            $modelData['is_dropshipping'] = true;
        } elseif ($shop->type == ShopTypeEnum::FULFILMENT) {
            $modelData['is_fulfilment'] = true;
        }

        data_set($modelData, 'location', $this->getLocation(request()->ip()));


        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set($modelData, 'reference', GetSerialReference::run(container: $shop, modelType: SerialReferenceModelEnum::CUSTOMER));

        $customer = Customer::create($modelData);

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
            'google_id'        => ['sometimes', 'string', 'max:255', 'regex:/^\d{21}$/'],
            'is_with_google'      => ['sometimes', 'boolean'],
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
