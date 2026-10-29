<?php

/*
 * Author Louis Perez
 * Created on 27-10-2026-12h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\CreateProductDevelopmentTask;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentPurchaseOrders;
use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The verdict on the sample made from an approved artwork (the product itself, a box, a label...): approved, which
 * lets its purchase orders go ahead, or changes needed with a comment, which sends the artwork back for its next
 * version. The comment also goes into the artwork's discussion.
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
        $approved           = $modelData['status'] === ProductDevelopmentArtwork::PACKAGING_SAMPLE_APPROVED;
        $note               = trim((string) ($modelData['comment'] ?? '')) ?: null;
        $productDevelopment = $artwork->productDevelopment;

        DB::transaction(function () use ($artwork, $user, $modelData, $approved, $note, $productDevelopment) {
            $artwork->versions()->where('version', $artwork->version)->update([
                'sample_status'         => $modelData['status'],
                'sample_note'           => $note,
                'sample_reviewed_by_id' => $user->id,
                'sample_reviewed_at'    => now(),
            ]);

            if ($approved) {
                $artwork->update([
                    'packaging_sample_status'         => $modelData['status'],
                    'packaging_sample_note'           => $note,
                    'packaging_sample_reviewed_by_id' => $user->id,
                    'packaging_sample_reviewed_at'    => now(),
                ]);
            } else {
                $this->sendBackForChanges($artwork, $user, $note);
            }

            if ($note) {
                $productDevelopment->comments()->create([
                    'user_id'    => $user->id,
                    'body'       => $note,
                    'mentions'   => [],
                    'model_type' => $artwork->getMorphClass(),
                    'model_id'   => $artwork->id,
                    'data'       => ['packaging_sample' => $modelData['status'], 'version' => $artwork->version],
                ]);
            }
        });

        $released = $approved ? ProductDevelopmentPurchaseOrders::releaseSamples($artwork->refresh(), $user) : [];

        RecordProductDevelopmentActivity::record(
            $productDevelopment,
            $approved ? RecordProductDevelopmentActivity::PACKAGING_SAMPLE_APPROVED : RecordProductDevelopmentActivity::PACKAGING_SAMPLE_CHANGES_REQUIRED,
            [],
            ['artwork' => ProductDevelopmentArtworks::label($artwork), 'version' => $artwork->version, 'comment' => $note, 'purchase_orders' => $released]
        );

        if (!$approved) {
            ProductDevelopmentArtworks::notifyDesigners($artwork, $user);
            CreateProductDevelopmentTask::make()->ensureForDevelopment($productDevelopment->refresh(), $user);
        }

        return $artwork;
    }

    /**
     * A rejected sample sends its artwork back to the designer for the next version. The sample's photos stay with
     * the version they were made from, and an artwork that was already signed off is reopened on its own.
     */
    private function sendBackForChanges(ProductDevelopmentArtwork $artwork, User $user, ?string $note): void
    {
        $artwork->packagingSamplePhotos()->update(['superseded_at' => now(), 'version' => $artwork->version]);

        $artwork->update([
            'status'                          => ProductDevelopmentArtworkStatusEnum::CHANGES_REQUIRED,
            'review_comment'                  => $note,
            'reviewed_by_id'                  => $user->id,
            'reviewed_at'                     => now(),
            'approved_label_hash'             => null,
            'packaging_sample_status'         => null,
            'packaging_sample_note'           => null,
            'packaging_sample_reviewed_by_id' => null,
            'packaging_sample_reviewed_at'    => null,
            'sample_reopened_at'              => ProductDevelopmentDesignRounds::isArtworkLocked($artwork->productDevelopment) ? now() : null,
        ]);
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

    public function afterValidator(Validator $validator): void
    {
        if ($this->artwork->status !== ProductDevelopmentArtworkStatusEnum::APPROVED) {
            $validator->errors()->add('status', __('Approve the artwork first: the sample is made from the approved artwork'));
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
