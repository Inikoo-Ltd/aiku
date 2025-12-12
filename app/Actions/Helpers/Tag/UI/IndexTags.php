<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Catalogue\TagsResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTags extends OrgAction
{
    use WithCustomersSubNavigation;

    private Shop $parent;
    private ?TagScopeEnum $forcedScope = null;

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): LengthAwarePaginator
    {
        $this->forcedScope = TagScopeEnum::PRODUCT_PROPERTY;
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit);
    }

    public function inCustomer(Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer->shop;
        $this->forcedScope = TagScopeEnum::ADMIN_CUSTOMER;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($this->parent);
    }

    public function inRetina(Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer->shop;
        $this->forcedScope = TagScopeEnum::USER_CUSTOMER;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($this->parent);
    }

    public function inSelfFilledTags(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->forcedScope = TagScopeEnum::USER_CUSTOMER;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function inInternalTags(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->forcedScope = TagScopeEnum::ADMIN_CUSTOMER;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function handle(Shop|TradeUnit $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query
                    ->whereStartWith('name', $value)
                    ->orWhereWith('scope', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Tag::class);

        if ($this->forcedScope) {
            $queryBuilder->where('scope', $this->forcedScope);
        }

        if ($parent instanceOf Shop) {
            $queryBuilder->where('shop_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('name')
            ->select(['id', 'name', 'slug', 'scope'])
            ->allowedSorts(['name', 'scope'])
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
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'scope', label: __('Scope'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'action', label: __('Action'))
                ->defaultSort('name');
        };
    }

    public function jsonResponse(LengthAwarePaginator $tags): AnonymousResourceCollection
    {
        return TagsResource::collection($tags);
    }

    public function htmlResponse(LengthAwarePaginator $tags, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Tags/Tags',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Tags'),
                'pageHeading' => [
                    'title'  => __('Tags'),
                    'icon'   => [
                        'title' => __('Tags'),
                        'icon'  => ['fal', 'fa-tags'],
                    ],
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Create Tag'),
                            'label'   => __('Create Tag'),
                            'route'   => [
                                'name'       => preg_replace('/index$/', 'create', $request->route()->getName()),
                                'parameters' => [
                                    'organisation' => $this->organisation->slug,
                                    'shop' => $this->shop->slug
                                ],
                            ],
                        ],
                    ],
                    'subNavigation' => $this->getSubNavigation($request)
                ],
                'data' => TagsResource::collection($tags),
            ],
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.self_filled_tags.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Tags'),
                        'icon'  => 'fal fa-tags'
                    ],
                ],
            ],
        );
    }
}
