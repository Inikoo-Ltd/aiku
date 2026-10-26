<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-13h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\Goods\ProductDevelopmentSample;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Changes the batch size or notes of a sample still being made.
 */
class UpdateProductDevelopmentSample extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentSample $sample;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->sample->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canManageSamples($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentSample $sample, array $modelData): ProductDevelopmentSample
    {
        if (array_key_exists('notes', $modelData)) {
            $modelData['notes'] = trim((string) $modelData['notes']) ?: null;
        }
        $sample->update($modelData);

        return $sample;
    }

    public function rules(): array
    {
        return [
            'batch_size' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999'],
            'batch_unit' => ['sometimes', 'nullable', Rule::in(StoreProductDevelopmentSample::BATCH_UNITS)],
            'notes'      => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->sample->status !== ProductDevelopmentSampleStatusEnum::IN_PROGRESS) {
            $validator->errors()->add('sample', __('Only a sample still being made can be changed'));
        }
    }

    public function asController(ProductDevelopmentSample $productDevelopmentSample, ActionRequest $request): ProductDevelopmentSample
    {
        $this->sample = $productDevelopmentSample;
        $this->initialisationFromGroup($productDevelopmentSample->productDevelopment->group, $request);

        return $this->handle($productDevelopmentSample, $this->validatedData);
    }

    public function action(ProductDevelopmentSample $sample, array $modelData): ProductDevelopmentSample
    {
        $this->asAction = true;
        $this->sample   = $sample;
        $this->initialisationFromGroup($sample->productDevelopment->group, $modelData);

        return $this->handle($sample, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
