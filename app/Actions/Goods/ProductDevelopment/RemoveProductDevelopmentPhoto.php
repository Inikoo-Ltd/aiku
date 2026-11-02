<?php

/*
 * Author Louis Perez
 * Created on 27-10-2026-12h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\Goods\ProductDevelopmentFile;
use App\Models\Goods\ProductDevelopmentSample;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Takes a photo off a sample (while it is being made) or off a packaging sample.
 */
class RemoveProductDevelopmentPhoto extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentFile $file;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->file->productDevelopment;
        $access             = $this->productDevelopmentAccess();

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && match ($this->file->scope) {
                ProductDevelopmentFileScopeEnum::SAMPLE_PHOTO => $access->canManageSamples($request->user(), $productDevelopment),
                ProductDevelopmentFileScopeEnum::PACKAGING_SAMPLE_PHOTO => $access->canDesign($request->user(), $productDevelopment),
                default => false,
            };
    }

    public function handle(ProductDevelopmentFile $file): void
    {
        $productDevelopment = $file->productDevelopment;
        $name               = $file->media?->name ?? __('a photo');
        $file->delete();

        RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::FILE_REMOVED, ['file' => $name], []);
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->file->scope->isModelPhoto()) {
            $validator->errors()->add('file', __('Only sample photos can be removed here'));

            return;
        }

        $sample = $this->file->model;
        if ($sample instanceof ProductDevelopmentSample && $sample->status !== ProductDevelopmentSampleStatusEnum::IN_PROGRESS) {
            $validator->errors()->add('file', __('Photos can only be removed while the sample is being made'));
        }
    }

    public function asController(ProductDevelopmentFile $productDevelopmentFile, ActionRequest $request): void
    {
        $this->file = $productDevelopmentFile;
        $this->initialisationFromGroup($productDevelopmentFile->productDevelopment->group, $request);

        $this->handle($productDevelopmentFile);
    }

    public function action(ProductDevelopmentFile $file): void
    {
        $this->asAction = true;
        $this->file     = $file;
        $this->initialisationFromGroup($file->productDevelopment->group, []);

        $this->handle($file);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
