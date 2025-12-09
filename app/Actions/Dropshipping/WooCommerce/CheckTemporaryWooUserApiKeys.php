<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\WooCommerce\Traits\WithWooCommerceApiRequest;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CheckTemporaryWooUserApiKeys extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithWooCommerceApiRequest;

    public function handle(Customer $customer, $modelData = []): string
    {
        $uniqueIdCache = md5($customer->slug . '-woo-user-creation');
        $currentWooUser = Cache::get($uniqueIdCache);
        $name = Arr::get($currentWooUser, 'name');
        $key = Arr::get($currentWooUser, 'consumer_key');
        $secret = Arr::get($currentWooUser, 'consumer_secret');
        $storeUrl = Arr::get($currentWooUser, 'url');

        if ($key && $secret && $storeUrl && $name) {
            $this->woocommerceApiUrl = $storeUrl;
            $this->woocommerceConsumerKey = $key;
            $this->woocommerceConsumerSecret = $secret;

            $response = $this->checkConnection();

            if (! Arr::get($response, 'environment')) {
                throw ValidationException::withMessages(['url' => __('We can\'t access your store, make sure you already put correct store url.')]);
            }

            data_set($modelData, 'name', $name);
            data_set($modelData, 'consumer_key', $key);
            data_set($modelData, 'consumer_secret', $secret);
            data_set($modelData, 'store_url', $storeUrl);

            $wooCommerceUser = StoreWooCommerceUser::run($customer, $modelData);

            CheckWooChannel::run($wooCommerceUser);

            return $uniqueIdCache;
        }

        throw ValidationException::withMessages(['url' => __('You are not connected yet, click auth store to connect and follow the instructions.')]);
    }

    public function asController(ActionRequest $request): string
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }
}
