<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Templates\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Http\Resources\Chat\MetaMessageTemplatesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWhatsappMessageTemplates extends OrgAction
{
    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('meta_message_templates.name', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MetaMessageTemplate::class);
        $queryBuilder->where('meta_message_templates.shop_id', $shop->id);
        $queryBuilder->whereHas('metaChannel', function ($query) {
            $query->where('code', 'whatsapp');
        });

        return $queryBuilder
            ->defaultSort('meta_message_templates.name')
            ->select([
                'meta_message_templates.id',
                'meta_message_templates.template_id',
                'meta_message_templates.name',
                'meta_message_templates.language',
                'meta_message_templates.status',
                'meta_message_templates.category',
                'meta_message_templates.synchronize_at',
            ])
            ->allowedSorts(['name', 'language', 'status', 'category', 'synchronize_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('No WhatsApp templates found'),
                        'description' => __('Press synchronize to fetch the templates from Meta.'),
                    ]
                );

            $table
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'language', label: __('Language'), canBeHidden: false, sortable: true)
                ->column(key: 'category', label: __('Category'), canBeHidden: false, sortable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true)
                ->column(key: 'synchronize_at', label: __('Synchronized at'), canBeHidden: false, sortable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $templates): AnonymousResourceCollection
    {
        return MetaMessageTemplatesResource::collection($templates);
    }

    public function htmlResponse(LengthAwarePaginator $templates, ActionRequest $request): Response
    {
        $actions = [];
        // if ($this->canEdit) {
        $actions[] = [
            'type'        => 'button',
            'style'       => 'tertiary',
            'icon'        => ['fab', 'fa-whatsapp'],
            'tooltip'     => __('Fetch the message templates from Meta'),
            'label'       => __('Synchronize'),
            'fullLoading' => true,
            'route'       => [
                'method'     => 'post',
                'name'       => 'grp.org.shops.show.chat.whatsapp_templates.sync',
                'parameters' => [
                    'organisation' => $this->organisation->slug,
                    'shop'         => $this->shop->slug,
                ],
            ],
        ];
        // }

        return Inertia::render(
            'Org/Chat/WhatsappTemplates',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('WhatsApp Templates'),
                'pageHead'    => [
                    'title'   => __('WhatsApp Templates'),
                    'icon'    => [
                        'icon'  => ['fab', 'fa-whatsapp'],
                        'title' => __('WhatsApp Templates'),
                    ],
                    'actions' => $actions,
                ],
                'data'        => MetaMessageTemplatesResource::collection($templates),
            ]
        )->table($this->tableStructure());
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fab fa-whatsapp',
                        'route' => [
                            'name'       => 'grp.org.shops.show.chat.whatsapp_templates.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('WhatsApp Templates'),
                    ],
                ],
            ]
        );
    }
}
