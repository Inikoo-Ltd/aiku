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
use App\Traits\SanitizeInputs;
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
    use SanitizeInputs;

    /**
     * Each step adds to what earlier steps stored: re-entering the account name after the store
     * has posted its keys must not throw those keys away. Keys only belong to the url they came
     * from, so they are dropped when the url changes.
     */
    public function handle(Customer $customer, array $modelData): string
    {
        $uniqueIdCache = md5($customer->slug . '-woo-user-creation');
        $currentWooUser = Cache::get($uniqueIdCache) ?? [];

        if (Arr::has($modelData, 'url') && Arr::get($modelData, 'url') !== Arr::get($currentWooUser, 'url')) {
            $currentWooUser = Arr::except($currentWooUser, ['consumer_key', 'consumer_secret']);
        }

        Cache::put($uniqueIdCache, array_merge($currentWooUser, $modelData), 3600);

        return $uniqueIdCache;
    }

    public function prepareForValidation(ActionRequest $request)
    {
        $this->setSanitizeFields([
            'name',
            'url'
        ]);
        $this->sanitizeInputs();
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
        $this->enableSanitize();
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }
}
