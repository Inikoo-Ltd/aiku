<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Which clocked time a factory pays, dated so a change applies from a day on. With no rule in force every clocked hour is
 * paid. With inside_shift_only the time clocked before the shift starts or after it ends is paid only as approved
 * overtime, and a late clock in is paid from the nearest rounding_minutes counted from the shift start.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property bool $inside_shift_only
 * @property int $rounding_minutes
 * @property \Illuminate\Support\Carbon $effective_from
 * @property int|null $set_by_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $setBy
 * @mixin \Eloquent
 */
class ManufacturePaidTimeRule extends Model
{
    public const int DEFAULT_ROUNDING_MINUTES = 5;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'inside_shift_only' => 'boolean',
            'effective_from'    => 'date',
        ];
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }

    /**
     * @return Collection<int, self> newest first
     */
    public static function of(int $productionId): Collection
    {
        return self::where('production_id', $productionId)->orderByDesc('effective_from')->get();
    }

    /**
     * The rule that limits pay to the shift on a day, null when every clocked hour is paid.
     *
     * @param  Collection<int, self>  $rules  newest first, from of()
     */
    public static function limitingOn(Collection $rules, string $date): ?self
    {
        $rule = $rules->first(fn (self $rule) => $rule->effective_from->toDateString() <= $date);

        return $rule?->inside_shift_only ? $rule : null;
    }
}
