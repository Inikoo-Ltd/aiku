<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $user_id
 * @property string $tool
 * @property array<array-key, mixed> $arguments
 * @property bool $is_error
 * @property int|null $duration_ms
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\SysAdmin\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|McpRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|McpRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|McpRequest query()
 * @mixin \Eloquent
 */
class McpRequest extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'is_error'  => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
