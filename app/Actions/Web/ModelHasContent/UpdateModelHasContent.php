<?php

/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-13h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\ModelHasContent;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Web\ModelHasContent\ModelHasContentTypeEnum;
use App\Models\Web\ModelHasContent;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateModelHasContent extends OrgAction
{
    use WithActionUpdate;

    public function handle(ModelHasContent $modelHasContent, array $modelData): ModelHasContent
    {
        $modelHasContent = $this->update($modelHasContent, $modelData);

        return $modelHasContent;
    }

    public function rules(): array
    {
        return [
            'type'         => ['sometmes', Rule::enum(ModelHasContentTypeEnum::class)],
            'title'        => ['sometmes', 'string'],
            'text'         => ['sometmes', 'string'],
            'image'        => ['nullable', 'image', 'mimes:jpg,png,jpeg', 'max:10240'],
            'position'     => ['sometmes']
        ];
    }

    public function asController(ModelHasContent $modelHasContent, ActionRequest $request)
    {
        $this->initialisationFromShop($modelHasContent->model->shop, $request);

        return $this->handle($modelHasContent, $this->validatedData);
    }
}
