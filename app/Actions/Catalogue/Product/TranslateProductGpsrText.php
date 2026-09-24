<?php

/*
 * Author Louis Perez
 * Created on 15-09-2026-16h-22m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\Helpers\Translations\Translate;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateProductGpsrText implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'translate-model';

    public const REVIEW_FLAGS = [
        'gpsr_warnings' => 'is_gpsr_warnings_reviewed',
        'gpsr_manual'   => 'is_gpsr_manual_reviewed',
    ];

    /**
     * @param array<int, string> $fields
     */
    public function getJobUniqueId(Product $product, array $fields): string
    {
        return $product->id.'-'.implode('-', $fields);
    }

    /**
     * Runs queued, translation is slow. The English source and the review flag are read when the
     * job runs, so it translates the latest text and leaves alone a copy a person wrote meanwhile.
     *
     * @param array<int, string> $fields
     */
    public function handle(Product $product, array $fields): void
    {
        $english      = Language::where('code', 'en')->first();
        $shopLanguage = $product->shop->language;

        if ($shopLanguage->id == $english->id) {
            return;
        }

        $attributes = [];

        foreach (array_intersect(array_keys(self::REVIEW_FLAGS), $fields) as $field) {
            $englishText = $product->getTranslation($field.'_i8n', 'en', false);

            if (blank($englishText) || $product->{self::REVIEW_FLAGS[$field]} !== null) {
                continue;
            }

            $attributes[$field] = Translate::run($englishText, $english, $shopLanguage, 'gpt-5-nano');
        }

        if (!$attributes) {
            return;
        }

        $product->update($attributes);

        $this->recordShopTranslation($product, array_keys($product->getChanges()));

        if ($product->wasChanged(array_keys(self::REVIEW_FLAGS)) && $product->webpage && $product->webpage->state == WebpageStateEnum::LIVE) {
            BreakWebpageCache::dispatch($product->webpage)->delay(5);
        }
    }

    /**
     * The English GPSR text comes from the trade units or the master and is kept under the "en" key
     * of the product map, which is also how an unchanged source is recognised and skipped. A shop in
     * another language gets it translated while nobody has written its copy (flag null), which is
     * returned as a field to translate rather than done here. Once a person has written it (flag
     * true) that copy is kept and the flag drops to false to ask for a review, and it stays kept
     * while it waits, so a later source change cannot translate over it.
     *
     * @param array<string, string|null> $englishTexts
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    public function fromSource(Product $product, array $englishTexts): array
    {
        $isEnglishShop    = $product->shop->language->code == 'en';
        $attributes       = [];
        $fieldsToTranslate = [];

        foreach (array_intersect_key($englishTexts, self::REVIEW_FLAGS) as $field => $englishText) {
            if ($englishText === null) {
                continue;
            }

            $translations = $product->getTranslations($field.'_i8n');

            if (Arr::get($translations, 'en') === $englishText && $product->$field !== null) {
                continue;
            }

            $translations['en'] = $englishText;
            $reviewFlag         = self::REVIEW_FLAGS[$field];

            if ($isEnglishShop || $englishText === '') {
                $attributes[$field] = $englishText;
            } elseif ($product->$reviewFlag === null) {
                $fieldsToTranslate[] = $field;
            } elseif ($product->$reviewFlag) {
                $attributes[$reviewFlag] = false;
            }

            $attributes[$field.'_i8n'] = $translations;
        }

        return [$attributes, $fieldsToTranslate];
    }

    /**
     * @param array<string, string|null> $englishTexts
     */
    public function applyTo(Product $product, array $englishTexts): void
    {
        [$attributes, $fieldsToTranslate] = $this->fromSource($product, $englishTexts);

        if ($attributes) {
            $product->update($attributes);

            $this->recordShopTranslation($product, array_keys($product->getChanges()));

            if ($product->wasChanged(array_keys(self::REVIEW_FLAGS)) && $product->webpage && $product->webpage->state == WebpageStateEnum::LIVE) {
                BreakWebpageCache::dispatch($product->webpage)->delay(5);
            }
        }

        $this->dispatchTranslations($product, $fieldsToTranslate);
    }

    /**
     * @param array<int, string> $fields
     */
    public function dispatchTranslations(Product $product, array $fields): void
    {
        if ($fields) {
            static::dispatch($product, $fields);
        }
    }

    /**
     * The shop locale entry mirrors the text the shop shows, on the product and on its master, the
     * same way UpdateProductAndMasterTranslations keeps the name and descriptions in step. Written
     * straight to the rows so the caller's change set is left as it was.
     *
     * @param array<int, string> $changedFields
     */
    public function recordShopTranslation(Product $product, array $changedFields): void
    {
        $fields = array_intersect(array_keys(self::REVIEW_FLAGS), $changedFields);

        if (!$fields) {
            return;
        }

        $shopLocale        = $product->shop->language->code;
        $masterProduct     = $product->masterProduct;
        $productAttributes = [];
        $masterAttributes  = [];

        foreach ($fields as $field) {
            $productAttributes[$field.'_i8n'] = json_encode(array_merge($product->getTranslations($field.'_i8n'), [$shopLocale => $product->$field]));

            if ($masterProduct) {
                $masterAttributes[$field.'_i8n'] = json_encode(array_merge($masterProduct->getTranslations($field.'_i8n'), [$shopLocale => $product->$field]));
            }
        }

        $product->newQuery()->whereKey($product->id)->update($productAttributes);
        $product->setRawAttributes(array_merge($product->getAttributes(), $productAttributes), true);

        if ($masterAttributes) {
            $masterProduct->newQuery()->whereKey($masterProduct->id)->update($masterAttributes);
        }
    }

    /**
     * @param array<int, string> $changedFields
     */
    public function recordMasterSource(MasterAsset $masterAsset, array $changedFields): void
    {
        $fields = array_intersect(array_keys(self::REVIEW_FLAGS), $changedFields);

        if (!$fields) {
            return;
        }

        $attributes = [];

        foreach ($fields as $field) {
            $attributes[$field.'_i8n'] = json_encode(array_merge($masterAsset->getTranslations($field.'_i8n'), ['en' => $masterAsset->$field]));
        }

        $masterAsset->newQuery()->whereKey($masterAsset->id)->update($attributes);
        $masterAsset->setRawAttributes(array_merge($masterAsset->getAttributes(), $attributes), true);
    }
}
