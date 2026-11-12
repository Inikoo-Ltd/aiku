<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Snapshot;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Every kept version of an info website's pages and shared parts, newest first. Only metadata is read:
 * the HTML of each version stays in the database until someone previews or restores it.
 */
class IndexStaticWebsiteSnapshots extends OrgAction
{
    public function handle(StaticWebsite|StaticWebpage|StaticWebsiteComponent $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('snapshots.comment', $value)
                    ->orWhereStartWith('static_webpages.path', '/'.ltrim($value, '/'));
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Snapshot::class)
            ->where('snapshots.builder', SnapshotBuilderEnum::STATIC_HTML->value)
            ->leftJoin('static_webpages', function ($join) {
                $join->on('static_webpages.id', '=', 'snapshots.parent_id')->where('snapshots.parent_type', '=', 'StaticWebpage');
            })
            ->leftJoin('static_website_components', function ($join) {
                $join->on('static_website_components.id', '=', 'snapshots.parent_id')->where('snapshots.parent_type', '=', 'StaticWebsiteComponent');
            })
            ->leftJoin('static_websites', 'static_websites.id', '=', DB::raw('coalesce(static_webpages.static_website_id, static_website_components.static_website_id)'))
            ->leftJoin('users', function ($join) {
                $join->on('users.id', '=', 'snapshots.publisher_id')->where('snapshots.publisher_type', '=', 'User');
            });

        if ($parent instanceof StaticWebsite) {
            $queryBuilder->where('static_websites.id', $parent->id);
        } else {
            $queryBuilder->where('snapshots.parent_type', $parent->getMorphClass())->where('snapshots.parent_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('-snapshots.id')
            ->select([
                'snapshots.id',
                'snapshots.parent_type',
                'snapshots.parent_id',
                'snapshots.state',
                'snapshots.comment',
                'snapshots.created_at',
                'static_webpages.path as webpage_path',
                'static_website_components.code as component_code',
                'static_websites.slug as static_website_slug',
                'users.contact_name as publisher_name',
            ])
            ->selectRaw('octet_length(snapshots.compiled_layout) as size')
            ->allowedSorts(['id', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(bool $withPart = true, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($withPart, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No snapshots yet'),
                    'description' => __('A snapshot is kept every time a page or shared part changes'),
                ])
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true, type: 'date_hms');

            if ($withPart) {
                $table->column(key: 'part', label: __('Page or part'), canBeHidden: false);
            }

            $table
                ->column(key: 'comment', label: __('What changed'), canBeHidden: false)
                ->column(key: 'publisher_name', label: __('By'), canBeHidden: false)
                ->column(key: 'size', label: __('Size'), canBeHidden: false, type: 'number')
                ->column(key: 'actions', label: '', canBeHidden: false)
                ->defaultSort('-id');
        };
    }
}
