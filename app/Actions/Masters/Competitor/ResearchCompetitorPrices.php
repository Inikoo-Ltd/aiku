<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\Helpers\AI\AskToAi;
use App\Enums\Masters\Competitor\CompetitorStatusEnum;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Enums\Masters\MasterAsset\MasterAssetPriceTipStatusEnum;
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

    public const string MODEL = 'openai/gpt-5.6-luna';

    public const string USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    private const int CHUNK = 20;

    /**
     * @param  array<int, int>  $masterAssetIds
     */
    public function handle(Competitor $competitor, array $masterAssetIds): int
    {
        if (!$competitor->search_url) {
            return 0;
        }

        $masterAssets = MasterAsset::whereIn('id', $masterAssetIds)->get()->values();

        $result = $this->browse($competitor, $masterAssets->map(fn (MasterAsset $masterAsset) => static::searchUrl($competitor, $masterAsset))->all());

        $pages     = $result['pages'] ?? [];
        $lastError = $result['error'] ?? $result['login_error'] ?? (Arr::last($pages)['blocked'] ?? false ? __('The website asked to prove we are not a robot') : null);

        $previousStatus = $competitor->status;

        $competitor->update([
            'status'     => match (true) {
                isset($result['login_error']) => $result['login_error'] === 'blocked' ? CompetitorStatusEnum::BLOCKED : CompetitorStatusEnum::LOGIN_FAILED,
                $lastError !== null           => isset($result['error']) ? CompetitorStatusEnum::ERROR : CompetitorStatusEnum::BLOCKED,
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
        foreach ($pages as $index => $page) {
            if (empty($page['text']) || $page['blocked']) {
                continue;
            }

            foreach (static::candidates($this->ask($competitor, $masterAssets[$index], $page), $page) as $candidate) {
                $this->save($competitor, $masterAssets[$index], $candidate);
                $matches++;
            }
        }

        $competitor->update(['number_products' => $competitor->products()->count()]);

        return $matches;
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
     * The same product found by two searches must be one row, so the search words come off its link.
     */
    public static function withoutSearchWords(string $url, string $searchWords): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) {
            return $url;
        }

        parse_str($query, $parameters);
        $parameters = array_filter($parameters, fn ($value) => $value !== $searchWords);

        return strtok($url, '?').($parameters ? '?'.http_build_query($parameters) : '');
    }

    public static function searchUrl(Competitor $competitor, MasterAsset $masterAsset): string
    {
        return str_replace('{query}', urlencode($masterAsset->name), $competitor->search_url);
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function browse(Competitor $competitor, array $urls): array
    {
        $process = Process::timeout(60 * (count($urls) + 2))
            ->input(json_encode([
                'user_agent' => self::USER_AGENT,
                'cookies'    => $competitor->cookies,
                'login'      => $competitor->login_url && $competitor->username ? [
                    'url'      => $competitor->login_url,
                    'username' => $competitor->username,
                    'password' => $competitor->password,
                ] : null,
                'urls'       => $urls,
            ]))
            ->run(['node', resource_path('node/competitor-pages.cjs')]);

        return json_decode($process->output(), true) ?: ['error' => trim($process->errorOutput()) ?: __('The browser did not answer')];
    }

    private function ask(Competitor $competitor, MasterAsset $masterAsset, array $page): ?string
    {
        $links = collect($page['links'])->map(fn ($link) => $link['href'].' '.$link['text'])->implode("\n");

        $prompt = <<<PROMPT
We sell this product to retailers:
name: {$masterAsset->name}
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

    private function save(Competitor $competitor, MasterAsset $masterAsset, array $candidate): void
    {
        $competitorProduct = CompetitorProduct::updateOrCreate(
            ['competitor_id' => $competitor->id, 'code' => $url = static::withoutSearchWords($candidate['url'], $masterAsset->name)],
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
     * ponytail: weekly, only products with an open price tip (or the codes asked for), 20 products
     * per browser session; widen to every product once the matches prove useful.
     */
    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $competitors = Competitor::whereNotNull('search_url')
            ->whereNull('feed_url')
            ->when($command->option('competitor'), fn ($query, $id) => $query->where('id', $id))
            ->when($command->option('master-shop'), fn ($query, $slug) => $query->whereHas('masterShop', fn ($query) => $query->where('slug', $slug)))
            ->get();

        foreach ($competitors as $competitor) {
            $ids = MasterAsset::where('master_shop_id', $competitor->master_shop_id)
                ->where('status', true)
                ->when(
                    $command->option('product'),
                    fn ($query, $codes) => $query->whereIn('code', $codes),
                    fn ($query) => $query->whereHas('priceTips', fn ($query) => $query->where('status', MasterAssetPriceTipStatusEnum::OPEN))
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
