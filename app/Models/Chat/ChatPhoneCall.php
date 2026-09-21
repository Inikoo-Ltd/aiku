<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatPhoneCallContactTypeEnum;
use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A telephone call an agent took away from the keyboard. Nothing here dials anybody: the row
 * exists so the minutes an agent spends on the phone are a fact their colleagues can see,
 * rather than an unexplained silence in a conversation somebody is waiting on.
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property int $chat_agent_id
 * @property int $user_id
 * @property ChatPhoneCallStatusEnum $status
 * @property ChatPhoneCallContactTypeEnum|null $contact_type
 * @property int|null $customer_id
 * @property int|null $chat_session_id
 * @property string|null $contact_name
 * @property string|null $notes
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChatAgent|null $chatAgent
 * @property-read ChatSession|null $chatSession
 * @property-read Customer|null $customer
 * @property-read Shop|null $shop
 * @property-read User|null $user
 * @method static Builder<static>|ChatPhoneCall inProgress()
 * @method static Builder<static>|ChatPhoneCall newModelQuery()
 * @method static Builder<static>|ChatPhoneCall newQuery()
 * @method static Builder<static>|ChatPhoneCall query()
 * @mixin \Eloquent
 */
class ChatPhoneCall extends Model
{
    use HasFactory;

    protected $table = 'chat_phone_calls';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'       => ChatPhoneCallStatusEnum::class,
            'contact_type' => ChatPhoneCallContactTypeEnum::class,
            'started_at'   => 'datetime',
            'ended_at'     => 'datetime',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
        ];
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', ChatPhoneCallStatusEnum::IN_PROGRESS);
    }

    public function chatAgent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === ChatPhoneCallStatusEnum::IN_PROGRESS;
    }

    public function elapsedSeconds(): int
    {
        return (int) $this->started_at->diffInSeconds($this->ended_at ?? now(), absolute: true);
    }
}
