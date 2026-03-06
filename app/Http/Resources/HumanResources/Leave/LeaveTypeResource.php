<?php

namespace App\Http\Resources\HumanResources;

use App\Enums\HumanResources\Leave\LeaveCategoryEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property string|null $description
 * @property LeaveCategoryEnum $category
 * @property bool $requires_approval
 * @property numeric|null $max_days_per_year
 * @property array<array-key, mixed>|null $settings
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class LeaveTypeResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'name'              => $this->name,
            'color'             => $this->color,
            'description'       => $this->description,
            'category'          => $this->category?->value,
            'category_label'    => LeaveCategoryEnum::labels()[$this->category?->value] ?? $this->category?->value,
            'category_color'    => LeaveCategoryEnum::colors()[$this->category?->value] ?? 'gray',
            'requires_approval' => (bool) $this->requires_approval,
            'max_days_per_year' => $this->max_days_per_year,
            'settings'          => $this->settings,
            'is_active'         => (bool) $this->is_active,
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
