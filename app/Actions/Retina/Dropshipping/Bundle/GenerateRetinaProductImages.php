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
use App\Models\Bundle;
use App\Traits\SanitizeInputs;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GenerateRetinaProductImages extends RetinaAction
{
    use WithActionUpdate;
    use SanitizeInputs;

    public function handle(array $modelData): array
    {
        $prompt = Arr::get($modelData, 'prompt');

        return GetGeneratedImages::run($prompt, $modelData);
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string'],
            'images' => ['nullable', 'array']
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->enableSanitize();
        $this->initialisation($request);

        return $this->handle($this->validatedData);
    }
}
