<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\WooCommerce\Traits\WithWooCommerceAuthorizationToken;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallbackRetinaWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithWooCommerceAuthorizationToken;

    public $commandSignature = 'retina:ds:callback-woo {customer} {store_url} {consumer_key} {consumer_secret}';

    public function handle(Customer $customer, array $modelData): void
    {
        StoreTemporaryWooUser::run($customer, $modelData);
    }

    public function handleReAuthorization(WooCommerceUser $wooCommerceUser, array $modelData): void
    {
        $this->update($wooCommerceUser, [
            'consumer_key' => Arr::get($modelData, 'consumer_key'),
            'consumer_secret' => Arr::get($modelData, 'consumer_secret'),
        ]);

        CheckWooChannel::run($wooCommerceUser);
    }

    public function rules(): array
    {
        return [
            'consumer_key' => ['required', 'string'],
            'consumer_secret' => ['required', 'string']
        ];
    }

    public function asController(ActionRequest $request): string
    {
        $tokenPayload = $this->getWooAuthorizationTokenPayload($request->input('user_id'));

        if (blank($tokenPayload)) {
            abort(404);
        }

        if ($wooCommerceUserId = Arr::get($tokenPayload, 'woo_commerce_user_id')) {
            $wooCommerceUser = WooCommerceUser::findOrFail($wooCommerceUserId);
            $this->initialisationFromShop($wooCommerceUser->customer->shop, $request);

            $this->handleReAuthorization($wooCommerceUser, $this->validatedData);

            return 'success';
        }

        $customer = Customer::findOrFail(Arr::get($tokenPayload, 'customer_id'));
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer, $this->validatedData);

        return 'success';
    }

    public function asCommand(Command $command): void
    {
        $modelData = [
            'url' => rtrim(trim($command->argument('store_url')), '/'),
            'consumer_key' => $command->argument('consumer_key'),
            'consumer_secret' => $command->argument('consumer_secret')
        ];

        $customer = Customer::findOrFail($command->argument('customer'));

        data_set($modelData, 'name', $customer->name);

        $this->handle($customer, $modelData);
    }
}
