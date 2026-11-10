<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\CRM\Customer\ReportCustomerShopTypeCoverage;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeSourceEnum;
use App\Http\Resources\CRM\CustomerShopTypesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasSearchableText;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Active customers are the ones invoiced within ACTIVE_MONTHS, the same rule as ReportCustomerShopTypeCoverage.
 */
class IndexCustomerShopTypes extends OrgAction
{
    use WithCRMAuthorisation;
    use WithCustomersSubNavigation;
    use HasSearchableText;

    private const int ACTIVE_MONTHS = 24;

    private Shop $parent;

    private ?array $coverage = null;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    protected function whereActive(QueryBuilder|Builder $query): void
    {
        $query->whereNull('customers.deleted_at')
            ->whereRaw('customers.last_invoiced_at >= now() - make_interval(months => ?)', [self::ACTIVE_MONTHS]);
    }

    /**
     * @return array<int, array{key: string, label: string, customers: int, source_customer: int, source_staff: int, source_inferred: int}>
     */
    public function getCoverage(Shop $shop): array
    {
        if ($this->coverage !== null) {
            return $this->coverage;
        }

        $query = DB::table('customers')
            ->selectRaw('customers.shop_type, customers.shop_type_source, count(*) as number')
            ->where('customers.shop_id', $shop->id)
            ->groupBy('customers.shop_type', 'customers.shop_type_source');
        $this->whereActive($query);
        $counts = $query->get();

        $rows = [];
        foreach (CustomerShopTypeEnum::labels() as $key => $label) {
            $rows[$key] = ['key' => $key, 'label' => $label, 'customers' => 0, 'source_customer' => 0, 'source_staff' => 0, 'source_inferred' => 0];
        }
        $rows['none'] = ['key' => 'none', 'label' => __('No shop type yet'), 'customers' => 0, 'source_customer' => 0, 'source_staff' => 0, 'source_inferred' => 0];

        foreach ($counts as $count) {
            $key = $count->shop_type ?? 'none';
            if (!isset($rows[$key])) {
                continue;
            }
            $rows[$key]['customers'] += $count->number;
            if ($count->shop_type && in_array($count->shop_type_source, CustomerShopTypeSourceEnum::values(), true)) {
                $rows[$key]['source_'.$count->shop_type_source] += $count->number;
            }
        }

        $this->coverage = array_values($rows);

        return $this->coverage;
    }

    /**
     * @return array<int, array{code: string, organisation_slug: string, shop_slug: string, active: int, with_shop_type: int, is_current: bool}>
     */
    public function getShopsCoverage(ActionRequest $request): array
    {
        $rows = array_filter(
            ReportCustomerShopTypeCoverage::run(self::ACTIVE_MONTHS),
            fn ($row) => $row->shop !== 'All'
        );

        $shops = Shop::where('shops.group_id', $this->parent->group_id)
            ->where('shops.type', ShopTypeEnum::B2B)
            ->where('shops.state', ShopStateEnum::OPEN)
            ->with('organisation:id,slug')
            ->get()
            ->filter(fn (Shop $shop) => $request->user()->authTo("crm.$shop->id.view"))
            ->keyBy('code');

        $shopsCoverage = [];
        foreach ($rows as $row) {
            $shop = $shops->get($row->shop);
            if (!$shop) {
                continue;
            }
            $shopsCoverage[] = [
                'code'              => $shop->code,
                'organisation_slug' => $shop->organisation->slug,
                'shop_slug'         => $shop->slug,
                'active'            => (int) $row->active,
                'with_shop_type'    => (int) $row->with_shop_type,
                'is_current'        => $shop->id === $this->parent->id,
            ];
        }

        return $shopsCoverage;
    }

    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $normalized = $this->normalizeSearchableText($value);

                $searchTokens = array_values(array_filter(
                    explode(' ', trim($normalized)),
                    fn ($t) => strlen($t) >= 2
                ));

                $query->where(function ($textQuery) use ($searchTokens) {
                    foreach ($searchTokens as $searchToken) {
                        $textQuery->where('searchable_text', 'ILIKE', "% {$searchToken}%");
                    }
                });

                $postalCode = str_replace(' ', '', trim($normalized));

                if (strlen($postalCode) >= 2) {
                    $query->orWhereExists(function ($existsQuery) use ($postalCode) {
                        $existsQuery->select(DB::raw(1))
                            ->from('addresses')
                            ->whereColumn('addresses.id', 'customers.address_id')
                            ->whereRaw(
                                "regexp_replace(lower(addresses.postal_code), '[^a-z0-9]', '', 'g') LIKE ?",
                                ["{$postalCode}%"]
                            );
                    });
                }
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Customer::class)
            ->where('customers.shop_id', $shop->id);
        $this->whereActive($queryBuilder);

        foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('-last_invoiced_at')
            ->select([
                'customers.id',
                'customers.slug',
                'customers.reference',
                'customers.name',
                'customers.company_name',
                'customers.location',
                'customers.last_invoiced_at',
                'customers.shop_type',
                'customers.shop_type_source',
                'customer_stats.number_invoices_type_invoice',
                'customer_stats.sales_all',
            ])
            ->leftJoin('customer_stats', 'customers.id', 'customer_stats.customer_id')
            ->allowedSorts(['name', 'last_invoiced_at', 'shop_type'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function getElementGroups(Shop $shop): array
    {
        $elements = [];
        foreach ($this->getCoverage($shop) as $row) {
            $elements[$row['key']] = [$row['label'], $row['customers']];
        }

        return [
            'shop_type' => [
                'label'    => __('Shop type'),
                'elements' => $elements,
                'engine'   => function ($query, $elements) {
                    $query->where(function ($query) use ($elements) {
                        $query->whereIn('customers.shop_type', array_values(array_diff($elements, ['none'])));
                        if (in_array('none', $elements, true)) {
                            $query->orWhereNull('customers.shop_type');
                        }
                    });
                }
            ],
        ];
    }

    public function tableStructure(Shop $shop, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withLabelRecord([__('customer'), __('customers')])
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No active customers found')])
                ->column(key: 'reference', label: __('Ref'), canBeHidden: false, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Country'), canBeHidden: false)
                ->column(key: 'last_invoiced_at', label: __('Last invoice'), canBeHidden: false, sortable: true, type: 'date')
                ->column(key: 'number_invoices_type_invoice', label: __('Invoices'), canBeHidden: false, align: 'right')
                ->column(key: 'sales_all', label: __('Sales'), canBeHidden: false, align: 'right')
                ->column(key: 'shop_type', label: __('Shop type'), canBeHidden: false, sortable: true)
                ->column(key: 'shop_type_source', label: __('Set by'), canBeHidden: false)
                ->defaultSort('-last_invoiced_at');
        };
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Shop/CRM/CustomerShopTypes',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => __('Shop types'),
                'pageHead'      => [
                    'title'         => __('Shop types'),
                    'model'         => __('Customers'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-store'],
                        'title' => __('Shop types')
                    ],
                    'subNavigation' => $this->getSubNavigation($request),
                ],
                'coverage'      => $this->getCoverage($this->parent),
                'shopsCoverage' => $this->getShopsCoverage($request),
                'canEdit'       => $this->canEdit,
                'currencyCode'  => $this->parent->currency->code,
                'shopTypeOptions' => collect(CustomerShopTypeEnum::labels())
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values(),
                'bulkUpdateRoute' => [
                    'name'       => 'grp.models.shop.customers.shop_type.update',
                    'parameters' => ['shop' => $this->parent->id],
                    'method'     => 'patch',
                ],
                'customers'     => CustomerShopTypesResource::collection($customers),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexCustomers::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.index', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.customers.shop_types.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Shop types'),
                    ],
                ],
            ]
        );
    }
}
