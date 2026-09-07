<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\Catalogue;

use App\Actions\Catalogue\Product\UI\IndexProductsWithNoImage;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\QueryBuilder\AllowedFilter;

class ProductsWithNoImageExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param array<int, string> $fields Selected field keys; empty means all fields.
     */
    public function __construct(public Shop $shop, public array $fields = [], public ?string $prefix = null)
    {
    }

    /**
     * Ordered registry of exportable fields: key => heading.
     *
     * @return array<string, string>
     */
    public static function fieldDefinitions(): array
    {
        return [
            'id'               => '#',
            'code'             => 'Code',
            'state'            => 'State',
            'not_for_sale'     => 'Not for sale',
            'webpage_url'      => 'Webpage url',
            'live_product_url' => 'Live product url',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function selectedFields(): array
    {
        $keys = array_keys(self::fieldDefinitions());

        if (count($this->fields) === 0) {
            return $keys;
        }

        return array_values(array_intersect($keys, $this->fields));
    }

    protected function backOfficeUrlPrefix(): string
    {
        return route('grp.org.shops.show.catalogue.products.no_image_product.index', [
            'organisation' => $this->shop->organisation->slug,
            'shop'         => $this->shop->slug,
        ]);
    }

    protected function filteredQuery(): QueryBuilder
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        if ($this->prefix) {
            InertiaTable::updateQueryBuilderParameters($this->prefix);
        }

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $this->shop->id);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');
        $queryBuilder->whereNull('products.image_id');

        foreach (IndexProductsWithNoImage::make()->getElementGroups($this->shop) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $this->prefix
            );
        }

        return $queryBuilder->allowedFilters([$globalSearch]);
    }

    public function count(): int
    {
        return $this->filteredQuery()->getEloquentBuilder()->toBase()->count();
    }

    public function query(): Builder
    {
        return $this->filteredQuery()->getEloquentBuilder()
            ->with('webpage:id,model_type,model_id,canonical_url')
            ->select(['products.id', 'products.code', 'products.slug', 'products.state', 'products.is_for_sale'])
            ->orderBy('products.code')
            ->orderBy('products.id');
    }

    /**
     * @param Product $product
     *
     * @return array<int, mixed>
     */
    public function map($product): array
    {
        $row = [
            'id'               => $product->id,
            'code'             => $product->code,
            'state'            => $product->state->label(),
            'not_for_sale'     => $product->is_for_sale ? __('No') : __('Yes'),
            'webpage_url'      => $this->backOfficeUrlPrefix().'/'.$product->slug.'?tab=images',
            'live_product_url' => $product->webpage?->canonical_url,
        ];

        return array_values(array_intersect_key($row, array_flip($this->selectedFields())));
    }

    public function headings(): array
    {
        $definitions = self::fieldDefinitions();

        return array_map(
            fn ($field) => $definitions[$field],
            $this->selectedFields()
        );
    }
}
