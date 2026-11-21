<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Actions\Helpers\Translations\GetCatalogueTranslationBrief;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Fills the name and descriptions of every active product of a shop in one more language, on the
 * product only, never on its master. A product whose code and unit content (trade units and
 * units) match a product of a shop written in that language gets that shop's copy as it is,
 * without the AI; every other text is machine translated from the shop's own. How each text was made is kept in data.translation_sources.<locale>, so machine text can be
 * found and redone with --force, and German a person wrote (no marker) is never overwritten. A product
 * whose translation is rejected keeps its copies, is marked failed and is left alone until --retry-failed.
 */
class FillShopProductTranslations
{
    use AsAction;
    use WithAIGateway;

    public const array FIELDS = ['name', 'description_title', 'description', 'description_extra'];

    public const string COPIED = 'copied';

    public const string MACHINE = 'machine';

    private const array INLINE_TAGS = ['strong', 'b', 'em', 'i', 'u', 'span'];

    private const array VOID_TAGS = ['br', 'img', 'hr', 'wbr'];

    public string $commandSignature = 'products:fill-translations
        {shop : shop id or slug}
        {language : language code, e.g. de}
        {--source-shop= : shop id or slug to copy from (default: the open shop of the same organisation written in that language)}
        {--limit= : stop after this many products}
        {--chunk=50 : products per batch}
        {--apply : write the texts (without it nothing is saved, but texts to translate still go to the AI and are paid for)}
        {--force : redo products whose texts this command already made}
        {--retry-failed : only the products whose translation was rejected in an earlier run}
        {--model= : OpenRouter model (default: the catalogue translation model)}';

    private array $stats = [];

    private float $cost = 0;

    private array $briefs = [];

    private ?string $lastFailure = null;

    private ?Shop $sourceShop = null;

    /**
     * @return array{stats: array<string, int>, cost: float}
     */
    public function handle(Shop $shop, Language $language, ?Shop $sourceShop = null, bool $apply = false, bool $force = false, ?int $limit = null, int $chunk = 50, ?string $model = null, ?callable $report = null, bool $retryFailed = false): array
    {
        $this->stats = ['copied' => 0, 'machine' => 0, 'mixed' => 0, 'nothing_to_translate' => 0, 'failed' => 0];
        $this->cost  = 0;
        $this->sourceShop = $sourceShop;
        $model ??= config('auto-translations.drivers.catalogue.model');
        $locale    = $language->code;
        $processed = 0;

        $query = Product::where('shop_id', $shop->id)
            ->where('state', ProductStateEnum::ACTIVE)
            ->whereNull('deleted_at');

        if ($retryFailed) {
            $query->whereRaw("data->'translation_sources'->?->>'failed' is not null", [$locale]);
        } elseif ($force) {
            $query->where(fn ($query) => $query->whereRaw('NOT (coalesce(name_i8n, \'{}\') ?? ?)', [$locale])->orWhereRaw("data->'translation_sources' ?? ?", [$locale]));
        } else {
            $query->whereRaw('NOT (coalesce(name_i8n, \'{}\') ?? ?)', [$locale])->whereRaw("NOT (coalesce(data->'translation_sources', '{}') ?? ?)", [$locale]);
        }

        $query->chunkById($chunk, function (Collection $products) use ($sourceShop, $language, $locale, $apply, $model, $limit, $report, &$processed) {
            $siblings     = $sourceShop ? $this->siblings($products, $sourceShop) : collect();
            $this->briefs = [];

            foreach ($products as $product) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }
                $processed++;
                $this->fillProduct($product, $siblings->get(strtolower($product->code)), $language, $locale, $apply, $model, $report);
            }

            return $limit === null || $processed < $limit;
        });

        return ['stats' => $this->stats, 'cost' => round($this->cost, 4)];
    }

    private function fillProduct(Product $product, ?Product $sibling, Language $language, string $locale, bool $apply, string $model, ?callable $report): void
    {
        $fields      = [];
        $copies      = [];
        $sources     = [];
        foreach (self::FIELDS as $field) {
            $copy  = $sibling ? trim((string) $sibling->{$field}) : '';
            $shopText = trim((string) $product->{$field});
            if (!$this->isOurs($product, $field, $locale)) {
                continue;
            }
            if ($copy !== '' && $copy !== $shopText) {
                $copies[$field]  = $copy;
                $sources[$field] = self::COPIED;
            } elseif ($shopText !== '') {
                $fields[$field]  = ['translate' => $shopText];
                $sources[$field] = self::MACHINE;
            }
        }

        if (!$sources) {
            $this->stats['nothing_to_translate']++;

            return;
        }

        $translated = $fields ? $this->askWithRetry($fields, $product, $language, $model) : [];
        $failure    = $translated === null ? $this->lastFailure : null;
        $texts      = $copies + ($translated ?? []);
        $fields    += array_map(fn (string $copy) => ['copy' => $copy], $copies);

        if ($failure !== null) {
            $this->stats['failed']++;
            $report && $report($product, $fields, null, $sources, $failure);
        } else {
            $kinds = array_unique(array_values($sources));
            $this->stats[count($kinds) > 1 ? 'mixed' : $kinds[0]]++;
            $report && $report($product, $fields, $texts, $sources);
        }

        if (!$apply) {
            return;
        }

        $product = $product->fresh();
        $texts   = array_filter($texts, fn (string $field) => $this->isOurs($product, $field, $locale), ARRAY_FILTER_USE_KEY);
        foreach ($texts as $field => $text) {
            $product->setTranslation($field.'_i8n', $locale, $text);
        }
        $data = $product->data ?? [];
        data_set($data, "translation_sources.$locale", array_filter(['failed' => $failure]) + [
            'fields'          => Arr::only($sources, array_keys($texts)) + Arr::get($data, "translation_sources.$locale.fields", []),
            'hashes'          => array_map('md5', $texts) + Arr::get($data, "translation_sources.$locale.hashes", []),
            'from_product_id' => in_array(self::COPIED, $sources) ? $sibling->id : null,
            'model'           => in_array(self::MACHINE, Arr::only($sources, array_keys($texts))) ? $model : null,
            'at'              => now()->toIso8601String(),
        ]);
        $product->data = $data;
        $product->save();
    }

    /**
     * A field is ours to (re)write while it is empty or still holds exactly the text this command
     * wrote: German a person wrote, before or after a run, is never overwritten, even with --force.
     */
    private function isOurs(Product $product, string $field, string $locale): bool
    {
        $current = $product->getTranslation($field.'_i8n', $locale, false);

        return blank($current) || Arr::get($product->data, "translation_sources.$locale.hashes.$field") === md5($current);
    }

    /**
     * @return array<string, string>|null
     */
    private function askWithRetry(array $fields, Product $product, Language $language, string $model): ?array
    {
        foreach ([0, 1] as $attempt) {
            try {
                $texts             = $this->ask($product, $fields, $language, $model);
                $this->lastFailure = $this->invalidReason($fields, $texts);
                if ($this->lastFailure === null) {
                    return Arr::only($texts, array_keys($fields));
                }
            } catch (RequestException $exception) {
                if (in_array($exception->response->status(), [401, 402, 403])) {
                    throw $exception;
                }
                $this->lastFailure = $exception->getMessage();
            } catch (Throwable $exception) {
                $this->lastFailure = $exception->getMessage();
            }
        }

        return null;
    }

    private function ask(Product $product, array $fields, Language $language, string $model): array
    {
        $response = $this->aiRequest()->timeout(120)->post('chat/completions', array_merge([
            'model'           => $model,
            'temperature'     => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $this->prompt($product, $language)],
                ['role' => 'user', 'content' => json_encode($fields, JSON_UNESCAPED_UNICODE)],
            ],
        ], config('services.openrouter.model_options')[$model] ?? []))->throw()->json();

        $this->cost += (float) $this->aiUsageCost((array) Arr::get($response, 'usage', []));
        $content     = trim(preg_replace('/^```(?:json)?|```$/m', '', (string) Arr::get($response, 'choices.0.message.content', '')));

        return (array) json_decode($content, true);
    }

    private function prompt(Product $product, Language $language): string
    {
        $brief = $this->briefs[$product->family_id ?? 0] ??= GetCatalogueTranslationBrief::run($language, $this->sourceFamily($product));
        return $brief.<<<EOT


This time the text is not for retailers: it is for the product page of an online retail store run by a dropshipper, read by a consumer buying the item.

You get a JSON object whose keys are fields of one product (name, description_title, description, description_extra), each holding {"translate": "..."}: translate it into {$language->name}, written for that consumer.
Keep every HTML tag in the same order, keep placeholders in braces and every number, size and measurement.
Reply with a JSON object with the same keys, each holding only the final {$language->name} text as a string.
EOT;
    }

    /**
     * The family with the same code in the shop we copy from, whose published names in the target
     * language the brief shows as examples. The product's own family would show English beside English.
     */
    private function sourceFamily(Product $product): ?ProductCategory
    {
        if (!$this->sourceShop || !$product->family) {
            return null;
        }

        return ProductCategory::where('shop_id', $this->sourceShop->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->whereRaw('lower(code) = ?', [strtolower($product->family->code)])
            ->first();
    }

    public function invalidReason(array $fields, array $texts): ?string
    {
        foreach ($fields as $field => $input) {
            $output = Arr::get($texts, $field);
            $source = $input['translate'];
            if (!is_string($output) || trim($output) === '') {
                return "$field: empty";
            }
            if (!$this->sameHtml($source, $output)) {
                return "$field: HTML tags changed";
            }
            if ($this->placeholders($source) !== $this->placeholders($output)) {
                return "$field: placeholders changed";
            }
            if ($lost = $this->lostNumbers($source, $output)) {
                return "$field: numbers lost (".implode(', ', $lost).')';
            }
            if ($output === $source && mb_strlen($source) > 25) {
                return "$field: not translated";
            }
        }

        return null;
    }

    /**
     * Every tag outside inline emphasis must come back character for character and in the same
     * order. Inline emphasis may move, merge or drop, because word order differs between languages,
     * but never grows past the source, is bare or copied exactly from the source, and wraps no block
     * the source does not wrap. Every tag must still nest, and a "<" that opens no complete tag fails.
     * A source that does not nest itself keeps the strict comparison of every tag.
     */
    private function sameHtml(string $source, string $output): bool
    {
        $sourceTags = $this->tags($source);
        $outputTags = $this->tags($output);

        if (preg_match_all('/<\s*\/?\s*[a-z]/i', $output) !== count($outputTags)) {
            return false;
        }

        $raw = fn (array $tags) => array_column($tags, 'raw');
        if (!$this->nests($sourceTags, $sourceWrapsBlock)) {
            return $raw($sourceTags) === $raw($outputTags);
        }

        $isEmphasis = fn (array $tag) => in_array($tag['name'], self::INLINE_TAGS);
        $structure  = fn (array $tags) => $raw(array_values(array_filter($tags, fn (array $tag) => !$isEmphasis($tag))));
        $count      = fn (array $tags) => array_count_values(array_column(array_filter($tags, fn (array $tag) => !$tag['closing'] && $isEmphasis($tag)), 'name'));

        $sourceRaw   = $raw($sourceTags);
        $sourceCount = $count($sourceTags);
        foreach ($outputTags as $tag) {
            if (str_ends_with($tag['raw'], '/>') && !in_array($tag['name'], self::VOID_TAGS) && !in_array($tag['raw'], $sourceRaw)) {
                return false;
            }
            if ($isEmphasis($tag) && !preg_match('/^<\/?'.$tag['name'].'>$/i', $tag['raw']) && !in_array($tag['raw'], $sourceRaw)) {
                return false;
            }
        }
        foreach ($count($outputTags) as $name => $times) {
            if ($times > ($sourceCount[$name] ?? 0)) {
                return false;
            }
        }

        return $structure($sourceTags) === $structure($outputTags)
            && $this->nests($outputTags, $outputWrapsBlock)
            && (!$outputWrapsBlock || $sourceWrapsBlock);
    }

    /**
     * @return list<array{closing: string, name: string, raw: string}>
     */
    private function tags(string $text): array
    {
        preg_match_all('/<\s*(\/?)\s*([a-z][a-z0-9]*)[^<>]*>/i', $text, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match) => [
            'closing' => $match[1],
            'name'    => strtolower($match[2]),
            'raw'     => preg_replace(['/\s+/', '/^<\s*(\/?)\s*([a-z][a-z0-9]*)/i', '/\s*>$/'], [' ', '<$1$2', '>'], $match[0]),
        ], $matches);
    }

    private function nests(array $tags, ?bool &$emphasisWrapsBlock = false): bool
    {
        $open               = [];
        $emphasisWrapsBlock = false;
        foreach ($tags as $tag) {
            if (in_array($tag['name'], self::VOID_TAGS) || str_ends_with($tag['raw'], '/>')) {
                continue;
            }
            if ($tag['closing']) {
                if (array_pop($open) !== $tag['name']) {
                    return false;
                }
                continue;
            }
            if ($tag['name'] !== 'a' && !in_array($tag['name'], self::INLINE_TAGS) && array_intersect($open, self::INLINE_TAGS)) {
                $emphasisWrapsBlock = true;
            }
            $open[] = $tag['name'];
        }

        return $open === [];
    }

    private function placeholders(string $text): array
    {
        preg_match_all('/\{\{?[^{}]+\}\}?/', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }

    /**
     * A separator before three digits is read both as a thousands separator (1,000) and as a list
     * (10,50,100ml): a number is lost only when the output misses it under both readings.
     *
     * @return list<string>
     */
    private function lostNumbers(string $source, string $output): array
    {
        $found = array_merge($this->numbers($output, true), $this->numbers($output, false));
        $lost  = array_values(array_diff($this->numbers($source, true), $found));

        return $lost && array_diff($this->numbers($source, false), $found) ? $lost : [];
    }

    private function numbers(string $text, bool $thousands): array
    {
        $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        if ($thousands) {
            $plain = preg_replace('/(?<=\d)[.,\x{00A0}\x{202F}](?=\d{3}(?!\d))/u', '', $plain);
        }
        preg_match_all('/\d+/', $plain, $matches);

        return array_values(array_unique($matches[0]));
    }

    /**
     * The sibling with the same code, preferring an active one, only when it holds the same trade
     * units in the same quantities and the same units: a wholesale pack describes another thing.
     *
     * @return Collection<string, Product>
     */
    private function siblings(Collection $products, Shop $sourceShop): Collection
    {
        $candidates = Product::where('shop_id', $sourceShop->id)
            ->whereNull('deleted_at')
            ->whereIn(DB::raw('lower(code)'), $products->map(fn (Product $product) => strtolower($product->code))->all())
            ->orderByRaw("(state = 'active') desc, id desc")
            ->get()
            ->unique(fn (Product $product) => strtolower($product->code))
            ->keyBy(fn (Product $product) => strtolower($product->code));

        $contents = $this->unitContents($products->pluck('id')->merge($candidates->pluck('id'))->all());

        return $products
            ->mapWithKeys(function (Product $product) use ($candidates, $contents) {
                $sibling = $candidates->get(strtolower($product->code));

                return [
                    strtolower($product->code) => $sibling
                        && (float) $sibling->units === (float) $product->units
                        && ($contents[$sibling->id] ?? null) === ($contents[$product->id] ?? null)
                            ? $sibling : null,
                ];
            })
            ->filter();
    }

    /**
     * @return array<int, string>
     */
    private function unitContents(array $productIds): array
    {
        return DB::table('model_has_trade_units')
            ->where('model_type', 'Product')
            ->whereIn('model_id', $productIds)
            ->orderBy('trade_unit_id')
            ->get(['model_id', 'trade_unit_id', 'quantity'])
            ->groupBy('model_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => $row->trade_unit_id.':'.(float) $row->quantity)->implode(','))
            ->all();
    }

    public function sourceShop(Shop $shop, Language $language, ?string $sourceShop): ?Shop
    {
        if ($sourceShop) {
            return $this->findShop($sourceShop);
        }

        $candidates = Shop::where('organisation_id', $shop->organisation_id)
            ->where('language_id', $language->id)
            ->where('state', ShopStateEnum::OPEN)
            ->where('id', '!=', $shop->id)
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function findShop(string $shop): Shop
    {
        return Shop::where('slug', $shop)->when(is_numeric($shop), fn ($query) => $query->orWhere('id', (int) $shop))->firstOrFail();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shop       = $this->findShop($command->argument('shop'));
        $language   = Language::where('code', $command->argument('language'))->firstOrFail();
        $sourceShop = $this->sourceShop($shop, $language, $command->option('source-shop'));
        $apply      = (bool) $command->option('apply');

        $command->info(($apply ? 'Writing' : 'Dry run (nothing saved)').": {$shop->code} -> {$language->code}, copying from ".($sourceShop?->code ?? 'no shop, machine translation only'));

        $result = $this->handle(
            $shop,
            $language,
            $sourceShop,
            $apply,
            (bool) $command->option('force'),
            $command->option('limit') !== null ? (int) $command->option('limit') : null,
            (int) $command->option('chunk'),
            $command->option('model'),
            function (Product $product, array $fields, ?array $texts, array $sources, ?string $failure = null) use ($command) {
                $command->line("<comment>{$product->code}</comment> ".($texts === null ? "<error>FAILED</error> $failure" : implode(', ', array_unique($sources))));
                if ($command->getOutput()->isVerbose() && $texts !== null) {
                    foreach ($texts as $field => $text) {
                        $command->line("  $field: ".mb_strimwidth(strip_tags(Arr::first($fields[$field])), 0, 160, '…'));
                        $command->line("  => ".mb_strimwidth(strip_tags($text), 0, 160, '…'));
                    }
                }
            },
            (bool) $command->option('retry-failed')
        );

        $command->table(['source', 'products'], collect($result['stats'])->map(fn ($count, $source) => [$source, $count])->values()->all());
        $command->info('AI cost: $'.number_format($result['cost'], 4));

        return Command::SUCCESS;
    }
}
