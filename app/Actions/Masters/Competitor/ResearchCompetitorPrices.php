<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\Helpers\AI\AskToAi;
use App\Actions\Masters\MasterAsset\GenerateMasterAssetPriceTips;
use App\Enums\Masters\Competitor\CompetitorStatusEnum;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Enums\Masters\MasterAsset\MasterAssetPriceTipStatusEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\Competitor;
use App\Models\Masters\CompetitorProduct;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetCompetitorProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Searches a competitor's website for our master products (HELP-3605): a headless browser logs in
 * with our trade account when the competitor has one, opens the search page for each product, and
 * the AI picks the same or the closest item from the results, with its price and pack size.
 * Matches start as suggested; staff confirm or reject them, and only confirmed ones are trusted.
 */
class ResearchCompetitorPrices
{
    use AsAction;

    public string $commandSignature = 'masters:competitor-prices {--s|master-shop= : Only this master shop slug} {--competitor= : Only this competitor id} {--product=* : Master product codes instead of the ones with an open price tip} {--sync : Run in this process instead of queueing}';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 1800;

    public const string MODEL = 'anthropic/claude-haiku-5.5';

    public const string USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    private const int CHUNK = 20;

    private const array SEARCH_TRACKING = ['_pos', '_sid', '_ss'];

    /**
     * @param  array<int, int>  $masterAssetIds
     */
    public function handle(Competitor $competitor, array $masterAssetIds): int
    {
        if (!$competitor->search_url) {
            return 0;
        }

        $masterAssets = MasterAsset::whereIn('id', $masterAssetIds)->get()->values();
        $localNames   = MatchCompetitorProducts::localNames($competitor, $masterAssetIds);
        $searchWords  = $masterAssets->map(fn (MasterAsset $masterAsset) => $localNames[$masterAsset->id] ?? $masterAsset->name)->all();
        $search       = fn () => $this->browse($competitor, array_map(fn (string $words) => static::searchUrl($competitor, $words), $searchWords));

        $result       = $search();
        $searchBroken = static::searchIsBroken($result['pages'] ?? [], array_map(static::searchQuery(...), $searchWords));
        $publishedUrl = $searchBroken ? $this->publishedSearchUrl($competitor, $result['pages'] ?? []) : null;
        if ($publishedUrl && $publishedUrl !== $competitor->search_url) {
            $competitor->update(['search_url' => $publishedUrl]);
            $result       = $search();
            $searchBroken = static::searchIsBroken($result['pages'] ?? [], array_map(static::searchQuery(...), $searchWords));
        }

        $pages     = $result['pages'] ?? [];
        $lastError = $this->plainError($competitor, $result) ?? match (true) {
            Arr::last($pages)['blocked'] ?? false => __('The website asked to prove we are not a robot'),
            $searchBroken                         => __('Their website showed a page without the searched words. Search their website for any word, copy the link of the results page and put {query} where the word was'),
            default                               => null,
        };

        $previousStatus = $competitor->status;

        $competitor->update([
            'status'     => match (true) {
                isset($result['login_error']) => $result['login_error'] === 'blocked' ? CompetitorStatusEnum::BLOCKED : CompetitorStatusEnum::LOGIN_FAILED,
                isset($result['error'])       => CompetitorStatusEnum::ERROR,
                $searchBroken                 => CompetitorStatusEnum::SEARCH_BROKEN,
                $lastError !== null           => CompetitorStatusEnum::BLOCKED,
                default                       => CompetitorStatusEnum::OK,
            },
            'last_error' => $lastError,
            'cookies'    => $result['cookies'] ?? $competitor->cookies,
            'fetched_at' => now(),
        ]);

        if ($competitor->status !== CompetitorStatusEnum::OK && $previousStatus !== $competitor->status) {
            $this->alert($competitor);
        }

        $matches = 0;
        foreach ($searchBroken ? [] : $pages as $index => $page) {
            if (empty($page['text']) || $page['blocked']) {
                continue;
            }

            foreach (static::candidates($this->ask($competitor, $masterAssets[$index], $searchWords[$index], $page), $page) as $candidate) {
                $this->save($competitor, $masterAssets[$index], $searchWords[$index], $this->withSizePrice($competitor, $masterAssets[$index], $candidate));
                $matches++;
            }
        }

        $competitor->update(['number_products' => $competitor->products()->count()]);

        return $matches;
    }

    /**
     * What staff read under the competitor's status: the browser's own wording ("no login form",
     * "Runtime.callFunctionOn timed out") means nothing to them, so it goes to the log instead.
     *
     * @param  array<string, mixed>  $result
     */
    private function plainError(Competitor $competitor, array $result): ?string
    {
        if (isset($result['error'])) {
            logger()->warning("Competitor $competitor->id search read failed: {$result['error']}");

            return __('The browser could not read their website this time. It is tried again at the next read');
        }

        return match ($result['login_error'] ?? null) {
            null               => null,
            'no login form'    => __('The login page has no email box. Check the login page link on Edit'),
            'no password field' => __('The login page did not ask for a password. Check the login page link on Edit'),
            'wrong login'      => __('Their website did not accept the email and password. Check them on Edit'),
            'blocked'          => __('The website asked to prove we are not a robot'),
            default            => __('Could not log in to their website'),
        };
    }

    private function alert(Competitor $competitor): void
    {
        if (!$webhookUrl = config('services.discord.webhook_url')) {
            return;
        }

        try {
            Http::post($webhookUrl, [
                'content' => "🔭 **Competitor prices stopped** for {$competitor->name} ({$competitor->masterShop->code}): ".$competitor->last_error,
            ]);
        } catch (Throwable) {
            return;
        }
    }

    /**
     * The same product found by two searches must be one row, so the search words come off its link, and so
     * does the tracking a Shopify search adds (_sid changes with every search, HELP-4686).
     */
    public static function withoutSearchWords(string $url, string $searchWords): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) {
            return $url;
        }

        parse_str($query, $parameters);
        $parameters = array_filter($parameters, fn ($value, $key) => !in_array($value, [$searchWords, static::searchQuery($searchWords)], true) && !in_array($key, self::SEARCH_TRACKING, true), ARRAY_FILTER_USE_BOTH);

        return strtok($url, '?').($parameters ? '?'.http_build_query($parameters) : '');
    }

    /**
     * A search link that does not search lands every product on the same page without the searched words,
     * like a results page that dropped the query. A page that keeps a searched word in its link or its text
     * searched; one batch where none does means the link is wrong. A page the website answers with "not found"
     * did not search, whatever its own address holds: a link missing its "?" (/searchq=word) keeps the word in the address.
     *
     * @param  array<int, array>  $pages
     * @param  array<int, string>  $searchedNames
     */
    public static function searchIsBroken(array $pages, array $searchedNames): bool
    {
        $read = collect($pages)->filter(fn ($page) => filled($page['final_url'] ?? null) && filled($page['text'] ?? null) && !($page['blocked'] ?? false));

        return $read->isNotEmpty() && $read->every(function (array $page, int $index) use ($searchedNames) {
            if (in_array($page['status'] ?? null, [404, 410], true)) {
                return true;
            }

            $word = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($searchedNames[$index] ?? '')))->first(fn ($word) => mb_strlen($word) >= 4);

            return $word && !str_contains(mb_strtolower(urldecode($page['final_url'])), $word) && !str_contains(mb_strtolower($page['text']), $word);
        });
    }

    /**
     * The search link the website publishes: on a page just read, else on its homepage fetched plainly, else on its
     * homepage opened in the browser, as some sites turn away a plain request from a server (seen: a 403).
     *
     * @param  array<int, array>  $pages
     */
    private function publishedSearchUrl(Competitor $competitor, array $pages = []): ?string
    {
        $fromPages = fn (array $pages) => static::searchUrlFrom(collect($pages)->flatMap(fn ($page) => $page['search_actions'] ?? [])->all(), $competitor->website);

        return $fromPages($pages)
            ?? static::findSearchUrl($competitor->website)
            ?? $fromPages($this->browse($competitor, [$competitor->website])['pages'] ?? []);
    }

    /**
     * The search link a website publishes about itself (schema.org SearchAction), read from its homepage.
     */
    public static function findSearchUrl(string $website): ?string
    {
        $html = rescue(fn () => Http::timeout(20)->get($website)->body(), '', report: false);
        preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#si', $html, $scripts);

        return static::searchUrlFrom(collect($scripts[1])->flatMap(fn ($script) => static::searchActions(json_decode(html_entity_decode($script), true)))->all(), $website);
    }

    /**
     * A SearchAction's link with {query} for the words, only when it is on the website's own domain.
     *
     * @param  array<int, mixed>  $actions
     */
    public static function searchUrlFrom(array $actions, string $website): ?string
    {
        $host = fn (?string $url) => preg_replace('/^www\./', '', mb_strtolower((string) parse_url((string) $url, PHP_URL_HOST)));
        foreach (array_filter($actions, 'is_array') as $action) {
            $target = is_array($action['target'] ?? null) ? ($action['target']['urlTemplate'] ?? null) : ($action['target'] ?? null);
            $input  = $action['query-input'] ?? $action['query'] ?? '';
            $name   = match (true) {
                is_array($input) && is_string($input['valueName'] ?? null)          => $input['valueName'],
                is_string($input) && preg_match('/name=([\w-]+)/', $input, $match) === 1 => $match[1],
                default                                                             => 'search_term_string',
            };
            if (is_string($target) && str_contains($target, '{'.$name.'}') && preg_match('#^https?://#i', $target) && $host($target) === $host($website)) {
                return str_replace('{'.$name.'}', '{query}', $target);
            }
        }

        return null;
    }

    /**
     * @return array<int, array>
     */
    private static function searchActions(mixed $node): array
    {
        if (!is_array($node)) {
            return [];
        }

        $found = str_contains(json_encode($node['@type'] ?? ''), 'SearchAction') ? [$node] : [];
        foreach ($node as $child) {
            $found = [...$found, ...static::searchActions($child)];
        }

        return $found;
    }

    /**
     * Their search link for our product, searched with our name in their language (see MatchCompetitorProducts::localNames).
     */
    public static function searchUrl(Competitor $competitor, string $searchWords): string
    {
        return str_replace('{query}', urlencode(static::searchQuery($searchWords)), $competitor->search_url);
    }

    /**
     * What a person would type in their search box (HELP-4686): our name without its size, pack, label
     * or codes. A search needing every word found nothing for "10ml Basil Essential Oil White Label"; the
     * AI still gets the whole name to pick the right item and size.
     */
    public static function searchQuery(string $name): string
    {
        $query = preg_replace([
            '/\([^)]*\)/u',
            '/\b(white|special)\s+label\b/iu',
            '/\b\d+\s*x\b|\bx\s*\d+\b|\b(box|pack|set|case)\s+of\s+\d+\b/iu',
            '/\b\d+(?:[.,]\d+)?\s*(?:ml|l|ltr|litres?|liters?|g|gr|kg|cl|oz|cm|mm)\b/iu',
            '/\b[A-Z]{2,}-?\d{3,}\b/u',
            '/\s+[-–]\s+|[,:;]/u',
        ], ' ', $name);
        $query = trim(preg_replace('/\s+/u', ' ', $query), " -–");

        return mb_strlen($query) >= 3 ? $query : $name;
    }

    /**
     * @param  array<int, string>  $urls
     */
    public function browse(Competitor $competitor, array $urls): array
    {
        $logsIn = $competitor->login_url && $competitor->username;

        $process = Process::timeout(60 * (count($urls) + 2))
            ->input(json_encode([
                'user_agent' => self::USER_AGENT,
                'cookies'    => $logsIn ? [] : $competitor->cookies,
                'login'      => $logsIn ? [
                    'url'      => $competitor->login_url,
                    'username' => $competitor->username,
                    'password' => $competitor->password,
                ] : null,
                'urls'       => $urls,
            ]))
            ->run(['node', resource_path('node/competitor-pages.cjs')]);

        return json_decode($process->output(), true) ?: ['error' => trim($process->errorOutput()) ?: __('The browser did not answer')];
    }

    private function ask(Competitor $competitor, MasterAsset $masterAsset, string $searchWords, array $page): ?string
    {
        $links        = collect($page['links'])->map(fn ($link) => $link['href'].' '.$link['text'])->implode("\n");
        $theirLanguage = $searchWords !== $masterAsset->name ? "\nname in their language: $searchWords" : '';

        $prompt = <<<PROMPT
We sell this product to retailers:
name: {$masterAsset->name}{$theirLanguage}
units per pack: {$masterAsset->units}
barcode: {$masterAsset->barcode}

Below are the search results for it on a competitor's website ({$competitor->website}), prices in {$competitor->currency->code}.
Pick at most 3 results that are the same item or a very close substitute (same kind of product, similar size and material). Ignore anything else.

Reply with JSON only: {"products":[{"url":"link copied exactly from the links list","name":"its name","price":number or null if no price is shown,"units":units in the pack the price is for,"minimum_order":number of packs or null,"same_item":true if it is the same item, false if only similar}]}
Reply {"products":[]} when nothing matches.

Page text:
{$this->clip($page['text'], 12000)}

Links:
{$this->clip($links, 8000)}
PROMPT;

        return AskToAi::run($prompt, self::MODEL, ['response_format' => ['type' => 'json_object']]);
    }

    /**
     * A product sold in several sizes shows no price in a Shopify search, only "Choose options" (HELP-4686).
     * The store publishes every size with its price at /products/<handle>.js; the size closest to ours is
     * kept, and the link names it so another size of the same product is its own row.
     *
     * @param  array{url: string, name: string, price: float|null, units: float, minimum_order: int|null, same_item: bool}  $candidate
     * @return array{url: string, name: string, price: float|null, units: float, minimum_order: int|null, same_item: bool}
     */
    private function withSizePrice(Competitor $competitor, MasterAsset $masterAsset, array $candidate): array
    {
        if ($candidate['price'] !== null || !preg_match('#^(https?://[^/?\#]+/products/[^/?\#.]+)#i', $candidate['url'], $match)) {
            return $candidate;
        }

        $variants = collect(rescue(fn () => Http::timeout(20)->withUserAgent(self::USER_AGENT)->get($match[1].'.js')->json('variants'), [], report: false) ?: [])
            ->filter(fn ($variant) => is_array($variant) && isset($variant['id']) && is_numeric($variant['price'] ?? null) && $variant['price'] > 0)
            ->values();

        $choice = $variants->count() === 1 ? ['size' => 0, 'units' => $candidate['units']] : $this->pickSize($competitor, $masterAsset, $variants->all());
        $size   = $variants->get($choice['size'] ?? -1);
        if (!$size) {
            return $candidate;
        }

        return [
            ...$candidate,
            'url'   => $match[1].'?variant='.$size['id'],
            'name'  => mb_substr(trim($candidate['name'].' '.($variants->count() > 1 ? ($size['title'] ?? '') : '')), 0, 500),
            'price' => $size['price'] / 100,
            'units' => is_numeric($choice['units'] ?? null) && $choice['units'] > 0 ? (float) $choice['units'] : $candidate['units'],
        ];
    }

    /**
     * @param  array<int, array>  $variants
     * @return array{size?: int|null, units?: float|null}
     */
    private function pickSize(Competitor $competitor, MasterAsset $masterAsset, array $variants): array
    {
        if (!$variants) {
            return [];
        }

        $sizes = collect($variants)->map(fn ($variant, $index) => $index.': '.($variant['title'] ?? '').' '.$competitor->currency->code.' '.number_format($variant['price'] / 100, 2))->implode("\n");

        $prompt = <<<PROMPT
We sell this product to retailers:
name: {$masterAsset->name}
units per pack: {$masterAsset->units}

A competitor sells a matching product in these sizes:
{$sizes}

Pick the size closest to ours. Reply with JSON only: {"size":its number,"units":how many of our units its price is for}
Reply {"size":null} when no size is comparable.
PROMPT;

        $answer = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', trim((string) AskToAi::run($prompt, self::MODEL, ['response_format' => ['type' => 'json_object']])))), true);

        return is_array($answer) && is_int($answer['size'] ?? null) ? $answer : [];
    }

    private function clip(string $text, int $length): string
    {
        return mb_substr($text, 0, $length);
    }

    /**
     * Only links that were on the page count, so a made-up link is never saved.
     *
     * @return array<int, array{url: string, name: string, price: float|null, units: float, minimum_order: int|null, same_item: bool}>
     */
    public static function candidates(?string $answer, array $page): array
    {
        $products = Arr::get(json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', trim((string) $answer))), true) ?: [], 'products', []);
        $hrefs    = array_column($page['links'] ?? [], 'href');

        return collect(is_array($products) ? $products : [])
            ->filter(fn ($product) => is_array($product) && in_array($product['url'] ?? null, $hrefs, true) && filled($product['name'] ?? null))
            ->map(fn ($product) => [
                'url'           => $product['url'],
                'name'          => mb_substr((string) $product['name'], 0, 500),
                'price'         => is_numeric($product['price'] ?? null) && $product['price'] > 0 ? (float) $product['price'] : null,
                'units'         => is_numeric($product['units'] ?? null) && $product['units'] > 0 ? (float) $product['units'] : 1.0,
                'minimum_order' => is_numeric($product['minimum_order'] ?? null) ? (int) $product['minimum_order'] : null,
                'same_item'     => (bool) ($product['same_item'] ?? false),
            ])
            ->take(3)
            ->values()
            ->all();
    }

    private function save(Competitor $competitor, MasterAsset $masterAsset, string $searchWords, array $candidate): void
    {
        $competitorProduct = CompetitorProduct::updateOrCreate(
            ['competitor_id' => $competitor->id, 'code' => $url = static::withoutSearchWords($candidate['url'], $searchWords)],
            Arr::only($candidate, ['name', 'price', 'units', 'minimum_order']) + ['url' => $url, 'fetched_at' => now()]
        );

        $match = MasterAssetCompetitorProduct::firstOrNew([
            'master_asset_id'       => $masterAsset->id,
            'competitor_product_id' => $competitorProduct->id,
        ], [
            'master_shop_id' => $masterAsset->master_shop_id,
            'status'         => MasterAssetCompetitorProductStatusEnum::SUGGESTED,
        ]);

        if ($match->status === MasterAssetCompetitorProductStatusEnum::SUGGESTED) {
            $match->is_same_item = $candidate['same_item'];
        }

        $match->save();
    }

    /**
     * ponytail: daily, a seventh of the products each day so every one is read once a week without a
     * Sunday burst on the competitor's website (HELP-4686); only products with an open price tip (or the
     * codes asked for), 20 per browser session. A master shop left out of price tips (Aroma) never has
     * one, so all its main products are read; widen the others once the matches prove useful.
     */
    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        Competitor::whereNull('search_url')
            ->whereNull('feed_url')
            ->whereNotNull('login_url')
            ->where('prices_by_hand', false)
            ->each(function (Competitor $competitor) {
                if ($searchUrl = $this->publishedSearchUrl($competitor)) {
                    $competitor->update(['search_url' => $searchUrl]);
                }
            });

        $competitors = Competitor::whereNotNull('search_url')
            ->whereNull('feed_url')
            ->where('prices_by_hand', false)
            ->when($command->option('competitor'), fn ($query, $id) => $query->where('id', $id))
            ->when($command->option('master-shop'), fn ($query, $slug) => $query->whereHas('masterShop', fn ($query) => $query->where('slug', $slug)))
            ->get();

        foreach ($competitors as $competitor) {
            $ids = MasterAsset::where('master_shop_id', $competitor->master_shop_id)
                ->where('status', true)
                ->when(
                    $command->option('product'),
                    fn ($query, $codes) => $query->whereIn('code', $codes),
                    fn ($query) => $query->whereRaw('master_assets.id % 7 = ?', [now()->dayOfWeek])
                        ->when(
                            in_array($competitor->masterShop->slug, GenerateMasterAssetPriceTips::EXCLUDED_MASTER_SHOPS, true),
                            fn ($query) => $query->where('is_main', true)->where('type', MasterAssetTypeEnum::PRODUCT),
                            fn ($query) => $query->whereHas('priceTips', fn ($query) => $query->where('status', MasterAssetPriceTipStatusEnum::OPEN))
                        )
                )
                ->pluck('id');

            foreach ($ids->chunk(self::CHUNK) as $chunk) {
                if ($command->option('sync')) {
                    $command->info("$competitor->name: ".$this->handle($competitor, $chunk->values()->all()).' matches');
                } else {
                    static::dispatch($competitor, $chunk->values()->all());
                }
            }

            $command->info("$competitor->name: ".$ids->count().' products');
        }

        return 0;
    }
}
