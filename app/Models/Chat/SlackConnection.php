<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's own permission to write in Slack from aiku, one per aiku user. The token never leaves the server.
 *
 * @property int $id
 * @property int $user_id
 * @property string $slack_user_id
 * @property string $team_id
 * @property string|null $token
 * @property string|null $scopes
 * @property \Illuminate\Support\Carbon|null $connected_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property bool $needs_reconnect
 */
class SlackConnection extends Model
{
    protected $guarded = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token'           => 'encrypted',
            'connected_at'    => 'datetime',
            'revoked_at'      => 'datetime',
            'needs_reconnect' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNotNull('token')->whereNull('revoked_at')->where('needs_reconnect', false);
    }

    /**
     * @return list<string>
     */
    public function missingScopes(): array
    {
        return array_values(array_diff(SlackWriteAccess::USER_SCOPES, array_map('trim', explode(',', (string) $this->scopes))));
    }

    public function needsReconnection(): bool
    {
        return $this->needs_reconnect || ($this->getRawOriginal('token') !== null && $this->revoked_at === null && $this->missingScopes() !== []);
    }

    public function isLive(): bool
    {
        return $this->getRawOriginal('token') !== null && $this->revoked_at === null && !$this->needs_reconnect;
    }
}
