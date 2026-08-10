<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $artefact_manufacture_task_id
 * @property int $raw_material_id
 * @property numeric $quantity_per_unit
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Production\ArtefactManufactureTask|null $recipeStep
 * @property-read \App\Models\Production\RawMaterial|null $rawMaterial
 * @property-read Group|null $group
 * @property-read Organisation|null $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecipeStepRawMaterial newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecipeStepRawMaterial newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecipeStepRawMaterial query()
 * @mixin \Eloquent
 */
class RecipeStepRawMaterial extends Model
{
    protected $table = 'recipe_step_raw_materials';

    protected $guarded = [];

    public function recipeStep(): BelongsTo
    {
        return $this->belongsTo(ArtefactManufactureTask::class, 'artefact_manufacture_task_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
