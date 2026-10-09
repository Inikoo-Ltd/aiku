<?php

/*
 * author Louis Perez
 * created on 23-12-2025-13h-27m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterVariant;

use App\Actions\Catalogue\Variant\IndexVariantInMasterVariant;
use App\Actions\Catalogue\Variant\VariantOptionLabel;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Masters\MasterAsset\UI\IndexMasterProductsPricing;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamily;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterVariantTabsEnum;
use App\Http\Resources\Catalogue\VariantsResource;
use App\Http\Resources\Masters\MasterProductVariantResource;
use App\Http\Resources\Masters\MasterProductsPricingResource;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterVariant;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterVariant extends OrgAction
{
    use WithMastersAuthorisation;

    private MasterProductCategory $parent;

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    public function inMasterFamily(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup(group(), $request)->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    /**
     * @throws \Throwable
     */
    public function handle(MasterVariant $masterVariant): Response
    {
        $masterProductInVariant = MasterProductVariantResource::collection(MasterAsset::whereIn('id', data_get($masterVariant->data, 'products.*.product.id', []))->get());
        $masterShop             = $masterVariant->masterFamily->masterShop;

        $pricingSupportCache = null;
        $pricingSupportProps = function () use ($masterShop, &$pricingSupportCache): array {
            if ($pricingSupportCache !== null) {
                return $pricingSupportCache;
            }
            $currenciesRate = GetMasterShopCurrenciesRate::run($masterShop);

            return $pricingSupportCache = [
                'pricingCurrencies' => $currenciesRate,
                'pricingCostRates'  => $currenciesRate
                    ->keys()
                    ->mapWithKeys(function (string $currencyCode) use ($masterShop) {
                        $currency = Currency::where('code', $currencyCode)->first();

                        return [
                            $currencyCode => $currency
                                ? GetCurrencyExchange::run($masterShop->group->currency, $currency)
                                : null
                        ];
                    }),
            ];
        };

        return Inertia::render(
            'Masters/MasterVariant',
            [
                'breadcrumbs'     => $this->getBreadcrumbs(
                    $masterVariant,
                    request()->route()->getName(),
                    request()->route()->originalParameters()
                ),
                'title'           => __('Show Master Variant'),
                'pageHead'        => [
                    'title' => $masterVariant->code,
                    'model'         => __('Master Variants'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shapes'],
                        'title' => __('Master Variant')
                    ],
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', request()->route()->getName()),
                                'parameters' => request()->route()->originalParameters()
                            ]
                        ],
                    ],
                ],
                'tabs'                    => [
                    'current'    => $this->tab,
                    'navigation' => MasterVariantTabsEnum::navigation()
                ],
                'masterProductCategoryId' => $masterVariant->masterFamily->id,
                'pricingMajorCurrencies'  => collect($masterShop->price_exchanges ?? [])
                    ->filter(fn (array $exchangeData) => $exchangeData['is_major'] ?? false)
                    ->keys()
                    ->values(),
                'pricingCurrencies'       => $this->tab === MasterVariantTabsEnum::PRICING->value
                    ? $pricingSupportProps()['pricingCurrencies']
                    : Inertia::optional(fn () => $pricingSupportProps()['pricingCurrencies']),
                'pricingCostRates'        => $this->tab === MasterVariantTabsEnum::PRICING->value
                    ? $pricingSupportProps()['pricingCostRates']
                    : Inertia::optional(fn () => $pricingSupportProps()['pricingCostRates']),
                MasterVariantTabsEnum::SHOWCASE->value =>
                    $this->tab === MasterVariantTabsEnum::SHOWCASE->value ? [
                        'master_variant'            => $masterVariant,
                        'master_products' => $masterProductInVariant
                    ] : Inertia::optional(fn () => [
                        'master_variant'            => $masterVariant,
                        'master_products' => $masterProductInVariant,
                    ]),
                'reorderRoute'                         => $this->canEdit ? [
                    'name'       => 'grp.models.master_variant.reorder_products',
                    'parameters' => ['masterVariant' => $masterVariant->id],
                ] : null,
                MasterVariantTabsEnum::PRODUCTS->value =>
                    $this->tab === MasterVariantTabsEnum::PRODUCTS->value ? $this->orderedProducts($masterVariant)
                    : Inertia::optional(fn () => $this->orderedProducts($masterVariant)),
                MasterVariantTabsEnum::VARIANTS->value =>
                    $this->tab === MasterVariantTabsEnum::VARIANTS->value ? VariantsResource::collection(IndexVariantInMasterVariant::run($masterVariant, MasterVariantTabsEnum::VARIANTS->value))
                    : Inertia::optional(fn () => VariantsResource::collection(IndexVariantInMasterVariant::run($masterVariant, MasterVariantTabsEnum::VARIANTS->value))),
                MasterVariantTabsEnum::PRICING->value =>
                    $this->tab === MasterVariantTabsEnum::PRICING->value ? MasterProductsPricingResource::collection(IndexMasterProductsPricing::run($masterVariant, MasterVariantTabsEnum::PRICING->value))
                    : Inertia::optional(fn () => MasterProductsPricingResource::collection(IndexMasterProductsPricing::run($masterVariant, MasterVariantTabsEnum::PRICING->value))),
            ]
        )
        ->table(IndexVariantInMasterVariant::make()->tableStructure(masterVariant: $masterVariant, prefix: MasterVariantTabsEnum::VARIANTS->value))
        ->table(IndexMasterProductsPricing::make()->tableStructure($masterVariant, prefix: MasterVariantTabsEnum::PRICING->value));
    }


    /**
     * @return array<int, array{id: int, code: string, name: string|null, image_thumbnail: mixed}>
     */
    private function orderedProducts(MasterVariant $masterVariant): array
    {
        $masterShop   = $masterVariant->masterFamily->masterShop;
        $masterFamily = $masterVariant->masterFamily;

        return MasterAsset::where('master_variant_id', $masterVariant->id)
            ->leftJoin('master_asset_stats', 'master_asset_stats.master_asset_id', 'master_assets.id')
            ->orderByRaw('master_assets.index_under_master_variant asc nulls last')
            ->orderByDesc('master_assets.is_variant_leader')
            ->orderBy('master_assets.code')
            ->get(['master_assets.id', 'master_assets.slug', 'master_assets.code', 'master_assets.name', 'master_assets.web_images', 'master_assets.status', 'master_assets.unit', 'master_assets.is_variant_leader', 'master_asset_stats.number_current_assets as used_in'])
            ->map(fn (MasterAsset $masterAsset) => [
                'id'                => $masterAsset->id,
                'code'              => $masterAsset->code,
                'name'              => $masterAsset->name,
                'unit'              => $masterAsset->unit,
                'option_label'      => VariantOptionLabel::run($masterVariant->data, $masterAsset->id),
                'used_in'           => (int) $masterAsset->used_in,
                'is_variant_leader' => (bool) $masterAsset->is_variant_leader,
                'image_thumbnail'   => Arr::get($masterAsset->web_images, 'main.thumbnail'),
                'status_icon'       => $masterAsset->status
                    ? ['tooltip' => __('Active'), 'icon' => 'fas fa-check-circle', 'class' => 'text-green-400']
                    : ['tooltip' => __('Closed'), 'icon' => 'fas fa-times-circle', 'class' => 'text-red-400'],
                'url'               => route('grp.masters.master_shops.show.master_families.master_products.show', [
                    'masterShop'    => $masterShop->slug,
                    'masterFamily'  => $masterFamily->slug,
                    'masterProduct' => $masterAsset->slug,
                ]),
            ])
            ->all();
    }

    /**
     * @throws \Throwable
     */
    public function asController(MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->initialisationFromGroup($masterVariant->group, $request);
        return $this->handle($masterVariant);
    }

    public function getBreadcrumbs(MasterVariant $masterVariant, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterVariant $masterVariant, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'label' => __('Master variant')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $masterVariant->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        return array_merge(
            ShowMasterFamily::make()->getBreadcrumbs(
                masterFamily: $masterVariant->masterFamily,
                routeName: 'grp.masters.master_shops.show.master_families.show',
                routeParameters: $routeParameters,
            ),
            $headCrumb(
                $masterVariant,
                [
                    'model' => [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                ],
                $suffix
            )
        );
    }
}
