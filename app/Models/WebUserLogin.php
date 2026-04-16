<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property int|null $web_user_id
 * @property string $source A: aiku login form, G: google login
 * @property string|null $os
 * @property string|null $device
 * @property string|null $browser
 * @property string|null $ip_address
 * @property array<array-key, mixed>|null $location
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserLogin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserLogin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserLogin query()
 * @mixin \Eloquent
 */
class WebUserLogin extends Model
{
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'date'     => 'datetime',
        'location' => 'array',
    ];

    protected $attributes = [
        'location' => '{}',
    ];
}
