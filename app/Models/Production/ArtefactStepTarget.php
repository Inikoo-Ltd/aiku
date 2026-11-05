<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A target a recipe step had from a moment on. Sessions take the one that applied when they started.
 *
 * @property int $id
 * @property int $artefact_id
 * @property int $manufacture_task_id
 * @property numeric|null $standard_rate
 * @property Carbon|null $effective_from
 * @property int|null $user_id
 * @property array<string, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @mixin \Eloquent
 */
class ArtefactStepTarget extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'data'           => 'array',
        ];
    }

    protected $attributes = [
        'data' => '{}',
    ];

    /**
     * The target set last among those applying by then: a target dated back behind a later change replaces it from its
     * day on, as whoever set it meant. A step is its task and label on the artefact, so the same task used more than once
     * in a recipe has a target per label, and a step taken off and added back keeps the history it was paid against.
     *
     * @return array{found: bool, standard_rate: float|null}
     */
    public static function rateAt(int $artefactId, int $manufactureTaskId, Carbon $at, ?string $label = null): array
    {
        $target = self::query()->ofStep($artefactId, $manufactureTaskId, $label)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->orderByDesc('id')
            ->first();

        if ($target) {
            return ['found' => true, 'standard_rate' => $target->standard_rate === null ? null : (float) $target->standard_rate];
        }

        return ['found' => self::query()->ofStep($artefactId, $manufactureTaskId, $label)->exists(), 'standard_rate' => null];
    }

    public function scopeOfStep(Builder $query, int $artefactId, int $manufactureTaskId, ?string $label = null): Builder
    {
        return self::withLabel($query->where('artefact_id', $artefactId)->where('manufacture_task_id', $manufactureTaskId), $label);
    }

    /**
     * Labels match whatever their case, as they do in the recipe.
     */
    public static function withLabel(Builder $query, ?string $label, string $column = 'label'): Builder
    {
        $label = trim((string) $label);

        return $label === '' ? $query->whereNull($column) : $query->whereRaw("lower({$column}) = ?", [mb_strtolower($label)]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
