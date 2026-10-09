<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteNotFoundPath\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Crawl\AuditWebsite;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Actions\Web\WebsiteNotFoundPath\PruneWebsiteNotFoundPaths;
use App\Http\Resources\Web\WebsiteNotFoundPathResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Models\Web\WebsiteNotFoundPath;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebsiteNotFoundPaths extends OrgAction
{
    use WithWebAuthorisation;

    private Website $website;

    private const string FIXED_SQL = "(EXISTS (SELECT 1 FROM redirects WHERE redirects.website_id = website_not_found_paths.website_id AND redirects.from_path = website_not_found_paths.last_segment)
        OR EXISTS (SELECT 1 FROM webpages WHERE webpages.website_id = website_not_found_paths.website_id AND webpages.url = LOWER(website_not_found_paths.last_segment) AND webpages.state = 'live' AND webpages.deleted_at IS NULL))";

    private function elementGroups(Website $website): array
    {
        $countFor = fn (Closure $scope) => $scope(WebsiteNotFoundPath::where('website_id', $website->id))->count();

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => [
                    'open'    => [__('Open'), $countFor(fn ($query) => $query->where('is_ignored', false)->whereRaw('NOT '.self::FIXED_SQL))],
                    'fixed'   => [__('Fixed'), $countFor(fn ($query) => $query->where('is_ignored', false)->whereRaw(self::FIXED_SQL))],
                    'ignored' => [__('Ignored'), $countFor(fn ($query) => $query->where('is_ignored', true))],
                ],
                'engine'   => function ($query, array $elements) {
                    $query->where(function ($query) use ($elements) {
                        if (in_array('open', $elements)) {
                            $query->orWhere(fn ($query) => $query->where('website_not_found_paths.is_ignored', false)->whereRaw('NOT '.self::FIXED_SQL));
                        }
                        if (in_array('fixed', $elements)) {
                            $query->orWhere(fn ($query) => $query->where('website_not_found_paths.is_ignored', false)->whereRaw(self::FIXED_SQL));
                        }
                        if (in_array('ignored', $elements)) {
                            $query->orWhere('website_not_found_paths.is_ignored', true);
                        }
                    });
                },
            ],
        ];
    }

    public function handle(Website $website, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('website_not_found_paths.path', 'ilike', '%'.addcslashes(strip_tags($value), '%_\\').'%');
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebsiteNotFoundPath::class)
            ->where('website_not_found_paths.website_id', $website->id);

        foreach ($this->elementGroups($website) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: 'open'
            );
        }

        return $queryBuilder
            ->defaultSort('-hits')
            ->select([
                'website_not_found_paths.id',
                'website_not_found_paths.path',
                'website_not_found_paths.hits',
                'website_not_found_paths.last_referrer',
                'website_not_found_paths.is_ignored',
                'website_not_found_paths.first_seen_at',
                'website_not_found_paths.last_seen_at',
            ])
            ->selectRaw(self::FIXED_SQL.' as is_fixed')
            ->selectRaw('(SELECT COUNT(*) FROM seo_backlinks WHERE seo_backlinks.website_id = website_not_found_paths.website_id AND seo_backlinks.target_path = website_not_found_paths.path AND seo_backlinks.lost_at IS NULL) as backlinks')
            ->allowedSorts(['path', 'hits', 'backlinks', 'first_seen_at', 'last_seen_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Website $website, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($website, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->elementGroups($website) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: 'open'
                );
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('path'), __('paths')])
                ->withEmptyState([
                    'title'       => __('No missing pages recorded'),
                    'description' => __('Each time someone opens a URL on the website that has no page, the path is listed here. Paths not seen for :days days are removed.', ['days' => PruneWebsiteNotFoundPaths::RETENTION_DAYS]),
                ])
                ->column(key: 'path', label: __('Path'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'hits', label: __('Hits'), tooltip: __('Visits to this path that got a 404. Search engine bots are not counted'), canBeHidden: false, sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'backlinks', label: __('Backlinks'), tooltip: __('Links from other websites pointing at this path, from the monthly backlink fetch. A redirect wins them back'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'last_seen_at', label: __('Last seen'), sortable: true)
                ->column(key: 'first_seen_at', label: __('First seen'), sortable: true)
                ->column(key: 'last_referrer', label: __('Last came from'), tooltip: __('The page that linked to this path on the last visit, when the browser sent it'), tooltipIcon: true)
                ->column(key: 'actions', label: '', canBeHidden: false)
                ->defaultSort('-hits');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($shop->website, 404);

        $this->website = $shop->website;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($this->website);
    }

    public function htmlResponse(LengthAwarePaginator $notFoundPaths, ActionRequest $request): Response
    {
        $title = __('Missing pages (404)');

        return Inertia::render(
            'Org/Web/WebsiteNotFoundPaths',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => $title,
                'pageHead'      => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-unlink'],
                        'title' => $title,
                    ],
                    'title' => $title,
                ],
                'canEdit'       => $this->canEdit,
                'website'       => [
                    'id'     => $this->website->id,
                    'domain' => $this->website->domain,
                    'url'    => AuditWebsite::publicBaseUrl($this->website),
                ],
                'retentionDays' => PruneWebsiteNotFoundPaths::RETENTION_DAYS,
                'data'          => WebsiteNotFoundPathResource::collection($notFoundPaths),
            ]
        )->table($this->tableStructure($this->website));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $shopParameters = Arr::only($routeParameters, ['organisation', 'shop']);

        return array_merge(
            ShowSeoDashboard::make()->getBreadcrumbs($shopParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.seo.not_found.index',
                            'parameters' => $shopParameters,
                        ],
                        'label' => __('Missing pages'),
                    ],
                ],
            ]
        );
    }
}
