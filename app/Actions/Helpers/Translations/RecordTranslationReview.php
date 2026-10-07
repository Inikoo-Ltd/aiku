<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 00:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\TranslationReview;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * One row per machine translation a webmaster looked at: the stars they gave it and, once they
 * save their own wording, what they wrote instead. Rating first and editing after land on the
 * same row because both are keyed by the machine text they judged.
 */
class RecordTranslationReview extends OrgAction
{
    public const array FIELDS = ['name', 'description_title', 'description', 'description_extra'];

    /**
     * @param array{rating?: int|null, corrected_text?: string|null} $review
     */
    public function handle(Product|ProductCategory $model, string $field, string $machineText, array $review, ?User $user): ?TranslationReview
    {
        $master     = $model instanceof Product ? $model->masterProduct : $model->masterProductCategory;
        $sourceText = $master?->{$field};
        $shop       = $model->shop;

        if (blank($sourceText) || blank($machineText) || $shop->language->code === 'en') {
            return null;
        }

        $translationReview = TranslationReview::firstOrNew([
            'model_type'        => $model->getMorphClass(),
            'model_id'          => $model->id,
            'field'             => $field,
            'machine_text_hash' => md5($machineText),
        ]);

        $translationReview->fill([
            'group_id'        => $shop->group_id,
            'organisation_id' => $shop->organisation_id,
            'shop_id'         => $shop->id,
            'language_id'     => $shop->language_id,
            'source_text'     => $sourceText,
            'machine_text'    => $machineText,
            'user_id'         => $user?->id ?? $translationReview->user_id,
            ...Arr::only($review, ['rating', 'corrected_text']),
        ]);
        $translationReview->save();

        return $translationReview;
    }

    /**
     * Called by the edit controllers with the text as it was before the save: only text nobody
     * had reviewed yet is the machine's, so only that is worth learning from.
     *
     * @param array<string, mixed> $modelData
     */
    public function fromEdit(Product|ProductCategory $model, array $modelData, ?User $user): void
    {
        foreach (self::FIELDS as $field) {
            $corrected = Arr::get($modelData, $field);
            $before    = $model->{$field};

            if (!is_string($corrected) || $model->{'is_'.$field.'_reviewed'} || $corrected === $before || !is_string($before)) {
                continue;
            }

            $this->handle($model, $field, $before, ['corrected_text' => $corrected], $user);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "products.{$this->shop->id}.edit",
            "web.{$this->shop->id}.edit",
            'group-webmaster.edit',
            'masters.edit',
        ]);
    }

    public function rules(): array
    {
        return [
            'field'  => ['required', Rule::in(self::FIELDS)],
            'rating' => ['required', 'integer', 'between:1,5'],
        ];
    }

    public function inProduct(Product $product, ActionRequest $request): void
    {
        $this->rate($product, $request);
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->rate($productCategory, $request);
    }

    private function rate(Product|ProductCategory $model, ActionRequest $request): void
    {
        $this->initialisationFromShop($model->shop, $request);
        $field = $this->validatedData['field'];

        $this->handle($model, $field, (string) $model->{$field}, ['rating' => $this->validatedData['rating']], $request->user());
    }
}
