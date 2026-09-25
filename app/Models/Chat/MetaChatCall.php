<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\MetaChatCallDirectionEnum;
use App\Enums\CRM\Livechat\MetaChatCallStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A WhatsApp voice call, carried over the Cloud API calling endpoints. Unlike a
 * ChatPhoneCall, which an agent writes down after dialling from their own handset, this row
 * is the call itself: Meta opens it with a webhook and closes it with another, and the media
 * runs between the customer's phone and the agent's browser for as long as it is open.
 *
 * @property int $id
 * @property int $meta_channel_id
 * @property int $meta_chat_session_id
 * @property int|null $shop_id
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property string $wa_call_id
 * @property MetaChatCallDirectionEnum $direction
 * @property MetaChatCallStatusEnum $status
 * @property string|null $phone_number
 * @property Carbon|null $ringing_at
 * @property Carbon|null $answered_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property string|null $termination_reason
 * @property array|null $metadata
 * @method static Builder<static>|MetaChatCall live()
 * @mixin \Eloquent
 */
class MetaChatCall extends Model
{
    use HasFactory;

    protected $table = 'meta_chat_calls';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'direction'   => MetaChatCallDirectionEnum::class,
            'status'      => MetaChatCallStatusEnum::class,
            'metadata'    => 'array',
            'ringing_at'  => 'datetime',
            'answered_at' => 'datetime',
            'ended_at'    => 'datetime',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    /**
     * Meta gives up on an unanswered call after about a minute. When its terminate webhook is
     * lost the row would ring for ever and block every later call, so a ring older than that
     * no longer counts as live.
     */
    public const int RING_TIMEOUT_SECONDS = 90;

    public function scopeLive(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', MetaChatCallStatusEnum::IN_PROGRESS)
                ->orWhere(function (Builder $query) {
                    $query->where('status', MetaChatCallStatusEnum::RINGING)
                        ->where('ringing_at', '>', now()->subSeconds(self::RING_TIMEOUT_SECONDS));
                });
        });
    }

    public function metaChannel(): BelongsTo
    {
        return $this->belongsTo(MetaChannel::class);
    }

    public function metaChatSession(): BelongsTo
    {
        return $this->belongsTo(MetaChatSession::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLive(): bool
    {
        if ($this->status === MetaChatCallStatusEnum::IN_PROGRESS) {
            return true;
        }

        return $this->status === MetaChatCallStatusEnum::RINGING
            && $this->ringing_at?->gt(now()->subSeconds(self::RING_TIMEOUT_SECONDS));
    }

    public function elapsedSeconds(): int
    {
        if (!$this->answered_at) {
            return 0;
        }

        return (int) $this->answered_at->diffInSeconds($this->ended_at ?? now(), absolute: true);
    }
}
