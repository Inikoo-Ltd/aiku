<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Language;
use App\Models\Helpers\TranslationReview;
use App\Models\Helpers\TranslationTerm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the translator is told before a catalogue text: who reads it, which names never change,
 * how this family's products already read, and what our webmasters corrected before. Without it
 * the model translated "Ancient Witch" as "Bruja Antigua" and named one family's products three ways.
 */
class GetCatalogueTranslationBrief
{
    use AsAction;

    private const int FAMILY_NAMES = 30;

    private const int CORRECTIONS = 25;

    private const int TERMS = 40;

    private const int MAX_CORRECTION_LENGTH = 300;

    public function handle(Language $languageTo, ?ProductCategory $family = null): string
    {
        $language = $languageTo->name;

        $brief = <<<EOT
You translate catalogue texts (product names, descriptions, offers, tags) for the $language web shop of a wholesaler selling gifts, home fragrance, wellbeing and craft products to retailers.
Write as a native $language e-commerce copywriter would for shoppers in that market: natural wording and the product terms local customers really use and search for, never word for word.
Keep HTML tags and attributes exactly as they are and translate only the visible text. Keep product codes, numbers and measurements as written.
These brand and range names are never translated, adapted or transliterated, keep them exactly as written: {$this->keptNames()}.
EOT;

        $familyNames = $family ? $this->familyNames($family) : [];
        if ($familyNames) {
            $brief .= "\n\nOther products in the same family are already published like this; name things the same way so the family reads consistently:\n"
                .implode("\n", array_map(fn (object $row) => "- {$row->english} => {$row->translated}", $familyNames));
        }

        $corrections = $this->corrections($languageTo);
        if ($corrections) {
            $brief .= "\n\nOur native webmasters corrected these earlier machine translations; follow their choices of words:\n"
                .implode("\n", array_map(fn (object $row) => "- English: {$row->source_text}\n  machine: {$row->machine_text}\n  corrected: {$row->corrected_text}", $corrections));
        }

        return $brief;
    }

    /**
     * Only the glossary terms the text really contains, so the brief stays short.
     */
    public function termsFor(Language $language, string $text): string
    {
        $plainText = html_entity_decode(strip_tags($text));
        $mining    = MineTranslationTerms::make();

        $terms = collect(Cache::remember('translation-terms:'.$language->id, now()->addHour(), fn () => TranslationTerm::where('language_id', $language->id)
            ->pluck('target_term', 'source_term')
            ->all()))
            ->filter(fn (string $target, string $source) => $mining->containsTerm($plainText, $source))
            ->sortKeysUsing(fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a))
            ->take(self::TERMS);

        if ($terms->isEmpty()) {
            return '';
        }

        return "\n\nOur native webmasters translate these product terms like this; use the same words, adapting only grammar, unless the text clearly means something else:\n"
            .$terms->map(fn (string $target, string $source) => "- $source => $target")->implode("\n");
    }

    public function keptNames(): string
    {
        return Cache::remember('translation-brief:kept-names', now()->addHour(), fn () => DB::table('brands')
            ->pluck('name')
            ->merge(config('auto-translations.keep_in_english', []))
            ->filter()
            ->unique()
            ->sort()
            ->implode(', '));
    }

    /**
     * Names a webmaster approved come first; machine ones still keep the family consistent.
     *
     * @return array<int, object{english: string, translated: string}>
     */
    public function familyNames(ProductCategory $family): array
    {
        return DB::table('products')
            ->join('master_assets', 'master_assets.id', 'products.master_product_id')
            ->where('products.family_id', $family->id)
            ->whereNull('products.deleted_at')
            ->whereColumn('products.name', '!=', 'master_assets.name')
            ->orderByDesc('products.is_name_reviewed')
            ->orderByDesc('products.id')
            ->limit(self::FAMILY_NAMES)
            ->get(['master_assets.name as english', 'products.name as translated'])
            ->all();
    }

    /**
     * @return array<int, TranslationReview>
     */
    public function corrections(Language $language): array
    {
        return TranslationReview::where('language_id', $language->id)
            ->whereNotNull('corrected_text')
            ->whereColumn('corrected_text', '!=', 'machine_text')
            ->whereRaw('length(source_text) <= ?', [self::MAX_CORRECTION_LENGTH])
            ->whereRaw('length(corrected_text) <= ?', [self::MAX_CORRECTION_LENGTH])
            ->latest('updated_at')
            ->limit(self::CORRECTIONS)
            ->get(['source_text', 'machine_text', 'corrected_text'])
            ->all();
    }
}
