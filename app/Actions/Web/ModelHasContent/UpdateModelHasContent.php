<?php

/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-13h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\ModelHasContent;

use App\Actions\OrgAction;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Web\ModelHasContent\ModelHasContentTypeEnum;
use App\Models\Web\ModelHasContent;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateModelHasContent extends OrgAction
{
    use WithActionUpdate;
    use WithImageCatalogue;

    public function handle(ModelHasContent $modelHasContent, array $modelData): ModelHasContent
    {
        $imageData = [];
        if (Arr::exists($modelData, 'image')) {
            $imageData = ['image' => Arr::pull($modelData, 'image')]; //TODO: image handling
        }

        $modelHasContent = $this->update($modelHasContent, $modelData);

        if (Arr::exists($imageData, 'image')) {
            $this->processCatalogueImage($imageData, $modelHasContent);
        }
        return $modelHasContent;
    }

    public function rules(): array
    {
        return [
            'type'         => ['sometimes', Rule::enum(ModelHasContentTypeEnum::class)],
            'title'        => ['sometimes', 'string'],
            'text'         => ['sometimes', 'string'],
            'image'        => ['sometimes', 'nullable', 'image', 'mimes:jpg,png,jpeg', 'max:10240'],
            'position'     => ['sometimes']
        ];
    }

    public function asController(ModelHasContent $modelHasContent, ActionRequest $request)
    {
        // dd($request->all());
        $this->initialisationFromShop($modelHasContent->model->shop, $request);

        return $this->handle($modelHasContent, $this->validatedData);
    }
}
