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
use App\Models\Goods\ProductDevelopmentLabelTranslation;
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

        $content    = GetProductDevelopmentLabelContent::run($productDevelopment, $item->load('tradeUnit'));
        $sourceHash = ProductDevelopmentLabelTranslation::sourceHash($content['label_text']);

        return [
            ...$content,
            'translate_route' => ['name' => 'grp.models.product_development.item.label_translation.store', 'parameters' => ['productDevelopmentItem' => $item->id]],
            'languages'       => Language::where('code', '!=', 'en')->orderBy('name')->get(['code', 'name'])->map(fn (Language $language) => ['value' => $language->code, 'label' => $language->name])->all(),
            'translations'    => $item->labelTranslations()->with(['language', 'translatedBy'])->get()
                ->sortBy(fn (ProductDevelopmentLabelTranslation $translation) => $translation->language->name)
                ->map(fn (ProductDevelopmentLabelTranslation $translation) => self::translationRow($translation, $sourceHash))
                ->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function translationRow(ProductDevelopmentLabelTranslation $translation, string $currentSourceHash): array
    {
        return [
            'id'            => $translation->id,
            'language'      => $translation->language->code,
            'language_name' => $translation->language->name,
            'text'          => $translation->text,
            'outdated'      => $translation->source_hash !== $currentSourceHash,
            'translated_by' => $translation->translatedBy?->contact_name ?: $translation->translatedBy?->username,
            'translated_at' => $translation->updated_at?->toIso8601String(),
            'delete_route'  => ['name' => 'grp.models.product_development.label_translation.delete', 'parameters' => ['productDevelopmentLabelTranslation' => $translation->id]],
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
