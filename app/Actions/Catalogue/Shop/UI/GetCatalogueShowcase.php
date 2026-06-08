<?php

namespace App\Actions\Catalogue\Shop\UI;

use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCatalogueShowcase
{
    use AsObject;

    public function handle(Shop $shop): array
    {
        $topFamily     = $shop->stats->top1mFamily;
        $topDepartment = $shop->stats->top1mDepartment;
        $topProduct    = $shop->stats->top1mProduct;

        $orgSlug  = $shop->organisation->slug;
        $shopSlug = $shop->slug;

        $stats = [
            $this->buildProductsStat($shop, $orgSlug, $shopSlug),
            $this->buildBundlesStat($shop, $orgSlug, $shopSlug)
        ];

        if ($shop->engine == ShopEngineEnum::AIKU) {
            $stats = array_merge(
                [
                    $this->buildDepartmentsStat($shop, $orgSlug, $shopSlug),
                    $this->buildSubDepartmentsStat($shop, $orgSlug, $shopSlug),
                    $this->buildFamiliesStat($shop, $orgSlug, $shopSlug),
                    $this->buildCollectionsStat($shop, $orgSlug, $shopSlug),
                ],
                $stats,
            );

            $stats['additionalStatBox'] = [
                $this->buildStrayFamiliesStat($orgSlug, $shopSlug),
                $this->buildOrphanProductsStat($shop, $orgSlug, $shopSlug),
                $this->buildRRPViolationStat($shop, $orgSlug, $shopSlug),
                $this->buildOutOfStockStat($shop, $orgSlug, $shopSlug),
                $this->buildMissingDescriptionProductsStat($shop, $orgSlug, $shopSlug),
            ];
        }

        return [
            'currency_code' => $shop->currency->code,
            'top_selling' => [
                'family'     => [
                    'label' => __('Top Family'),
                    'icon'  => 'fal fa-folder',
                    'value' => $topFamily,
                ],
                'department' => [
                    'label' => __('Top Department'),
                    'icon'  => 'fal fa-folder-tree',
                    'value' => $topDepartment,
                ],
                'product'    => [
                    'label' => __('Top Product'),
                    'icon'  => 'fal fa-folder-tree',
                    'value' => $topProduct,
                ],
            ],
            'stats' => $stats,
        ];
    }

    private function buildProductsStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label' => __('Current Products'),
            'route' => [
                'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'      => 'fal fa-cube',
            'color'     => '#38bdf8',
            'value'     => $shop->stats->number_current_products,
            'metaRight' => $shop->engine == ShopEngineEnum::AIKU ? [
                'tooltip' => __('Variants'),
                'icon'    => ['icon' => 'fal fa-cubes', 'class' => ''],
                'count'   => $shop->stats->number_current_product_variants,
            ] : null,
            'metas' => [
                [
                    'icon'    => ['icon' => 'fas fa-check-circle', 'class' => 'text-green-500'],
                    'count'   => $shop->stats->number_products_state_active,
                    'tooltip' => __('Active'),
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
                    ],
                ],
                [
                    'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-amber-500'],
                    'count'   => $shop->stats->number_products_state_discontinuing,
                    'tooltip' => __('Discontinuing'),
                ],
                [
                    'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-red-500'],
                    'count'   => $shop->stats->number_products_state_discontinued,
                    'tooltip' => __('Discontinued'),
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.products.discontinued_products.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
                    ],
                ],
                [
                    'icon'    => ['icon' => 'fal fa-seedling', 'class' => 'text-green-500 animate-pulse'],
                    'count'   => $shop->stats->number_products_state_in_process,
                    'tooltip' => __('Products In Process'),
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.products.in_process_products.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
                    ],
                ],
                [
                    'icon'    => ['icon' => 'fas fa-hat-cowboy', 'class' => 'text-red-500 animate-pulse'],
                    'count'   => 0,
                    'tooltip' => __('Products with Independent Trade Units'),
                    'route'   => [
                        'name'      => 'grp.org.shops.show.catalogue.products.independent_products.all.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
                    ]
                ]
            ],
        ];
    }

    private function buildBundlesStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label' => __('Current Bundles'),
            'route' => [
                'name'       => 'grp.org.shops.show.catalogue.products.current_products.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'  => 'fal fa-layer-group',
            "color" => "#4f46e5",
            'value'     => $shop->stats->number_bundles,
            'metas' => [
                [
                    "icon"    => ["icon"    => "fas fa-check-circle", "class"   => "text-green-500"],
                    "count"   => $shop->stats->number_bundles_state_active,
                    "tooltip" => "Active"
                ],
                [
                    'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-red-500'],
                    'count'   => $shop->stats->number_bundles_state_inactive,
                    'tooltip' => "Inactive"
                ],
                [
                    "icon"    => ["icon"    => "fas fa-times-circle", "class"   => "text-amber-500"],
                    "count"   => $shop->stats->number_bundles_state_discontinuing,
                    "tooltip" => "Discontinuing"
                ],
            ],
        ];
    }

    private function buildDepartmentsStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label' => __('Departments'),
            'route' => [
                'name'       => 'grp.org.shops.show.catalogue.departments.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'      => 'fal fa-folder-tree',
            'color'     => '#a3e635',
            'value'     => $shop->stats->number_current_departments,
            'metas' => [
                [
                    'tooltip' => __('Active departments'),
                    'icon'    => ['tooltip' => 'active', 'icon' => 'fas fa-check-circle', 'class' => 'text-green-500'],
                    'count'   => $shop->stats->number_departments_state_active,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.departments.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'active'],
                    ],
                ],
                [
                    'tooltip' => __('Discontinuing'),
                    'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-amber-500'],
                    'count'   => $shop->stats->number_departments_state_discontinuing,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.departments.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'discontinuing'],
                    ],
                ],
                [
                    'tooltip' => __('Discontinued Departments'),
                    'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-red-500'],
                    'count'   => $shop->stats->number_departments_state_discontinued,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.departments.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'discontinued'],
                    ],
                ],
                [
                    'tooltip' => __('In process'),
                    'icon'    => ['icon' => 'fal fa-seedling', 'class' => 'text-green-500 animate-pulse'],
                    'count'   => $shop->stats->number_departments_state_in_process,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.departments.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'in_process'],
                    ],
                ],
            ],
        ];
    }

    private function buildSubDepartmentsStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label' => __('Sub Departments'),
            'route' => [
                'name'       => 'grp.org.shops.show.catalogue.sub_departments.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'      => 'fal fa-folder-download',
            'color'     => '#facc15',
            'value'     => $shop->stats->number_current_sub_departments,
            'metas' => [
                [
                    'tooltip' => __('Active sub-departments'),
                    'icon'    => ['tooltip' => 'active', 'icon' => 'fas fa-check-circle', 'class' => 'text-green-500'],
                    'count'   => $shop->stats->number_sub_departments_state_active,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.sub_departments.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'active'],
                    ],
                ],
                [
                    'tooltip' => __('Discontinuing sub-departments'),
                    'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-amber-500'],
                    'count'   => $shop->stats->number_sub_departments_state_discontinuing,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.sub_departments.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'discontinuing'],
                    ],
                ],
                [
                    'tooltip' => __('Discontinued sub-departments'),
                    'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-red-500'],
                    'count'   => $shop->stats->number_sub_departments_state_discontinued,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.sub_departments.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'discontinued'],
                    ],
                ],
                [
                    'tooltip' => __('In process'),
                    'icon'    => ['icon' => 'fal fa-seedling', 'class' => 'text-green-500 animate-pulse'],
                    'count'   => $shop->stats->number_sub_departments_state_in_process,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.sub_departments.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'in_process'],
                    ],
                ],
            ],
        ];
    }

    private function buildFamiliesStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label' => __('Families'),
            'route' => [
                'name'       => 'grp.org.shops.show.catalogue.families.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'  => 'fal fa-folder',
            'color' => '#e879f9',
            'value' => $shop->stats->number_current_families,
            'metas' => [
                [
                    'tooltip' => __('Active families'),
                    'icon'    => ['tooltip' => 'active', 'icon' => 'fas fa-check-circle', 'class' => 'text-green-500'],
                    'count'   => $shop->stats->number_families_state_active,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.families.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'active'],
                    ],
                ],
                [
                    'tooltip' => __('Discontinuing families'),
                    'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-amber-500'],
                    'count'   => $shop->stats->number_families_state_discontinuing,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.families.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'discontinuing'],
                    ],
                ],
                [
                    'tooltip' => __('Discontinued families'),
                    'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-red-500'],
                    'count'   => $shop->stats->number_families_state_discontinued,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.families.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'discontinued'],
                    ],
                ],
                [
                    'tooltip' => __('Families in process'),
                    'icon'    => ['icon' => 'fal fa-seedling', 'class' => 'text-green-500 animate-pulse'],
                    'count'   => $shop->stats->number_families_state_in_process,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.families.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'in_process'],
                    ],
                ],
            ],
        ];
    }

    private function buildCollectionsStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label' => __('Collections'),
            'route' => [
                'name'       => 'grp.org.shops.show.catalogue.collections.active.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'  => 'fal fa-album-collection',
            'color' => '#4f46e5',
            'value' => $shop->stats->number_collections_state_active,
            'metas' => [
                [
                    'hide'    => $shop->stats->number_collections_products_status_discontinuing == 0,
                    'tooltip' => __('Discontinuing collections'),
                    'icon'    => ['icon' => 'fas fa-exclamation-triangle', 'class' => 'text-amber-500'],
                    'count'   => $shop->stats->number_collections_products_status_discontinuing,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.collections.active.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'discontinuing'],
                    ],
                ],
                [
                    'hide'    => $shop->stats->number_collections_products_status_discontinued == 0,
                    'tooltip' => __('Discontinued collections'),
                    'icon'    => ['icon' => 'fas fa-exclamation-triangle', 'class' => 'text-red-500'],
                    'count'   => $shop->stats->number_collections_products_status_discontinued,
                    'route'   => [
                        'name'       => 'grp.org.shops.show.catalogue.collections.active.index',
                        'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug, 'index_elements[state]' => 'discontinued'],
                    ],
                ],
            ],
        ];
    }

    private function buildStrayFamiliesStat(string $orgSlug, string $shopSlug): array
    {
        return [
            'label'           => __('Stray Families'),
            'is_negative'     => true,
            'route'           => [
                'name'       => 'grp.org.shops.show.catalogue.families.no_department.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'            => 'fal fa-folder',
            'backgroundColor' => '#ff000011',
            'value'           => app()->make(Shop::class)->stats->number_families_no_department ?? 0,
        ];
    }

    private function buildOrphanProductsStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label'           => __('Orphan Products'),
            'is_negative'     => true,
            'route'           => [
                'name'       => 'grp.org.shops.show.catalogue.products.orphan_products.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'            => 'fal fa-cube',
            'backgroundColor' => '#ff000011',
            'value'           => $shop->stats->number_products_no_family,
        ];
    }

    private function buildOutOfStockStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label'           => __('Out Of Stock Products'),
            'is_negative'     => true,
            'route'           => [
                'name'       => 'grp.org.shops.show.catalogue.products.out_of_stock_products.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'            => 'fal fa-cube',
            'backgroundColor' => '#ff000011',
            'value'           => $shop->stats->number_products_status_out_of_stock,
            'metaRight'       => $shop->type == ShopTypeEnum::EXTERNAL ? null : [
                'tooltip'     => __('Back In Stock Reminders'),
                'customClass' => 'border border-red-500',
                'icon'        => ['icon' => 'fal fa-mail-bulk', 'class' => 'mr-1'],
                'route'       => [
                    'name'       => 'grp.org.shops.show.catalogue.products.pending_back_in_stock_reminders.index',
                    'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
                ],
                'count' => $shop->stats->number_current_sub_departments,
            ],
        ];
    }

    private function buildMissingDescriptionProductsStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label'           => __('Products without description'),
            'is_negative'     => true,
            'route'           => [
                'name'       => 'grp.org.shops.show.catalogue.products.missing_description_products.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'            => 'fal fa-align-left',
            'backgroundColor' => '#ff000011',
            'value'           => $shop->products()->where('is_main', true)->where(function ($q) {
                $q->whereNull('description')->orWhere('description', '');
            })->count(),
        ];
    }

    private function buildRRPViolationStat(Shop $shop, string $orgSlug, string $shopSlug): array
    {
        return [
            'label'           => __('Products with RRP Violation'),
            'is_negative'     => true,
            'route'           => [
                'name'       => 'grp.org.shops.show.catalogue.products.rrp_violation_products.index',
                'parameters' => ['organisation' => $orgSlug, 'shop' => $shopSlug],
            ],
            'icon'            => 'fal fa-cube',
            'backgroundColor' => '#ff000011',
            'value'           => $shop->stats->number_products_with_rrp_violation,
        ];
    }
}
