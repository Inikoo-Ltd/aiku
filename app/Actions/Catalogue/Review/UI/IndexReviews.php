<?php

namespace App\Actions\Catalogue\Review\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\Review;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReviews extends OrgAction
{
    public function getStats(Shop $shop): array
    {
        $row = Review::query()
            ->where('shop_id', $shop->id)
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(AVG(rating_main), 0) as average_rating,
                COUNT(*) FILTER (WHERE review_status = ?) as status_approved,
                COUNT(*) FILTER (WHERE review_status = ?) as status_pending,
                COUNT(*) FILTER (WHERE review_status = ?) as status_rejected,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 1) as number_reviews_rating_1,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 2) as number_reviews_rating_2,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 3) as number_reviews_rating_3,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 4) as number_reviews_rating_4,
                COUNT(*) FILTER (WHERE ROUND(rating_main) = 5) as number_reviews_rating_5,
                COALESCE(AVG(rating_a), 0) as average_rating_a,
                COALESCE(AVG(rating_b), 0) as average_rating_b,
                COALESCE(AVG(rating_c), 0) as average_rating_c,
                COALESCE(AVG(rating_d), 0) as average_rating_d,
                COALESCE(AVG(rating_e), 0) as average_rating_e
            ', [
                ReviewStatusEnum::APPROVED->value,
                ReviewStatusEnum::PENDING->value,
                ReviewStatusEnum::REJECTED->value,
            ])
            ->first();

        return [
            'total'                   => (int) $row->total,
            'average_rating'          => (float) $row->average_rating,
            'status_approved'         => (int) $row->status_approved,
            'status_pending'          => (int) $row->status_pending,
            'status_rejected'         => (int) $row->status_rejected,
            'number_reviews_rating_1' => (int) $row->number_reviews_rating_1,
            'number_reviews_rating_2' => (int) $row->number_reviews_rating_2,
            'number_reviews_rating_3' => (int) $row->number_reviews_rating_3,
            'number_reviews_rating_4' => (int) $row->number_reviews_rating_4,
            'number_reviews_rating_5' => (int) $row->number_reviews_rating_5,
            'average_rating_a'        => (float) $row->average_rating_a,
            'average_rating_b'        => (float) $row->average_rating_b,
            'average_rating_c'        => (float) $row->average_rating_c,
            'average_rating_d'        => (float) $row->average_rating_d,
            'average_rating_e'        => (float) $row->average_rating_e,
        ];
    }

    public function handle(ProductCategory|Product|Shop $parent, ?string $prefix = null, ?string $scope = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $table = (new Review())->getTable();
        $foreignKey = $this->foreignKey($parent);

        return QueryBuilder::for(Review::class)
            ->with('media')
            ->leftJoin('customers', 'customers.id', '=', $table . '.customer_id')
            ->where($table . '.' . $foreignKey, $parent->id)
            ->when($scope === 'product', fn ($query) => $query->whereNotNull($table . '.product_id'))
            ->when($scope === 'family', fn ($query) => $query->whereNotNull($table . '.product_category_id'))
            ->when($scope === 'overall', fn ($query) => $query->whereNull($table . '.product_id')->whereNull($table . '.product_category_id'))
            ->defaultSort('-' . $table . '.created_at')
            ->select([
                $table . '.id',
                $table . '.customer_id',
                $table . '.review_status as status',
                $table . '.rating_main as rating',
                $table . '.rating_a',
                $table . '.rating_b',
                $table . '.rating_c',
                $table . '.rating_d',
                $table . '.rating_e',
                $table . '.message',
                $table . '.likes',
                $table . '.replied',
                $table . '.reply_message',
                $table . '.reply_at',
                $table . '.meta',
                $table . '.created_at',
                'customers.contact_name as contact_name',
            ])
            ->allowedSorts(['id', 'created_at', 'rating', 'likes'])
            ->allowedFilters([$globalSearch, 'status', 'rating', 'contact_name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function inProductCategory(ProductCategory $parent, ?string $prefix = null): LengthAwarePaginator
    {
        return $this->handle($parent, $prefix);
    }

    public function inProduct(Product $parent, ?string $prefix = null): LengthAwarePaginator
    {
        return $this->handle($parent, $prefix);
    }

    public function inShop(Shop $parent, ?string $prefix = null): LengthAwarePaginator
    {
        return $this->handle($parent, $prefix);
    }

    public function tableStructure(ProductCategory|Product|Shop $parent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->defaultSort('likes')
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No reviews found'),
                    'count' => 0,
                ]);

            $table->column(key: 'created_at', label: __('Created'), sortable: true, type: 'date');
            $table->column(key: 'image_thumbnails', label: __('Images'), sortable: false, searchable: false);
            $table->column(key: 'contact_name', label: __('Name'), sortable: false, searchable: true);
            $table->column(key: 'rating', label: __('Rating'), sortable: true, searchable: false, align: 'right');
            $table->column(key: 'message', label: __('Message'), sortable: false, searchable: true);
            $table->column(key: 'reply_status', label: __('Reply Status'), sortable: false, searchable: false, align: 'center');
            $table->column(key: 'likes', label: __('Like'), sortable: true, searchable: false, align: 'right');
            $table->column(key: 'action', label: __('Actions'), sortable: false, searchable: false, align: 'right');
        };
    }

    private function foreignKey(ProductCategory|Product|Shop $parent): string
    {
        if ($parent instanceof Product) {
            return 'product_id';
        }

        if ($parent instanceof Shop) {
            return 'shop_id';
        }

        return 'product_category_id';
    }
}
