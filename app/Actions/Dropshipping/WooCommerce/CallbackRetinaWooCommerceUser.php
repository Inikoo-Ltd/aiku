<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CallbackRetinaWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:callback-woo {customer} {store_url} {consumer_key} {consumer_secret}';

    public function handle(Customer $customer, array $modelData): Customer
    {
        StoreTemporaryWooUser::run($customer, $modelData);

        return $customer;
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
        $customer = Customer::findOrFail($request->get('user_id'));
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function asCommand(Command $command): void
    {
        $modelData = [
            'store_url' => $command->argument('store_url'),
            'consumer_key' => $command->argument('consumer_key'),
            'consumer_secret' => $command->argument('consumer_secret')
        ];

        $customer = Customer::find($command->argument('customer'));

        data_set($modelData, 'name', $customer->name);

        $this->handle($customer, $modelData);
    }
}
