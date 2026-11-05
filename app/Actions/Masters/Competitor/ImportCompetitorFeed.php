<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Enums\Masters\Competitor\CompetitorStatusEnum;
use App\Models\Masters\Competitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Reads a competitor's product feed (a CSV or tab separated file they publish for their trade
 * customers, or the public catalogue of a Shopify or WooCommerce shop) into competitor_products,
 * then queues the matching of our products against it. Columns are found by their header, so a
 * feed with the usual names needs no setup, and a competitor with no feed and no search link whose
 * website runs on Shopify or WooCommerce gets its catalogue as the feed.
 */
class ImportCompetitorFeed
{
    use AsAction;

    public string $commandSignature = 'masters:competitor-feeds {--competitor= : Only this competitor id} {--no-match : Only read the feeds} {--sync : Match in this process instead of queueing}';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 900;

    public const array COLUMNS = [
        'code'          => '/^(item ?no|item ?number|item ?code|sku|code|product ?code|reference)$/i',
        'name'          => '/^(item ?name|name|title|product ?name)$/i',
        'price'         => '/^(price|trade ?price|wholesale ?price|unit ?price)$/i',
        'rrp'           => '/^(rrp|retail ?price|msrp|recommended ?retail ?price)$/i',
        'barcode'       => '/^(barcode|ean|ean13|gtin|upc)$/i',
        'minimum_order' => '/^(min ?order ?qty|minimum ?order|moq|min ?qty)$/i',
        'image_url'     => '/^(url ?links|image|image ?url|image ?link|picture)$/i',
        'url'           => '/^(url|link|product ?url|product ?link)$/i',
        'discontinued'  => '/^discontinued$/i',
    ];

    public const array DETAILS = [
        'description' => '/^(description|long ?description|product ?description)$/i',
        'material'    => '/^(material|materials)$/i',
        'height'      => '/^height$/i',
        'width'       => '/^width$/i',
        'depth'       => '/^(depth|length)$/i',
        'weight'      => '/^(individual ?weight|net ?weight|weight)$/i',
        'tariff_code' => '/^(commodity ?code|tariff ?code|hs ?code)$/i',
    ];

    /**
     * A Shopify shop lists 250 products a page: this many pages is far beyond any competitor's catalogue.
     */
    private const int SHOPIFY_PAGES = 40;

    /**
     * 100 products a page in WooCommerce.
     */
    private const int WOOCOMMERCE_PAGES = 100;

    public function handle(Competitor $competitor): int
    {
        $startedAt = now();

        try {
            [$rows, $isComplete] = match (true) {
                static::isShopifyFeed($competitor->feed_url)     => [static::shopifyRows($competitor->feed_url), true],
                static::isWooCommerceFeed($competitor->feed_url) => static::wooCommerceRows($competitor->feed_url),
                default                                          => [static::parse(Http::timeout(120)->get($competitor->feed_url)->throw()->body()), true],
            };
        } catch (Throwable $exception) {
            $competitor->update(['status' => CompetitorStatusEnum::ERROR, 'last_error' => mb_substr($exception->getMessage(), 0, 500), 'fetched_at' => now()]);

            return 0;
        }

        if (!$rows) {
            $competitor->update(['status' => CompetitorStatusEnum::ERROR, 'last_error' => __('The feed has no products or its columns were not recognised'), 'fetched_at' => now()]);

            return 0;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $competitor->products()->upsert(
                array_map(fn (array $row) => $row + [
                    'competitor_id' => $competitor->id,
                    'fetched_at'    => $startedAt,
                    'created_at'    => $startedAt,
                    'updated_at'    => $startedAt,
                ], $chunk),
                ['competitor_id', 'code'],
                ['url', 'image_url', 'name', 'barcode', 'price', 'rrp', 'minimum_order', 'data', 'fetched_at', 'updated_at']
            );
        }

        if ($isComplete) {
            $competitor->products()->where('fetched_at', '<', $startedAt)->delete();
        }

        $competitor->update([
            'status'          => CompetitorStatusEnum::OK,
            'last_error'      => null,
            'fetched_at'      => $startedAt,
            'number_products' => $competitor->products()->count(),
        ]);

        return count($rows);
    }

    /**
     * Products with no code, name or price and discontinued ones are left out.
     *
     * @return array<int, array{code: string, name: string, price: float, rrp: float|null, barcode: string|null, minimum_order: int|null, image_url: string|null, url: string|null, data: string}>
     */
    public static function parse(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $content)));
        if (count($lines) < 2) {
            return [];
        }

        $delimiter = collect(["\t", ';', ','])->sortByDesc(fn ($candidate) => substr_count($lines[0], $candidate))->first();
        $header    = array_map('trim', str_getcsv(array_shift($lines), $delimiter, '"', ''));

        $positions = [];
        foreach (self::COLUMNS + self::DETAILS as $field => $pattern) {
            $position = collect($header)->search(fn ($title) => preg_match($pattern, $title));
            if ($position !== false) {
                $positions[$field] = $position;
            }
        }

        if (!isset($positions['code'], $positions['name'], $positions['price'])) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line, $delimiter, '"', '');
            $cell  = fn (string $field) => isset($positions[$field]) ? trim($cells[$positions[$field]] ?? '') : '';
            $price = (float) str_replace(',', '.', $cell('price'));

            if ($cell('code') === '' || $cell('name') === '' || $price <= 0 || strtolower($cell('discontinued')) === 'true') {
                continue;
            }

            $rows[$cell('code')] = [
                'code'          => mb_substr($cell('code'), 0, 255),
                'name'          => mb_substr($cell('name'), 0, 500),
                'price'         => $price,
                'rrp'           => (float) str_replace(',', '.', $cell('rrp')) ?: null,
                'barcode'       => mb_substr($cell('barcode'), 0, 255) ?: null,
                'minimum_order' => (int) $cell('minimum_order') ?: null,
                'image_url'     => str_starts_with($cell('image_url'), 'http') ? $cell('image_url') : null,
                'url'           => str_starts_with($cell('url'), 'http') ? $cell('url') : null,
                'data'          => json_encode(array_filter([
                    'description' => mb_substr(strip_tags($cell('description')), 0, 400),
                    'material'    => $cell('material'),
                    'size_cm'     => implode(' x ', array_filter([$cell('height'), $cell('width'), $cell('depth')], fn ($value) => (float) $value > 0)),
                    'weight_g'    => (float) $cell('weight') ?: null,
                    'tariff_code' => $cell('tariff_code'),
                ])),
            ];
        }

        return array_values($rows);
    }

    public static function isShopifyFeed(string $feedUrl): bool
    {
        return str_ends_with(parse_url($feedUrl, PHP_URL_PATH) ?? '', '/products.json');
    }

    /**
     * The catalogue link of a competitor's website when it is a Shopify shop that shows its products.
     */
    public static function findShopifyFeed(string $website): ?string
    {
        $feedUrl = parse_url($website, PHP_URL_SCHEME).'://'.parse_url($website, PHP_URL_HOST).'/products.json';

        try {
            return Http::timeout(20)->retry(2, 5000, throw: false)->get($feedUrl, ['limit' => 1])->json('products') ? $feedUrl : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{code: string, name: string, price: float, rrp: null, barcode: null, minimum_order: null, image_url: string|null, url: string, data: string}>
     */
    public static function shopifyRows(string $feedUrl): array
    {
        $rows = [];
        for ($page = 1; $page <= self::SHOPIFY_PAGES; $page++) {
            $products = Http::timeout(60)->retry(3, 5000)->get($feedUrl, ['limit' => 250, 'page' => $page])->json('products');
            if (!is_array($products)) {
                throw new RuntimeException(__('The shop did not send page :page of its products', ['page' => $page]));
            }
            if (!$products) {
                break;
            }

            $rows += static::parseShopify($products, $feedUrl);
        }

        return array_values($rows);
    }

    /**
     * One row per variant, which in these shops is a pack size: its name is the product's title with the
     * variant's (e.g. "Mandarin Essential Oil 100ml") and its code the variant's id, as shops often give
     * every size of a product the same SKU.
     *
     * @return array<string, array{code: string, name: string, price: float, rrp: null, barcode: null, minimum_order: null, image_url: string|null, url: string, data: string}>
     */
    public static function parseShopify(array $products, string $feedUrl): array
    {
        $shopUrl = Str::beforeLast($feedUrl, '/products.json');

        $rows = [];
        foreach ($products as $product) {
            foreach ($product['variants'] ?? [] as $variant) {
                $price = (float) ($variant['price'] ?? 0);
                $code  = (string) $variant['id'];
                if ($price <= 0) {
                    continue;
                }

                $rows[$code] = [
                    'code'          => $code,
                    'name'          => mb_substr(trim($product['title'].(($variant['title'] ?? 'Default Title') === 'Default Title' ? '' : ' '.$variant['title'])), 0, 500),
                    'price'         => $price,
                    'rrp'           => null,
                    'barcode'       => null,
                    'minimum_order' => null,
                    'image_url'     => $variant['featured_image']['src'] ?? $product['images'][0]['src'] ?? null,
                    'url'           => "$shopUrl/products/{$product['handle']}?variant={$variant['id']}",
                    'data'          => json_encode(array_filter([
                        'description' => mb_substr(Str::squish(html_entity_decode(strip_tags($product['body_html'] ?? ''))), 0, 400),
                        'weight_g'    => (float) ($variant['grams'] ?? 0) ?: null,
                    ])),
                ];
            }
        }

        return $rows;
    }

    public static function isWooCommerceFeed(string $feedUrl): bool
    {
        return str_ends_with(parse_url($feedUrl, PHP_URL_PATH) ?? '', '/wc/store/v1/products');
    }

    /**
     * The public catalogue link of a competitor's website when it is a WooCommerce shop that shows its prices.
     */
    public static function findWooCommerceFeed(string $website): ?string
    {
        $feedUrl = parse_url($website, PHP_URL_SCHEME).'://'.parse_url($website, PHP_URL_HOST).'/wp-json/wc/store/v1/products';

        try {
            return Http::timeout(20)->retry(2, 5000, throw: false)->get($feedUrl, ['per_page' => 1])->json('0.prices') ? $feedUrl : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Products with options (sizes, colours) are read from their variations, one row each. When the shop
     * does not list its variations they are left out rather than read at their lowest "from" price, and the
     * read counts as incomplete, so the options read before and their reviewed matches are kept.
     *
     * @return array{0: array<int, array{code: string, name: string, price: float, rrp: null, barcode: null, minimum_order: null, image_url: string|null, url: string|null, data: string}>, 1: bool}
     */
    public static function wooCommerceRows(string $feedUrl): array
    {
        $products   = static::wooCommercePages($feedUrl, []);
        $variations = rescue(fn () => static::wooCommercePages($feedUrl, ['type' => 'variation']), null, report: false);
        $isComplete = $variations !== null || !collect($products)->contains('type', 'variable');

        return [array_values(static::parseWooCommerce([...$products, ...($variations ?? [])])), $isComplete];
    }

    /**
     * WooCommerce answers an error after its last page, so the number of pages comes from its X-WP-TotalPages header.
     */
    private static function wooCommercePages(string $feedUrl, array $query): array
    {
        $products = [];
        $pages    = 1;
        for ($page = 1; $page <= min($pages, self::WOOCOMMERCE_PAGES); $page++) {
            $response = Http::timeout(60)->retry(3, 5000)->get($feedUrl, $query + ['per_page' => 100, 'page' => $page]);
            if (!is_array($response->json())) {
                throw new RuntimeException(__('The shop did not send page :page of its products', ['page' => $page]));
            }

            $pages    = (int) $response->header('X-WP-TotalPages') ?: 1;
            $products = [...$products, ...$response->json()];
        }

        return $products;
    }

    /**
     * @return array<string, array{code: string, name: string, price: float, rrp: null, barcode: null, minimum_order: null, image_url: string|null, url: string|null, data: string}>
     */
    public static function parseWooCommerce(array $products): array
    {
        $rows = [];
        foreach ($products as $product) {
            $price = (float) ($product['prices']['price'] ?? 0) / 10 ** (int) ($product['prices']['currency_minor_unit'] ?? 2);
            if (($product['type'] ?? '') === 'variable' || $price <= 0) {
                continue;
            }

            $rows[(string) $product['id']] = [
                'code'          => (string) $product['id'],
                'name'          => mb_substr(Str::squish(html_entity_decode(strip_tags($product['name'].' '.($product['variation'] ?? '')))), 0, 500),
                'price'         => $price,
                'rrp'           => null,
                'barcode'       => null,
                'minimum_order' => null,
                'image_url'     => $product['images'][0]['src'] ?? null,
                'url'           => $product['permalink'] ?? null,
                'data'          => json_encode(array_filter([
                    'description' => mb_substr(Str::squish(html_entity_decode(strip_tags(($product['short_description'] ?? '') ?: ($product['description'] ?? '')))), 0, 400),
                ])),
            ];
        }

        return $rows;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        Competitor::whereNull('feed_url')
            ->whereNull('search_url')
            ->when($command->option('competitor'), fn ($query, $id) => $query->where('id', $id))
            ->each(function (Competitor $competitor) use ($command) {
                if ($feedUrl = static::findShopifyFeed($competitor->website) ?? static::findWooCommerceFeed($competitor->website)) {
                    $competitor->update(['feed_url' => $feedUrl]);
                    $command->info("$competitor->name: its shop's public catalogue is read as the feed");
                }
            });

        $competitors = Competitor::whereNotNull('feed_url')
            ->when($command->option('competitor'), fn ($query, $id) => $query->where('id', $id))
            ->get();

        foreach ($competitors as $competitor) {
            $command->info("$competitor->name: ".$this->handle($competitor).' products in the feed');

            if (!$command->option('no-match') && $competitor->status === CompetitorStatusEnum::OK) {
                $command->info("$competitor->name: ".MatchCompetitorProducts::make()->queue($competitor, sync: (bool) $command->option('sync')).' products to match');
            }
        }

        return 0;
    }
}
