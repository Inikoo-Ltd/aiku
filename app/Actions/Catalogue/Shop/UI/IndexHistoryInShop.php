<?php

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\Traits\WithFormattedUserHistories;
use App\Http\Resources\History\HistoryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
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
    use WithFormattedUserHistories;

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

        $queryBuilder->where('auditable_type', $shop->getMorphClass())
            ->where('auditable_id', $shop->id);

        $queryBuilder->orderBy('id', 'DESC');

        return $queryBuilder->defaultSort('audits.created_at')
            ->allowedSorts(['ip_address','auditable_id', 'auditable_type', 'user_type', 'url', 'created_at'])
            ->allowedFilters([
                $globalSearch,
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
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
                ->column(key: 'datetime', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'user_name', label: __('User'), canBeHidden: false, sortable: true)
                ->column(key: 'values', label: __('Value'), canBeHidden: false, sortable: true)
                ->column(key: 'event', label: __('Action'), canBeHidden: false, sortable: true)
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
                'title'       => __('Shop Settings Changelog'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-store'],
                        'title' => __('Shop Changelog')
                    ],
                    'title' => __('Shop Changelog'),
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
            'grp.org.shops.show.dashboard.changelog.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'shop'])),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }
}
