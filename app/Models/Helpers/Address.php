<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 01:45:25 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\Helpers;

use App\Models\Traits\IsAddress;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Helpers\Address
 *
 * @property int $id
 * @property int $group_id
 * @property int $usage usage by models/scopes
 * @property int $fixed_usage count usage by fixed models/fixed_scopes
 * @property int $multiplicity count address with same checksum
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $sorting_code
 * @property string|null $postal_code
 * @property string|null $dependent_locality
 * @property string|null $locality
 * @property string|null $administrative_area
 * @property string|null $country_code
 * @property int|null $country_id
 * @property string|null $checksum
 * @property bool $is_fixed Directly related to the model class, (no model_has_addresses entry)
 * @property string|null $fixed_scope Key where address can be shared if have same checksum
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Helpers\Country|null $country
 * @property-read string $formatted_address
 * @property-read Model|\Eloquent $owner
 *
 * @method static \Database\Factories\Helpers\AddressFactory factory($count = null, $state = [])
 * @method static Builder<static>|Address newModelQuery()
 * @method static Builder<static>|Address newQuery()
 * @method static Builder<static>|Address query()
 *
 * @mixin Eloquent
 */
class Address extends Model
{
    use HasFactory;
    use IsAddress;

    protected $table = 'addresses';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::created(
            function (Address $address) {
                if ($country = (new Country)->firstWhere('id', $address->country_id)) {
                    $address->country_code = $country->code;
                    $address->checksum = $address->getChecksum();
                    $address->save();
                }
            }
        );
    }
}
