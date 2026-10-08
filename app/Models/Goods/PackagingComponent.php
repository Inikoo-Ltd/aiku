<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Goods\Packaging\PackagingLevelEnum;
use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $group_id
 * @property string|null $name
 * @property PackagingLevelEnum $packaging_level
 * @property PackagingMaterialCategoryEnum $material_category
 * @property string|null $material
 * @property string|null $material_id_code
 * @property string|null $weight_g
 * @property bool $weight_is_measured
 * @property string|null $recycled_content_pct
 * @property string|null $recycled_content_evidence
 * @property string|null $recyclability
 * @property string|null $separable
 * @property string|null $marks
 * @property string|null $national_marks
 * @property string|null $artwork_owner
 * @property string|null $notes
 * @property string|null $signature
 * @property array<array-key, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PackagingFamily> $families
 * @mixin \Eloquent
 */
class PackagingComponent extends Model
{
    use InGroup;

    protected $casts = [
        'data'               => 'array',
        'packaging_level'    => PackagingLevelEnum::class,
        'material_category'  => PackagingMaterialCategoryEnum::class,
        'weight_is_measured' => 'boolean',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function families(): BelongsToMany
    {
        return $this->belongsToMany(PackagingFamily::class, 'packaging_family_has_components')
            ->withPivot(['quantity', 'quantity_per_unit'])->withTimestamps();
    }
}
