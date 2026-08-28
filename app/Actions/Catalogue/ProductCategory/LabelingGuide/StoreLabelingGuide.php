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
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;

class StoreLabelingGuide extends OrgAction
{
    public function handle(ProductCategory|TradeUnitFamily $parent, array $modelData): void
    {
        foreach ($parent->attachments()->where('scope', 'labeling_guide')->get() as $previousAttachment) {
            DetachAttachmentFromModel::make()->action($parent, $previousAttachment);
        }

        AttachAttachmentToModel::make()->action($parent, [
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

    public function action(ProductCategory|TradeUnitFamily $parent, array $modelData): void
    {
        $this->asAction = true;

        $this->initialisationFromGroup(group(), $modelData);

        $this->handle($parent, $modelData);
    }
}
