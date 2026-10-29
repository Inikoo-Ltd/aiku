<?php

/*
 * Author Louis Perez
 * Created on 27-10-2026-12h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFiles;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\Goods\ProductDevelopmentFile;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Adds photos of an in-house sample while it is being made.
 */
class UploadProductDevelopmentSamplePhotos extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string ACCEPTED_EXTENSIONS = 'jpg,jpeg,png,webp';

    public const int MAX_PHOTOS = 10;

    public const int MAX_KILOBYTES = 20480;

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

    /**
     * @param array<int, UploadedFile> $photos
     *
     * @return array<int, ProductDevelopmentFile>
     */
    public function handle(ProductDevelopmentSample $sample, User $user, array $photos): array
    {
        return UploadProductDevelopmentFiles::make()->attachPhotos($sample, $sample->item, $user, $photos, ProductDevelopmentFileScopeEnum::SAMPLE_PHOTO);
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
        if ($this->sample->status !== ProductDevelopmentSampleStatusEnum::IN_PROGRESS) {
            $validator->errors()->add('photos', __('Photos can only be added while the trial run is being made'));
        }
    }

    public function asController(ProductDevelopmentSample $productDevelopmentSample, ActionRequest $request): array
    {
        $this->sample = $productDevelopmentSample;
        $this->initialisationFromGroup($productDevelopmentSample->productDevelopment->group, $request);

        return $this->handle($productDevelopmentSample, $request->user(), $this->validatedData['photos']);
    }

    public function action(ProductDevelopmentSample $sample, User $user, array $modelData): array
    {
        $this->asAction = true;
        $this->sample   = $sample;
        $this->initialisationFromGroup($sample->productDevelopment->group, $modelData);

        return $this->handle($sample, $user, $this->validatedData['photos']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
