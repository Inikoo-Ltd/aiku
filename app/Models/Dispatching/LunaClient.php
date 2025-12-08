<?php

namespace App\Models\Dispatching;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * App\Models\Dispatching\LunaClient
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LunaClient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LunaClient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LunaClient query()
 *
 * @mixin \Eloquent
 */
class LunaClient extends Model
{
    use HasApiTokens;
}
