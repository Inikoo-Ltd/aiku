<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use Illuminate\Console\Command;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Translates real catalogue and chat texts with several OpenRouter models through the same
 * ChatGPT5Driver production uses, has stronger models score every result blind, and projects
 * each model's monthly cost from the last 30 days of translated volume.
 */
class EvaluateTranslationModels
{
    use AsAction;
    use WithAIGateway;

    public const array DEFAULT_MODELS = [
        'openai/gpt-5-nano',
        'openai/gpt-4o-mini',
        'google/gemini-2.5-flash-lite',
        'google/gemini-3.1-flash-lite',
        'deepseek/deepseek-v3.2',
        'mistralai/mistral-medium-3.1',
        'anthropic/claude-haiku-4.5',
        'anthropic/claude-sonnet-5.5',
    ];

    public const array DEFAULT_JUDGES = [
        'anthropic/claude-opus-5.5',
        'google/gemini-3.1-pro-preview',
    ];

    private const int PROCESSES_PER_MODEL = 4;

    private const int PROCESS_TIMEOUT_SECONDS = 7200;

    private const int RATE_LIMIT_RETRIES = 30;

    private const int RATE_LIMIT_WAIT_MS = 15000;

    public function getCommandSignature(): string
    {
        return 'translations:evaluate-models
            {--models=* : OpenRouter model ids to compare; append +brief to send catalogue texts with the catalogue brief, +terms for the brief plus the mined glossary}
            {--judges=* : OpenRouter model ids that score the translations}
            {--no-judges : Only translate; score the saved JSON some other way}
            {--languages=* : Target language codes for catalogue texts, default every open shop language but English}
            {--per-language=3 : Catalogue texts per content type and target language}
            {--chat=0 : Real customer chat messages to include; they are sent to every model and saved in the report}';
    }

    /**
     * @return array{samples: array<int, array{type: string, bucket: string, from: string, to: string, text: string}>, results: array<string, array<int, array{translation: ?string, cost: float, seconds: float, error: ?string}>>, scores: array<string, array<int, array<string, array{score: int, issue: string}>>>, judge_cost: float, monthly_chars: array{catalogue: int, chat: int}}
     */
    public function handle(array $models, array $judges, array $languageCodes, int $perLanguage, int $chatSamples): array
    {
        $samples = [...$this->catalogueSamples($languageCodes, $perLanguage), ...$this->chatSamples($chatSamples)];
        $chunks  = array_chunk(array_keys($samples), (int) max(1, ceil(count($samples) / self::PROCESSES_PER_MODEL)));

        $payloadKey = 'translation-evaluation:'.Str::uuid();
        Cache::put($payloadKey, ['samples' => $samples], now()->addDay());

        $translations = [];
        foreach ($models as $model) {
            foreach ($chunks as $chunk => $indexes) {
                $translations["$chunk|$model"] = fn () => $this->translateWith($model, $payloadKey, $indexes);
            }
        }

        $results = [];
        foreach (Concurrency::run($translations, self::PROCESS_TIMEOUT_SECONDS) as $key => $chunkResults) {
            $model           = explode('|', $key, 2)[1];
            $results[$model] = ($results[$model] ?? []) + $chunkResults;
        }

        Cache::put($payloadKey, ['samples' => $samples, 'results' => $results], now()->addDay());

        $judgements = [];
        foreach ($judges as $judge) {
            foreach ($chunks as $chunk => $indexes) {
                $judgements["$chunk|$judge"] = fn () => $this->judgeWith($judge, $payloadKey, $indexes);
            }
        }

        $scores    = [];
        $judgeCost = 0.0;
        foreach ($judgements ? Concurrency::run($judgements, self::PROCESS_TIMEOUT_SECONDS) : [] as $key => $chunkResult) {
            $judge     = explode('|', $key, 2)[1];
            $judgeCost += $chunkResult['cost'];
            foreach ($chunkResult['scores'] as $index => $byModel) {
                $scores[$judge][$index] = $byModel;
            }
        }

        Cache::forget($payloadKey);

        return [
            'samples'       => $samples,
            'results'       => $results,
            'scores'        => $scores,
            'judge_cost'    => $judgeCost,
            'monthly_chars' => $this->monthlySourceCharacters(),
        ];
    }

    /**
     * English source texts assigned round robin to the target languages, as shops receive them
     * from the English masters.
     */
    public function catalogueSamples(array $languageCodes, int $perLanguage): array
    {
        $count = $perLanguage * count($languageCodes);

        $englishProducts = fn () => DB::table('products')
            ->join('shops', 'shops.id', 'products.shop_id')
            ->join('languages', 'languages.id', 'shops.language_id')
            ->where('languages.code', 'en')
            ->where('shops.state', ShopStateEnum::OPEN)
            ->where('products.state', ProductStateEnum::ACTIVE)
            ->inRandomOrder()
            ->limit($count);

        $sources = [
            'product_name'        => $englishProducts()->whereRaw('length(products.name) > 10')->pluck('products.name'),
            'brand_product_name'  => $englishProducts()
                ->whereExists(fn ($query) => $query->from('brands')->whereRaw("products.name ilike '%' || brands.name || '%'"))
                ->pluck('products.name'),
            'product_description' => $englishProducts()->whereRaw('length(products.description) > 80')->pluck('products.description'),
            'family_description'  => DB::table('product_categories')
                ->join('shops', 'shops.id', 'product_categories.shop_id')
                ->join('languages', 'languages.id', 'shops.language_id')
                ->where('languages.code', 'en')
                ->where('shops.state', ShopStateEnum::OPEN)
                ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
                ->whereRaw('length(product_categories.description) > 80')
                ->inRandomOrder()
                ->limit($count)
                ->pluck('product_categories.description'),
        ];

        $samples = [];
        foreach ($sources as $type => $texts) {
            foreach ($texts->values() as $position => $text) {
                $samples[] = [
                    'type'   => $type,
                    'bucket' => 'catalogue',
                    'from'   => 'en',
                    'to'     => $languageCodes[$position % count($languageCodes)],
                    'text'   => $text,
                ];
            }
        }

        return $samples;
    }

    /**
     * Messages that really were translated, in the direction they were translated.
     */
    public function chatSamples(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        return DB::table('chat_message_translations')
            ->join('chat_messages', 'chat_messages.id', 'chat_message_translations.chat_message_id')
            ->leftJoin('languages as source', 'source.id', 'chat_messages.original_language_id')
            ->join('languages as target', 'target.id', 'chat_message_translations.target_language_id')
            ->where('chat_message_translations.created_at', '>', now()->subDays(90))
            ->whereRaw("length(coalesce(chat_messages.original_text, chat_messages.message_text, '')) between 5 and 3000")
            ->whereRaw("coalesce(source.code, 'en') <> target.code")
            ->inRandomOrder()
            ->limit($limit)
            ->get([
                DB::raw("coalesce(chat_messages.original_text, chat_messages.message_text) as text"),
                DB::raw("coalesce(source.code, 'en') as from_code"),
                'target.code as to_code',
            ])
            ->map(fn (object $row) => [
                'type'   => 'chat',
                'bucket' => 'chat',
                'from'   => $row->from_code,
                'to'     => $row->to_code,
                'text'   => $row->text,
            ])
            ->all();
    }

    /**
     * Runs in its own process, which reads the samples from the cache: passed inline they
     * would travel in an environment variable and outgrow the OS limit.
     */
    public function translateWith(string $model, string $payloadKey, array $indexes): array
    {
        $samples   = Arr::only(Cache::get($payloadKey)['samples'], $indexes);
        $withTerms = str_ends_with($model, '+terms');
        $withBrief = $withTerms || str_ends_with($model, '+brief');
        $modelId   = Str::before($model, '+');

        $driver = new class ([
            'model'        => $modelId,
            'max_tokens'   => 16384,
            'http_timeout' => 300,
            'temperature'     => str_starts_with($modelId, 'openai/gpt-5') ? 1 : 0.2,
            'fallback_models' => [],
        ]) extends ChatGPT5Driver {
            public function translateOne(string $text, string $from, string $to): ?string
            {
                return Arr::get($this->sendTranslationRequest(['text' => $text], $from, $to), 'text');
            }
        };

        $results = [];
        foreach ($samples as $index => $sample) {
            $driver->lastUsage = null;
            $started           = microtime(true);

            app()->forgetInstance(ChatGPT5Driver::BRIEF);
            if ($withBrief && $sample['bucket'] === 'catalogue') {
                $language = Language::where('code', $sample['to'])->firstOrFail();
                app()->instance(ChatGPT5Driver::BRIEF, GetCatalogueTranslationBrief::run($language).($withTerms ? GetCatalogueTranslationBrief::make()->termsFor($language, $sample['text']) : ''));
            }

            try {
                $translation = retry(self::RATE_LIMIT_RETRIES, fn () => $driver->translateOne($sample['text'], $sample['from'], $sample['to']), self::RATE_LIMIT_WAIT_MS, $this->isRateLimited(...));
                $error       = $translation === null ? 'empty translation' : null;
            } catch (Throwable $exception) {
                $translation = null;
                $error       = $exception->getMessage();
            }

            $results[$index] = [
                'translation' => $translation,
                'cost'        => (float) $this->aiUsageCost($driver->lastUsage ?? []),
                'seconds'     => round(microtime(true) - $started, 2),
                'error'       => $error,
            ];
        }

        return $results;
    }

    /**
     * One call per text scores every model's version side by side, under shuffled letters so
     * the judge can not favour a model by name.
     *
     * @return array{scores: array<int, array<string, array{score: int, issue: string}>>, cost: float}
     */
    public function judgeWith(string $judge, string $payloadKey, array $indexes): array
    {
        ['samples' => $samples, 'results' => $results] = Cache::get($payloadKey);

        $scores = [];
        $cost   = 0.0;

        foreach ($indexes as $index) {
            $sample     = $samples[$index];
            $translated = array_filter(array_keys($results), fn (string $model) => $results[$model][$index]['translation'] !== null);
            shuffle($translated);

            $labels     = array_slice(range('A', 'Z'), 0, count($translated));
            $candidates = array_combine($labels, array_map(fn (string $model) => $results[$model][$index]['translation'], $translated));

            foreach (array_keys($results) as $model) {
                $scores[$index][$model] = ['score' => 0, 'issue' => 'failed: '.$results[$model][$index]['error']];
            }

            if (!$candidates) {
                continue;
            }

            try {
                $response = retry(self::RATE_LIMIT_RETRIES, fn () => $this->aiRequest()->timeout(300)->post('chat/completions', [
                    'model'           => $judge,
                    'temperature'     => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->judgePrompt()],
                        ['role' => 'user', 'content' => json_encode([
                            'content_type'    => $sample['type'],
                            'source_language' => $sample['from'],
                            'target_language' => $sample['to'],
                            'source'          => $sample['text'],
                            'candidates'      => $candidates,
                        ], JSON_UNESCAPED_UNICODE)],
                    ],
                ])->throw()->json(), self::RATE_LIMIT_WAIT_MS, $this->isRateLimited(...));

                $cost    += (float) Arr::get($response, 'usage.cost', 0);
                $content  = trim(preg_replace('/^```(?:json)?|```$/m', '', (string) Arr::get($response, 'choices.0.message.content', '')));
                $verdicts = json_decode($content, true) ?: [];

                foreach ($labels as $position => $label) {
                    $scores[$index][$translated[$position]] = [
                        'score' => is_numeric(Arr::get($verdicts, "$label.score")) ? (int) Arr::get($verdicts, "$label.score") : null,
                        'issue' => (string) Arr::get($verdicts, "$label.issue", 'no verdict'),
                    ];
                }
            } catch (Throwable $exception) {
                foreach ($translated as $model) {
                    $scores[$index][$model] = ['score' => null, 'issue' => 'judge failed: '.$exception->getMessage()];
                }
            }
        }

        return ['scores' => $scores, 'cost' => $cost];
    }

    /**
     * New OpenRouter accounts get 20 requests a minute per Anthropic model, and some providers
     * throttle upstream.
     */
    public function isRateLimited(Throwable $exception): bool
    {
        return (bool) preg_match('/rate.?limit|429/i', $exception->getMessage());
    }

    public function judgePrompt(): string
    {
        return <<<'EOL'
You are a senior professional translator reviewing machine translations for an online wholesale and retail gift company. Catalogue texts (product names, product and family descriptions, may contain HTML) are published on web shops; chat texts are customer service conversations.

Score every candidate from 0 to 100 for how ready it is to use without editing, as a native speaker of the target language would judge it:
- meaning is accurate, nothing added or left out
- natural, fluent wording and the right register for a shop or a customer service reply
- correct product terminology, units, numbers and names
- brand and product range names (Ancient Witch, Ancient Wisdom, Agnes + Cat, Greenman Rituals...) stay in English; translating them is an error
- the product terms local shoppers actually use, not a word-for-word rendering of the English
- HTML tags, placeholders, emojis and line structure preserved
90-100 publishable as is, 70-89 small fixes, 40-69 real errors, below 40 wrong or unusable.

Judge each candidate on its own merits; several may deserve the same score.
Reply with a JSON object only, keyed by candidate letter: {"A": {"score": 85, "issue": "worst problem in a few words, empty if none"}, ...}
EOL;
    }

    /**
     * Source characters translated over the last 30 days. Catalogue counts every name and
     * description written in a non English shop, so staff edits made in the shop language are
     * included too and it errs high.
     *
     * @return array{catalogue: int, chat: int}
     */
    public function monthlySourceCharacters(): array
    {
        $since = now()->subDays(30);

        $catalogue = DB::table('audits')
            ->join('shops', 'shops.id', 'audits.shop_id')
            ->join('languages', 'languages.id', 'shops.language_id')
            ->where('audits.created_at', '>', $since)
            ->whereIn('audits.auditable_type', ['Product', 'ProductCategory'])
            ->where('languages.code', '<>', 'en')
            ->sum(DB::raw("coalesce(length(audits.new_values->>'name'), 0) + coalesce(length(audits.new_values->>'description'), 0) + coalesce(length(audits.new_values->>'description_title'), 0) + coalesce(length(audits.new_values->>'description_extra'), 0)"));

        $chat = DB::table('chat_message_translations')->where('created_at', '>', $since)->sum(DB::raw('length(translated_text)'))
            + DB::table('meta_chat_message_translations')->where('created_at', '>', $since)->sum(DB::raw('length(translated_text)'))
            + DB::table('staff_message_translations')->where('created_at', '>', $since)->sum(DB::raw('length(body)'));

        return ['catalogue' => (int) $catalogue, 'chat' => (int) $chat];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        if (!$this->usesOpenRouter()) {
            $command->error('OPENROUTER_API_KEY is not set, the candidate models are only reachable through OpenRouter.');

            return 1;
        }

        $models        = $command->option('models') ?: self::DEFAULT_MODELS;
        $judges        = $command->option('no-judges') ? [] : ($command->option('judges') ?: self::DEFAULT_JUDGES);
        $languageCodes = $command->option('languages') ?: DB::table('shops')
            ->join('languages', 'languages.id', 'shops.language_id')
            ->where('shops.state', ShopStateEnum::OPEN)
            ->where('languages.code', '<>', 'en')
            ->distinct()
            ->orderBy('languages.code')
            ->pluck('languages.code')
            ->all();

        $command->info(sprintf('Translating with %d models into %s, judged by %s ...', count($models), implode(', ', $languageCodes), implode(', ', $judges) ?: 'nobody'));

        $report = $this->handle($models, $judges, $languageCodes, (int) $command->option('per-language'), (int) $command->option('chat'));

        $this->printReport($command, $report, $models, $judges, $languageCodes);

        return 0;
    }

    public function printReport(Command $command, array $report, array $models, array $judges, array $languageCodes): void
    {
        $samples = $report['samples'];
        $monthly = $report['monthly_chars'];

        $scoreOf = function (string $model, int $index) use ($report, $judges): ?float {
            $judged = array_filter(array_map(fn (string $judge) => $report['scores'][$judge][$index][$model]['score'] ?? null, $judges), fn ($score) => $score !== null);

            return $judged ? array_sum($judged) / count($judged) : null;
        };

        $average = fn (array $values): ?float => ($values = array_filter($values, fn ($value) => $value !== null)) ? round(array_sum($values) / count($values), 1) : null;

        $costPerChar = function (string $model, string $bucket) use ($report, $samples): float {
            $indexes = array_keys(array_filter($samples, fn (array $sample) => $sample['bucket'] === $bucket));
            $chars   = array_sum(array_map(fn (int $index) => mb_strlen($samples[$index]['text']), $indexes));
            $cost    = array_sum(array_map(fn (int $index) => $report['results'][$model][$index]['cost'], $indexes));

            return $chars ? $cost / $chars : 0.0;
        };

        $summary = [];
        foreach ($models as $model) {
            $results      = $report['results'][$model];
            $catalogueUsd = $costPerChar($model, 'catalogue') * $monthly['catalogue'];
            $chatUsd      = $costPerChar($model, 'chat') * $monthly['chat'];

            $summary[] = [
                $model,
                $average(array_map(fn (int $index) => $scoreOf($model, $index), array_keys($samples))),
                ...array_map(fn (string $judge) => $average(array_map(fn (int $index) => $report['scores'][$judge][$index][$model]['score'] ?? null, array_keys($samples))), $judges),
                count(array_filter($results, fn (array $result) => $result['error'] !== null)),
                $average(array_column($results, 'seconds')),
                '$'.number_format($catalogueUsd, 2),
                '$'.number_format($chatUsd, 2),
                '$'.number_format($catalogueUsd + $chatUsd, 2),
            ];
        }
        usort($summary, fn (array $a, array $b) => $b[1] <=> $a[1]);

        $command->newLine();
        $command->info(sprintf('Monthly volume (last 30 days): catalogue %s source characters, chat %s', number_format($monthly['catalogue']), number_format($monthly['chat'])));
        $command->table(
            ['Model', 'Score', ...array_map(fn (string $judge) => 'by '.class_basename(str_replace('/', '\\', $judge)), $judges), 'Failed', 'Sec', 'Catalogue $/mo', 'Chat $/mo', 'Total $/mo'],
            $summary
        );

        $breakdown = function (string $title, array $groups, string $field) use ($command, $models, $samples, $scoreOf, $average): void {
            $command->info($title);
            $command->table(
                ['Model', ...$groups],
                array_map(fn (string $model) => [
                    $model,
                    ...array_map(
                        fn (string $group) => $average(array_map(fn (int $index) => $scoreOf($model, $index), array_keys(array_filter($samples, fn (array $sample) => $sample[$field] === $group)))),
                        $groups
                    ),
                ], $models)
            );
        };

        $breakdown('Score by target language', array_values(array_unique([...$languageCodes, ...array_column($samples, 'to')])), 'to');
        $breakdown('Score by content type', array_values(array_unique(array_column($samples, 'type'))), 'type');

        $path = 'translation-evaluations/'.now()->format('Y-m-d_His').'.csv';
        $rows = [['type', 'from', 'to', 'source', 'model', 'translation', 'score', ...array_map(fn (string $judge) => $judge.' issue', $judges), 'cost', 'seconds']];
        foreach ($samples as $index => $sample) {
            foreach ($models as $model) {
                $result = $report['results'][$model][$index];
                $rows[] = [
                    $sample['type'], $sample['from'], $sample['to'], $sample['text'], $model,
                    $result['translation'] ?? $result['error'],
                    $scoreOf($model, $index),
                    ...array_map(fn (string $judge) => $report['scores'][$judge][$index][$model]['issue'] ?? '', $judges),
                    $result['cost'], $result['seconds'],
                ];
            }
        }
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row, escape: "");
        }
        rewind($stream);
        Storage::disk('local')->put($path, stream_get_contents($stream));
        Storage::disk('local')->put(str_replace('.csv', '.json', $path), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $translationCost = array_sum(array_map(fn (array $results) => array_sum(array_column($results, 'cost')), $report['results']));
        $command->info(sprintf('Every translation with its scores: %s (and .json)', Storage::disk('local')->path($path)));
        $command->info(sprintf('This evaluation cost $%.2f (translations $%.2f, judges $%.2f)', $translationCost + $report['judge_cost'], $translationCost, $report['judge_cost']));
    }
}
