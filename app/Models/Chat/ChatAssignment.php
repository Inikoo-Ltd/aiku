<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:07:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $chat_session_id
 * @property int|null $chat_agent_id
 * @property ChatAssignmentStatusEnum $status
 * @property ChatAssignmentAssignedByEnum $assigned_by
 * @property \Illuminate\Support\Carbon $assigned_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Chat\ChatAgent|null $chatAgent
 * @property-read \App\Models\Chat\ChatSession|null $chatSession
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAssignment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAssignment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAssignment withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatAssignment withoutTrashed()
 * @mixin \Eloquent
 */
class ChatAssignment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'chat_assignments';

    protected $casts = [
       'status' => ChatAssignmentStatusEnum::class,
       'assigned_by' => ChatAssignmentAssignedByEnum::class,
       'assigned_at' => 'datetime',
       'resolved_at' => 'datetime',
       'created_at' => 'datetime',
       'updated_at' => 'datetime',
       'deleted_at' => 'datetime',
    ];

    protected $guarded = [];


    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }


    public function chatAgent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class);
    }


    public function markAsActive(): bool
    {
        return $this->update([
            'status' => ChatAssignmentStatusEnum::ACTIVE,
        ]);
    }

    public function markAsResolved(): bool
    {
        return $this->update([
            'status' => ChatAssignmentStatusEnum::RESOLVED,
            'resolved_at' => now(),
        ]);
    }

    public function isActive(): bool
    {
        return $this->status === ChatAssignmentStatusEnum::ACTIVE;
    }

    public function isResolved(): bool
    {
        return $this->status === ChatAssignmentStatusEnum::RESOLVED;
    }





}
