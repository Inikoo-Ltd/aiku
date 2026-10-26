<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-18h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\GetProductDevelopmentStageGate;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentPurchaseOrders;
use App\Actions\Goods\ProductDevelopment\StoreProductDevelopmentPurchaseOrder;
use App\Actions\Goods\ProductDevelopment\SignOffProductDevelopment;
use App\Actions\Goods\ProductDevelopment\UI\Traits\WithProductDevelopmentsIndex;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTypeEnum;
use App\Enums\UI\Goods\ProductDevelopmentTabsEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowProductDevelopment extends OrgAction
{
    use WithProductDevelopmentAuthorisation;
    use WithProductDevelopmentsIndex {
        WithProductDevelopmentsIndex::getBreadcrumbs as getIndexBreadcrumbs;
    }

    public function handle(ProductDevelopment $productDevelopment): ProductDevelopment
    {
        return $productDevelopment->load(['stages', 'members', 'stats', 'owner', 'supplier', 'agent', 'production.organisation', 'tradeUnitFamily']);
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): ProductDevelopment
    {
        $this->initialisationFromGroup(group(), $request)->withTab(ProductDevelopmentTabsEnum::values());

        return $this->handle($productDevelopment);
    }

    public function htmlResponse(ProductDevelopment $productDevelopment, ActionRequest $request): Response
    {
        $tabData = GetProductDevelopmentTabData::make();
        $gate    = GetProductDevelopmentStageGate::run($productDevelopment);
        $brand   = $productDevelopment->brand();
        $canSeeCosts = $this->canSeeProductDevelopmentCosts($request->user(), $productDevelopment);
        $overview    = GetProductDevelopmentOverview::run($productDevelopment, $canSeeCosts);

        $navigation = ProductDevelopmentTabsEnum::navigation();
        $navigation[ProductDevelopmentTabsEnum::PRODUCTS->value]['number'] = count($overview['products']);
        $navigation[ProductDevelopmentTabsEnum::FILES->value]['number']    = $productDevelopment->files()->whereNull('superseded_at')->where('scope', '!=', ProductDevelopmentFileScopeEnum::DESIGN_IMAGE->value)->count();
        $navigation[ProductDevelopmentTabsEnum::TASKS->value]['number']    = StaffTask::query()->where('model_type', 'ProductDevelopment')->where('model_id', $productDevelopment->id)->open()->count();
        if ($productDevelopment->kind !== ProductDevelopmentKindEnum::RECIPE) {
            unset($navigation[ProductDevelopmentTabsEnum::FORMULATION->value], $navigation[ProductDevelopmentTabsEnum::BUYERS_LIST->value]);
        }

        $tab = fn (ProductDevelopmentTabsEnum $tabEnum, callable $resolver) => $this->tab === $tabEnum->value
            ? $resolver
            : Inertia::optional($resolver);

        return Inertia::render(
            'Goods/ProductDevelopment',
            [
                'title'       => $productDevelopment->code.' '.$productDevelopment->name,
                'breadcrumbs' => $this->getShowBreadcrumbs($productDevelopment),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Product development'),
                        'icon'  => 'fal fa-lightbulb-on',
                    ],
                    'model' => $productDevelopment->code,
                    'title' => $productDevelopment->name,
                ],
                'header'      => [
                    'code'               => $productDevelopment->code,
                    'name'               => $productDevelopment->name,
                    'kind'               => [
                        'label' => ProductDevelopmentKindEnum::labels()[$productDevelopment->kind->value],
                        'icon'  => $productDevelopment->kind->icon(),
                        'color' => $productDevelopment->kind->color(),
                    ],
                    'type'               => [
                        'label' => ProductDevelopmentTypeEnum::labels()[$productDevelopment->type->value],
                        'icon'  => $productDevelopment->type->icon(),
                        'color' => $productDevelopment->type->color(),
                    ],
                    'status'             => [
                        'label' => ProductDevelopmentStatusEnum::labels()[$productDevelopment->status->value],
                        'icon'  => $productDevelopment->status->icon(),
                        'color' => $productDevelopment->status->color(),
                    ],
                    'brand'              => $brand ? [
                        'label' => $brand->name,
                        'color' => 'rose',
                        'route' => ['name' => 'grp.trade_units.brands.show', 'parameters' => ['brand' => $brand->slug]],
                    ] : null,
                    'family'             => $productDevelopment->tradeUnitFamily ? [
                        'label' => $productDevelopment->tradeUnitFamily->code,
                        'name'  => $productDevelopment->tradeUnitFamily->name,
                        'color' => 'cyan',
                        'route' => ['name' => 'grp.trade_units.families.show', 'parameters' => ['tradeUnitFamily' => $productDevelopment->tradeUnitFamily->slug]],
                    ] : null,
                    'supplier'           => $productDevelopment->supplier ? [
                        'label' => $productDevelopment->supplier->name,
                        'color' => 'orange',
                        'route' => ['name' => 'grp.supply-chain.suppliers.show', 'parameters' => ['supplier' => $productDevelopment->supplier->getRouteKey()]],
                    ] : null,
                    'agent'              => $productDevelopment->agent ? [
                        'label' => $productDevelopment->agent->name,
                        'color' => 'sky',
                        'route' => ['name' => 'grp.supply-chain.agents.show', 'parameters' => ['agent' => $productDevelopment->agent->getRouteKey()]],
                    ] : null,
                    'production'         => $productDevelopment->production ? [
                        'label'        => $productDevelopment->production->name,
                        'organisation' => $productDevelopment->production->organisation?->name,
                        'color'        => 'amber',
                        'route'        => ['name' => 'grp.org.productions.show', 'parameters' => ['organisation' => $productDevelopment->production->organisation?->slug, 'production' => $productDevelopment->production->slug]],
                    ] : null,
                    'owner'              => $productDevelopment->owner ? [
                        'id'          => $productDevelopment->owner->id,
                        'name'        => $productDevelopment->owner->contact_name ?: $productDevelopment->owner->username,
                        'username'    => $productDevelopment->owner->username,
                        'avatar'      => $productDevelopment->owner->image_id ? $productDevelopment->owner->imageSources(48, 48) : null,
                        'profile_url' => $request->user()->authTo('sysadmin.view') ? route('grp.sysadmin.users.show', ['user' => $productDevelopment->owner->slug]) : null,
                    ] : null,
                    'markets'            => collect($productDevelopment->markets)->map(fn (string $market) => TradeUnitMarketEnum::labels()[$market] ?? $market)->values()->all(),
                    'target_landing_date' => $productDevelopment->target_landing_date?->toDateString(),
                    'target_online_date' => $productDevelopment->target_online_date?->toDateString(),
                    'can_edit'           => $this->canEditProductDevelopment($request->user(), $productDevelopment),
                    'update_route'       => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.product_development.update',
                        'parameters' => ['productDevelopment' => $productDevelopment->id],
                    ],
                    'purchase_orders'    => [
                        'rows' => $purchaseOrders = ProductDevelopmentPurchaseOrders::rows($productDevelopment),
                        'link' => $this->canEditProductDevelopment($request->user(), $productDevelopment) ? [
                            'route'        => ['name' => 'grp.models.product_development.purchase_order', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
                            'search_route' => ['name' => 'grp.product_developments.json.purchase_orders', 'parameters' => ['productDevelopment' => $productDevelopment->slug]],
                            'create'       => [
                                'route'   => ['name' => 'grp.models.product_development.purchase_order.store', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
                                'options' => StoreProductDevelopmentPurchaseOrder::options($productDevelopment, $request->user()),
                                'empty'   => $productDevelopment->supplier_id ? __('None of your organisations buys from this supplier yet') : __('Set the supplier of this development first'),
                            ],
                        ] : null,
                    ],
                    'number_purchase_orders' => count($purchaseOrders),
                    'number_missing'     => (int) $productDevelopment->stats?->number_required_missing,
                ],
                'overview'    => $overview,
                'finalApproval' => $this->finalApproval($productDevelopment, $request->user()),
                'push'          => config('services.webpush.public_key') && in_array('browser', UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK->channelsFor($request->user()), true)
                    ? ['public_key' => config('services.webpush.public_key'), 'profile_route' => ['name' => 'grp.profile.show', 'parameters' => []]]
                    : null,
                'reminders'     => $this->canEditProductDevelopment($request->user(), $productDevelopment) ? [
                    'route'     => ['name' => 'grp.models.product_development.remind', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
                    'last_sent' => $productDevelopment->data['reminders_sent'] ?? null,
                ] : null,
                'canEditStageDates' => $this->canEditProductDevelopment($request->user(), $productDevelopment),
                'editor'      => $this->canEditProductDevelopment($request->user(), $productDevelopment) ? $this->editor($productDevelopment) : null,
                'stageMove'   => [
                    'can_move'   => $gate['can_move'],
                    'reason'     => $gate['reason'],
                    'is_allowed' => $this->canMoveProductDevelopmentStage($request->user(), $productDevelopment),
                    'is_done'    => $productDevelopment->status === ProductDevelopmentStatusEnum::COMPLETED,
                    'next_label' => ProductDevelopmentStageEnum::positionLabel($productDevelopment->current_position + 1),
                    'route'      => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.product_development.next_stage',
                        'parameters' => ['productDevelopment' => $productDevelopment->id],
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],

                ProductDevelopmentTabsEnum::BRIEF->value      => $tab(ProductDevelopmentTabsEnum::BRIEF, fn () => [...$tabData->brief($productDevelopment, $request->user()), 'editor' => GetProductDevelopmentChecklistEditor::run($productDevelopment, $request->user(), $canSeeCosts)]),
                ProductDevelopmentTabsEnum::PRODUCTS->value   => $tab(ProductDevelopmentTabsEnum::PRODUCTS, fn () => ['rows' => $overview['products'], 'editor' => GetProductDevelopmentChecklistEditor::run($productDevelopment, $request->user(), $canSeeCosts)]),
                ProductDevelopmentTabsEnum::FORMULATION->value => $tab(ProductDevelopmentTabsEnum::FORMULATION, fn () => $productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE ? GetProductDevelopmentFormulation::run($productDevelopment, $request->user()) : null),
                ProductDevelopmentTabsEnum::BUYERS_LIST->value => $tab(ProductDevelopmentTabsEnum::BUYERS_LIST, fn () => $productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE ? GetProductDevelopmentBuyersList::run($productDevelopment, $request->user()) : null),
                ProductDevelopmentTabsEnum::COMPLIANCE->value => $tab(ProductDevelopmentTabsEnum::COMPLIANCE, fn () => GetProductDevelopmentChecklistEditor::run($productDevelopment, $request->user(), $canSeeCosts, ProductDevelopmentMemberRoleEnum::COMPLIANCE)),
                ProductDevelopmentTabsEnum::DESIGN->value     => $tab(ProductDevelopmentTabsEnum::DESIGN, fn () => GetProductDevelopmentDesignOverview::run($productDevelopment, $request->user())),
                ProductDevelopmentTabsEnum::FILES->value      => $tab(ProductDevelopmentTabsEnum::FILES, fn () => GetProductDevelopmentFiles::run($productDevelopment, $this->productDevelopmentAccess()->canUpload($request->user(), $productDevelopment))),
                ProductDevelopmentTabsEnum::TASKS->value      => $tab(ProductDevelopmentTabsEnum::TASKS, fn () => ['model_type' => 'ProductDevelopment', 'model_id' => $productDevelopment->id]),
                ProductDevelopmentTabsEnum::ACTIVITY->value   => $tab(ProductDevelopmentTabsEnum::ACTIVITY, fn () => GetProductDevelopmentActivity::run($productDevelopment, GetProductDevelopmentActivity::LIMIT, $request->user())),
                'comments'       => fn () => GetProductDevelopmentComments::run($productDevelopment),
                'recentActivity' => fn () => GetProductDevelopmentActivity::run($productDevelopment, GetProductDevelopmentActivity::LIMIT, $request->user()),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function editor(ProductDevelopment $productDevelopment): array
    {
        $asOptions      = fn (array $labels) => collect($labels)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all();
        $agentIdsByUser = $this->productDevelopmentAccess()->agentIdsByUser($productDevelopment->group_id);

        return [
            'values'  => [
                'markets'             => $productDevelopment->markets ?? [],
                'target_landing_date' => $productDevelopment->target_landing_date?->toDateString(),
                'target_online_date'  => $productDevelopment->target_online_date?->toDateString(),
                'agent_id'            => $productDevelopment->agent_id,
                'members'             => $productDevelopment->members()->get(['user_id', 'role'])->map(fn ($member) => ['user_id' => $member->user_id, 'role' => $member->role->value])->all(),
            ],
            'options' => [
                'markets' => $asOptions(TradeUnitMarketEnum::labels()),
                'roles'   => collect(ProductDevelopmentMemberRoleEnum::cases())->map(fn (ProductDevelopmentMemberRoleEnum $role) => [
                    'value' => $role->value,
                    'label' => ProductDevelopmentMemberRoleEnum::labels()[$role->value],
                    'icon'  => $role->icon(),
                    'color' => $role->color(),
                ])->all(),
                'users'   => User::where('group_id', $productDevelopment->group_id)->where('status', true)->orderBy('contact_name')
                    ->get(['id', 'contact_name', 'username', 'image_id'])
                    ->map(fn (User $user) => [
                        'value'  => $user->id,
                        'label'  => $user->contact_name ?: $user->username,
                        'avatar'    => $user->image_id ? $user->imageSources(48, 48) : null,
                        'agent_ids' => $agentIdsByUser[$user->id] ?? [],
                    ])
                    ->all(),
            ],
            'routes'  => [
                'update'  => ['name' => 'grp.models.product_development.update', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
                'members' => ['name' => 'grp.models.product_development.members.sync', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
            ],
        ];
    }

    /**
     * The buyer and compliance sign-offs, shown from the final approval stage on.
     *
     * @return array<string, mixed>|null
     */
    private function finalApproval(ProductDevelopment $productDevelopment, User $viewer): ?array
    {
        $open    = SignOffProductDevelopment::isOpenForSignOff($productDevelopment);
        $signOffs = SignOffProductDevelopment::signOffs($productDevelopment);
        if (!$open && collect($signOffs)->filter()->isEmpty()) {
            return null;
        }

        $roles      = $this->productDevelopmentAccess()->memberRoles($viewer, $productDevelopment);
        $roleLabels = ProductDevelopmentMemberRoleEnum::labels();

        return [
            'open'      => $open,
            'locked_at' => $productDevelopment->data['artwork_locked_at'] ?? null,
            'route'     => ['name' => 'grp.models.product_development.sign_off', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
            'rows'      => collect(SignOffProductDevelopment::SIGNERS)->map(fn (ProductDevelopmentMemberRoleEnum $role) => [
                'role'      => $role->value,
                'label'     => $roleLabels[$role->value],
                'icon'      => $role->icon(),
                'color'     => $role->color(),
                'signed_by' => $signOffs[$role->value]['name'] ?? null,
                'signed_at' => $signOffs[$role->value]['at'] ?? null,
                'can_sign'  => $open && in_array($role, $roles, true),
            ])->all(),
        ];
    }

    public function getShowBreadcrumbs(ProductDevelopment $productDevelopment): array
    {
        return array_merge(
            $this->getIndexBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.product_developments.show',
                            'parameters' => ['productDevelopment' => $productDevelopment->slug],
                        ],
                        'label' => $productDevelopment->code,
                    ],
                ],
            ]
        );
    }
}
