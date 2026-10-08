<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Variant;

use App\Actions\Catalogue\Product\BreakProductInWebpagesCache;
use App\Actions\Helpers\Translations\GetCatalogueTranslationBrief;
use App\Actions\Helpers\Translations\Translate;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Variant;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateVariantOptions
{
    use AsAction;

    public string $jobQueue = 'translate-model';

    public string $commandSignature = 'variants:translate-options {variant? : variant slug, all open shops variants when omitted}';

    /**
     * Machine-translates the axis labels and option names the variant has no translation for yet.
     * Translations already there, typed by a webmaster or translated before, are kept.
     */
    public function handle(Variant $variant): Variant
    {
        $language = $variant->shop->language;
        if ($language->code === 'en') {
            return $variant;
        }

        $translations = $variant->option_translations ?? [];
        $missing      = array_filter(
            LocaliseVariantData::make()->terms($variant->data),
            fn (string $term) => blank($translations[$term] ?? null)
        );
        if (!$missing) {
            return $variant;
        }

        $english = Language::where('code', 'en')->first();
        $axes    = collect(data_get($variant->data, 'variants', []))->pluck('label')->filter()->implode(', ');
        $brief   = GetCatalogueTranslationBrief::run($language)."\nThis text is a short option label of a product variant picker on an online shop (option groups: $axes). Answer with the translated label only.";

        foreach ($missing as $term) {
            $translations[$term] = Translate::run($term, $english, $language, 'catalogue', brief: $brief);
        }

        $variant->update(['option_translations' => $translations]);

        if ($variant->leaderProduct) {
            BreakProductInWebpagesCache::run($variant->leaderProduct);
        }

        return $variant;
    }

    public function asCommand(Command $command): int
    {
        $variants = $command->argument('variant')
            ? Variant::where('slug', $command->argument('variant'))->get()
            : Variant::whereHas('shop', fn ($query) => $query->whereNot('state', ShopStateEnum::CLOSED))->get();

        foreach ($variants as $variant) {
            $this->handle($variant);
            $command->line($variant->slug.': '.json_encode($variant->option_translations, JSON_UNESCAPED_UNICODE));
        }

        return 0;
    }
}
