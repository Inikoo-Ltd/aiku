<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Country;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The product information written once on a master product for every shop: price, texts, the
 * physical data of its trade unit and the shipping and return texts shared by its family.
 */
class EditMasterProductInformation extends OrgAction
{
    use WithMastersAuthorisation;

    public function handle(MasterAsset $masterAsset): MasterAsset
    {
        return $masterAsset;
    }

    public function asController(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        if ($masterShop->id != $masterProduct->master_shop_id) {
            abort(404);
        }

        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    public function htmlResponse(MasterAsset $masterAsset, ActionRequest $request): Response
    {
        $masterAsset->loadMissing(['masterShop', 'masterFamily.stats', 'tradeUnits', 'group.currency']);

        $masterShop   = $masterAsset->masterShop;
        $masterFamily = $masterAsset->masterFamily;
        $tradeUnit    = $masterAsset->tradeUnits->count() === 1 ? $masterAsset->tradeUnits->first() : null;

        $masterProductRoute = [
            'name'       => 'grp.masters.master_shops.show.master_products.show',
            'parameters' => [
                'masterShop'    => $masterShop->slug,
                'masterProduct' => $masterAsset->slug,
            ],
        ];

        $backRoute = $masterFamily ? [
            'name'       => 'grp.masters.master_shops.show.master_families.master_products.index',
            'parameters' => [
                'masterShop'   => $masterShop->slug,
                'masterFamily' => $masterFamily->slug,
            ],
        ] : $masterProductRoute;

        $dimensions = $masterAsset->marketing_dimensions ?? [];
        $shared     = Arr::get($masterFamily?->data ?? [], 'shared_information', []);

        return Inertia::render(
            'Masters/EditMasterProductInformation',
            [
                'title'       => __('Edit product').': '.$masterAsset->code,
                'breadcrumbs' => ShowMasterProduct::make()->getBreadcrumbs(
                    masterAsset: $masterAsset,
                    routeName: 'grp.masters.master_shops.show.master_products.show',
                    routeParameters: $request->route()->originalParameters(),
                    suffix: '('.__('Edit product').')'
                ),
                'pageHead'    => [
                    'model'      => __('Edit product'),
                    'title'      => $masterAsset->name,
                    'afterTitle' => [
                        'label' => $masterAsset->code,
                    ],
                    'icon'       => [
                        'icon'  => ['fal', 'fa-cube'],
                        'title' => __('Master product'),
                    ],
                    'actions'    => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => $backRoute,
                        ]
                    ],
                ],
                'canEdit'     => $this->canEdit,
                'back_route'  => $backRoute,
                'back_label'  => $masterFamily ? __('Back to Master Products') : __('Back to master product'),
                'update_route' => [
                    'name'       => 'grp.models.master_asset.information.update',
                    'parameters' => ['masterAsset' => $masterAsset->id],
                ],
                'images_route' => [
                    'name'       => 'grp.masters.master_shops.show.master_products.show',
                    'parameters' => [...$masterProductRoute['parameters'], 'tab' => 'images'],
                ],
                'product'     => [
                    'code'              => $masterAsset->code,
                    'name'              => $masterAsset->name,
                    'image'             => $masterAsset->image_id ? $masterAsset->imageSources(200, 200) : null,
                    'price'             => $masterAsset->price !== null ? (float)$masterAsset->price : null,
                    'currency_code'     => $masterAsset->group->currency->code,
                    'description'       => $masterAsset->description ?? '',
                    'description_extra' => $masterAsset->description_extra ?? '',
                    'barcode'           => $masterAsset->barcode ?? '',
                ],
                'seo'         => [
                    'meta_description' => $masterAsset->meta_description ?? '',
                    'seo_image'        => $masterAsset->seo_image_url
                        ? ['original' => $masterAsset->seo_image_url]
                        : $masterAsset->imageSources(1200, 1200, 'seoImage'),
                    'seo_image_url'    => $masterAsset->seo_image_url,
                    'seo_image_alt'    => $masterAsset->seo_image_alt,
                ],
                'information' => [
                    'trade_unit'            => $tradeUnit ? $this->tradeUnitData($tradeUnit) : null,
                    'number_trade_units'    => $masterAsset->tradeUnits->count(),
                    'marketing_weight'      => $masterAsset->marketing_weight,
                    'gross_weight'          => $masterAsset->gross_weight,
                    'dimensions_cm'         => collect(['l', 'w', 'h'])->mapWithKeys(fn (string $side) => [
                        $side => Arr::get($dimensions, $side) !== null ? round((float)Arr::get($dimensions, $side) * 100, 2) : null,
                    ])->all(),
                    'marketing_ingredients' => $masterAsset->marketing_ingredients ?? '',
                    'origin_country_id'     => $masterAsset->origin_country_id,
                ],
                'countries'   => Country::orderBy('name')->get(['id', 'name'])->map(fn (Country $country) => ['value' => $country->id, 'label' => $country->name])->all(),
                'shared'      => $masterFamily ? [
                    'shipping'        => Arr::get($shared, 'shipping', ''),
                    'return_policy'   => Arr::get($shared, 'return_policy', ''),
                    'number_products' => $masterFamily->stats?->number_master_assets ?? 0,
                    'family_name'     => $masterFamily->name,
                ] : null,
                'checklist'   => GetMasterProductChecklist::run(GetMasterProductChecklist::make()->checklistRow($masterAsset)),
            ]
        );
    }

    private function tradeUnitData(TradeUnit $tradeUnit): array
    {
        return [
            'code'  => $tradeUnit->code,
            'route' => [
                'name'       => 'grp.trade_units.units.show',
                'parameters' => ['tradeUnit' => $tradeUnit->slug],
            ],
        ];
    }
}
