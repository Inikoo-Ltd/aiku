<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\PollReply\StoreMultiPollReply;
use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\CRM\WebUser\UpdateWebUser;
use App\Actions\OrgAction;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class FinishPreRegisterCustomer extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(WebUser $webUser, array $modelData): Customer
    {

        $shop = $webUser->shop;
        $customer = $webUser->customer;

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

        $customer = UpdateCustomer::make()->action($customer, $modelData);

        if (Arr::get($modelData, 'is_opt_in', false)) {
            StoreProspect::make()->action($shop, array_filter([
                'email'       => $customer->email,
                'contact_name' => $customer->contact_name,
                'phone'       => $customer->phone,
                'company_name' => $customer->company_name,
                'address'    => Arr::get($modelData, 'contact_address'),
                'is_opt_in'   => true,
                'customer_id' => $customer->id,
            ]));
        }

        if (Arr::get($modelData, 'poll_replies', []) != []) {
            $storePollyRepliesData = [
                'customer_id' => $customer->id,
                'poll_replies' => $modelData['poll_replies'],
            ];
            StoreMultiPollReply::make()->action($shop, $storePollyRepliesData);
        }

        UpdateWebUser::make()->action($webUser, [
            'contact_name' => Arr::get($modelData, 'contact_name'),
            'password'     => Arr::get($modelData, 'password', Str::random(15)),
        ]);


        return $customer;
    }


    public function rules(): array
    {
        return [
            'contact_name'       => ['sometimes', 'string', 'max:255'],
            'company_name'       => ['required', 'string', 'max:255'],
            'phone'              => ['required', 'max:255'],
            'contact_address'    => ['required', new ValidAddress()],
            'password'           =>
                [
                    'sometimes',
                    'required',
                    app()->isLocal() || app()->environment('testing') ? null : Password::min(8)
                ],
            'poll_replies'            => ['sometimes', 'required', 'array'],
            'is_opt_in'       => ['sometimes', 'boolean'],
        ];
    }
    /**
     * @throws \Throwable
     */
    public function action(WebUser $webUser, array $modelData): Customer
    {
        $this->asAction = true;
        $this->initialisationFromShop($webUser->shop, $modelData);

        return $this->handle($webUser, $this->validatedData);
    }

}
