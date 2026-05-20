<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 20 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Wati\UI;

use App\Actions\Comms\Mailshot\UI\HasUIMailshots;
use App\Actions\OrgAction;
use App\Http\Resources\Comms\WatiTemplateResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WatiTemplate;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWatiTemplates extends OrgAction
{
    use HasUIMailshots;

    public Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('wati_templates.element_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WatiTemplate::class)
            ->where('shop_id', $parent->id);

        return $queryBuilder
            ->defaultSort('-wati_templates.created_at')
            ->select([
                'wati_templates.id',
                'wati_templates.element_name',
                'wati_templates.category',
                'wati_templates.status',
                'wati_templates.created_at',
            ])
            ->allowedSorts(['element_name', 'category', 'status', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'element_name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'category', label: __('category'), canBeHidden: false, sortable: true)
                ->column(key: 'status', label: __('status'), canBeHidden: false, sortable: true)
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true, align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $watiTemplates, ActionRequest $request): Response
    {
        $title = __('Wati Templates');

        return Inertia::render(
            'Comms/WatiTemplates',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    $this->parent
                ),
                'title'    => $title,
                'pageHead' => [
                    'title' => $title,
                    'icon'  => ['fal', 'fa-comment'],
                ],
                'data' => WatiTemplateResource::collection($watiTemplates),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
