<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-14h-00m
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Approves a sample, which makes its formulation the final recipe of the SKU, or sends it back with a comment
 * saying what to change. The comment also goes into the sample's discussion.
 */
class ReviewProductDevelopmentSample extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string APPROVE = 'approve';

    public const string REQUEST_CHANGES = 'request_changes';

    private ProductDevelopmentSample $sample;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->sample->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canReviewSamples($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentSample $sample, User $user, array $modelData): ProductDevelopmentSample
    {
        $approved = $modelData['operation'] === self::APPROVE;
        $comment  = trim((string) ($modelData['comment'] ?? '')) ?: null;

        $sample->update([
            'status'         => $approved ? ProductDevelopmentSampleStatusEnum::APPROVED : ProductDevelopmentSampleStatusEnum::CHANGES_REQUIRED,
            'review_comment' => $comment,
            'reviewed_by_id' => $user->id,
            'reviewed_at'    => now(),
        ]);

        if ($comment) {
            $sample->productDevelopment->comments()->create([
                'user_id'    => $user->id,
                'body'       => $comment,
                'mentions'   => [],
                'model_type' => $sample->getMorphClass(),
                'model_id'   => $sample->id,
                'data'       => ['review' => $modelData['operation']],
            ]);
        }

        RecordProductDevelopmentActivity::record(
            $sample->productDevelopment,
            $approved ? RecordProductDevelopmentActivity::SAMPLE_APPROVED : RecordProductDevelopmentActivity::SAMPLE_CHANGES_REQUIRED,
            [],
            ['sample' => ProductDevelopmentRecipes::label($sample), 'comment' => $comment]
        );
        ProductDevelopmentRecipes::notifyProduction($sample, $user);

        return $sample;
    }

    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in([self::APPROVE, self::REQUEST_CHANGES])],
            'comment'   => ['required_if:operation,'.self::REQUEST_CHANGES, 'nullable', 'string', 'max:2000'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'comment.required_if' => __('Say what needs changing'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->sample->status !== ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW) {
            $validator->errors()->add('operation', __('This sample is not ready for review'));
        }
    }

    public function asController(ProductDevelopmentSample $productDevelopmentSample, ActionRequest $request): ProductDevelopmentSample
    {
        $this->sample = $productDevelopmentSample;
        $this->initialisationFromGroup($productDevelopmentSample->productDevelopment->group, $request);

        return $this->handle($productDevelopmentSample, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopmentSample $sample, User $user, array $modelData): ProductDevelopmentSample
    {
        $this->asAction = true;
        $this->sample   = $sample;
        $this->initialisationFromGroup($sample->productDevelopment->group, $modelData);

        return $this->handle($sample, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
