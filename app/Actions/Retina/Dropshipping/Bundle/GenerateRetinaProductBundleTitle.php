<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle;

use App\Actions\Helpers\AI\GetGeneratedProductTitle;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Bundle;
use App\Traits\SanitizeInputs;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GenerateRetinaProductBundleTitle extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    public function handle(array $modelData): string
    {
        $prompt = Arr::get($modelData, 'prompt');

        return GetGeneratedProductTitle::run($prompt, $modelData);
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string'],
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
