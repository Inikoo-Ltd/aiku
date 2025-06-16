<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 10:05:33 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\Snapshot\StoreWebsiteSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AutosaveWebsiteMarginal extends OrgAction
{
    use WithActionUpdate;

    public bool $isAction = false;
    public string $marginal;

    public function handle(Website $website, string $marginal, array $modelData): Website
    {
        $this->marginal = $marginal;

        if ($marginal == 'header') {
            $layout = Arr::get($modelData, 'layout') ?? $website->unpublishedHeaderSnapshot->layout;

            $this->update($website->unpublishedHeaderSnapshot, [
                'layout' => [
                    'header' => $layout
                ]
            ]);
        } elseif ($marginal == 'footer') {
            $layout = Arr::get($modelData, 'layout') ?? $website->unpublishedFooterSnapshot->layout;

            $this->update($website->unpublishedFooterSnapshot, [
                'layout' => [
                    'footer' => $layout
                ]
            ]);
        } elseif ($marginal == 'menu') {
            if (!$website->unpublishedMenuSnapshot) {
                $menuSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::MENU,
                        'layout' => []
                    ]
                );

                $website->update(
                    [
                        'unpublished_menu_snapshot_id' => $menuSnapshot->id
                    ]
                );
                $website->refresh();
            }

            $layout = Arr::get($modelData, 'layout') ?? $website->unpublishedMenuSnapshot->layout;


            $this->update($website->unpublishedMenuSnapshot, [
                'layout' => [
                    'menu' => $layout
                ]
            ]);
        } elseif ($marginal == 'department') {
            if (!$website->unpublishedDepartmentSnapshot) {
                $departmentSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::DEPARTMENT,
                        'layout' => []
                    ]
                );

                $website->update(
                    [
                        'unpublished_department_snapshot_id' => $departmentSnapshot->id
                    ]
                );
                $website->refresh();
            }

            $layout = Arr::get($modelData, 'layout') ?? $website->unpublishedDepartmentSnapshot->layout;

            $this->update($website->unpublishedDepartmentSnapshot, [
                'layout' => [
                    'department' => $layout
                ]
            ]);
        } elseif ($marginal == 'sub_department') {
            if (!$website->unpublishedSubDepartmentSnapshot) {
                $subDepartmentSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::SUB_DEPARTMENT,
                        'layout' => []
                    ]
                );

                $website->update(
                    [
                        'unpublished_sub_department_snapshot_id' => $subDepartmentSnapshot->id
                    ]
                );
                $website->refresh();
            }

            $layout = Arr::get($modelData, 'layout') ?? $website->unpublishedSubDepartmentSnapshot->layout;

            $this->update($website->unpublishedSubDepartmentSnapshot, [
                'layout' => [
                    'sub_department' => $layout
                ]
            ]);
        } elseif ($marginal == 'family') {
            if (!$website->unpublishedFamilySnapshot) {
                $familySnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::FAMILY,
                        'layout' => []
                    ]
                );

                $website->update(
                    [
                        'unpublished_family_snapshot_id' => $familySnapshot->id
                    ]
                );
                $website->refresh();
            }

            $layout = Arr::get($modelData, 'layout') ?? $website->unpublishedFamilySnapshot->layout;

            $this->update($website->unpublishedFamilySnapshot, [
                'layout' => [
                    'family' => $layout
                ]
            ]);
        } elseif ($marginal == 'product') {
            if (!$website->unpublishedProductSnapshot) {
                $productSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::PRODUCT,
                        'layout' => []
                    ]
                );

                $website->update(
                    [
                        'unpublished_product_snapshot_id' => $productSnapshot->id
                    ]
                );
                $website->refresh();
            }

            $layout = Arr::get($modelData, 'layout') ?? $website->unpublishedProductSnapshot->layout;

            $this->update($website->unpublishedProductSnapshot, [
                'layout' => [
                    'product' => $layout
                ]
            ]);
        } elseif ($marginal == 'products') {
            if (!$website->unpublishedProductsSnapshot) {
                $productsSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::PRODUCTS,
                        'layout' => []
                    ]
                );

                $website->update(
                    [
                        'unpublished_products_snapshot_id' => $productsSnapshot->id
                    ]
                );
                $website->refresh();
            }

            $layout = Arr::get($modelData, 'layout') ?? $website->unpublishedProductsSnapshot->layout;

            $this->update($website->unpublishedProductsSnapshot, [
                'layout' => [
                    'products' => $layout
                ]
            ]);
        } elseif ($marginal == 'collection') {
            if (!$website->unpublishedCollectionSnapshot) {
                $collectionSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::COLLECTION,
                        'layout' => []
                    ]
                );

                $website->update(
                    [
                        'unpublished_collection_snapshot_id' => $collectionSnapshot->id
                    ]
                );
                $website->refresh();
            }

            $layout = Arr::get($modelData, 'layout') ?? $website->unpublishedCollectionSnapshot->layout;

            $this->update($website->unpublishedCollectionSnapshot, [
                'layout' => [
                    'collection' => $layout
                ]
            ]);
        }
        return $website;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(
            [
                'publisher_id'   => $request->user()->id,
                'publisher_type' => 'User'
            ]
        );
    }

    public function rules(): array
    {
        return [
            'comment'        => ['sometimes', 'required', 'string', 'max:1024'],
            'publisher_id'   => ['sometimes'],
            'publisher_type' => ['sometimes', 'string'],
            'layout'         => ['sometimes']
        ];
    }


    public function header(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'header', $this->validatedData);
    }

    public function footer(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'footer', $this->validatedData);
    }

    public function theme(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'theme', $this->validatedData);
    }

    public function menu(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'menu', $this->validatedData);
    }

    public function department(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'department', $this->validatedData);
    }

    public function subDepartment(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'sub_department', $this->validatedData);
    }

    public function family(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'family', $this->validatedData);
    }

    public function product(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'product', $this->validatedData);
    }

    public function products(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'products', $this->validatedData);
    }

    public function collection(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'collection', $this->validatedData);
    }

    public function action(Website $website, $marginal, $modelData): string
    {
        $this->isAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        $this->handle($website, $marginal, $validatedData);

        return "🚀";
    }


}
