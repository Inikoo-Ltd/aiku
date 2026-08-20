<?php

/*
 * Author Louis Perez
 * Created on 20-08-2026-14h-03m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\LabelingGuide;

use App\Actions\Helpers\Media\AttachAttachmentToModel;
use App\Actions\Helpers\Media\DetachAttachmentFromModel;
use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;

class StoreLabelingGuide extends OrgAction
{
    public function handle(ProductCategory $productCategory, array $modelData): void
    {
        foreach ($productCategory->attachments()->where('scope', 'labeling_guide')->get() as $previousAttachment) {
            DetachAttachmentFromModel::make()->action($productCategory, $previousAttachment);
        }

        AttachAttachmentToModel::make()->action($productCategory, [
            'attachments'   => [
                Arr::pull($modelData, 'labeling_guide_file')
            ],
            'scope'         => 'labeling_guide'
        ]);
    }

    public function rules(): array
    {
        return [
            // labeling_guide_file
            'labeling_guide_file'           => ['sometimes', 'nullable', File::types(['pdf'])->max(64000)], // 64mb max, following server max (prod on php.ini max file size upload)
        ];
    }

    public function action(ProductCategory $productCategory, array $modelData): void
    {
        $this->asAction = true;
        $this->productCategory = $productCategory;

        $this->initialisation($productCategory->organisation, $modelData);

        $this->handle($productCategory, $modelData);
    }
}
