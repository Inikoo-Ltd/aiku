<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsObject;

class PropagateMasterContentToProducts
{
    use AsObject;

    public const REVIEW_FLAGS = [
        'name'              => 'is_name_reviewed',
        'description_title' => 'is_description_title_reviewed',
        'description'       => 'is_description_reviewed',
        'description_extra' => 'is_description_extra_reviewed',
    ];

    /**
     * A master content change is never machine translated onto a shop that speaks another
     * language: that copy belongs to the shopkeeper who wrote it, and overwriting it loses
     * work no one can get back. Those shops keep their text and are flagged for review, which
     * is what raises their badge. A shop that speaks the master's language has nothing to
     * translate, so it takes the new text verbatim, the same way the unit label already does.
     *
     * @param array<int, string> $changedFields
     */
    public function handle(MasterAsset $masterAsset, array $changedFields): void
    {
        $changedFields = array_intersect($changedFields, array_keys(self::REVIEW_FLAGS));

        if (!$changedFields) {
            return;
        }

        $english = Language::where('code', 'en')->first();

        foreach ($masterAsset->products()->with('shop.language')->get() as $product) {
            if ($product->shop->language_id == $english->id) {
                $this->writeMasterText($masterAsset, $product, $changedFields);

                continue;
            }

            $this->flagForReview($masterAsset, $product, $changedFields);
        }
    }

    /**
     * @param array<int, string> $changedFields
     */
    private function writeMasterText(MasterAsset $masterAsset, Product $product, array $changedFields): void
    {
        $dataToBeUpdated = [];

        foreach ($changedFields as $field) {
            $dataToBeUpdated[$field] = $this->englishValue($masterAsset, $field);
        }

        $dataToBeUpdated = array_filter($dataToBeUpdated, fn ($value) => $value !== null);

        if ($dataToBeUpdated) {
            UpdateProduct::make()->action($product, $dataToBeUpdated);
        }
    }

    /**
     * The shop's visible text is left alone, but the translation map is still kept in step with
     * the master for every OTHER language: those entries are the master's to fill, nobody edits
     * them from the shop, and leaving them stale was how the previous propagation earned its
     * keep. The shop's own locale is the one entry that is skipped, because that is the copy the
     * shopkeeper wrote.
     *
     * Written straight to the row rather than through UpdateProduct: a review flag is not
     * business data, and the full update pipeline would fire an audit event and re-run the asset
     * sync on every child of every master text edit.
     *
     * @param array<int, string> $changedFields
     */
    private function flagForReview(MasterAsset $masterAsset, Product $product, array $changedFields): void
    {
        $shopLocale = $product->shop->language->code;
        $attributes = [];

        foreach ($changedFields as $field) {
            $attributes[self::REVIEW_FLAGS[$field]] = false;

            $masterTranslations = $masterAsset->getTranslations($field.'_i8n');
            unset($masterTranslations[$shopLocale]);

            if ($masterTranslations) {
                $productTranslations = array_merge(
                    $product->getTranslations($field.'_i8n'),
                    $masterTranslations
                );

                $attributes[$field.'_i8n'] = json_encode($productTranslations);
            }
        }

        $product->newQuery()->whereKey($product->id)->update($attributes);
    }

    /**
     * A master edited through its translation form writes the English text into the i8n map
     * rather than the scalar column, so the map is read first and the scalar is the fallback.
     */
    private function englishValue(MasterAsset $masterAsset, string $field): ?string
    {
        return data_get($masterAsset->{$field.'_i8n'}, 'en') ?? $masterAsset->$field;
    }
}
