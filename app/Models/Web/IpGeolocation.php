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
 * @property string $postcode
 * @property float|null $latitude
 * @property float|null $longitude
 */
class IpGeolocation extends Model
{
    protected $table = 'ip_geolocations';

    protected $guarded = [];
}
