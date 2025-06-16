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
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RegisterCustomer extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Customer
    {
        $password = Arr::pull($modelData, 'password');
        data_set($modelData, 'registered_at', now());

        $requireApproval = Arr::get($shop->settings, 'registration.require_approval', false);

        if ($requireApproval) {
            data_set($modelData, 'status', CustomerStatusEnum::PENDING_APPROVAL);
            data_set($modelData, 'state', CustomerStateEnum::REGISTERED);
        } else {
            data_set($modelData, 'status', CustomerStatusEnum::APPROVED->value);
            data_set($modelData, 'state', CustomerStateEnum::ACTIVE->value);
        }

        if (Arr::get($modelData, 'is_opt_in', false)) {
            data_set($modelData, 'email_subscriptions.is_subscribed_to_newsletter', true);
        }

        $customer = DB::transaction(function () use ($shop, $modelData, $password) {
            $customer = StoreCustomer::make()->action($shop, $modelData);
            StoreWebUser::make()->action($customer, [
                'contact_name' => Arr::get($modelData, 'contact_name'),
                'username'     => Arr::get($modelData, 'email'),
                'email'        => Arr::get($modelData, 'email'),
                'password'     => $password,
                'is_root'      => true,
            ]);
            if (Arr::get($modelData, 'poll_replies', []) != []) {
                $storePollyRepliesData = [
                    'customer_id'  => $customer->id,
                    'poll_replies' => $modelData['poll_replies'],
                ];
                StoreMultiPollReply::make()->action($shop, $storePollyRepliesData);
            }

            return $customer;
        });

        SendCustomerWelcomeEmail::run($customer);
        SendNewCustomerNotification::run($customer);
        ShopHydrateCrmStats::run($shop);

        auth('retina')->login($customer->webUser, true);

        return $customer;
    }


}
