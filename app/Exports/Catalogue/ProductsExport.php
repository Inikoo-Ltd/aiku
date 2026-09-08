<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 31 Jul 2026 10:12:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Catalogue;

use App\Actions\Catalogue\Product\UI\IndexProductsInCatalogue;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Spatie\QueryBuilder\AllowedFilter;

class ProductsExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param array<int, string> $fields Selected field keys; empty means all fields.
     */
    public function __construct(public Shop $shop, public ?string $bucket = null, public array $fields = [], public ?string $prefix = null)
    {
    }

    /**
     * Ordered registry of exportable fields: key => [heading, select].
     *
     * @return array<string, array{heading: string, select: string}>
     */
    public static function fieldDefinitions(): array
    {
        return [
            'id'                    => ['heading' => '#', 'select' => 'products.id'],
            'code'                  => ['heading' => 'Code', 'select' => 'products.code'],
            'name'                  => ['heading' => 'Name', 'select' => 'products.name'],
            'state'                 => ['heading' => 'State', 'select' => 'products.state'],
            'status'                => ['heading' => 'Status', 'select' => 'products.status'],
            'barcode'               => ['heading' => 'Barcode', 'select' => 'products.barcode'],
            'cpnp_number'           => ['heading' => 'CPNP number', 'select' => 'products.cpnp_number'],
            'ufi_number'            => ['heading' => 'UFI (Poison Centres)', 'select' => 'products.ufi_number'],
            'scpn_number'           => ['heading' => 'SCPN number', 'select' => 'products.scpn_number'],
            'parts'                 => ['heading' => 'Parts', 'select' => self::partsExpression()],
            'department'            => ['heading' => 'Department', 'select' => 'departments.code'],
            'sub_department'        => ['heading' => 'Sub department', 'select' => 'sub_departments.code'],
            'family'                => ['heading' => 'Family', 'select' => 'families.code'],
            'family_name'           => ['heading' => 'Family name', 'select' => 'families.name'],
            'units'                 => ['heading' => 'Units per outer', 'select' => 'products.units'],
            'price'                 => ['heading' => 'Outer price', 'select' => 'products.price'],
            'cost'                  => ['heading' => 'Outer cost', 'select' => self::costExpression()],
            'margin'                => ['heading' => 'Margin (%)', 'select' => self::marginExpression()],
            'unit_price'            => ['heading' => 'Unit price', 'select' => 'COALESCE(products.unit_price, products.price / NULLIF(products.units, 0))'],
            'unit'                  => ['heading' => 'Unit label', 'select' => 'products.unit'],
            'unit_name'             => ['heading' => 'Unit name', 'select' => self::tradeUnitNamesExpression()],
            'brand'                 => ['heading' => 'Brand', 'select' => self::brandsExpression()],
            'rrp'                   => ['heading' => 'Outer RRP', 'select' => 'products.rrp'],
            'unit_rrp'              => ['heading' => 'Unit RRP', 'select' => 'products.rrp / NULLIF(products.units, 0)'],
            'currency'              => ['heading' => 'Currency', 'select' => 'currencies.code'],
            'marketing_weight'      => ['heading' => 'Unit weight (marketing)', 'select' => 'products.marketing_weight'],
            'gross_weight'          => ['heading' => 'Gross weight', 'select' => 'products.gross_weight'],
            'marketing_dimensions'  => ['heading' => 'Unit dimensions', 'select' => self::dimensionsExpression()],
            'marketing_ingredients' => ['heading' => 'Materials/Ingredients', 'select' => 'products.marketing_ingredients'],
            'description_title'     => ['heading' => 'Webpage description title', 'select' => 'products.description_title'],
            'description'           => ['heading' => 'Webpage description (html)', 'select' => 'products.description'],
            'description_plain'     => ['heading' => 'Webpage description (plain text)', 'select' => self::plainTextExpression('products.description')],
            'description_extra'     => ['heading' => 'Webpage extra description (plain text)', 'select' => self::plainTextExpression('products.description_extra')],
            'country_of_origin'     => ['heading' => 'Country of origin', 'select' => 'COALESCE(countries.name, products.country_of_origin)'],
            'tariff_code'           => ['heading' => 'Tariff code', 'select' => 'products.tariff_code'],
            'duty_rate'             => ['heading' => 'Duty rate', 'select' => 'products.duty_rate'],
            'hts_us'                => ['heading' => 'HTS US', 'select' => 'products.hts_us'],
            'available_quantity'    => ['heading' => 'Available quantity', 'select' => 'products.available_quantity'],
            'url'                   => ['heading' => 'Webpage url', 'select' => 'products.url'],
            'created_at'            => ['heading' => 'Creation date', 'select' => 'products.created_at'],
        ];
    }

    protected static function partsExpression(): string
    {
        return "(SELECT STRING_AGG(org_stocks.code, ', ' ORDER BY org_stocks.code) "
            .'FROM product_has_org_stocks '
            .'JOIN org_stocks ON org_stocks.id = product_has_org_stocks.org_stock_id '
            .'WHERE product_has_org_stocks.product_id = products.id)';
    }

    protected static function costExpression(): string
    {
        return '(SELECT ROUND(SUM(org_stocks.current_supplier_sku_cost * product_has_org_stocks.quantity), 2) '
            .'FROM product_has_org_stocks '
            .'JOIN org_stocks ON org_stocks.id = product_has_org_stocks.org_stock_id '
            .'WHERE product_has_org_stocks.product_id = products.id)';
    }

    protected static function marginExpression(): string
    {
        return 'ROUND(((products.price - '.self::costExpression().') / NULLIF(products.price, 0)) * 100, 2)';
    }

    protected static function tradeUnitNamesExpression(): string
    {
        return "(SELECT STRING_AGG(trade_units.name, ', ' ORDER BY trade_units.name) "
            .'FROM model_has_trade_units '
            .'JOIN trade_units ON trade_units.id = model_has_trade_units.trade_unit_id '
            ."WHERE model_has_trade_units.model_type = 'Product' "
            .'AND model_has_trade_units.model_id = products.id)';
    }

    protected static function brandsExpression(): string
    {
        return "(SELECT STRING_AGG(brands.name, ', ' ORDER BY brands.name) "
            .'FROM model_has_brands '
            .'JOIN brands ON brands.id = model_has_brands.brand_id '
            ."WHERE model_has_brands.model_type = 'Product' "
            .'AND model_has_brands.model_id = products.id)';
    }

    protected static function dimensionsExpression(): string
    {
        return "NULLIF(CONCAT_WS(' ', "
            ."NULLIF(CONCAT_WS(' x ', "
            ."products.marketing_dimensions->>'l', "
            ."products.marketing_dimensions->>'w', "
            ."products.marketing_dimensions->>'h'"
            ."), ''), "
            ."products.marketing_dimensions->>'units'"
            ."), '')";
    }

    protected static function plainTextExpression(string $column): string
    {
        return "TRIM(REGEXP_REPLACE(REGEXP_REPLACE($column, '<[^>]*>', ' ', 'g'), '\\s+', ' ', 'g'))";
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

    public function query(): QueryBuilder
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
        $queryBuilder->where('products.is_main', true);
        $queryBuilder->where('products.shop_id', $this->shop->id);
        $queryBuilder->whereNull('products.exclusive_for_customer_id');

        if ($this->bucket == 'current') {
            $queryBuilder->whereIn('products.state', [ProductStateEnum::ACTIVE, ProductStateEnum::DISCONTINUING]);
        } elseif ($this->bucket == 'discontinued') {
            $queryBuilder->where('products.state', ProductStateEnum::DISCONTINUED);
        } elseif ($this->bucket == 'in_process') {
            $queryBuilder->where('products.state', ProductStateEnum::IN_PROCESS);
        }

        foreach (IndexProductsInCatalogue::make()->getElementGroups($this->shop, $this->bucket) as $key => $elementGroup) {
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
        return $this->query()->getEloquentBuilder()->toBase()->count();
    }

    public function dataQuery(): Builder
    {
        $definitions = self::fieldDefinitions();

        $selects = array_map(
            fn ($field) => DB::raw($definitions[$field]['select'].' as '.$field),
            $this->selectedFields()
        );

        return $this->query()->getEloquentBuilder()->toBase()
            ->leftJoin('product_categories as families', 'products.family_id', '=', 'families.id')
            ->leftJoin('product_categories as sub_departments', 'products.sub_department_id', '=', 'sub_departments.id')
            ->leftJoin('product_categories as departments', 'products.department_id', '=', 'departments.id')
            ->leftJoin('countries', 'products.origin_country_id', '=', 'countries.id')
            ->leftJoin('currencies', 'products.currency_id', '=', 'currencies.id')
            ->select($selects)
            ->orderBy('products.code');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->dataQuery()->get()
            ->map(fn ($row) => array_values((array) $row))
            ->all();
    }

    public function headings(): array
    {
        $definitions = self::fieldDefinitions();

        return array_map(
            fn ($field) => $definitions[$field]['heading'],
            $this->selectedFields()
        );
    }
}
