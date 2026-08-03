<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ip
 * @property string $country
 * @property string|null $city
 * @property string|null $postcode
 * @property numeric|null $latitude
 * @property numeric|null $longitude
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpGeolocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpGeolocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IpGeolocation query()
 * @mixin \Eloquent
 */
class IpGeolocation extends Model
{
    protected $table = 'ip_geolocations';

    protected $guarded = [];
}
