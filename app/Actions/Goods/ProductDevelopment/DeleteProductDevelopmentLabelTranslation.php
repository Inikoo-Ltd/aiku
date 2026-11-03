<?php

/*
 * Author Louis Perez
 * Created on 03-11-2026-14h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Json\TranslateProductDevelopmentLabel;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopmentLabelTranslation;
use Lorisleiva\Actions\ActionRequest;

class DeleteProductDevelopmentLabelTranslation extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentLabelTranslation $translation;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return TranslateProductDevelopmentLabel::canTranslate($request->user(), $this->translation->item->productDevelopment);
    }

    public function handle(ProductDevelopmentLabelTranslation $translation): void
    {
        $translation->delete();
    }

    public function asController(ProductDevelopmentLabelTranslation $labelTranslation, ActionRequest $request): void
    {
        $this->translation = $labelTranslation;
        $this->initialisationFromGroup($labelTranslation->item->productDevelopment->group, $request);

        $this->handle($labelTranslation);
    }

    public function jsonResponse(): array
    {
        return [];
    }
}
