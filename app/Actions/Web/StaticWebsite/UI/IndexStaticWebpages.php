<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Web\StaticWebpagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStaticWebpages extends OrgAction
{
    use WithStaticWebsiteAuthorisation;

    private StaticWebsite $staticWebsite;

    public function handle(StaticWebsite $staticWebsite, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('static_webpages.path', '/'.ltrim($value, '/'))
                    ->orWhereAnyWordStartWith('static_webpages.title', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(StaticWebpage::class)
            ->join('static_websites', 'static_websites.id', '=', 'static_webpages.static_website_id')
            ->where('static_webpages.static_website_id', $staticWebsite->id)
            ->defaultSort('static_webpages.path')
            ->select([
                'static_webpages.id',
                'static_webpages.path',
                'static_webpages.title',
                'static_webpages.updated_at',
                'static_websites.slug as static_website_slug',
            ])
            ->withCount('snapshots')
            ->allowedSorts(['path', 'title', 'updated_at', 'snapshots_count'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No webpages yet')])
                ->column(key: 'path', label: __('Path'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'title', label: __('Title'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'snapshots_count', label: __('Snapshots'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'updated_at', label: __('Last change'), canBeHidden: false, sortable: true, type: 'date')
                ->column(key: 'preview', label: '', canBeHidden: false)
                ->defaultSort('path');
        };
    }

    public function asController(StaticWebsite $staticWebsite, ActionRequest $request): LengthAwarePaginator
    {
        $this->staticWebsite = $staticWebsite;
        $this->initialisationFromGroup(group(), $request);
        abort_unless($staticWebsite->group_id === $this->group->id, 404);

        return $this->handle($staticWebsite);
    }

    public function htmlResponse(LengthAwarePaginator $webpages, ActionRequest $request): Response
    {
        $title = __('Webpages');

        return Inertia::render(
            'Org/Marketing/StaticWebpages',
            [
                'breadcrumbs' => ShowStaticWebsite::make()->getBreadcrumbs($this->staticWebsite, [
                    'route' => ['name' => 'grp.marketing.info_websites.webpages.index', 'parameters' => [$this->staticWebsite->slug]],
                    'label' => $title,
                ]),
                'title'       => $title.' · '.$this->staticWebsite->name,
                'pageHead'    => [
                    'title'     => $title,
                    'model'     => $this->staticWebsite->name,
                    'icon'      => ['icon' => 'fal fa-browser', 'title' => $title],
                    'iconRight' => ['icon' => 'fal fa-globe', 'tooltip' => $this->staticWebsite->domain],
                ],
                'data'        => StaticWebpagesResource::collection($webpages),
            ]
        )->table($this->tableStructure());
    }
}
