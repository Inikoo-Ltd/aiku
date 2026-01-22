<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 19 Jan 2026 14:35:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate\UI;

use App\Actions\OrgAction;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexEmailTemplates extends OrgAction
{
    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereAnyWordStartWith('email_templates.name', $value);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(EmailTemplate::class);
        $queryBuilder->join('shops', 'email_templates.shop_id', '=', 'shops.id')
            ->where('email_templates.shop_id', $shop->id)
            ->where('email_templates.is_seeded', false)
            ->where('email_templates.builder', EmailTemplateBuilderEnum::BEEFREE->value)
            ->where('email_templates.state', EmailTemplateStateEnum::ACTIVE->value);
        $queryBuilder
            ->select([
                'email_templates.id',
                'email_templates.name',
                'shops.name as shop_name',
                'email_templates.created_at'
            ]);

        return $queryBuilder
            ->allowedSorts(['created_at', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }
            $table->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true);
            $table->column(key: 'title', label: __('Title'), canBeHidden: false, sortable: true);
            $table->column(key: 'created_at', label: __('Created'), canBeHidden: false, sortable: true);
            $table->column(key: 'actions', label: __('Action'));
            $table->defaultSort('-created_at');
        };
    }
}
