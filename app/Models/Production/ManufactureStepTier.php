<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pay band thresholds edited by hand for a recipe step, on one artefact or on every artefact of a family (HELP-4465).
 * Rows are only added and apply from the start of the day they were saved: the latest one in force when a session
 * started is the one it is paid on, and a row with no thresholds puts the step back on the ones calculated from the
 * band multipliers.
 *
 * @property int $id
 * @property int|null $artefact_id
 * @property int|null $artefact_family_id
 * @property int $manufacture_task_id
 * @property array<string, float>|null $thresholds
 * @property array<string, float>|null $previous_thresholds
 * @property Carbon $effective_from
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @mixin \Eloquent
 */
class ManufactureStepTier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'thresholds'          => 'array',
            'previous_thresholds' => 'array',
            'effective_from'      => 'datetime',
        ];
    }

    /**
     * @return array{source: string, thresholds: array<string, float>}|null
     */
    public static function inForce(?int $artefactId, ?int $artefactFamilyId, int $manufactureTaskId, Carbon $at): ?array
    {
        foreach (['artefact' => $artefactId, 'family' => $artefactFamilyId] as $source => $id) {
            $thresholds = $id ? self::lastFor($source, $id, $manufactureTaskId, $at)?->thresholds : null;
            if ($thresholds) {
                return ['source' => $source, 'thresholds' => $thresholds];
            }
        }

        return null;
    }

    public static function lastFor(string $source, int $id, int $manufactureTaskId, ?Carbon $at = null): ?self
    {
        return self::where($source == 'artefact' ? 'artefact_id' : 'artefact_family_id', $id)
            ->where('manufacture_task_id', $manufactureTaskId)
            ->when($at, fn ($query) => $query->where('effective_from', '<=', $at))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
