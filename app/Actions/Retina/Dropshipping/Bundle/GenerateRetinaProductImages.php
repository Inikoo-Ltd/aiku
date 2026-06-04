<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle;

use App\Actions\Helpers\AI\GetGeneratedImages;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Traits\SanitizeInputs;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class GenerateRetinaProductImages extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    public function handle(Product $product, array $modelData): void
    {
        $customer = $this->customer;

        $attempt = Cache::get('ai:image:limit:' . $customer->id,  0);

        if($attempt >= 3) {
            throw ValidationException::withMessages(['message' => __('You have reached the limit of 3 attempts.')]);
        }

        $prompt = Arr::get($modelData, 'prompt');

        GetGeneratedImages::dispatch($product, $prompt, $modelData);
        Cache::put('ai:image:limit:' . $customer->id, $attempt + 1, now()->addMinutes(5));
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string'],
            'images' => ['required', 'array']
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Product $product, ActionRequest $request): void
    {
        $this->enableSanitize();
        $this->initialisation($request);

        $this->handle($product, $this->validatedData);
    }
}
