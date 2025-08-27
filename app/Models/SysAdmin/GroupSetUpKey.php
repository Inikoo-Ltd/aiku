<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jul 2024 15:32:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Enums\SysAdmin\GroupSetUpKey\GroupSetUpKeyStateEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property GroupSetUpKeyStateEnum $state
 * @property \Illuminate\Support\Carbon $expires_at
 * @property array<array-key, mixed> $limits
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSetUpKey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSetUpKey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSetUpKey query()
 * @mixin \Eloquent
 */
class GroupSetUpKey extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state'      => GroupSetUpKeyStateEnum::class,
            'expires_at' => 'datetime',
            'limits'     => 'array'
        ];
    }

    protected $attributes = [
        'limits'   => '{}',
    ];


}
