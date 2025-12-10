<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreTemporaryWooUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): string
    {
        $uniqueIdCache = md5($customer->slug . '-woo-user-creation');
        $currentWooUser = Cache::get($uniqueIdCache);
        $name = Arr::get($currentWooUser, 'name');
        $storeUrl = Arr::get($currentWooUser, 'url');

        if ($name && ! Arr::has($modelData, 'name')) {
            data_set($modelData, 'name', $name);
        }
        if ($storeUrl && ! Arr::has($modelData, 'url')) {
            data_set($modelData, 'url', $storeUrl);
        }

        Cache::put($uniqueIdCache, $modelData, 3600);

        return $uniqueIdCache;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
            'url' => ['sometimes', 'string'],
            'consumer_key' => ['sometimes', 'string'],
            'consumer_secret' => ['sometimes', 'string']
        ];
    }

    public function asController(ActionRequest $request): string
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }
}
