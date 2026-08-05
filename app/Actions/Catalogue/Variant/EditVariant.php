<?php

/*
 * Author Louis Perez
 * Created on 09-07-2026-11h-53m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Variant;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\VariantTabsEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Variant;
use App\Models\Masters\MasterVariant;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Inertia\Inertia;
use Inertia\Response;

class EditVariant extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Organisation|ProductCategory|Shop $parent;

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ProductCategory $family, Variant $variant, ActionRequest $request): Variant
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(VariantTabsEnum::values());

        return $this->handle($variant);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, Variant $variant, ActionRequest $request): Variant
    {
        $this->parent = $subDepartment;

        $this->initialisationFromShop($shop, $request)->withTab(VariantTabsEnum::values());

        return $this->handle($variant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, ProductCategory $family, Variant $variant, ActionRequest $request): Variant
    {
        $this->parent = $subDepartment;

        $this->initialisationFromShop($shop, $request)->withTab(VariantTabsEnum::values());

        return $this->handle($variant);
    }

    /**
     * @throws \Throwable
     */
    public function handle(Variant $variant): Variant
    {
        return $variant;
    }

    /**
     * The variant structure is owned by the master variant, the shop only decides which of its
     * products are hidden, so the master data is the value and the shop's is_hide is laid over it.
     *
     * @return array<string, mixed>
     */
    private function getVariantFieldValue(Variant $variant, MasterVariant $masterVariant): array
    {
        $hiddenMasterProductIds = Product::whereIn(
            'id',
            collect(data_get($variant->data, 'products', []))
                ->filter(fn (array $product) => Arr::get($product, 'is_hide'))
                ->keys()
        )->pluck('master_product_id')->all();

        $variantData = $masterVariant->data;

        $variantData['products'] = collect(data_get($variantData, 'products', []))
            ->map(fn (array $product, int $masterProductId) => array_merge($product, [
                'is_hide' => in_array($masterProductId, $hiddenMasterProductIds)
            ]))
            ->all();

        return $variantData;
    }

    public function htmlResponse(Variant $variant, ActionRequest $request): Response
    {
        $blueprint = [
            [
                'label'   => __('Properties'),
                'icon'    => 'fa-light fa-fingerprint',
                'fields'  => [
                    'status'    => [
                        'type'  => 'toggle',
                        'label' => __('Enable Variant under this shop'),
                        'value' => $variant->status,

                    ]
                ],
            ],
        ];

        if ($masterVariant = $variant->masterVariant) {
            $blueprint[] = [
                'label'   => __('Variants'),
                'icon'    => 'fa-light fa-shapes',
                'fields'  => [
                    'variants' => [
                        'type'               => 'variant_field',
                        'label'              => __('Variants'),
                        'value'              => $this->getVariantFieldValue($variant, $masterVariant),
                        'required'           => true,
                        'full'               => true,
                        'revisit_after_save' => true,
                    ]
                ],
            ];
        }

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit Master Variant'),
                'breadcrumbs' => ShowVariant::make()->getBreadcrumbs(
                    $variant,
                    preg_replace('/edit$/', 'show', $request->route()->getName()),
                    $request->route()->originalParameters(),
                    '(editing)'
                ),
                'pageHead'    => [
                    'title'     => __('Edit master variant'),
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'warning'     => [
                    'type'  => 'warning',
                    'title' => 'Warning',
                    'text'  => __('Adding a product into variants would force it as for sale'),
                    'icon'  => ['fas', 'fa-exclamation-triangle'],
                ],
                'formData' => [
                    'blueprint' => $blueprint,
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.variant.update',
                            'parameters' => [$variant->id]
                        ],
                    ]
                ]
            ]
        );
    }
}
