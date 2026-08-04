<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ProposeSearchSynonyms
{
    use AsAction;
    use WithTypesenseApi;

    public string $commandSignature = 'search:propose-synonyms {--days=7}';

    public string $commandDescription = 'Classify recent no-result searches with AI and stage validated synonym suggestions for staff approval';

    public const string SUGGESTIONS_COLLECTION = 'synonym_suggestions';

    /**
     * @return array<string, int> per-language count of new pending suggestions
     */
    public function handle(int $days = 7): array
    {
        $this->ensureSuggestionsCollection();

        $rows = DB::table('website_search_logs')
            ->join('shops', 'shops.id', '=', 'website_search_logs.shop_id')
            ->join('languages', 'languages.id', '=', 'shops.language_id')
            ->where('website_search_logs.created_at', '>=', now()->subDays($days))
            ->where('website_search_logs.keyword_results_count', 0)
            ->where('website_search_logs.scope', 'catalogue')
            ->selectRaw('languages.code as language, lower(trim(website_search_logs.query)) as q, count(*) as sessions')
            ->groupBy('language', 'q')
            ->get();

        $byLanguage = [];
        foreach ($rows as $row) {
            $query = $row->q;
            if (mb_strlen($query) < 3 || str_contains($query, '?') || count(explode(' ', $query)) > 6) {
                continue;
            }
            $byLanguage[$row->language][$query] = ($byLanguage[$row->language][$query] ?? 0) + $row->sessions;
        }

        $validationShops = $this->validationShopByLanguage(array_keys($byLanguage));

        $created = [];
        foreach ($byLanguage as $language => $queries) {
            $known   = array_merge($this->existingSynonymWords($language), $this->existingSuggestionQueries($language));
            $queries = array_diff_key($queries, array_flip($known));
            if (empty($queries) || !isset($validationShops[$language])) {
                continue;
            }

            $created[$language] = 0;
            foreach (array_chunk(array_keys($queries), 100) as $chunk) {
                foreach ($this->classify($language, $chunk) as $item) {
                    if (($item['action'] ?? '') !== 'synonym' || count($item['words'] ?? []) < 2) {
                        continue;
                    }
                    $target = implode(' ', array_slice($item['words'], 1));
                    if (!$this->hasProducts($target, $validationShops[$language])) {
                        continue;
                    }

                    $query = $item['q'];
                    $this->typesenseClient()->post(
                        $this->typesenseUrl().'/collections/'.self::SUGGESTIONS_COLLECTION.'/documents?action=upsert',
                        [
                            'id'         => $language.'-'.(Str::slug($query) ?: substr(md5($query), 0, 12)),
                            'language'   => $language,
                            'query'      => $query,
                            'words'      => array_values($item['words']),
                            'sessions'   => (int)($queries[$query] ?? 0),
                            'status'     => 'pending',
                            'created_at' => now()->timestamp,
                        ]
                    );
                    $created[$language]++;
                }
            }
        }

        return $created;
    }

    public function asCommand(Command $command): int
    {
        $created = $this->handle((int)$command->option('days'));

        foreach ($created as $language => $count) {
            $command->line("[$language] $count new suggestions");
        }
        $command->info('Total: '.array_sum($created).' suggestions staged for approval');

        return 0;
    }

    protected function ensureSuggestionsCollection(): void
    {
        $exists = $this->typesenseClient()
            ->get($this->typesenseUrl().'/collections/'.self::SUGGESTIONS_COLLECTION)
            ->successful();

        if (!$exists) {
            $this->typesenseClient()->post($this->typesenseUrl().'/collections', [
                'name'   => self::SUGGESTIONS_COLLECTION,
                'fields' => [
                    ['name' => 'language', 'type' => 'string'],
                    ['name' => 'query', 'type' => 'string'],
                    ['name' => 'words', 'type' => 'string[]'],
                    ['name' => 'sessions', 'type' => 'int32'],
                    ['name' => 'status', 'type' => 'string'],
                    ['name' => 'created_at', 'type' => 'int64'],
                ],
                'default_sorting_field' => 'sessions',
            ]);
        }
    }

    /**
     * The shop with the largest catalogue per language, used to validate that
     * proposed target words actually return products.
     *
     * @param array<int, string> $languages
     *
     * @return array<string, int>
     */
    protected function validationShopByLanguage(array $languages): array
    {
        if (empty($languages)) {
            return [];
        }

        return DB::table('shops')
            ->join('languages', 'languages.id', '=', 'shops.language_id')
            ->leftJoin('products', 'products.shop_id', '=', 'shops.id')
            ->whereIn('languages.code', $languages)
            ->selectRaw('languages.code as language, shops.id as shop_id, count(products.id) as product_count')
            ->groupBy('languages.code', 'shops.id')
            ->orderByDesc('product_count')
            ->get()
            ->unique('language')
            ->pluck('shop_id', 'language')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function existingSynonymWords(string $language): array
    {
        $items = $this->typesenseClient()
            ->get($this->typesenseUrl().'/synonym_sets/'.StoreSearchSynonym::synonymSet($language).'/items')
            ->json();

        return is_array($items)
            ? collect($items)->pluck('synonyms')->flatten()->filter(fn ($word) => is_string($word))->values()->all()
            : [];
    }

    /**
     * @return array<int, string>
     */
    protected function existingSuggestionQueries(string $language): array
    {
        $hits = $this->typesenseClient()
            ->get($this->typesenseUrl().'/collections/'.self::SUGGESTIONS_COLLECTION.'/documents/search', [
                'q'         => '*',
                'query_by'  => 'query',
                'filter_by' => "language:=$language",
                'per_page'  => 250,
            ])
            ->json('hits', []);

        return collect($hits)->pluck('document.query')->all();
    }

    protected function hasProducts(string $query, int $shopId): bool
    {
        $found = $this->typesenseClient()
            ->get($this->typesenseUrl().'/collections/products/documents/search', [
                'q'         => $query,
                'query_by'  => 'code,name,barcode,description,description_extra',
                'filter_by' => "shop_id:=$shopId && is_in_website:=true",
                'per_page'  => 1,
            ])
            ->json('found', 0);

        return $found > 0;
    }

    /**
     * @param array<int, string> $queries
     *
     * @return array<int, array{q: string, action: string, words?: array<int, string>}>
     */
    protected function classify(string $language, array $queries): array
    {
        $prompt = 'Ancient Wisdom group runs giftware wholesale webshops: aromatherapy (essential oils, incense, candles, wax melts, bath bombs, soap), '
            .'crystals/gemstones, home fragrance, spiritual items (tarot, pendulums, smudge, buddha), bags, wooden carvings, jewellery displays.'
            ."\n\nThese search queries (language: $language) returned ZERO results on the shop. For each, classify:\n"
            .'- "synonym": customer used different words/misspelling for something the shop DOES likely sell -> give 2-4 words in the SAME language mapping it to likely catalogue vocabulary (original query must be first word)'."\n"
            .'- "gap": a real product/brand the shop does NOT sell'."\n"
            .'- "junk": SKU codes, barcodes, fragments, nonsense'."\n\n"
            .'Return ONLY a JSON array: [{"q":"...","action":"synonym|gap|junk","words":["..."]}]'."\n\n"
            .'Queries: '.json_encode($queries, JSON_UNESCAPED_UNICODE);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $content = Http::withToken(config('services.openai.api_key'))
                    ->timeout(300)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'            => 'gpt-5-nano',
                        'reasoning_effort' => 'low',
                        'messages'         => [['role' => 'user', 'content' => $prompt]],
                    ])
                    ->json('choices.0.message.content') ?? '';

                $start = strpos($content, '[');
                $end   = strrpos($content, ']');
                if ($start === false || $end === false) {
                    continue;
                }

                $items = json_decode(substr($content, $start, $end - $start + 1), true);
                if (is_array($items)) {
                    return $items;
                }
            } catch (Throwable) {
                sleep(3);
            }
        }

        return [];
    }
}
