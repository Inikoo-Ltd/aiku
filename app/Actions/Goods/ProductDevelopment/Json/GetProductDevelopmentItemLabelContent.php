<?php

/*
 * Author Louis Perez
 * Created on 22-10-2026-17h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Json;

use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentLabelContent;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Helpers\Language;
use Lorisleiva\Actions\ActionRequest;

/**
 * A SKU's approved label and pack information, shown beside an artwork while it is reviewed.
 */
class GetProductDevelopmentItemLabelContent extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): array
    {
        abort_unless($item->product_development_id === $productDevelopment->id, 404);

        return [
            ...GetProductDevelopmentLabelContent::run($productDevelopment, $item->load('tradeUnit')),
            'translate_route' => ['name' => 'grp.product_developments.json.label_translate', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'item' => $item->id]],
            'languages'       => Language::where('code', '!=', 'en')->orderBy('name')->get(['code', 'name'])->map(fn (Language $language) => ['value' => $language->code, 'label' => $language->name])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item, ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($productDevelopment, $item);
    }
}
