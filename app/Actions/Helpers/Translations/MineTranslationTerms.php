<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 01:25:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Helpers\Language;
use App\Models\Helpers\TranslationReview;
use App\Models\Helpers\TranslationTerm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Builds the per-language glossary of product terms from what our webmasters actually wrote: the
 * product names they reviewed and the machine translations they corrected. The model only reads
 * those examples and names the word they use; a term is kept only when that word really appears
 * in most of the examples, so a guess the webmasters never used can not reach the glossary.
 */
class MineTranslationTerms
{
    use AsAction;
    use WithAIGateway;

    public string $commandSignature = 'translations:mine-terms
        {--languages=* : Language codes, default every open shop language but English}
        {--terms=400 : Most frequent English product terms to look up}
        {--examples=6 : Webmaster-written examples shown per term}';

    private const int TERMS_PER_CALL = 40;

    private const int MIN_SUPPORT = 2;

    private const array STOPWORDS = [
        'a', 'an', 'and', 'the', 'of', 'for', 'with', 'in', 'on', 'to', 'by', 'or', 'from', 'set', 'pack', 'x', 'cm', 'mm', 'ml', 'g', 'kg', 'pcs', 'assorted', 'mixed', 'new', 'per', 'box', 'default', 'lrg', 'sml', 'med', 'asst', 'xl', 'xxl',
    ];

    /**
     * @param array<int, string> $englishTerms
     */
    public function handle(Language $language, array $englishTerms, int $examplesPerTerm): int
    {
        $pairs = $this->webmasterPairs($language);
        $saved = 0;

        foreach (array_chunk($englishTerms, self::TERMS_PER_CALL) as $batch) {
            $examples = [];
            foreach ($batch as $term) {
                $matching = array_slice(array_filter($pairs, fn (array $pair) => $this->containsTerm($pair[0], $term)), 0, $examplesPerTerm);
                if (count($matching) >= self::MIN_SUPPORT) {
                    $examples[$term] = array_values($matching);
                }
            }

            if (!$examples) {
                continue;
            }

            foreach ($this->askTerms($language, $examples) as $term => $translation) {
                if (!isset($examples[$term]) || !is_string($translation) || blank($translation)) {
                    continue;
                }

                $support = count(array_filter($examples[$term], fn (array $pair) => $this->usesTranslation($pair[1], $translation)));
                if ($support < self::MIN_SUPPORT || $support * 2 < count($examples[$term])) {
                    continue;
                }

                TranslationTerm::updateOrCreate(
                    ['language_id' => $language->id, 'source_term' => $term],
                    ['target_term' => trim($translation), 'support' => $support, 'examples' => count($examples[$term])]
                );
                $saved++;
            }
        }

        Cache::forget('translation-terms:'.$language->id);

        return $saved;
    }

    /**
     * Corrections first: they are the webmasters fixing exactly what the machine got wrong.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function webmasterPairs(Language $language): array
    {
        $corrections = TranslationReview::where('language_id', $language->id)
            ->whereNotNull('corrected_text')
            ->where('field', 'name')
            ->latest('updated_at')
            ->limit(5000)
            ->get(['source_text', 'corrected_text'])
            ->map(fn (TranslationReview $review) => [$review->source_text, $review->corrected_text]);

        $reviewedNames = DB::table('products')
            ->join('shops', 'shops.id', 'products.shop_id')
            ->join('master_assets', 'master_assets.id', 'products.master_product_id')
            ->where('shops.language_id', $language->id)
            ->where('products.is_name_reviewed', true)
            ->whereNull('products.deleted_at')
            ->whereColumn('products.name', '!=', 'master_assets.name')
            ->orderByDesc('products.id')
            ->limit(30000)
            ->get(['master_assets.name as english', 'products.name as translated'])
            ->map(fn (object $row) => [$row->english, $row->translated]);

        return $corrections->concat($reviewedNames)->all();
    }

    /**
     * @return array<int, string>
     */
    public function frequentEnglishTerms(int $limit): array
    {
        $brandWords = collect(explode(', ', GetCatalogueTranslationBrief::make()->keptNames()))
            ->flatMap(fn (string $name) => $this->words($name))
            ->flip();

        $counts = [];
        DB::table('master_assets')->whereNull('deleted_at')->whereNotNull('name')->orderBy('id')
            ->chunk(5000, function ($rows) use (&$counts, $brandWords) {
                foreach ($rows as $row) {
                    $words = array_values(array_filter($this->words($row->name), fn (string $word) => !$brandWords->has($word)));
                    foreach ($words as $position => $word) {
                        if (!in_array($word, self::STOPWORDS) && !preg_match('/\d/', $word) && mb_strlen($word) > 2) {
                            $counts[$word] = ($counts[$word] ?? 0) + 1;
                        }
                        $next = $words[$position + 1] ?? null;
                        if ($next && !in_array($word, self::STOPWORDS) && !in_array($next, self::STOPWORDS) && !preg_match('/\d/', $word.$next)) {
                            $counts["$word $next"] = ($counts["$word $next"] ?? 0) + 1;
                        }
                    }
                }
            });

        arsort($counts);

        return array_map('strval', array_slice(array_keys(array_filter($counts, fn (int $count, string $term) => $count >= (str_contains($term, ' ') ? 10 : 20), ARRAY_FILTER_USE_BOTH)), 0, $limit));
    }

    /**
     * @return array<int, string>
     */
    private function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public function containsTerm(string $text, string $term): bool
    {
        return mb_stripos($text, $term) !== false && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/iu', $text);
    }

    /**
     * Word stems rather than exact words, so an inflected "kužeľov" still counts for "kužele".
     */
    public function usesTranslation(string $text, string $translation): bool
    {
        $words = $this->words($text);

        foreach ($this->words($translation) as $wanted) {
            $stem = mb_substr($wanted, 0, max(4, mb_strlen($wanted) - 2));
            if (!array_filter($words, fn (string $word) => str_starts_with($word, $stem))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, array<int, array{0: string, 1: string}>> $examples
     *
     * @return array<string, string|null>
     */
    private function askTerms(Language $language, array $examples): array
    {
        $prompt = "These English product names were translated into {$language->name} by native webmasters of a gift wholesaler's web shops.\n"
            ."For each English term, give the word or phrase in {$language->name} these webmasters use for it, in its base dictionary form, exactly as it appears (or its lemma) in their translations.\n"
            ."Use null when they do not use one consistent word, or when the term is a brand, a name or not translated.\n"
            .'Reply with a JSON object only: {"<english term>": "<translation>" | null, ...}'."\n\n"
            .json_encode(array_map(fn (array $pairs) => array_map(fn (array $pair) => ['en' => $pair[0], $language->code => $pair[1]], $pairs), $examples), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $content = (string) $this->aiRequest()
                    ->timeout(300)
                    ->post('chat/completions', [
                        'model'           => $this->aiModel(config('auto-translations.terms_model')),
                        'temperature'     => 0,
                        'response_format' => ['type' => 'json_object'],
                        'messages'        => [['role' => 'user', 'content' => $prompt]],
                    ])
                    ->throw()
                    ->json('choices.0.message.content');

                $start = strpos($content, '{');
                $terms = $start === false ? null : json_decode(substr($content, $start, strrpos($content, '}') - $start + 1), true);
                if (is_array($terms)) {
                    return $terms;
                }
            } catch (Throwable) {
                sleep(15);
            }
        }

        return [];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $languageCodes = $command->option('languages') ?: DB::table('shops')
            ->join('languages', 'languages.id', 'shops.language_id')
            ->where('shops.state', ShopStateEnum::OPEN)
            ->where('languages.code', '!=', 'en')
            ->distinct()
            ->pluck('languages.code')
            ->all();

        $terms = $this->frequentEnglishTerms((int) $command->option('terms'));
        $command->info(count($terms).' English terms, e.g. '.implode(', ', array_slice($terms, 0, 15)));

        foreach (Language::whereIn('code', $languageCodes)->get() as $language) {
            $command->info($language->code.': '.$this->handle($language, $terms, (int) $command->option('examples')).' terms saved');
        }

        return 0;
    }
}
