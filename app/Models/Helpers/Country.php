<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:36:40 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Models\Ordering\ShippingCountry;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string|null $iso3
 * @property string|null $phone_code
 * @property string $name
 * @property string|null $continent
 * @property string|null $capital
 * @property int|null $timezone_id Timezone in capital
 * @property int|null $currency_id
 * @property string|null $type
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property bool $status
 * @property bool $show_in_address
 * @property int|null $code_iso_numeric
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShippingCountry> $shippingCountries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Timezone> $timezones
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country withoutTrashed()
 * @mixin \Eloquent
 */
class Country extends Model
{
    use SoftDeletes;


    protected $table = 'countries';

    protected $casts = [
        'data' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    /**
     * The address boxes in the order this country writes an address. The jsonb column hands keys back sorted by
     * length, so a UK form showed Town first and customers typed their street into it (HELP-3102).
     * The order comes from the same commerceguys format the required fields come from.
     *
     * @return array<string, array<string, mixed>>
     */
    public function addressFieldsInDisplayOrder(): array
    {
        $fields = Arr::get($this->data, 'fields', []);
        /** Postal formats are fixed data, looked up once per country per worker rather than 247 times per request */
        static $formats = [];
        $format = $formats[$this->code] ??= (new AddressFormatRepository())->get($this->code)->getFormat();

        $tokens = [
            'address_line_1'      => '%addressLine1',
            'address_line_2'      => '%addressLine2',
            'address_line_3'      => '%addressLine3',
            'dependent_locality'  => '%dependentLocality',
            'locality'            => '%locality',
            'administrative_area' => '%administrativeArea',
            'postal_code'         => '%postalCode',
            'sorting_code'        => '%sortingCode',
            'additional_name'     => '%additionalName',
        ];

        $position = function (string $key) use ($format, $tokens): int {
            $found = isset($tokens[$key]) ? strpos($format, $tokens[$key]) : false;

            return $found === false ? PHP_INT_MAX : $found;
        };

        uksort($fields, fn (string $a, string $b) => $position($a) <=> $position($b));

        return $fields;
    }

    public function timezones(): BelongsToMany
    {
        return $this->belongsToMany(Timezone::class);
    }

    public static function getCountryCodesInEU(): array
    {
        return ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HU', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];
    }

    public static function isInEU(string $code): bool
    {
        return in_array($code, Country::getCountryCodesInEU(), true);
    }

    public function shippingCountries(): HasMany
    {
        return $this->hasMany(ShippingCountry::class);
    }
}
