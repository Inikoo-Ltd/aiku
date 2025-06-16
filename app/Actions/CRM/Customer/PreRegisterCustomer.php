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
use Google_Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class PreRegisterCustomer extends OrgAction
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
            data_set($modelData, 'contact_name', Arr::get($googleUser, 'name'));
            data_set($modelData, 'name', Arr::get($googleUser, 'name'));
        }

        $customer = StoreCustomer::make()->action($shop, $modelData);

        $password = Str::random(15);
        $webUser = StoreWebUser::make()->action($customer, array_filter([
            'username'     => Arr::get($modelData, 'email'),
            'email'        => Arr::get($modelData, 'email'),
            'password'     => $password,
            'contact_name' => Arr::get($modelData, 'contact_name'),
            'google_id' => Arr::get($modelData, 'google_id'),
            'is_root'      => true,
        ]));



        auth('retina')->login($webUser);

        return $customer;
    }

    private function verifyGoogleCredential(string $credential): array
    {
        $client = new Google_Client(['client_id' => config('services.google.client_id')]);
        $payload = $client->verifyIdToken($credential);
        if ($payload) {
            return [
                'id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'],
            ];
        }
        throw new \Exception('Invalid Google credential provided!');
    }

    public function rules(): array
    {
        return [
            'email'                    => [
                'required_without:google_credential',
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
            'google_credential'     => ['required_without:email', 'string', 'max:2048'],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $googleCredential = $request->input('google_credential', null);
        if ($googleCredential) {
            try {
                $googleUser = $this->verifyGoogleCredential($googleCredential);
                $request->merge(['google_user' => $googleUser]);
            } catch (\Exception $e) {
                $validator->errors()->add('google_credential', $e->getMessage());
            }
        }
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
