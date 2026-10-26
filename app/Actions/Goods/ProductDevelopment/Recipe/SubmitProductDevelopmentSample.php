<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-13h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Marks a sample ready for review. Its recipe is copied from Production now, so it keeps the formulation it
 * was made from whatever happens to the recipe later, and the reviewers are told.
 */
class SubmitProductDevelopmentSample extends OrgAction
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

    public function handle(ProductDevelopmentSample $sample, User $user): ProductDevelopmentSample
    {
        $sample->update([
            'status'   => ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW,
            'recipe'   => ProductDevelopmentRecipes::recipe($sample->item->artefact),
            'ready_at' => now(),
        ]);

        RecordProductDevelopmentActivity::record($sample->productDevelopment, RecordProductDevelopmentActivity::SAMPLE_READY, [], [
            'sample' => ProductDevelopmentRecipes::label($sample),
            'notes'  => $sample->notes,
        ]);
        ProductDevelopmentRecipes::notifyReviewers($sample, $user);

        return $sample;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->sample->status !== ProductDevelopmentSampleStatusEnum::IN_PROGRESS) {
            $validator->errors()->add('sample', __('This sample is not being made'));

            return;
        }

        if (!$this->sample->item->artefact) {
            $validator->errors()->add('sample', __('This SKU has no recipe in Production'));
        }
    }

    public function asController(ProductDevelopmentSample $productDevelopmentSample, ActionRequest $request): ProductDevelopmentSample
    {
        $this->sample = $productDevelopmentSample;
        $this->initialisationFromGroup($productDevelopmentSample->productDevelopment->group, $request);

        return $this->handle($productDevelopmentSample, $request->user());
    }

    public function action(ProductDevelopmentSample $sample, User $user): ProductDevelopmentSample
    {
        $this->asAction = true;
        $this->sample   = $sample;
        $this->initialisationFromGroup($sample->productDevelopment->group, []);

        return $this->handle($sample, $user);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
