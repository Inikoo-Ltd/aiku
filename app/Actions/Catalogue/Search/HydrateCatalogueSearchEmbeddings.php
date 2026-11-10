<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Search;

use App\Actions\Helpers\AI\EmbedTexts;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Meaning-based search index of what a shop shows on its website. Only name and description
 * text is embedded: price, stock and discounts are read live from products when searching.
 */
class HydrateCatalogueSearchEmbeddings
{
    use AsAction;

    public string $commandSignature = 'catalogue:embed-search {--shop= : shop slug to index, also the first fill of a new shop}';

    public string $commandDescription = 'Embed the website products and families of a shop for meaning-based search (changed text only)';

    private const int BATCH = 50;

    private const int MAX_TEXT = 2000;

    /**
     * @return array{embedded: int, unchanged: int, removed: int, failed: int}
     */
    public function handle(Shop $shop): array
    {
        $counts = ['embedded' => 0, 'unchanged' => 0, 'removed' => 0, 'failed' => 0];

        $this->websiteProducts($shop)->select(['id', 'shop_id', 'name', 'description', 'description_extra', 'family_id', 'department_id'])
            ->with(['family:id,name', 'department:id,name'])
            ->chunkById(self::BATCH, function (Collection $products) use ($shop, &$counts) {
                $this->embed($shop, 'Product', $products->mapWithKeys(fn (Product $product) => [
                    $product->id => $this->text([$product->name, $product->family?->name, $product->department?->name, $product->description, $product->description_extra]),
                ]), $counts);
            });

        $this->websiteFamilies($shop)->select(['id', 'shop_id', 'name', 'description', 'description_extra', 'department_id'])
            ->with('department:id,name')
            ->chunkById(self::BATCH, function (Collection $families) use ($shop, &$counts) {
                $this->embed($shop, 'ProductCategory', $families->mapWithKeys(fn (ProductCategory $family) => [
                    $family->id => $this->text([$family->name, $family->department?->name, $family->description, $family->description_extra]),
                ]), $counts);
            });

        $counts['removed'] = DB::table('catalogue_search_embeddings')->where('shop_id', $shop->id)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('model_type', 'Product')->whereNotIn('model_id', $this->websiteProducts($shop)->select('id')))
                ->orWhere(fn ($query) => $query->where('model_type', 'ProductCategory')->whereNotIn('model_id', $this->websiteFamilies($shop)->select('id'))))
            ->delete();

        return $counts;
    }

    private function websiteProducts(Shop $shop): Builder
    {
        return Product::where('shop_id', $shop->id)->where('is_in_website', true);
    }

    private function websiteFamilies(Shop $shop): Builder
    {
        return ProductCategory::where('shop_id', $shop->id)->where('type', ProductCategoryTypeEnum::FAMILY)->where('is_in_website', true);
    }

    /**
     * Name, family and department names first, then the descriptions as plain text, capped so
     * the embedding is about what the item is rather than a long blurb.
     *
     * @param  array<int, string|null>  $parts
     */
    private function text(array $parts): string
    {
        $plain = array_map(
            fn (?string $part) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $part)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
            $parts
        );

        return mb_substr(implode("\n", array_unique(array_filter($plain, fn (string $part) => $part !== ''))), 0, self::MAX_TEXT);
    }

    /**
     * @param  Collection<int, string>  $texts  keyed by model id
     * @param  array{embedded: int, unchanged: int, removed: int, failed: int}  $counts
     */
    private function embed(Shop $shop, string $modelType, Collection $texts, array &$counts): void
    {
        $texts  = $texts->filter(fn (string $text) => $text !== '');
        $hashes = DB::table('catalogue_search_embeddings')->where('model_type', $modelType)->whereIn('model_id', $texts->keys())->pluck('content_hash', 'model_id');
        $stale  = $texts->filter(fn (string $text, int $id) => ($hashes[$id] ?? null) !== sha1($text));

        $counts['unchanged'] += $texts->count() - $stale->count();

        if ($stale->isEmpty()) {
            return;
        }

        $vectors = EmbedTexts::run($stale->values()->all(), true);

        if ($vectors === null || count($vectors) !== $stale->count()) {
            $counts['failed'] += $stale->count();

            return;
        }

        $now = now();

        DB::table('catalogue_search_embeddings')->upsert(
            $stale->keys()->values()->map(fn (int $id, int $i) => [
                'shop_id'      => $shop->id,
                'model_type'   => $modelType,
                'model_id'     => $id,
                'content_hash' => sha1($stale[$id]),
                'embedding'    => json_encode($vectors[$i]),
                'created_at'   => $now,
                'updated_at'   => $now,
            ])->all(),
            ['model_type', 'model_id'],
            ['shop_id', 'content_hash', 'embedding', 'updated_at']
        );

        $counts['embedded'] += $stale->count();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shops = $command->option('shop')
            ? Shop::where('slug', $command->option('shop'))->get()
            : Shop::whereIn('id', DB::table('catalogue_search_embeddings')->distinct()->select('shop_id'))->get();

        if ($command->option('shop') && $shops->isEmpty()) {
            $command->error('Shop not found: '.$command->option('shop'));

            return 1;
        }

        foreach ($shops as $shop) {
            $counts = $this->handle($shop);
            $command->line("$shop->slug: embedded {$counts['embedded']}, unchanged {$counts['unchanged']}, removed {$counts['removed']}, failed {$counts['failed']}");
        }

        return 0;
    }
}
