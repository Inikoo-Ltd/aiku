<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every change an AI assistant makes through the MCP, with the state before and after, so it
 * can be reverted by RevertMcpChange.
 *
 * @property int $id
 * @property int $group_id
 * @property int $user_id
 * @property McpChangeTypeEnum $type
 * @property string $tool
 * @property string $label
 * @property string|null $request_text
 * @property array<array-key, mixed> $before
 * @property array<array-key, mixed> $after
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $reverted_at
 * @property int|null $reverted_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\User|null $user
 * @property-read \App\Models\SysAdmin\User|null $revertedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|McpChange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|McpChange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|McpChange query()
 * @mixin \Eloquent
 */
class McpChange extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'data' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'type'        => McpChangeTypeEnum::class,
            'before'      => 'array',
            'after'       => 'array',
            'data'        => 'array',
            'reverted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by_id');
    }

    public function canBeRevertedBy(User $user): bool
    {
        if ($this->reverted_at) {
            return false;
        }

        setPermissionsTeamId($user->group_id);

        return $user->authTo('sysadmin.edit') || $user->{$this->type->userSwitch()};
    }
}
