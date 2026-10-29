<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

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
 * @property numeric|null $standard_rate
 * @property float|null $mix_batch_kg
 * @property bool $blocks_later_steps
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

    protected $casts = [
        'mix_batch_kg'       => 'float',
        'blocks_later_steps' => 'boolean',
    ];

    /**
     * An artefact is one unit of its SKO, so a step counted in units that asks for the SKO's whole pack
     * per artefact counts the pack twice: jobs, SKOs received and raw materials all end up off by the
     * pack size (BOUL family, 20 Oct 2026). Steps recorded in kg are weights, never packs.
     */
    public static function countsSkoPackAgain(Artefact $artefact, bool $isRecordedInKg, float|int|string|null $unitsPerArtefact): bool
    {
        return !$isRecordedInKg && $artefact->packedIn() > 1 && (float) $unitsPerArtefact === (float) $artefact->packedIn();
    }

    /**
     * @param  Collection<int, Artefact>  $artefacts
     */
    public static function skoPackCountedAgainMessage(Collection $artefacts): string
    {
        return __('An artefact is one unit of its SKO, and the SKO already packs several: :artefacts. Counting that many per artefact counts the pack twice, so the job quantities, the SKOs received and the raw materials used would all be off by that much. Give the step per unit, usually 1.', [
            'artefacts' => $artefacts->map(fn (Artefact $artefact) => __(':code (:packed_in per SKO)', ['code' => $artefact->code, 'packed_in' => $artefact->packedIn()]))->implode(', '),
        ]);
    }

    /**
     * How many artefacts one unit of the step holds, when the step is counted in packs (a tray of 8
     * has units_per_artefact 0.125); null when the step is counted in artefacts or parts of one.
     */
    public static function packSize(float|string|null $unitsPerArtefact): ?int
    {
        $unitsPerArtefact = (float) $unitsPerArtefact;

        return $unitsPerArtefact > 0 && $unitsPerArtefact < 1 ? (int) round(1 / $unitsPerArtefact) : null;
    }

    /**
     * A pack step is typed as a rounded fraction (0.1667 for a box of six, 0.3333 for a pack of three).
     * The pack is what is meant, so 60 artefacts are 10 boxes and not 10.002.
     */
    public static function exactUnits(float|string|null $unitsPerArtefact): float
    {
        $unitsPerArtefact = (float) $unitsPerArtefact;
        $packSize         = self::packSize($unitsPerArtefact);

        return $packSize && abs($unitsPerArtefact - 1 / $packSize) <= 0.00005 ? 1 / $packSize : $unitsPerArtefact;
    }

    public static function stepQuantity(float|int|string $artefacts, float|string|null $unitsPerArtefact): float
    {
        return round((float) $artefacts * self::exactUnits($unitsPerArtefact), 3);
    }

    public static function artefactsIn(float|int|string $stepQuantity, float|string|null $unitsPerArtefact): float
    {
        return floor(round((float) $stepQuantity / self::exactUnits($unitsPerArtefact), 3));
    }

    /**
     * @return Collection<string, int> pack size by "artefact id:manufacture task id", only for steps counted in packs
     */
    public static function packSizes(): Collection
    {
        return self::where('units_per_artefact', '<', 1)
            ->whereNotIn('manufacture_task_id', ManufactureTask::where('is_recorded_in_kg', true)->select('id'))
            ->get(['artefact_id', 'manufacture_task_id', 'units_per_artefact'])
            ->mapWithKeys(fn (self $step) => [$step->artefact_id.':'.$step->manufacture_task_id => self::packSize($step->units_per_artefact)]);
    }

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
