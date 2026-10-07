<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Http\Resources\Web\WebsiteDialogsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use App\Actions\Web\WebsiteDialog\WithWebsiteDialogScope;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebsiteDialogs extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithWebAuthorisation;

    public function handle(Website $website, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereStartWith('website_dialogs.name', $value);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebsiteDialog::class)
            ->with(['liveSnapshot.publisher', 'pausedBy'])
            ->where('website_dialogs.website_id', $website->id);

        return $queryBuilder
            ->defaultSort('-created_at')
            ->allowedSorts(['name', 'created_at', 'live_at', 'closed_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No dialog found'),
                    'count' => 0,
                ])
                ->column(key: 'status', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'name', label: __('Name'), sortable: true)
                ->column(key: 'display_frequency', label: __('Shown'))
                ->column(key: 'publisher_name', label: __('Publisher name'))
                ->column(key: 'live_at', label: __('From'), sortable: true, type: 'date_hm')
                ->column(key: 'closed_at', label: __('Until'), sortable: true, type: 'date_hm')
                ->column(key: 'paused_note', label: __('Paused'))
                ->column(key: 'show_pages', label: __('Show pages'))
                ->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->ensureWebsiteDialogScope($shop, $website);
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    public function htmlResponse(LengthAwarePaginator $websiteDialogs, ActionRequest $request): Response
    {
        return Inertia::render(
            'Websites/WebsiteDialogs',
            [
                'title'    => __('Dialogs'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead' => [
                    'title'       => __('Dialogs'),
                    'icon'        => [
                        'title' => __('Dialogs'),
                        'icon'  => 'fal fa-window-restore'
                    ],
                    'actions'     => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'primary',
                            'label' => __('Create New'),
                            'icon'  => ['fas', 'fa-plus'],
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.website_dialogs.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false
                    ],
                ],
                'data'     => WebsiteDialogsResource::collection($websiteDialogs),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        /** @var Website $website */
        $website = request()->route()->parameter('website');

        return array_merge(
            ShowWebsite::make()->getBreadcrumbs(
                $website,
                'grp.org.shops.show.web.websites.show',
                $routeParameters
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.web.website_dialogs.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website'])
                        ],
                        'label' => __('Dialogs'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ]
        );
    }
}
