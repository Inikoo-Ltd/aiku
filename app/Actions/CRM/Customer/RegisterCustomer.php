<?php

/*
 * author Arya Permana - Kirin
 * created on 07-03-2025-08h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCrmStats;
use App\Actions\Comms\Email\SendCustomerWelcomeEmail;
use App\Actions\Comms\Email\SendNewCustomerNotification;
use App\Actions\CRM\PollReply\StoreMultiPollReply;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\OrgAction;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;

class RegisterCustomer extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Customer
    {
        $password      = Arr::pull($modelData, 'password');

        data_set($modelData, 'registered_at', now());
        data_set($modelData, 'status', CustomerStatusEnum::PENDING_APPROVAL);


        $customer = StoreCustomer::make()->action($shop, $modelData);


        $webUser = StoreWebUser::make()->action($customer, [
            'contact_name' => Arr::get($modelData, 'contact_name'),
            'username'     => Arr::get($modelData, 'email'),
            'email'        => Arr::get($modelData, 'email'),
            'password'     => $password,
            'is_root'      => true,
        ]);

        if (Arr::get($modelData, 'poll_replies', []) != []) {
            $storePollyRepliesData = [
                'customer_id' => $customer->id,
                'poll_replies' => $modelData['poll_replies'],
            ];
            StoreMultiPollReply::make()->action($shop, $storePollyRepliesData);
        }


        SendCustomerWelcomeEmail::run($customer);

        SendNewCustomerNotification::run($customer);

        ShopHydrateCrmStats::run($shop);

        auth('retina')->login($webUser);

        return $customer;
    }



    public function rules(): array
    {
        return [
            'contact_name'       => ['required', 'string', 'max:255'],
            'company_name'       => ['required', 'string', 'max:255'],
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
            'phone'              => ['required', 'max:255'],
            'contact_address'    => ['required', new ValidAddress()],
            'password'           =>
                [
                    'sometimes',
                    'required',
                    app()->isLocal() || app()->environment('testing') ? null : Password::min(8)
                ],
            'poll_replies'            => ['sometimes', 'required', 'array'],

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

}
