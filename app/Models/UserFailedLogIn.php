<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 22:35:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $failed_at
 * @property string $username
 * @property int|null $user_id
 * @property string|null $os
 * @property string|null $device
 * @property string|null $browser
 * @property string|null $ip_address
 * @property array<array-key, mixed>|null $location
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFailedLogIn newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFailedLogIn newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserFailedLogIn query()
 * @mixin \Eloquent
 */
class UserFailedLogIn extends Model
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
