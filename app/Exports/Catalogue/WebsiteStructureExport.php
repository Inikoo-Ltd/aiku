<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\Catalogue;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WebsiteStructureExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param array<int, string> $fields Selected field keys; empty means all fields.
     */
    public function __construct(public ProductCategory $department, public array $fields = [])
    {
    }

    /**
     * Ordered registry of exportable fields: key => [heading, select per branch of the union].
     *
     * @return array<string, array{heading: string, product_category: string, collection: string}>
     */
    public static function fieldDefinitions(): array
    {
        return [
            'level'                  => [
                'heading'          => 'Level',
                'product_category' => "CASE product_categories.type WHEN 'department' THEN 'Department' WHEN 'sub_department' THEN 'Sub department' ELSE 'Family' END",
                'collection'       => "'Collection'::text",
            ],
            'path'                   => [
                'heading'          => 'Path',
                'product_category' => "CONCAT_WS(' > ', departments.name, sub_departments.name, COALESCE(product_categories.name, product_categories.code))",
                'collection'       => "CONCAT_WS(' > ', collection_departments.name, collection_sub_departments.name, COALESCE(collections.name, collections.code))",
            ],
            'code'                   => [
                'heading'          => 'Code',
                'product_category' => 'product_categories.code',
                'collection'       => 'collections.code',
            ],
            'name'                   => [
                'heading'          => 'Name',
                'product_category' => 'product_categories.name',
                'collection'       => 'collections.name',
            ],
            'department'             => [
                'heading'          => 'Department',
                'product_category' => "COALESCE(departments.code, CASE WHEN product_categories.type = 'department' THEN product_categories.code END)",
                'collection'       => 'collection_departments.code',
            ],
            'sub_department'         => [
                'heading'          => 'Sub department',
                'product_category' => "COALESCE(sub_departments.code, CASE WHEN product_categories.type = 'sub_department' THEN product_categories.code END)",
                'collection'       => 'collection_sub_departments.code',
            ],
            'state'                  => [
                'heading'          => 'State',
                'product_category' => 'product_categories.state',
                'collection'       => 'collections.state',
            ],
            'show_in_website'        => [
                'heading'          => 'Show in website',
                'product_category' => 'product_categories.show_in_website',
                'collection'       => 'NULL::boolean',
            ],
            'is_in_website'          => [
                'heading'          => 'Online',
                'product_category' => 'product_categories.is_in_website',
                'collection'       => 'collections.is_in_website',
            ],
            'url'                    => [
                'heading'          => 'Url',
                'product_category' => 'product_categories.url',
                'collection'       => 'collections.url',
            ],
            'webpage_url'            => [
                'heading'          => 'Webpage url',
                'product_category' => 'webpages.url',
                'collection'       => 'webpages.url',
            ],
            'full_url'               => [
                'heading'          => 'Full url',
                'product_category' => self::fullUrlExpression(),
                'collection'       => self::fullUrlExpression(),
            ],
            'webpage_state'          => [
                'heading'          => 'Webpage state',
                'product_category' => 'webpages.state',
                'collection'       => 'webpages.state',
            ],
            'canonical_url'          => [
                'heading'          => 'Canonical url',
                'product_category' => 'webpages.canonical_url',
                'collection'       => 'webpages.canonical_url',
            ],
            'index_page'             => [
                'heading'          => 'Indexable',
                'product_category' => 'webpages.index_page',
                'collection'       => 'webpages.index_page',
            ],
            'follow_link'            => [
                'heading'          => 'Follow links',
                'product_category' => 'webpages.follow_link',
                'collection'       => 'webpages.follow_link',
            ],
            'seo_title'              => [
                'heading'          => 'SEO title',
                'product_category' => 'webpages.seo_title',
                'collection'       => 'webpages.seo_title',
            ],
            'seo_description'        => [
                'heading'          => 'SEO description',
                'product_category' => 'webpages.seo_description',
                'collection'       => 'webpages.seo_description',
            ],
            'description_title'      => [
                'heading'          => 'Description title',
                'product_category' => 'product_categories.description_title',
                'collection'       => 'collections.description_title',
            ],
            'description'            => [
                'heading'          => 'Description (plain text)',
                'product_category' => self::plainTextExpression('product_categories.description'),
                'collection'       => self::plainTextExpression('collections.description'),
            ],
            'description_extra'      => [
                'heading'          => 'Extra description (plain text)',
                'product_category' => self::plainTextExpression('product_categories.description_extra'),
                'collection'       => self::plainTextExpression('collections.description_extra'),
            ],
            'number_sub_departments' => [
                'heading'          => 'Number of sub departments',
                'product_category' => 'product_category_stats.number_sub_departments',
                'collection'       => 'collection_stats.number_sub_departments',
            ],
            'number_families'        => [
                'heading'          => 'Number of families',
                'product_category' => 'product_category_stats.number_families',
                'collection'       => 'collection_stats.number_families',
            ],
            'number_collections'     => [
                'heading'          => 'Number of collections',
                'product_category' => 'product_category_stats.number_collections',
                'collection'       => 'collection_stats.number_collections',
            ],
            'number_products'        => [
                'heading'          => 'Number of products',
                'product_category' => 'product_category_stats.number_products',
                'collection'       => 'collection_stats.number_products',
            ],
            'created_at'             => [
                'heading'          => 'Creation date',
                'product_category' => 'product_categories.created_at',
                'collection'       => 'collections.created_at',
            ],
        ];
    }

    protected static function fullUrlExpression(): string
    {
        return "CASE WHEN websites.domain IS NOT NULL AND webpages.url IS NOT NULL "
            ."THEN 'https://' || websites.domain || '/' || webpages.url END";
    }

    protected static function plainTextExpression(string $column): string
    {
        return "TRIM(REGEXP_REPLACE(REGEXP_REPLACE($column, '<[^>]*>', ' ', 'g'), '\\s+', ' ', 'g'))";
    }

    /**
     * Tree ordering key: department, then sub department, then the node's own rank
     * inside its parent (sub departments, families, collections) and its code.
     * Nodes with no department sort last.
     */
    protected static function productCategorySortExpression(): string
    {
        $department = "COALESCE(departments.code, CASE WHEN product_categories.type = 'department' THEN product_categories.code END)";

        return "CONCAT_WS('|', "
            ."CASE WHEN $department IS NULL THEN '1' ELSE '0' END, "
            ."COALESCE($department, ''), "
            ."COALESCE(sub_departments.code, CASE WHEN product_categories.type = 'sub_department' THEN product_categories.code END, ''), "
            ."CASE product_categories.type WHEN 'department' THEN '0' WHEN 'sub_department' THEN '1' ELSE '2' END, "
            .'product_categories.code)';
    }

    protected static function collectionSortExpression(): string
    {
        return "CONCAT_WS('|', "
            ."CASE WHEN collection_departments.code IS NULL THEN '1' ELSE '0' END, "
            ."COALESCE(collection_departments.code, ''), "
            ."COALESCE(collection_sub_departments.code, ''), "
            ."'3', "
            .'collections.code)';
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

    /**
     * @return array<int, \Illuminate\Contracts\Database\Query\Expression>
     */
    protected function selects(string $branch, string $sortExpression): array
    {
        $definitions = self::fieldDefinitions();

        $selects = array_map(
            fn ($field) => DB::raw($definitions[$field][$branch].' as '.$field),
            $this->selectedFields()
        );

        $selects[] = DB::raw($sortExpression.' as sort_path');

        return $selects;
    }

    protected function productCategoriesQuery(): Builder
    {
        return DB::table('product_categories')
            ->leftJoin('product_categories as departments', 'product_categories.department_id', '=', 'departments.id')
            ->leftJoin('product_categories as sub_departments', 'product_categories.sub_department_id', '=', 'sub_departments.id')
            ->leftJoin('product_category_stats', 'product_category_stats.product_category_id', '=', 'product_categories.id')
            ->leftJoin('webpages', 'webpages.id', '=', 'product_categories.webpage_id')
            ->leftJoin('websites', 'websites.id', '=', 'webpages.website_id')
            ->where(function ($query) {
                $query->where('product_categories.id', $this->department->id)
                    ->orWhere('product_categories.department_id', $this->department->id);
            })
            ->whereNull('product_categories.deleted_at')
            ->select($this->selects('product_category', self::productCategorySortExpression()));
    }

    protected function collectionsQuery(): Builder
    {
        return DB::table('collections')
            ->leftJoin('model_has_collections', function ($join) {
                $join->on('model_has_collections.collection_id', '=', 'collections.id')
                    ->where('model_has_collections.model_type', '=', 'ProductCategory');
            })
            ->leftJoin('product_categories as collection_parents', 'collection_parents.id', '=', 'model_has_collections.model_id')
            ->leftJoin('product_categories as collection_departments', function ($join) {
                $join->on('collection_departments.id', '=', DB::raw("CASE WHEN collection_parents.type = 'department' THEN collection_parents.id ELSE collection_parents.department_id END"));
            })
            ->leftJoin('product_categories as collection_sub_departments', function ($join) {
                $join->on('collection_sub_departments.id', '=', DB::raw("CASE WHEN collection_parents.type = 'sub_department' THEN collection_parents.id ELSE collection_parents.sub_department_id END"));
            })
            ->leftJoin('collection_stats', 'collection_stats.collection_id', '=', 'collections.id')
            ->leftJoin('webpages', 'webpages.id', '=', 'collections.webpage_id')
            ->leftJoin('websites', 'websites.id', '=', 'webpages.website_id')
            ->where('collection_departments.id', $this->department->id)
            ->whereNull('collections.deleted_at')
            ->select($this->selects('collection', self::collectionSortExpression()));
    }

    public function dataQuery(): Builder
    {
        return DB::query()
            ->fromSub(
                $this->productCategoriesQuery()->unionAll($this->collectionsQuery()),
                'website_structure'
            )
            ->select($this->selectedFields())
            ->orderBy('sort_path');
    }

    public function count(): int
    {
        return DB::query()
            ->fromSub($this->productCategoriesQuery()->unionAll($this->collectionsQuery()), 'website_structure')
            ->count();
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
