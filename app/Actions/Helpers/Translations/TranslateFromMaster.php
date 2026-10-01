<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 03:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * One button instead of one per field: every text of the page that nobody has reviewed is
 * translated again from the master and saved, still unreviewed, so the webmaster reads, rates
 * and fixes it. Text a person wrote is never touched: reviewed fields are skipped, and so is any
 * field a person ever saved according to the audit log, because until 2 Oct 2026 a category save
 * never raised the review flag.
 */
class TranslateFromMaster extends OrgAction
{
    /**
     * @return array<int, string> the fields translated
     */
    public function handle(Product|ProductCategory $model): array
    {
        $master = $model instanceof Product ? $model->masterProduct : $model->masterProductCategory;

        if (!$master || $model->shop->language->code === 'en') {
            return [];
        }

        $writtenByPeople = $this->fieldsSavedByPeople($model);
        $translationData = [];

        foreach (RecordTranslationReview::FIELDS as $field) {
            $english = data_get($master->{$field.'_i8n'}, 'en') ?? $master->{$field};

            if (blank($english) || $model->{'is_'.$field.'_reviewed'} || in_array($field, $writtenByPeople)) {
                continue;
            }

            $translationData[$field] = $english;
        }

        if ($translationData) {
            TranslateModel::run($model, $translationData);
        }

        return array_keys($translationData);
    }

    /**
     * @return array<int, string>
     */
    public function fieldsSavedByPeople(Product|ProductCategory $model): array
    {
        return array_values(array_filter(RecordTranslationReview::FIELDS, fn (string $field) => DB::table('audits')
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->id)
            ->whereNotNull('user_id')
            ->where('event', 'updated')
            ->whereJsonContainsKey('new_values->'.$field)
            ->where(fn ($query) => $query->whereNull('url')->orWhere('url', 'not like', '%/translate-from-master'))
            ->exists()));
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

    public function inProduct(Product $product, ActionRequest $request): array
    {
        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product);
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): array
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory);
    }

    public function htmlResponse(array $translatedFields): RedirectResponse
    {
        return back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Translated from master'),
            'description' => $translatedFields
                ? __('Translated: :fields. Read them and rate the translations.', ['fields' => implode(', ', $translatedFields)])
                : __('Nothing to translate: every text here has been reviewed by a person.'),
        ]);
    }
}
