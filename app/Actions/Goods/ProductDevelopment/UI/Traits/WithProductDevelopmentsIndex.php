<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-18h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI\Traits;

use App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFlagEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Models\Goods\ProductDevelopmentMember;
use App\Models\Helpers\Brand;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;

trait WithProductDevelopmentsIndex
{
    /**
     * @return array<string, mixed>
     */
    public function productDevelopmentFilterBar(Group $group, User $user): array
    {
        $designers = ProductDevelopmentMember::query()
            ->join('product_developments', 'product_developments.id', '=', 'product_development_members.product_development_id')
            ->join('users', 'users.id', '=', 'product_development_members.user_id')
            ->where('product_developments.group_id', $group->id)
            ->where('product_development_members.role', ProductDevelopmentMemberRoleEnum::DESIGNER->value)
            ->distinct()
            ->pluck('users.contact_name', 'users.id')
            ->all();

        return [
            'statuses' => collect(ProductDevelopmentStatusEnum::cases())->map(fn (ProductDevelopmentStatusEnum $status) => [
                'key'   => $status->value,
                'label' => ProductDevelopmentStatusEnum::labels()[$status->value],
                'icon'  => $status->icon(),
                'color' => $status->color(),
            ])->all(),
            'flags'    => collect(ProductDevelopmentFlagEnum::cases())->map(fn (ProductDevelopmentFlagEnum $flag) => [
                'key'   => $flag->value,
                'label' => ProductDevelopmentFlagEnum::labels()[$flag->value],
                'icon'  => $flag->icon(),
                'color' => $flag->color(),
            ])->all(),
            'options'  => [
                'kind'     => ProductDevelopmentKindEnum::labels(),
                'stage'    => array_slice(ProductDevelopmentStageEnum::positionLabels(), 0, ProductDevelopmentStageEnum::lastPosition(), true),
                'agent'    => Agent::where('group_id', $group->id)->orderBy('name')->pluck('name', 'id')->all(),
                'designer' => $designers,
                'brand'    => Brand::where('group_id', $group->id)->orderBy('name')->pluck('name', 'id')->all(),
            ],
            'counts'   => $this->productDevelopmentCounts($group, $user),
        ];
    }

    public function getBreadcrumbs(string $routeName = 'grp.product_developments.index', ?string $suffix = null): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.product_developments.index',
                        ],
                        'label' => __('Product development'),
                        'icon'  => 'fal fa-lightbulb-on',
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * The List / Board / Waiting on me switch; the last one carries how much waits on the viewer.
     */
    public function viewSwitch(string $current, ?int $waitingCount = null): array
    {
        $waitingCount ??= request()->user() ? (ProductDevelopmentWaitingOn::railBadge(request()->user())['waiting'] ?? 0) : 0;

        return [
            [
                'key'   => 'list',
                'label' => __('List'),
                'icon'  => 'fal fa-list',
                'route' => ['name' => 'grp.product_developments.index', 'parameters' => request()->query()],
                'active' => $current === 'list',
            ],
            [
                'key'   => 'board',
                'label' => __('Board'),
                'icon'  => 'fal fa-columns',
                'route' => ['name' => 'grp.product_developments.board', 'parameters' => request()->query()],
                'active' => $current === 'board',
            ],
            [
                'key'    => 'waiting',
                'label'  => __('Waiting on me'),
                'icon'   => 'fal fa-hourglass-half',
                'route'  => ['name' => 'grp.product_developments.waiting_on_me', 'parameters' => []],
                'active' => $current === 'waiting',
                'count'  => $waitingCount,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|false
     */
    public function createAction(): array|false
    {
        if (!$this->canCreateProductDevelopment(request()->user())) {
            return false;
        }

        return [
            'type'  => 'button',
            'style' => 'create',
            'label' => __('New development'),
            'route' => [
                'name'       => 'grp.product_developments.create',
                'parameters' => [],
            ],
        ];
    }
}
