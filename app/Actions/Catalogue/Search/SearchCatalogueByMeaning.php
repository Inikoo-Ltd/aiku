<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Search;

use App\Actions\CRM\RangeAdvisor\WithRecommendableProducts;
use App\Actions\Helpers\AI\EmbedTexts;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Nearest products and families of a shop to what the customer asked for, by cosine distance
 * over catalogue_search_embeddings. Price, stock and visibility come live from the products.
 *
 * ponytail: no ANN index, each query is an exact scan of one shop's ~10k rows. Add an HNSW
 * index (vector_cosine_ops) when a shop passes roughly 100k rows.
 */
class SearchCatalogueByMeaning
{
    use AsAction;
    use WithRecommendableProducts;

    private const int MAX_QUERY = 500;

    public function handle(Shop $shop, string $query, ?Customer $customer = null, int $limit = 12): Collection
    {
        $vector = $this->embedQuery($query);

        if ($vector === null) {
            return collect();
        }

        return $this->recommendableProducts($shop, $customer)
            ->join('catalogue_search_embeddings', fn (JoinClause $join) => $join->on('catalogue_search_embeddings.model_id', '=', 'products.id')->where('catalogue_search_embeddings.model_type', 'Product'))
            ->addSelect('products.*')
            ->selectVectorDistance('catalogue_search_embeddings.embedding', $vector, 'distance')
            ->reorder()
            ->orderByVectorDistance('catalogue_search_embeddings.embedding', $vector)
            ->limit($limit)
            ->get();
    }

    public function families(Shop $shop, string $query, int $limit = 6): Collection
    {
        $vector = $this->embedQuery($query);

        if ($vector === null) {
            return collect();
        }

        return ProductCategory::where('product_categories.shop_id', $shop->id)
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('product_categories.state', ProductCategoryStateEnum::ACTIVE)
            ->where('product_categories.is_in_website', true)
            ->join('catalogue_search_embeddings', fn (JoinClause $join) => $join->on('catalogue_search_embeddings.model_id', '=', 'product_categories.id')->where('catalogue_search_embeddings.model_type', 'ProductCategory'))
            ->addSelect('product_categories.*')
            ->selectVectorDistance('catalogue_search_embeddings.embedding', $vector, 'distance')
            ->orderByVectorDistance('catalogue_search_embeddings.embedding', $vector)
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int, float>|null
     */
    private function embedQuery(string $query): ?array
    {
        $query = mb_substr(trim($query), 0, self::MAX_QUERY);

        if ($query === '') {
            return null;
        }

        return EmbedTexts::run([$query])[0] ?? null;
    }
}
