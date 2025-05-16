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
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AuthorizeRetinaWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:authorize-woo {customer} {name} {url}';

    public function handle(Customer $customer, $modelData): string
    {
        $endpoint = '/wc-auth/v1/authorize';
        $params = [
            'app_name' => config('app.name'),
            'scope' => 'read_write',
            'user_id' => $modelData['name'],
            'return_url' => route('retina.dropshipping.platform.dashboard'),
            'callback_url' => route('retina.dropshipping.platform.wc.callback', [
                'customer' => $customer->id
            ])
        ];

        return $modelData['url'].$endpoint.'?'.http_build_query($params);
    }

    public function jsonResponse(string $url): string
    {
        return $url;
    }

    public function handleCallback(Customer $customer, ActionRequest|array $request)
    {
        $consumerKey    = $request->input('consumer_key');
        $consumerSecret = $request->input('consumer_secret');
        $storeUrl       = $request->input('store_url');

        if (!$consumerKey || !$consumerSecret || !$storeUrl) {
            return response('Invalid callback data', 400);
        }

        StoreRetinaWooCommerceUser::run($customer, [
            'name' => $this->get('name'),
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
            'name' => ['required', 'string', 'max:255', Rule::unique('woo_commerce_users', 'name')],
            'url' => ['required', 'string']
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('name', $request->input('name'));
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
            'name' => $command->argument('name'),
            'url' => $command->argument('url'),
            'url' => $command->argument('url'),
        ];

        $customer = Customer::find($command->argument('customer'))->first();

        $this->handleCallback($modelData);
    }
}
