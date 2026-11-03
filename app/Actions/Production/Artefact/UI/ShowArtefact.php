<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 08:39:50 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\Production\Artisan\GetArtisanAssignmentProps;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Actions\CRM\Customer\StoreCustomerProductFromArtefact;
use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\UI\Production\ArtefactTabsEnum;
use App\Http\Resources\Production\ArtefactBatchesResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\ArtefactResource;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowArtefact extends OrgAction
{
    use WithActionButtons;

    private bool $canDevelop = false;

    public function handle(Artefact $artefact): Artefact
    {
        return $artefact;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo('org-supervisor.'.$this->organisation->id);
        $this->canDelete = $request->user()->authTo('org-supervisor.'.$this->organisation->id);
        $this->canDevelop = $request->user()->authTo(['org-supervisor.'.$this->organisation->id, "productions_rd.{$this->production->id}.edit"]);


        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_rd.{$this->production->id}.view",
            "productions_procurement.{$this->production->id}.view",

        ]);
    }


    public function asController(Organisation $organisation, Production $production, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->initialisationFromProduction($production, $request)->withTab(ArtefactTabsEnum::values());

        return $this->handle($artefact);
    }

    public function htmlResponse(Artefact $artefact, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/Artefact',
            [
                'title'                                => __('Artefact') . ' ' . $artefact->code,
                'breadcrumbs'                          => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'                           => fn () => $this->getListNavigation($artefact, $request),
                'customer_product'                     => fn () => $this->getCustomerProduct($artefact, $request),
                'exclusive_products'                   => fn () => $this->getExclusiveProducts($artefact),
                'pageHead'                             => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-hamsa'],
                            'title' => __('Warehouse area')
                        ],
                    'model'   => $artefact->branchedFrom ? __('Branch of :code', ['code' => $artefact->branchedFrom->code]) : __('Artefact'),
                    'title'   => $artefact->name,
                    'actions' => [
                        $this->canDevelop && in_array($artefact->state, ArtefactStateEnum::launched(), true) ? [
                            'type'    => 'button',
                            'style'   => 'tertiary',
                            'icon'    => 'fal fa-code-branch',
                            'label'   => __('Branch for development'),
                            'tooltip' => __('Redesign it in the Development room as an experiment with a copy of its recipe, batch size, shelf life and family. This artefact is made, ordered and sold as it is until the branch is merged back.'),
                            'route'   => [
                                'method'     => 'post',
                                'name'       => 'grp.models.production.artefacts.branch',
                                'parameters' => ['production' => $artefact->production_id, 'artefact' => $artefact->id],
                            ],
                        ] : null,
                        // $this->canEdit ?
                        //     [
                        //         'type'    => 'button',
                        //         'style'   => 'create',
                        //         'tooltip' => __('New location'),
                        //         'label'   => __('New location'),
                        //         'route'   => [
                        //             'name'       => $request->route()->getName().'.locations.create',
                        //             'parameters' => $request->route()->originalParameters()
                        //         ]
                        //     ]
                        //     : null,
                        // $this->canDelete ? $this->getDeleteActionIcon($request) : null,

                        [
                            'type'    => 'button',
                            'tooltip'     => __('Edit'),
                            'icon'        => 'fal fa-pencil',
                            'style'       => 'tertiary',
                            'route'       => [
                                'name'       => preg_replace('/(show|dashboard)$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ],
                        [
                            'type'    => 'button',
                            'style'   => $artefact->rawMaterial ? 'tertiary' : 'secondary',
                            'icon'    => 'fal fa-blender-phone',
                            'label'   => $artefact->rawMaterial ? __('Used as ingredient') : __('Use as ingredient'),
                            'tooltip' => $artefact->rawMaterial
                                ? __('Click to stop using it as an ingredient. This artefact is also an in-house ingredient: other artefacts can use it in their recipes, and it is listed under To produce → Mixes when an open job order needs it.')
                                : __('Also make this artefact available as an in-house ingredient, so other artefacts can use it in their recipes. It is then listed under To produce → Mixes whenever an open job order needs it.'),
                            'route'   => [
                                'method'     => 'post',
                                'name'       => 'grp.org.productions.show.crafts.artefacts.mix',
                                'parameters' => array_merge($request->route()->originalParameters(), ['is_mix' => !$artefact->rawMaterial]),
                            ],
                        ],
                    ],
                    // 'meta'    => [
                    //     [
                    //         'name'     => trans_choice('location|locations', $artefact->stats->number_locations),
                    //         'number'   => $artefact->stats->number_locations,
                    //         'route'     => [
                    //             'name'       => 'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.index',
                    //             'parameters' => $request->route()->originalParameters()
                    //         ],
                    //         'leftIcon' => [
                    //             'icon'    => 'fal fa-inventory',
                    //             'tooltip' => __('locations')
                    //         ]
                    //     ]
                    // ]
                ],
                'artisans'                             => GetArtisanAssignmentProps::run($artefact, $this->canEdit),
                'tabs'                                 => [
                    'current'    => $this->tab,
                    'navigation' => $this->getTabsNavigation($artefact)
                ],
                ArtefactTabsEnum::SHOWCASE->value => $this->tab == ArtefactTabsEnum::SHOWCASE->value ?
                    fn () => GetArtefactShowcase::run($artefact)
                    : Inertia::optional(fn () => GetArtefactShowcase::run($artefact)),

                ArtefactTabsEnum::CURRENT_ORDERS->value => $this->tab == ArtefactTabsEnum::CURRENT_ORDERS->value
                    ? fn () => ArtefactBatchesResource::collection(IndexArtefactBatches::run($artefact, ArtefactTabsEnum::CURRENT_ORDERS->value, true))
                    : Inertia::optional(fn () => ArtefactBatchesResource::collection(IndexArtefactBatches::run($artefact, ArtefactTabsEnum::CURRENT_ORDERS->value, true))),

                ArtefactTabsEnum::BATCHES->value => $this->tab == ArtefactTabsEnum::BATCHES->value
                    ? fn () => IndexArtefactBatches::make()->jobsDone($artefact, ArtefactTabsEnum::BATCHES->value)
                    : Inertia::optional(fn () => IndexArtefactBatches::make()->jobsDone($artefact, ArtefactTabsEnum::BATCHES->value)),

                ArtefactTabsEnum::MANUFACTURE_TASKS->value => $this->tab == ArtefactTabsEnum::MANUFACTURE_TASKS->value
                    ? fn () => GetArtefactManufactureTasks::run($artefact)
                    : Inertia::optional(fn () => GetArtefactManufactureTasks::run($artefact)),

                ArtefactTabsEnum::LABELS->value => $this->tab == ArtefactTabsEnum::LABELS->value
                    ? fn () => GetArtefactLabels::run($artefact)
                    : Inertia::optional(fn () => GetArtefactLabels::run($artefact)),

                ArtefactTabsEnum::COMPLIANCE->value => $this->tab == ArtefactTabsEnum::COMPLIANCE->value
                    ? fn () => GetArtefactCompliance::run($artefact)
                    : Inertia::optional(fn () => GetArtefactCompliance::run($artefact)),

                // ArtefactTabsEnum::LOCATIONS->value => $this->tab == ArtefactTabsEnum::LOCATIONS->value
                //     ?
                //     fn () => LocationResource::collection(
                //         IndexLocations::run(
                //             parent: $artefact,
                //             prefix: ArtefactTabsEnum::LOCATIONS->value
                //         )
                //     )
                //     : Inertia::optional(fn () => LocationResource::collection(
                //         IndexLocations::run(
                //             parent: $artefact,
                //             prefix: ArtefactTabsEnum::LOCATIONS->value
                //         )
                //     )),

                ArtefactTabsEnum::HISTORY->value => $this->tab == ArtefactTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($artefact, ArtefactTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($artefact, ArtefactTabsEnum::HISTORY->value)))

            ]
            // )->table(
            //     IndexLocations::make()->tableStructure(
            //         parent: $artefact,
            //         prefix: ArtefactTabsEnum::LOCATIONS->value
            //     )
        )->table(IndexArtefactBatches::make()->tableStructure(prefix: ArtefactTabsEnum::CURRENT_ORDERS->value, open: true))
            ->table(IndexArtefactBatches::make()->tableStructure(artefact: $artefact, prefix: ArtefactTabsEnum::BATCHES->value))
            ->table(IndexHistory::make()->tableStructure(prefix: ArtefactTabsEnum::HISTORY->value));
    }


    private function getTabsNavigation(Artefact $artefact): array
    {
        $navigation = ArtefactTabsEnum::navigation();

        foreach ($this->getTabNumbers($artefact) as $tab => $number) {
            $navigation[$tab]['number'] = $number;
        }

        return $navigation;
    }

    /**
     * @return array<string, int>
     */
    public function getTabNumbers(Artefact $artefact): array
    {
        return [
            ArtefactTabsEnum::CURRENT_ORDERS->value    => IndexArtefactBatches::make()->toDo($artefact)->reorder()->count(),
            ArtefactTabsEnum::BATCHES->value           => IndexArtefactBatches::make()->history($artefact)->whereNull('job_orders.deleted_at')->count(),
            ArtefactTabsEnum::MANUFACTURE_TASKS->value => $artefact->manufactureTasks()->count(),
            ArtefactTabsEnum::LABELS->value            => $artefact->labels()->count(),
            ArtefactTabsEnum::COMPLIANCE->value        => $artefact->complianceItems()->count(),
        ];
    }

    public function jsonResponse(Artefact $artefact): ArtefactResource
    {
        return new ArtefactResource($artefact);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $artefact = Artefact::where('slug', $routeParameters['artefact'])->first();

        return array_merge(
            ShowCraftsDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefacts.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Artefacts'),
                            'icon'  => 'fal fa-bars',
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefacts.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => $artefact?->code,
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    /**
     * Previous and next artefact of the list the page was opened from (?list=production, family:{slug} or department:{slug}, with that
     * list's own query string in list_query for its filters and order), or of the artefact's family in code order when there is none.
     *
     * @return array{previous: ?array, next: ?array, position: ?string}
     */
    public function getListNavigation(Artefact $artefact, ActionRequest $request): array
    {
        $ids = $this->getListIds(
            $artefact,
            $this->getListParent($artefact, $request->query('list')),
            $request->has('list') ? (string) $request->query('list_query') : $this->getFamilyListQuery($artefact)
        );
        $index = $ids->search($artefact->id);

        if ($index === false && $request->has('list')) {
            $ids = $this->getListIds($artefact, $this->getListParent($artefact, null), $this->getFamilyListQuery($artefact));
            $index = $ids->search($artefact->id);
        }

        if ($index === false) {
            return ['previous' => null, 'next' => null, 'position' => null];
        }

        $neighbours = Artefact::whereIn('id', array_filter([$ids->get($index - 1), $ids->get($index + 1)]))->get(['id', 'slug', 'code', 'name'])->keyBy('id');

        return [
            'previous' => $this->getNavigation($neighbours->get($ids->get($index - 1))),
            'next'     => $this->getNavigation($neighbours->get($ids->get($index + 1))),
            'position' => __(':index of :total', ['index' => $index + 1, 'total' => $ids->count()]),
        ];
    }

    /**
     * Opened directly, the family list shows the artefact's own state, so a discontinued artefact steps through the discontinued ones.
     */
    private function getFamilyListQuery(Artefact $artefact): string
    {
        return http_build_query(['artefacts_elements' => ['state' => $artefact->state->value]]);
    }

    private function getListParent(Artefact $artefact, ?string $list): Production|ArtefactFamily|ArtefactDepartment
    {
        [$kind, $slug] = array_pad(explode(':', (string) $list, 2), 2, null);

        $parent = match ($kind) {
            'production' => $artefact->production,
            'family'     => ArtefactFamily::where('production_id', $artefact->production_id)->where('slug', $slug)->first(),
            'department' => ArtefactDepartment::where('production_id', $artefact->production_id)->where('slug', $slug)->first(),
            default      => null,
        };

        return $parent ?? $artefact->artefactFamily ?? $artefact->production;
    }

    /**
     * Runs the list's own query with its query string, so the order and filters are the ones the user saw.
     */
    private function getListIds(Artefact $artefact, Production|ArtefactFamily|ArtefactDepartment $parent, string $listQuery): Collection
    {
        parse_str($listQuery, $listParameters);

        $request = request();
        $query   = $request->query->all();
        // ponytail: IndexArtefacts reads its filters from request(), so the list's query string stands in for the page's while the ids are read
        $request->query->replace($listParameters);

        try {
            return IndexArtefacts::make()->query($parent, 'artefacts')
                ->where('artefacts.production_id', $artefact->production_id)
                ->orderBy('artefacts.code')
                ->pluck('artefacts.id');
        } finally {
            $request->query->replace($query);
        }
    }

    private function getNavigation(?Artefact $artefact): ?array
    {
        if (!$artefact) {
            return null;
        }

        return [
            'label' => $artefact->code.' '.$artefact->name,
            'route' => [
                'name'       => 'grp.org.productions.show.crafts.artefacts.show',
                'parameters' => [
                    'organisation' => $this->organisation->slug,
                    'production'   => $this->production->slug,
                    'artefact'     => $artefact->slug,
                ]
            ]
        ];
    }

    /**
     * Shops where the viewer may make this artefact into a customer's custom product: only a new artefact, with no stock yet, qualifies.
     */
    private function getCustomerProduct(Artefact $artefact, ActionRequest $request): ?array
    {
        if ($artefact->trade_unit_id || $artefact->org_stock_id || !StoreCustomerProductFromArtefact::newArtefacts($artefact->organisation_id)->whereKey($artefact->id)->exists()) {
            return null;
        }

        $shops = $artefact->organisation->shops()
            ->whereIn('state', [ShopStateEnum::IN_PROCESS, ShopStateEnum::OPEN])
            ->whereIn('type', [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING])
            ->orderBy('code')
            ->get(['id', 'slug', 'code', 'name'])
            ->filter(fn (Shop $shop) => $request->user()->authTo("crm.{$shop->id}.edit"))
            ->map(fn (Shop $shop) => $shop->only(['id', 'slug', 'code', 'name']))
            ->values()
            ->all();

        return $shops ? ['artefact_id' => $artefact->id, 'shops' => $shops] : null;
    }

    /**
     * Products made from this artefact that only one customer may buy.
     *
     * @return array<int, array{code: string, customer: string, route: array{name: string, parameters: array<int, string>}}>
     */
    private function getExclusiveProducts(Artefact $artefact): array
    {
        if (!$artefact->tradeUnit) {
            return [];
        }

        return $artefact->tradeUnit->products()
            ->whereNotNull('exclusive_for_customer_id')
            ->with('exclusiveForCustomer.shop', 'exclusiveForCustomer.organisation')
            ->get()
            ->map(fn (Product $product) => [
                'code'     => $product->code,
                'customer' => $product->exclusiveForCustomer->name,
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.customers.show',
                    'parameters' => [
                        $product->exclusiveForCustomer->organisation->slug,
                        $product->exclusiveForCustomer->shop->slug,
                        $product->exclusiveForCustomer->slug,
                    ],
                ],
            ])
            ->values()
            ->all();
    }
}
