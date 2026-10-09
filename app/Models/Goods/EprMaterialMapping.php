<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Goods\Packaging\EprSchemeEnum;
use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Enums\Goods\Packaging\PackagingPolymerEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How a packaging material category (and, for plastics, its polymer) is named in each EPR scheme's return.
 *
 * @property int $id
 * @property EprSchemeEnum $scheme
 * @property PackagingMaterialCategoryEnum $material_category
 * @property PackagingPolymerEnum|null $polymer
 * @property string $scheme_material
 * @property string|null $scheme_subcategory
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Eloquent
 */
class EprMaterialMapping extends Model
{
    protected $casts = [
        'scheme'            => EprSchemeEnum::class,
        'material_category' => PackagingMaterialCategoryEnum::class,
        'polymer'           => PackagingPolymerEnum::class,
    ];

    protected $guarded = [];

    public static function resolve(EprSchemeEnum $scheme, PackagingMaterialCategoryEnum $materialCategory, ?PackagingPolymerEnum $polymer = null): ?self
    {
        return self::where('scheme', $scheme)
            ->where('material_category', $materialCategory)
            ->where(fn ($query) => $query->whereNull('polymer')->when($polymer, fn ($query) => $query->orWhere('polymer', $polymer)))
            ->orderByRaw('polymer IS NULL')
            ->first();
    }
}
