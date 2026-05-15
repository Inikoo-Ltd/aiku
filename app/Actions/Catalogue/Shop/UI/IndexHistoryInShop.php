<?php

/*
 * Author: Dava Moreno
 * Created on: 11-05-2026, Bali, Indonesia
 * Github: https://github.com/davamoreno
 * Copyright: 2026
 *
*/

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\OrgAction;
use App\Http\Resources\History\HistoryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use OwenIt\Auditing\Models\Audit;
use Spatie\QueryBuilder\AllowedFilter;

class IndexHistoryInShop extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Shop $shop, $prefix = null): LengthAwarePaginator|array|bool
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('user_type', $value)
                    ->orWhereWith('user_type', $value)
                    ->orWhereWith('url', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Audit::class);
        $queryBuilder->whereIn('auditable_type', [Shop::class, class_basename(Shop::class)]);
        $queryBuilder->where('auditable_id', $shop->id);

        $queryBuilder->orderBy('id', 'DESC');

        return $queryBuilder
            ->defaultSort('audits.created_at')
            ->allowedSorts(['ip_address','auditable_id', 'auditable_type', 'user_type', 'url','created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($this->shop);
    }

    public function tableStructure($prefix = null, ?array $exportLinks = null): Closure
    {
        return function (InertiaTable $table) use ($exportLinks, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withExportLinks($exportLinks)
                ->column(key: 'expand', label: '', type: 'icon')
                ->column(key: 'datetime', label: __('Date'), canBeHidden: false)
                ->column(key: 'user_name', label: __('User'), canBeHidden: false)
                ->column(key: 'values', label: __('Values'), canBeHidden: false)
                ->column(key: 'event', label: __('Action'), canBeHidden: false)
                ->defaultSort('ip_address');
        };
    }

    public function htmlResponse(LengthAwarePaginator $histories, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/Histories',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('Changelog').' - '.$this->shop->code,
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-history'],
                        'title' => __('Changelog')
                    ],
                    'title'     => __('Changelog'),
                ],
                'data'        => HistoryResource::collection($histories),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Changelog'),
                        'icon'  => 'fal fa-history'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.dashboard.changelog' =>
            array_merge(
                EditShop::make()->getBreadcrumbs('grp.org.shops.show.settings.edit', $routeParameters),
                $headCrumb(
                    [
                        'name' => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }
}
