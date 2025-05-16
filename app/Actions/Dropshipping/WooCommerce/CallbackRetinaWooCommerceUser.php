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
use App\Models\CRM\WebUser;
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

    public $commandSignature = 'retina:ds:callback-woo {customer} {store_url} {consumer_key} {consumer_secret}';

    public function handle(Customer $customer, $modelData): string
    {
        $name = Arr::get($modelData, 'name');
        $consumerKey = Arr::get($modelData, 'consumer_key');
        $consumerSecret = Arr::get($modelData, 'consumer_secret');
        $storeUrl = Arr::get($modelData, 'store_url');

        StoreRetinaWooCommerceUser::run($customer, [
            'name' => $name,
            'store_url' => $storeUrl,
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret
        ]);

        return redirect()->route('retina.dropshipping.platform.dashboard');
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'store_url' => ['required', 'string'],
            'consumer_key' => ['required', 'string'],
            'consumer_secret' => ['required', 'string']
        ];
    }

    public function asController(ActionRequest $request): string
    {
        $customer = $request->user()->customer;
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

        $customer = Customer::find($command->argument('customer'))->first();

        data_set($modelData, 'name', $customer->name);

        $this->handle($customer, $modelData);
    }
}
