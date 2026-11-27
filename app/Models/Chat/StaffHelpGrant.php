<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * "Let a colleague help me" (INI-496): the granter lends their whole access to one colleague for a few minutes, like
 * handing over the keyboard. The helper stays logged in as themselves (so the granter's sign-in and private chats stay
 * out of reach) and every change they make is audited under both names. One open grant per granter
 * (staff_help_grants_one_open_per_granter).
 *
 * @property int $id
 * @property string $ulid
 * @property int $group_id
 * @property int $granter_user_id
 * @property int $helper_user_id
 * @property int|null $staff_conversation_id
 * @property int $minutes
 * @property Carbon $granted_at
 * @property Carbon|null $started_at
 * @property Carbon $expires_at
 * @property Carbon|null $ended_at
 * @property string|null $end_reason
 * @property-read User $granter
 * @property-read User $helper
 */
class StaffHelpGrant extends Model
{
    public const string SESSION_KEY = 'staff_help_grant_id';

    public const int DEFAULT_MINUTES = 15;

    public const array MINUTES = [15, 30, 60];

    public const string ENDED_BY_GRANTER = 'ended_by_granter';
    public const string STOPPED_BY_HELPER = 'stopped_by_helper';
    public const string HELPER_LOGGED_OUT = 'helper_logged_out';
    public const string ACCOUNT_DEACTIVATED = 'account_deactivated';
    public const string REPLACED = 'replaced';
    public const string EXPIRED = 'expired';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StaffHelpGrant $grant) {
            $grant->ulid ??= Str::ulid()->toBase32();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granter_user_id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'helper_user_id');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }

    /**
     * Why this grant may not lend the granter's access to $helper right now, or null when it may. Checked on every
     * request, so deactivating either account or reaching the time limit takes effect at once.
     */
    public function refusalFor(User $helper): ?string
    {
        if ($this->ended_at !== null) {
            return $this->end_reason ?? self::EXPIRED;
        }
        if (!$this->expires_at->isFuture()) {
            return self::EXPIRED;
        }
        if ($this->helper_user_id !== $helper->id || $this->group_id !== $helper->group_id) {
            return 'not_the_helper';
        }
        if (!$helper->status || !$this->granter?->status) {
            return self::ACCOUNT_DEACTIVATED;
        }

        return null;
    }

    public function end(string $reason): void
    {
        if ($this->ended_at !== null) {
            return;
        }

        $this->update([
            'ended_at'   => $this->expires_at->isFuture() ? now() : $this->expires_at,
            'end_reason' => $this->expires_at->isFuture() ? $reason : self::EXPIRED,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toBanner(): array
    {
        return [
            'ulid'         => $this->ulid,
            'granter'      => $this->granter->only(['id', 'username', 'contact_name']),
            'helper'       => $this->helper->only(['id', 'username', 'contact_name']),
            'started_at'   => $this->started_at?->toIso8601ZuluString(),
            'expires_at'   => $this->expires_at->toIso8601ZuluString(),
            'record_url'   => route('grp.chat.staff.help.show', $this->ulid),
        ];
    }
}
