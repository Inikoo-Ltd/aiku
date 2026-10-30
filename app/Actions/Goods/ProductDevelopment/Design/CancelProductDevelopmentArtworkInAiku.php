<?php

/*
 * Author Louis Perez
 * Created on 23-10-2026-17h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Models\Goods\ProductDevelopmentArtwork;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Drops a layout still being drawn in the Aiku editor, so the SKU's artwork can be uploaded as a PDF
 * instead. Earlier versions sent for review stay in the history.
 */
class CancelProductDevelopmentArtworkInAiku extends OrgAction
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

    public function handle(ProductDevelopmentArtwork $artwork): ProductDevelopmentArtwork
    {
        $artwork->update([
            'source'   => null,
            'layout'   => [],
            'media_id' => null,
        ]);

        return $artwork;
    }

    public static function isCancellable(ProductDevelopmentArtwork $artwork): bool
    {
        return $artwork->source === ProductDevelopmentArtwork::SOURCE_AIKU
            && in_array($artwork->status, [ProductDevelopmentArtworkStatusEnum::NOT_SUBMITTED, ProductDevelopmentArtworkStatusEnum::CHANGES_REQUIRED], true);
    }

    public function afterValidator(Validator $validator): void
    {
        if (!ProductDevelopmentArtworks::canChange($this->artwork)) {
            $validator->errors()->add('artwork', __('The artwork is signed off and locked'));

            return;
        }

        if (!self::isCancellable($this->artwork)) {
            $validator->errors()->add('artwork', __('Only a design still being made in Aiku can be cancelled'));
        }
    }

    public function asController(ProductDevelopmentArtwork $productDevelopmentArtwork, ActionRequest $request): ProductDevelopmentArtwork
    {
        $this->artwork = $productDevelopmentArtwork;
        $this->initialisationFromGroup($productDevelopmentArtwork->productDevelopment->group, $request);

        return $this->handle($productDevelopmentArtwork);
    }

    public function action(ProductDevelopmentArtwork $artwork): ProductDevelopmentArtwork
    {
        $this->asAction = true;
        $this->artwork  = $artwork;
        $this->initialisationFromGroup($artwork->productDevelopment->group, []);

        return $this->handle($artwork);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
