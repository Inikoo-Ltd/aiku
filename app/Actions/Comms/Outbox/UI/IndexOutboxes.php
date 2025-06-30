<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithCommsSubNavigation;
use App\Actions\Comms\UI\ShowCommsDashboard;
use App\Actions\Fulfilment\Fulfilment\UI\EditFulfilment;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Http\Resources\Mail\OutboxesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOutboxes extends OrgAction
{
    use WithCommsSubNavigation;

    private Group|Shop|Organisation|PostRoom|Website|Fulfilment $parent;


    public function authorize(ActionRequest $request): bool
    {

        if ($this->parent instanceof Fulfilment) {
            return    $this->canEdit = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        }

        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        return $request->user()->authTo([
            'shop-admin.'.$this->shop->id,
            'marketing.'.$this->shop->id.'.view',
            'web.'.$this->shop->id.'.view',
            'orders.'.$this->shop->id.'.view',
            'crm.'.$this->shop->id.'.view',
        ]);
    }

    public function handle(Group|Shop|Organisation|PostRoom|OrgPostRoom|Website|Fulfilment $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('outboxes.name', $value)
                    ->orWhere('outboxes.data', '=', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Outbox::class)
                        ->leftJoin('organisations', 'outboxes.organisation_id', '=', 'organisations.id')
                        ->leftJoin('shops', 'outboxes.shop_id', '=', 'shops.id');

        if ($parent instanceof Group) {
            $queryBuilder->where('outboxes.group_id', $parent->id);
        } elseif (class_basename($parent) == 'Shop') {
            $queryBuilder->where('outboxes.shop_id', $parent->id);
        } elseif (class_basename($parent) == 'PostRoom') {
            $queryBuilder->where('outboxes.post_room_id', $parent->id);
        } elseif (class_basename($parent) == 'Website') {
            $queryBuilder->where('outboxes.website_id', $parent->id);
        } elseif (class_basename($parent) == 'Fulfilment') {
            $queryBuilder->where('outboxes.fulfilment_id', $parent->id);
        } elseif (class_basename($parent) == 'OrgPostRoom') {
            $queryBuilder->where('outboxes.org_post_room_id', $parent->id);
        } else {
            $queryBuilder->where('outboxes.organisation_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('outboxes.name')
            ->select([
                'outboxes.name',
                'outboxes.slug',
                'outboxes.type',
                'outboxes.data',
                'outbox_intervals.dispatched_emails_lw',
                'outbox_intervals.opened_emails_lw',
                'outbox_intervals.unsubscribed_lw',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->selectRaw('outbox_intervals.runs_all runs')

            ->leftJoin('outbox_stats', 'outbox_stats.outbox_id', 'outboxes.id')
            ->leftJoin('outbox_intervals', 'outbox_intervals.outbox_id', 'outboxes.id')
            ->allowedSorts(['name', 'runs', 'number_mailshots', 'dispatched_emails_lw', 'opened_emails_lw', 'unsubscribed_lw'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }
    public function tableStructure($parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->column(key: 'type', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                        ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'runs', label: __('Mailshots/Runs'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'dispatched_emails_lw', label: __('Dispatched').' '.__('1w'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'opened_emails_lw', label: __('Opened').' '.__('1w'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'unsubscribed_lw', label: __('Unsubscribed').' '.__('1w'), canBeHidden: false, sortable: true, searchable: true);
        };
    }


    public function jsonResponse(LengthAwarePaginator $outboxes): AnonymousResourceCollection
    {
        return OutboxesResource::collection($outboxes);
    }


    public function htmlResponse(LengthAwarePaginator $outboxes, ActionRequest $request): Response
    {

        $subNavigation = $this->getCommsNavigation($this->parent);

        return Inertia::render(
            'Comms/Outboxes',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('outboxes '),
                'pageHead'    => [
                    'title'         => __('outboxes'),
                    'subNavigation' => $subNavigation,
                ],
                'data'        => OutboxesResource::collection($outboxes),


            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->parent);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWebsite(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $website;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Shop $shop, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }



    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Outboxes'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.shops.show.dashboard.comms.outboxes.index' =>
            array_merge(
                ShowCommsDashboard::make()->getBreadcrumbs(
                    'grp.org.shops.show.dashboard.comms.dashboard',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.dashboard.comms.outboxes.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.fulfilments.show.comms.dashboard' =>
            array_merge(
                EditFulfilment::make()->getBreadcrumbs(
                    'grp.org.fulfilments.show.setting.dashboard',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.setting.outboxes.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.overview.comms-marketing.outboxes.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'grp.overview.comms-marketing.outboxes.index',
                    ]
                )
            ),
            'grp.org.fulfilments.show.operations.comms.outboxes', 'grp.org.fulfilments.show.operations.comms.outboxes.dispatched-email.show' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.comms.outboxes',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Outboxes')
                        ]
                    ]
                ]
            ),
            'grp.org.shops.show.dashboard.comms.outboxes.dispatched-email.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.comms.outboxes.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Outboxes')
                        ]
                    ]
                ]
            ),
            default => []
        };
    }
}
