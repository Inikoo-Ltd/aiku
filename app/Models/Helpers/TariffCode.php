<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:36:40 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $section
 * @property string $hs_code
 * @property string $description
 * @property string|null $name
 * @property int|null $parent_id
 * @property int $level
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read TariffCode|null $parent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TariffCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TariffCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TariffCode query()
 * @mixin \Eloquent
 */
class TariffCode extends Model
{
    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TariffCode::class, 'parent_id');
    }

    /**
     * Short export label for a product tariff code, the Aurora rule: any named row that shares the
     * first 8 digits matches, whatever its national digits say, and the most specific one wins.
     * The 6 digit heading is the last resort. Codes may carry spaces.
     */
    public static function exportNameFor(?string $tariffCode): ?string
    {
        $digits = preg_replace('/\D/', '', (string)$tariffCode);
        if (strlen($digits) < 6) {
            return null;
        }

        return static::whereNotNull('name')
            ->where(function ($query) use ($digits) {
                $query->whereRaw('left(hs_code, 8) = ?', [substr($digits, 0, 8)])
                    ->orWhere('hs_code', substr($digits, 0, 6));
            })
            ->orderByDesc('level')
            ->value('name');
    }
}
