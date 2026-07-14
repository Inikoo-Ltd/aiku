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
use App\Models\CRM\WebUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AuthorizeRetinaWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithWooCommerceAuthorizationToken;

    public $commandSignature = 'retina:ds:authorize-woo {customer} {name} {url}';

    public function handle(Customer $customer, $modelData): string
    {
        StoreTemporaryWooUser::run($customer, $modelData);

        $token = $this->storeWooAuthorizationToken([
            'customer_id' => $customer->id
        ]);

        $params = [
            'app_name' => 'AW Connect',
            'scope' => 'read_write',
            'user_id' => $token,
            'return_url' => route('retina.dropshipping.platform.woo_callback.success'),
            'callback_url' => route('webhooks.woo.callback')
        ];

        return Arr::get($modelData, 'url') . '/wc-auth/v1/authorize?' . http_build_query($params);
    }

    public function jsonResponse(string $url): string
    {
        return $url;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($request->filled('url')) {
            $this->set('url', rtrim(trim($request->input('url')), '/'));
        }
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                'regex:/^https:\/\//',
                function ($attribute, $value, $fail) {
                    try {
                        $response = Http::timeout(10)->connectTimeout(10)->get(rtrim($value, '/') . '/wp-json/wc/v3');

                        if ($response->status() === 404) {
                            $fail(__('We could not find the WooCommerce API on this store, make sure WooCommerce is installed and its REST API is enabled.'));
                        } elseif ($response->serverError()) {
                            $fail(__('Your WooCommerce store returned an error, please try again later.'));
                        }
                    } catch (\Exception $e) {
                        $fail(__('Unable to connect to the WooCommerce store, please check your store url.'));
                    }
                }
            ]
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
            'name' => $command->argument('name'),
            'url' => rtrim(trim($command->argument('url')), '/'),
        ];

        $customer = Customer::findOrFail($command->argument('customer'));

        $command->info($this->handle($customer, $modelData));
    }
}
