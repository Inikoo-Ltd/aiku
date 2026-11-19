<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestSourceEnum;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Materials the factory found missing for a day of production, asked from the warehouse.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int $warehouse_id
 * @property \Illuminate\Support\Carbon $plan_date
 * @property ProductionMaterialRequestStateEnum $state
 * @property ProductionMaterialRequestSourceEnum $source
 * @property int|null $checker_employee_id
 * @property int|null $submitted_by_user_id
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Employee|null $checker
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductionMaterialRequestItem> $items
 * @property-read Production $production
 * @property-read User|null $submittedBy
 * @property-read Warehouse $warehouse
 * @mixin \Eloquent
 */
class ProductionMaterialRequest extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state'        => ProductionMaterialRequestStateEnum::class,
            'source'       => ProductionMaterialRequestSourceEnum::class,
            'plan_date'    => 'date',
            'submitted_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'checker_employee_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionMaterialRequestItem::class);
    }
}
