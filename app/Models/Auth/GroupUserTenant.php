<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 May 2023 09:58:13 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * App\Models\Auth\GroupUserTenant
 *
 * @property int $id
 * @property int $group_user_id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Auth\User|null $user
 * @method static Builder|GroupUserTenant newModelQuery()
 * @method static Builder|GroupUserTenant newQuery()
 * @method static Builder|GroupUserTenant query()
 * @mixin \Eloquent
 */
class GroupUserTenant extends Pivot
{
    use UsesLandlordConnection;

    public $incrementing = true;

    protected $casts = [
        'data'            => 'array',

    ];

    protected $attributes = [
        'data'            => '{}',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
