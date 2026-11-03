<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-20h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Json;

use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentLabelContent;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Goods\ProductDevelopmentLabelTranslation;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

/**
 * Machine translates a SKU's label text into one language and keeps it with the SKU, marked as AI-generated
 * and not checked. Translating the same language again replaces it. The approved text stays the English one.
 */
class TranslateProductDevelopmentLabel extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentItem $item;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $this->item->productDevelopment);
    }

    public function handle(ProductDevelopmentItem $item, Language $language, User $user): ProductDevelopmentLabelTranslation
    {
        $labelText = GetProductDevelopmentLabelContent::run($item->productDevelopment, $item->load('tradeUnit'))['label_text'];

        return ProductDevelopmentLabelTranslation::updateOrCreate(
            ['product_development_item_id' => $item->id, 'language_id' => $language->id],
            [
                'text'             => Translate::run($labelText, Language::where('code', 'en')->firstOrFail(), $language, null, true),
                'source_hash'      => ProductDevelopmentLabelTranslation::sourceHash($labelText),
                'translated_by_id' => $user->id,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'language' => ['required', 'string', 'exists:languages,code'],
        ];
    }

    public function asController(ProductDevelopmentItem $productDevelopmentItem, ActionRequest $request): ProductDevelopmentLabelTranslation
    {
        $this->item = $productDevelopmentItem;
        $this->initialisationFromGroup($productDevelopmentItem->productDevelopment->group, $request);

        return $this->handle($productDevelopmentItem, Language::where('code', $this->validatedData['language'])->firstOrFail(), $request->user());
    }

    public function action(ProductDevelopmentItem $item, User $user, array $modelData): ProductDevelopmentLabelTranslation
    {
        $this->asAction = true;
        $this->item     = $item;
        $this->initialisationFromGroup($item->productDevelopment->group, $modelData);

        return $this->handle($item, Language::where('code', $this->validatedData['language'])->firstOrFail(), $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonResponse(ProductDevelopmentLabelTranslation $translation): array
    {
        return GetProductDevelopmentItemLabelContent::translationRow($translation->load(['language', 'translatedBy']), $translation->source_hash);
    }
}
