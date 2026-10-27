<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-17h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Resources\Goods;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property ProductDevelopmentTypeEnum $type
 * @property ProductDevelopmentStatusEnum $status
 * @property int $current_position
 * @property mixed $target_online_date
 * @property int|null $number_required_missing
 * @property int|null $number_items
 * @property string|null $owner_name
 * @property string|null $supplier_name
 * @property string|null $agent_name
 * @property string|null $trade_unit_family_code
 * @property string|null $trade_unit_family_name
 * @property string|null $brand_name
 * @property string|null $current_stage_started_at
 * @property bool|null $is_overdue
 */
class ProductDevelopmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'slug'                    => $this->slug,
            'code'                    => $this->code,
            'name'                    => $this->name,
            'type'                    => $this->type->value,
            'type_label'              => ProductDevelopmentTypeEnum::labels()[$this->type->value],
            'status'                  => $this->status->value,
            'status_icon'             => ProductDevelopmentStatusEnum::stateIcon()[$this->status->value],
            'stage_position'          => $this->current_position,
            'stage_label'             => ProductDevelopmentStageEnum::positionLabel($this->current_position, $this->kind),
            'family'                  => $this->trade_unit_family_code,
            'family_name'             => $this->trade_unit_family_name,
            'brand'                   => $this->brand_name,
            'supplier'                => $this->supplier_name,
            'agent'                   => $this->agent_name,
            'owner'                   => $this->owner_name,
            'number_items'            => (int) $this->number_items,
            'number_required_missing' => (int) $this->number_required_missing,
            'target_online_date'      => $this->target_online_date ? Carbon::parse($this->target_online_date)->toDateString() : null,
            'days_in_stage'           => $this->current_stage_started_at ? (int) Carbon::parse($this->current_stage_started_at)->startOfDay()->diffInDays(Carbon::today()) : null,
            'is_overdue'              => (bool) $this->is_overdue,
            'type_icon'               => $this->type->icon(),
            'type_color'              => $this->type->color(),
            'number_required'         => (int) $this->number_required,
            'number_done'             => max((int) $this->number_required - (int) $this->number_required_missing, 0),
            'missing_by_role'         => collect(ProductDevelopmentMemberRoleEnum::cases())
                ->mapWithKeys(fn (ProductDevelopmentMemberRoleEnum $role) => [$role->value => (int) $this->{$role->statsColumn()}])
                ->filter()
                ->all(),
            'current_stage_due_at'    => $this->current_stage_due_at ? Carbon::parse($this->current_stage_due_at)->toDateString() : null,
            'is_stage_late'           => $this->current_stage_due_at && Carbon::parse($this->current_stage_due_at)->lt(Carbon::today()) && $this->status === ProductDevelopmentStatusEnum::ACTIVE,
            'owner_user'              => $this->owner ? [
                'id'       => $this->owner->id,
                'name'     => $this->owner->contact_name ?: $this->owner->username,
                'username' => $this->owner->username,
                'avatar'   => $this->owner->image_id ? $this->owner->imageSources(48, 48) : null,
            ] : null,
        ];
    }
}
