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
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Helpers\Language;
use Lorisleiva\Actions\ActionRequest;

/**
 * A quick translation of a SKU's label text into one language, on request, to copy. Nothing is saved: the
 * approved text stays the English one.
 */
class TranslateProductDevelopmentLabel extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    /**
     * @return array{text: string, language: string}
     */
    public function handle(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item, Language $language): array
    {
        abort_unless($item->product_development_id === $productDevelopment->id, 404);

        $text = GetProductDevelopmentLabelContent::run($productDevelopment, $item->load('tradeUnit'))['label_text'];

        return [
            'text'     => Translate::run($text, Language::where('code', 'en')->firstOrFail(), $language, null, true),
            'language' => $language->name,
        ];
    }

    public function rules(): array
    {
        return [
            'language' => ['required', 'string', 'exists:languages,code'],
        ];
    }

    /**
     * @return array{text: string, language: string}
     */
    public function asController(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item, ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($productDevelopment, $item, Language::where('code', $this->validatedData['language'])->firstOrFail());
    }
}
