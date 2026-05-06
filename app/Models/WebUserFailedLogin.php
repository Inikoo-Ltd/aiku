<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $failed_at
 * @property int $website_id
 * @property string $username
 * @property int|null $web_user_id
 * @property string $source A: aiku login form, G: google login
 * @property string|null $os
 * @property string|null $device
 * @property string|null $browser
 * @property string|null $ip_address
 * @property array<array-key, mixed>|null $location
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserFailedLogin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserFailedLogin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebUserFailedLogin query()
 * @mixin \Eloquent
 */
class WebUserFailedLogin extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'location'  => 'array',
        'failed_at' => 'datetime',
    ];

    protected $attributes = [
        'location' => '{}',
    ];
}
