<?php

/*
 * Author Louis Perez
 * Created on 27-10-2026-12h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * The designer's verdict on the physical box or label printed from an artwork: approved, or changes needed
 * with a comment. The comment also goes into the artwork's discussion.
 */
class ReviewProductDevelopmentPackagingSample extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentArtwork $artwork;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->artwork->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canDesign($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentArtwork $artwork, User $user, array $modelData): ProductDevelopmentArtwork
    {
        $approved = $modelData['status'] === ProductDevelopmentArtwork::PACKAGING_SAMPLE_APPROVED;

        $artwork->update([
            'packaging_sample_status'         => $modelData['status'],
            'packaging_sample_note'           => trim((string) ($modelData['comment'] ?? '')) ?: null,
            'packaging_sample_reviewed_by_id' => $user->id,
            'packaging_sample_reviewed_at'    => now(),
        ]);

        if ($artwork->packaging_sample_note) {
            $artwork->productDevelopment->comments()->create([
                'user_id'    => $user->id,
                'body'       => $artwork->packaging_sample_note,
                'mentions'   => [],
                'model_type' => $artwork->getMorphClass(),
                'model_id'   => $artwork->id,
                'data'       => ['packaging_sample' => $modelData['status']],
            ]);
        }

        RecordProductDevelopmentActivity::record(
            $artwork->productDevelopment,
            $approved ? RecordProductDevelopmentActivity::PACKAGING_SAMPLE_APPROVED : RecordProductDevelopmentActivity::PACKAGING_SAMPLE_CHANGES_REQUIRED,
            [],
            ['artwork' => ProductDevelopmentArtworks::label($artwork), 'comment' => $artwork->packaging_sample_note]
        );

        return $artwork;
    }

    public function rules(): array
    {
        return [
            'status'  => ['required', Rule::in([ProductDevelopmentArtwork::PACKAGING_SAMPLE_APPROVED, ProductDevelopmentArtwork::PACKAGING_SAMPLE_CHANGES_REQUIRED])],
            'comment' => ['required_if:status,'.ProductDevelopmentArtwork::PACKAGING_SAMPLE_CHANGES_REQUIRED, 'nullable', 'string', 'max:2000'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'comment.required_if' => __('Say what needs changing'),
        ];
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
