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
use Illuminate\Support\Facades\Http;
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
        data_set($modelData, 'url', Arr::pull($modelData, 'url'));

        $endpoint = '/wc-auth/v1/authorize';
        $params = [
            'app_name' => 'AW Connect',
            'scope' => 'read_write',
            'user_id' => $customer->id,
            'return_url' => route('retina.dropshipping.platform.woo_callback.success'),
            'callback_url' => route('webhooks.woo.callback')
        ];

        StoreTemporaryWooUser::run($customer, $modelData);

        return Arr::get($modelData, 'url').$endpoint.'?'.http_build_query($params);
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

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                'regex:/^https:\/\//',
                function ($attribute, $value, $fail) {
                    $testUrl = rtrim($value, '/') . '/wp-json/wc/v3';
                    try {
                        $response = Http::get($testUrl);
                        if ($response->status() !== 200) {
                            $fail(__('Your WooCommerce API endpoint is not accessible.'));
                        }
                    } catch (\Exception $e) {
                        $fail(__('Unable to connect to the WooCommerce store, please check your store url.'));
                    }
                }
            ]
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
        ];

        $customer = Customer::find($command->argument('customer'))->first();

        $this->handle($customer, $modelData);
    }
}
