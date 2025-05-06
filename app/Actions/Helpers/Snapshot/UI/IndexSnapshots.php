<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 08:55:05 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot\UI;

use App\Actions\InertiaAction;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\UI\ShowFooter;
use App\Actions\Web\Webpage\UI\ShowHeader;
use App\Actions\Web\Webpage\UI\ShowMenu;
use App\Actions\Web\Webpage\UI\WithFooterSubNavigation;
use App\Actions\Web\Webpage\UI\WithHeaderSubNavigation;
use App\Actions\Web\Webpage\UI\WithMenuSubNavigation;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Http\Resources\Web\SnapshotsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Helpers\Snapshot;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Banner;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexSnapshots extends OrgAction
{
    private Website $website;
    private string $scope;

    use WithFooterSubNavigation;
    use WithHeaderSubNavigation;
    use WithMenuSubNavigation;

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }


    public function handle(Website|Webpage|EmailTemplate $parent, $prefix = null, $scope = null): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(Snapshot::class);
        $queryBuilder->where('state', '!=', SnapshotStateEnum::UNPUBLISHED->value);

        if (class_basename($parent) == 'Banner') {
            $queryBuilder->where('parent_id', $parent->id)->where('parent_type', 'Banner');
        }

        if (class_basename($parent) == 'Webpage') {
            $queryBuilder->where('parent_id', $parent->id)->where('parent_type', 'Webpage');
        }

        if (class_basename($parent) == 'EmailTemplate') {
            $queryBuilder->where('parent_id', $parent->id)->where('parent_type', 'EmailTemplate');
        }

        if (class_basename($parent) === 'Website') {
            $queryBuilder->where('parent_id', $parent->id)
                            ->where('parent_type', 'Website');
        
            if (in_array($scope, ['header', 'footer', 'menu'], true)) {
                $queryBuilder->where('scope', $scope);
            }
        }
        return $queryBuilder
            ->defaultSort('-published_at')
            ->allowedSorts(['published_at', 'published_until'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $snapshots, ActionRequest $request): Response
    {
        $icon       = ['fal', 'fa-user'];
        $title         = __('snapshots');
        $iconRight  = [
            'icon'  => ['fal', 'fa-shopping-cart'],
            'title' => __('snapshots'),
        ];

        $afterTitle = [];
        $subNavigation = null;

        if($this->scope == 'header') {
            $afterTitle = [
                'label' => __('header'),
            ];
            $subNavigation = $this->getHeaderSubNavigation(
                $this->website,
            );
        } elseif($this->scope == 'menu') {
            $afterTitle = [
                'label' => __('menu'),
            ];
            $subNavigation = $this->getMenuSubNavigation(
                $this->website,
            );
        } elseif($this->scope == 'footer') {
            $afterTitle = [
                'label' => __('footer'),
            ];
            $subNavigation = $this->getFooterSubNavigation(
                $this->website,
            );
        }
        
        $actions = [];

        return Inertia::render(
            'Org/Web/Snapshots',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('snapshots'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => SnapshotsResource::collection($snapshots),

            ]
        )->table($this->tableStructure($this->website));
    }

    public function tableStructure(Website|Webpage|EmailTemplate|Banner $parent, ?array $modelOperations = null, $prefix = null, ?array $exportLinks = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $exportLinks) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('Banner has not been published yet'),
                        'count' => 0
                    ]
                );
            if ($exportLinks) {
                $table->withExportLinks($exportLinks);
            }


            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'publisher', label: __('publisher'), sortable: true)
                ->column(key: 'published_at', label: __('date published'), sortable: true)
                ->column(key: 'published_until', label: __('published until'))
                ->column(key: 'comment', label: __('comment'))
                ->column(key: 'recyclable', label: ['fal', 'fa-recycle'])
                ->defaultSort('published_at');
        };
    }

    public function inHeaderWorkshop(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->scope = 'header';
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $website, scope: 'header');
    }

    public function inMenuWorkshop(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->scope = 'menu';
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $website, scope: 'menu');
    }

    public function inFooterWorkshop(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->scope = 'footer';
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $website, scope: 'footer');
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Snapshots'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.web.websites.workshop.snapshots.footer' =>
            array_merge(
                ShowFooter::make()->getBreadcrumbs(
                    $this->website,
                    'grp.org.shops.show.web.websites.workshop.footer',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.web.websites.workshop.snapshots.footer',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.shops.show.web.websites.workshop.snapshots.header' =>
            array_merge(
                ShowHeader::make()->getBreadcrumbs(
                    $this->website,
                    'grp.org.shops.show.web.websites.workshop.header',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.web.websites.workshop.snapshots.header',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.shops.show.web.websites.workshop.snapshots.menu' =>
            array_merge(
                ShowMenu::make()->getBreadcrumbs(
                    $this->website,
                    'grp.org.shops.show.web.websites.workshop.menu',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.web.websites.workshop.snapshots.menu',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
