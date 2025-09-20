<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 20:37:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\Helpers\TaxNumber\TaxNumberStatusEnum;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Enums\Helpers\TaxNumber\TaxNumberValidationTypeEnum;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Helpers\TaxNumber
 *
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string|null $country_code
 * @property string $number
 * @property TaxNumberTypeEnum|null $type
 * @property int|null $country_id
 * @property TaxNumberStatusEnum $status
 * @property bool $valid
 * @property array<array-key, mixed> $data
 * @property bool $historic
 * @property int $usage
 * @property string|null $checksum hash of country_code,number,status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $checked_at Last time was validated online
 * @property \Illuminate\Support\Carbon|null $invalid_checked_at Last time was validated online with tax number invalid
 * @property string|null $external_service_failed_at Last time on;ine validation fail due external service down
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property TaxNumberValidationTypeEnum|null $validation_type
 * @property int|null $manual_validation_user_id
 * @property string|null $manual_validation_notes
 * @property-read \App\Models\Helpers\Country|null $country
 * @method static Builder<static>|TaxNumber newModelQuery()
 * @method static Builder<static>|TaxNumber newQuery()
 * @method static Builder<static>|TaxNumber onlyTrashed()
 * @method static Builder<static>|TaxNumber query()
 * @method static Builder<static>|TaxNumber withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|TaxNumber withoutTrashed()
 * @mixin Eloquent
 */
class TaxNumber extends Model
{
    use SoftDeletes;

    protected $casts = [
        'data'               => 'array',
        'audited_at'         => 'datetime',
        'checked_at'         => 'datetime',
        'invalid_checked_at' => 'datetime',
        'validation_type'    => TaxNumberValidationTypeEnum::class,
        'status'             => TaxNumberStatusEnum::class,
        'type'               => TaxNumberTypeEnum::class,
        'valid'              => 'boolean',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(
            function (TaxNumber $taxNumber) {
                /** @var Country $country */
                $country                 = Country::find($taxNumber->country_id);
                $taxNumber->country_code = $country?->code;
                $taxNumber->type         = $taxNumber->getType($country);
            }
        );

        static::created(
            function (TaxNumber $taxNumber) {
                $taxNumber->checksum = $taxNumber->getChecksum();
                $taxNumber->save();
            }
        );

        static::updated(function (TaxNumber $taxNumber) {
            if ($taxNumber->wasChanged('country_id')) {
                $taxNumber->country_code = $taxNumber->country?->code;
                $taxNumber->type         = $taxNumber->getType($taxNumber->country);
            }
        });
    }


    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }


    public static function getType(?Country $country): TaxNumberTypeEnum
    {
        $type = TaxNumberTypeEnum::OTHER;
        if (!$country) {
            return $type;
        }
        if (Country::isInEU($country->code)) {
            return TaxNumberTypeEnum::EU_VAT;
        }
        if ($country->code == 'GB') {
            return TaxNumberTypeEnum::GB_VAT;
        }

        return $type;
    }

    public function getChecksum(): string
    {
        return md5(
            json_encode(
                array_filter(
                    array_map(
                        'strtolower',
                        array_diff_key(
                            $this->toArray(),
                            array_flip(
                                [
                                    'id',
                                    'owner_type',
                                    'owner_id',
                                    'checksum',
                                    'created_at',
                                    'updated_at',
                                    'historic',
                                    'usage',
                                    'data',
                                    'valid',
                                    'country_code'
                                ]
                            )
                        )
                    )
                )
            )
        );
    }
}
