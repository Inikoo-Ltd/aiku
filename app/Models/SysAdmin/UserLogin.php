<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 Jul 2026 00:36:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property int $user_id
 * @property string|null $os
 * @property string|null $device
 * @property string|null $browser
 * @property string|null $ip_address
 * @property array<array-key, mixed>|null $location
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLogin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLogin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLogin query()
 * @mixin \Eloquent
 */
class UserLogin extends Model
{
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'date' => 'datetime',
        'location' => 'array',
    ];

    protected $attributes = [
        'location' => '{}',
    ];
}
