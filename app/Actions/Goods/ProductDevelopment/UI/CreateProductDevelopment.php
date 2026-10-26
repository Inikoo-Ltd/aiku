<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-18h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Actions\Goods\ProductDevelopment\UI\Traits\WithProductDevelopmentsIndex;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTypeEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Production\Production\ProductionStateEnum;
use App\Models\Helpers\Brand;
use App\Models\Production\Production;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateProductDevelopment extends OrgAction
{
    use WithProductDevelopmentAuthorisation;
    use WithProductDevelopmentsIndex;

    public function authorize(ActionRequest $request): bool
    {
        return $this->canCreateProductDevelopment($request->user());
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->group;
    }

    /**
     * @param array<string, string> $labels
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $labels): array
    {
        return collect($labels)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $agentIdsByUser = (new ProductDevelopmentAccess())->agentIdsByUser($group->id);

        return Inertia::render(
            'Goods/CreateProductDevelopment',
            [
                'breadcrumbs' => array_merge(
                    $this->getBreadcrumbs(),
                    [
                        [
                            'type'          => 'creatingModel',
                            'creatingModel' => [
                                'label' => __('Creating'),
                            ],
                        ],
                    ]
                ),
                'title'       => __('New product development'),
                'pageHead'    => [
                    'title'   => __('New product development'),
                    'icon'    => [
                        'title' => __('Product development'),
                        'icon'  => 'fal fa-lightbulb-on',
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'negative',
                            'icon'  => 'fal fa-arrow-left',
                            'label' => __('Back'),
                            'route' => [
                                'name'       => 'grp.product_developments.index',
                                'parameters' => [],
                            ],
                        ],
                    ],
                ],
                'currentUserId' => $request->user()->id,
                'options'     => [
                    'kinds'     => collect(ProductDevelopmentKindEnum::cases())->map(fn (ProductDevelopmentKindEnum $kind) => [
                        'value'       => $kind->value,
                        'label'       => ProductDevelopmentKindEnum::labels()[$kind->value],
                        'description' => ProductDevelopmentKindEnum::descriptions()[$kind->value],
                        'icon'        => $kind->icon(),
                    ])->all(),
                    'productions' => Production::where('group_id', $group->id)->whereNot('state', ProductionStateEnum::CLOSED->value)->with('organisation')->orderBy('name')->get()
                        ->map(fn (Production $production) => [
                            'value'        => $production->id,
                            'label'        => $production->name,
                            'organisation' => $production->organisation?->name,
                        ])->all(),
                    'types'     => collect(ProductDevelopmentTypeEnum::cases())->map(fn (ProductDevelopmentTypeEnum $type) => [
                        'value' => $type->value,
                        'label' => ProductDevelopmentTypeEnum::labels()[$type->value],
                        'icon'  => $type->icon(),
                        'color' => $type->color(),
                    ])->all(),
                    'roles'     => collect(ProductDevelopmentMemberRoleEnum::cases())->map(fn (ProductDevelopmentMemberRoleEnum $role) => [
                        'value' => $role->value,
                        'label' => ProductDevelopmentMemberRoleEnum::labels()[$role->value],
                        'icon'  => $role->icon(),
                        'color' => $role->color(),
                    ])->all(),
                    'markets'   => $this->enumOptions(TradeUnitMarketEnum::labels()),
                    'users'     => User::where('group_id', $group->id)->where('status', true)->orderBy('contact_name')
                        ->get(['id', 'contact_name', 'username', 'image_id'])
                        ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->contact_name ?: $user->username, 'avatar' => $user->image_id ? $user->imageSources(48, 48) : null, 'agent_ids' => $agentIdsByUser[$user->id] ?? []])
                        ->all(),
                    'brands'    => Brand::where('group_id', $group->id)->orderBy('name')->get(['id', 'name'])
                        ->map(fn (Brand $brand) => ['value' => $brand->id, 'label' => $brand->name])->all(),
                    'agents'    => Agent::where('group_id', $group->id)->orderBy('name')->get(['id', 'name'])
                        ->map(fn (Agent $agent) => ['value' => $agent->id, 'label' => $agent->name])->all(),
                    'suppliers' => Supplier::where('group_id', $group->id)->where('status', true)->orderBy('name')->get(['id', 'code', 'name', 'agent_id'])
                        ->map(fn (Supplier $supplier) => ['value' => $supplier->id, 'label' => $supplier->name.' ('.$supplier->code.')', 'agent_id' => $supplier->agent_id])->all(),
                ],
                'searchRoutes' => [
                    'trade_units'          => ['name' => 'grp.product_developments.json.search', 'parameters' => ['scope' => 'trade_units']],
                    'trade_unit_families'  => ['name' => 'grp.product_developments.json.search', 'parameters' => ['scope' => 'trade_unit_families']],
                    'packaging_families'   => ['name' => 'grp.product_developments.json.search', 'parameters' => ['scope' => 'packaging_families']],
                    'sku_code'             => ['name' => 'grp.product_developments.json.sku_code', 'parameters' => []],
                ],
                'storeRoute'  => [
                    'name'       => 'grp.models.product_development.store',
                    'parameters' => [],
                ],
            ]
        );
    }
}
