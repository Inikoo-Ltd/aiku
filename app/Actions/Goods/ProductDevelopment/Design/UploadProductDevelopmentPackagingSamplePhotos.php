<?php

/*
 * Author Louis Perez
 * Created on 27-10-2026-12h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFiles;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Models\Goods\ProductDevelopmentFile;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Adds photos of the printed box or label of an artwork, so the designer can check the physical packaging sample.
 */
class UploadProductDevelopmentPackagingSamplePhotos extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string ACCEPTED_EXTENSIONS = 'jpg,jpeg,png,webp';

    public const int MAX_PHOTOS = 10;

    public const int MAX_KILOBYTES = 20480;

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

    /**
     * @param array<int, UploadedFile> $photos
     *
     * @return array<int, ProductDevelopmentFile>
     */
    public function handle(ProductDevelopmentArtwork $artwork, User $user, array $photos): array
    {
        return UploadProductDevelopmentFiles::make()->attachPhotos($artwork, $artwork->item, $user, $photos, ProductDevelopmentFileScopeEnum::PACKAGING_SAMPLE_PHOTO);
    }

    public function rules(): array
    {
        return [
            'photos'   => ['required', 'array', 'min:1', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'max:'.self::MAX_KILOBYTES, 'extensions:'.self::ACCEPTED_EXTENSIONS, 'mimes:'.self::ACCEPTED_EXTENSIONS],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->artwork->status !== ProductDevelopmentArtworkStatusEnum::APPROVED) {
            $validator->errors()->add('photos', __('Approve the artwork first: the packaging is printed from the approved artwork'));
        }
    }

    public function asController(ProductDevelopmentArtwork $productDevelopmentArtwork, ActionRequest $request): array
    {
        $this->artwork  = $productDevelopmentArtwork;
        $this->initialisationFromGroup($productDevelopmentArtwork->productDevelopment->group, $request);

        return $this->handle($productDevelopmentArtwork, $request->user(), $this->validatedData['photos']);
    }

    public function action(ProductDevelopmentArtwork $artwork, User $user, array $modelData): array
    {
        $this->asAction = true;
        $this->artwork  = $artwork;
        $this->initialisationFromGroup($artwork->productDevelopment->group, $modelData);

        return $this->handle($artwork, $user, $this->validatedData['photos']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
