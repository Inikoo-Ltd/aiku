<?php

/*
 * Author Louis Perez
 * Created on 22-10-2026-15h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\CreateProductDevelopmentTask;
use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Approves an artwork in review, or sends it back with a comment saying exactly what to fix. The comment
 * also goes into the artwork's discussion, so earlier reviews stay readable after the next one.
 */
class ReviewProductDevelopmentArtwork extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string APPROVE = 'approve';

    public const string REQUEST_CHANGES = 'request_changes';

    private ProductDevelopmentArtwork $artwork;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->artwork->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canApproveDesign($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentArtwork $artwork, User $user, array $modelData): ProductDevelopmentArtwork
    {
        $approved = $modelData['operation'] === self::APPROVE;

        $artwork->update([
            'status'         => $approved ? ProductDevelopmentArtworkStatusEnum::APPROVED : ProductDevelopmentArtworkStatusEnum::CHANGES_REQUIRED,
            'review_comment' => trim((string) ($modelData['comment'] ?? '')) ?: null,
            'reviewed_by_id' => $user->id,
            'reviewed_at'    => now(),
            'approved_label_hash' => $approved ? ProductDevelopmentArtworks::labelHash($artwork->productDevelopment, $artwork->item) : null,
            'sample_reopened_at'  => $approved ? null : $artwork->sample_reopened_at,
        ]);
        ProductDevelopmentArtworks::recordVersion($artwork);

        if ($artwork->review_comment) {
            $artwork->productDevelopment->comments()->create([
                'user_id'    => $user->id,
                'body'       => $artwork->review_comment,
                'mentions'   => [],
                'model_type' => $artwork->getMorphClass(),
                'model_id'   => $artwork->id,
                'data'       => ['review' => $modelData['operation'], 'version' => $artwork->version],
            ]);
        }

        RecordProductDevelopmentActivity::record(
            $artwork->productDevelopment,
            $approved ? RecordProductDevelopmentActivity::ARTWORK_APPROVED : RecordProductDevelopmentActivity::ARTWORK_CHANGES_REQUIRED,
            [],
            ['artwork' => ProductDevelopmentArtworks::label($artwork), 'version' => $artwork->version, 'comment' => $artwork->review_comment]
        );

        ProductDevelopmentArtworks::notifyDesigners($artwork, $user);
        CreateProductDevelopmentTask::make()->ensureForDevelopment($artwork->productDevelopment->refresh(), $user);

        return $artwork;
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
            'comment.required_if' => __('Say what needs fixing'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!ProductDevelopmentArtworks::canChange($this->artwork)) {
            $validator->errors()->add('operation', __('The artwork is signed off and locked'));

            return;
        }

        if ($this->artwork->status !== ProductDevelopmentArtworkStatusEnum::IN_REVIEW) {
            $validator->errors()->add('operation', __('This artwork is not in review'));

            return;
        }

        if ($this->get('operation') === self::APPROVE && $reason = ProductDevelopmentArtworks::approvalBlockedReason($this->artwork)) {
            $validator->errors()->add('operation', $reason);
        }
    }

    public function asController(ProductDevelopmentArtwork $productDevelopmentArtwork, ActionRequest $request): ProductDevelopmentArtwork
    {
        $this->artwork = $productDevelopmentArtwork;
        $this->initialisationFromGroup($productDevelopmentArtwork->productDevelopment->group, $request);

        return $this->handle($productDevelopmentArtwork, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopmentArtwork $artwork, User $user, array $modelData): ProductDevelopmentArtwork
    {
        $this->asAction = true;
        $this->artwork  = $artwork;
        $this->initialisationFromGroup($artwork->productDevelopment->group, $modelData);

        return $this->handle($artwork, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
