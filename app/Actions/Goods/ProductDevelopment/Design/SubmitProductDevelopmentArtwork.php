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
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sends an artwork built in the Aiku editor for review, with a note on what changed.
 */
class SubmitProductDevelopmentArtwork extends OrgAction
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
            && $this->productDevelopmentAccess()->canManageArtwork($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentArtwork $artwork, User $user, array $modelData): ProductDevelopmentArtwork
    {
        $artwork->update([
            'version'            => $artwork->version + 1,
            'status'             => ProductDevelopmentArtworkStatusEnum::IN_REVIEW,
            'update_note'        => trim((string) ($modelData['update_note'] ?? '')) ?: null,
            'updated_by_id'      => $user->id,
            'artwork_updated_at' => now(),
            'review_comment'     => null,
            'reviewed_by_id'     => null,
            'reviewed_at'        => null,
            'compliance_ok_by_id' => null,
            'compliance_ok_at'    => null,
        ]);
        ProductDevelopmentArtworks::recordVersion($artwork);

        RecordProductDevelopmentActivity::record($artwork->productDevelopment, RecordProductDevelopmentActivity::ARTWORK_SUBMITTED, [], [
            'artwork' => ProductDevelopmentArtworks::label($artwork),
            'version' => $artwork->version,
            'note'    => $artwork->update_note,
        ]);

        ProductDevelopmentArtworks::notifyCompliance($artwork, $user);
        CreateProductDevelopmentTask::make()->ensureForDevelopment($artwork->productDevelopment->refresh(), $user);

        return $artwork;
    }

    public function rules(): array
    {
        return [
            'update_note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!UpdateProductDevelopmentArtworkLayout::isEditable($this->artwork)) {
            $validator->errors()->add('status', __('This artwork cannot be sent for review now'));

            return;
        }

        if (!ProductDevelopmentDesignLayout::hasSize($this->artwork->layout ?? [])) {
            $validator->errors()->add('layout', ($this->artwork->layout['kind'] ?? null) === ProductDevelopmentDesignLayout::KIND_SHEET
                ? __('Set the width and height of the label first')
                : __('Set the width, length and height of the box first'));
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
