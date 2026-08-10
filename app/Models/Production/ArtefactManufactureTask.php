<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * App\Models\Production\ArtefactManufactureTask
 *
 * @property int $id
 * @property int $artefact_id
 * @property int $manufacture_task_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $position
 * @property numeric $units_per_artefact
 * @property-read \App\Models\Production\Artefact|null $artefact
 * @property-read \App\Models\Production\ManufactureTask|null $manufactureTask
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production\RecipeStepRawMaterial> $rawMaterials
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactManufactureTask newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactManufactureTask newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArtefactManufactureTask query()
 * @mixin \Eloquent
 */

class ArtefactManufactureTask extends Pivot
{
    protected $table = 'artefacts_manufacture_tasks';

    public $incrementing = true;

    protected $guarded = [];

    public function artefact(): BelongsTo
    {
        return $this->belongsTo(Artefact::class);
    }

    public function manufactureTask(): BelongsTo
    {
        return $this->belongsTo(ManufactureTask::class);
    }

    public function rawMaterials(): HasMany
    {
        return $this->hasMany(RecipeStepRawMaterial::class, 'artefact_manufacture_task_id');
    }
}
