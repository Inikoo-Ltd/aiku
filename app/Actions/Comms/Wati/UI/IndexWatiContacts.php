<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Comms\Wati\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithWatiSubNavigation;
use App\Enums\UI\Comms\WatiContactsTabsEnum;
use App\Http\Resources\Comms\CustomerForWatiResource;
use App\Http\Resources\Comms\WatiContactResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WatiContact;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWatiContacts extends OrgAction
{
    use WithWatiSubNavigation;

    public Shop $parent;

    public function handle(Shop $shop, string $tab = WatiContactsTabsEnum::ALL->value, ?string $prefix = null): LengthAwarePaginator
    {
        if ($tab === WatiContactsTabsEnum::NOT_IN_WATI->value) {
            return $this->queryNotInWati($shop, $prefix);
        }

        return $this->queryWatiContacts($shop, $tab, $prefix);
    }

    private function queryWatiContacts(Shop $shop, string $tab, ?string $prefix): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('wati_contacts.name', 'ILIKE', "%{$value}%")
                    ->orWhere('wati_contacts.phone', 'ILIKE', "%{$value}%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WatiContact::class)
            ->where('wati_contacts.shop_id', $shop->id)
            ->with('customer');

        if ($tab === WatiContactsTabsEnum::LINKED->value) {
            $queryBuilder->whereNotNull('wati_contacts.customer_id');
        } elseif ($tab === WatiContactsTabsEnum::WATI_ONLY->value) {
            $queryBuilder->whereNull('wati_contacts.customer_id');
        }

        return $queryBuilder
            ->defaultSort('-wati_contacts.wati_created_at')
            ->select([
                'wati_contacts.id',
                'wati_contacts.wati_id',
                'wati_contacts.wa_id',
                'wati_contacts.phone',
                'wati_contacts.name',
                'wati_contacts.contact_status',
                'wati_contacts.source',
                'wati_contacts.opted_in',
                'wati_contacts.allow_broadcast',
                'wati_contacts.customer_id',
                'wati_contacts.synced_at',
            ])
            ->allowedSorts(['name', 'phone', 'contact_status', 'opted_in', 'allow_broadcast'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function queryNotInWati(Shop $shop, ?string $prefix): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('customers.name', 'ILIKE', "%{$value}%")
                    ->orWhere('customers.phone', 'ILIKE', "%{$value}%")
                    ->orWhere('customers.email', 'ILIKE', "%{$value}%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Customer::class)
            ->where('customers.shop_id', $shop->id)
            ->whereDoesntHave('watiContact')
            ->defaultSort('-customers.created_at')
            ->select([
                'customers.id',
                'customers.slug',
                'customers.name',
                'customers.phone',
                'customers.email',
                'customers.created_at',
            ])
            ->allowedSorts(['name', 'phone', 'email'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(string $tab, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($tab, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->withGlobalSearch();

            if ($tab === WatiContactsTabsEnum::NOT_IN_WATI->value) {
                $table
                    ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'phone', label: __('Phone'), canBeHidden: false, sortable: true)
                    ->column(key: 'email', label: __('Email'), canBeHidden: false)
                    ->column(key: 'actions', label: '', canBeHidden: false);
            } else {
                $table
                    ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'phone', label: __('Phone'), canBeHidden: false, sortable: true)
                    ->column(key: 'contact_status', label: __('Status'), canBeHidden: false)
                    ->column(key: 'opted_in', label: __('Opted In'), canBeHidden: false)
                    ->column(key: 'allow_broadcast', label: __('Broadcast'), canBeHidden: false)
                    ->column(key: 'actions', label: __('Action'), canBeHidden: false);
            }
        };
    }

    public function htmlResponse(LengthAwarePaginator $contacts, ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/WatiContacts',
            [
                'breadcrumbs'                          => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'                                => __('Wati Contacts'),
                'pageHead'                             => [
                    'title'         => __('Contacts'),
                    'icon'          => ['fal', 'fa-address-book'],
                    'subNavigation' => $this->getWatiSubNavigation($this->parent, $request),
                    'actions'       => [
                        [
                            'type'   => 'button',
                            'style'  => 'secondary',
                            'label'  => __('Sync Contacts'),
                            'icon'   => ['fal', 'fa-sync'],
                            'route'  => [
                                'name'       => 'grp.models.shop.wati-contacts.sync',
                                'parameters' => ['shop' => $this->parent->id],
                                'method'     => 'post',
                            ],
                        ],
                    ],
                ],
                'tabs'                                 => [
                    'current'    => $this->tab,
                    'navigation' => WatiContactsTabsEnum::navigation(),
                ],
                'routes'                               => [
                    'add'          => route('grp.models.shop.wati-contacts.add', ['shop' => $this->parent->id]),
                    'bulk_add'     => route('grp.models.shop.wati-contacts.bulk-add', ['shop' => $this->parent->id]),
                    'bulk_add_all' => route('grp.models.shop.wati-contacts.bulk-add-all', ['shop' => $this->parent->id]),
                ],
                WatiContactsTabsEnum::ALL->value         => $this->tab == WatiContactsTabsEnum::ALL->value
                    ? fn () => WatiContactResource::collection($contacts)
                    : Inertia::lazy(fn () => WatiContactResource::collection($this->handle($this->parent, WatiContactsTabsEnum::ALL->value, WatiContactsTabsEnum::ALL->value))),
                WatiContactsTabsEnum::LINKED->value      => $this->tab == WatiContactsTabsEnum::LINKED->value
                    ? fn () => WatiContactResource::collection($contacts)
                    : Inertia::lazy(fn () => WatiContactResource::collection($this->handle($this->parent, WatiContactsTabsEnum::LINKED->value, WatiContactsTabsEnum::LINKED->value))),
                WatiContactsTabsEnum::WATI_ONLY->value   => $this->tab == WatiContactsTabsEnum::WATI_ONLY->value
                    ? fn () => WatiContactResource::collection($contacts)
                    : Inertia::lazy(fn () => WatiContactResource::collection($this->handle($this->parent, WatiContactsTabsEnum::WATI_ONLY->value, WatiContactsTabsEnum::WATI_ONLY->value))),
                WatiContactsTabsEnum::NOT_IN_WATI->value => $this->tab == WatiContactsTabsEnum::NOT_IN_WATI->value
                    ? fn () => CustomerForWatiResource::collection($contacts)
                    : Inertia::lazy(fn () => CustomerForWatiResource::collection($this->handle($this->parent, WatiContactsTabsEnum::NOT_IN_WATI->value, WatiContactsTabsEnum::NOT_IN_WATI->value))),
            ]
        )
            ->table($this->tableStructure(WatiContactsTabsEnum::ALL->value, WatiContactsTabsEnum::ALL->value))
            ->table($this->tableStructure(WatiContactsTabsEnum::LINKED->value, WatiContactsTabsEnum::LINKED->value))
            ->table($this->tableStructure(WatiContactsTabsEnum::WATI_ONLY->value, WatiContactsTabsEnum::WATI_ONLY->value))
            ->table($this->tableStructure(WatiContactsTabsEnum::NOT_IN_WATI->value, WatiContactsTabsEnum::NOT_IN_WATI->value));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(WatiContactsTabsEnum::values());

        return $this->handle($shop, $this->tab, $this->tab);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name'       => 'grp.org.shops.show.marketing.wati.contacts.index',
                        'parameters' => $routeParameters,
                    ],
                    'label' => __('Contacts'),
                    'icon'  => 'fal fa-bars',
                ],
            ],
        ];
    }
}
