<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Packaging typed into an EPR return by hand because no document in Aiku records it: import cartons counted off
 * containers, self-managed waste from waste transfer notes.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property Carbon $date_from
 * @property Carbon $date_to
 * @property string $activity
 * @property string $packaging_type
 * @property string $packaging_class
 * @property PackagingMaterialCategoryEnum $material_category
 * @property string|null $from_nation
 * @property string|null $to_nation
 * @property string $kg
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @mixin \Eloquent
 */
class EprManualLine extends Model
{
    use InOrganisation;

    protected $casts = [
        'date_from'         => 'date',
        'date_to'           => 'date',
        'material_category' => PackagingMaterialCategoryEnum::class,
    ];

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
