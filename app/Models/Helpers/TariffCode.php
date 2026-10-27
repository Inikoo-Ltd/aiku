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
 * @property array<string, string>|null $name_i8n
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
    public const array NAME_LOCALES = ['en', 'sk', 'es'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'name_i8n' => 'array',
        ];
    }

    public static function nameLocaleFor(?string $languageCode): string
    {
        $code = substr((string) $languageCode, 0, 2);

        return in_array($code, self::NAME_LOCALES) ? $code : 'en';
    }

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
        return static::namedRowFor($tariffCode)?->name;
    }

    /**
     * Export name in each of NAME_LOCALES (English when a translation is missing), else the official
     * 6 digit heading for all of them.
     *
     * @return array<string, string|null>
     */
    public static function descriptionsFor(?string $tariffCode): array
    {
        $named   = static::namedRowFor($tariffCode);
        $heading = $named ? null : static::where('hs_code', substr(preg_replace('/\D/', '', (string)$tariffCode), 0, 6))->value('description');

        return collect(self::NAME_LOCALES)
            ->mapWithKeys(fn (string $locale) => [$locale => $named ? ($named->name_i8n[$locale] ?? $named->name) : $heading])
            ->all();
    }

    private static function namedRowFor(?string $tariffCode): ?self
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
            ->first(['name', 'name_i8n']);
    }
}
