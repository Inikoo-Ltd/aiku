<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle;

use App\Actions\Helpers\AI\GetGeneratedProductDescription;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Bundle;
use App\Models\Catalogue\Product;
use App\Traits\SanitizeInputs;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class GenerateRetinaProductBundleDescription extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    public function handle(array $modelData): string
    {
        $productDescriptions = Product::whereIn('id', Arr::get($modelData, 'products'))
            ->pluck('description');
        $prompt = __("Create an excellent, engaging, and cohesive description for the following products:\n");

        foreach ($productDescriptions as $description) {
            $prompt .= "- " . Str::limit($description) . "\n";
        }

        return GetGeneratedProductDescription::run($prompt, $modelData);
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array'],
            'products.*' => ['required', 'integer', 'exists:products,id'],
            'prompt' => ['nullable', 'string'],
            'images' => ['nullable', 'array']
        ];
    }

    public function asController(ActionRequest $request): string
    {
        $this->enableSanitize();
        $this->initialisation($request);

        return $this->handle($this->validatedData);
    }
}
