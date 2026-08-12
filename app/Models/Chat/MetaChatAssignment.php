<?php

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $meta_channel_id
 * @property int|null $meta_chat_session_id
 * @property int|null $meta_chat_agent_id
 * @property ChatAssignmentStatusEnum $status
 * @property ChatAssignmentAssignedByEnum $assigned_by
 * @property \Illuminate\Support\Carbon $assigned_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 */
class MetaChatAssignment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'meta_chat_assignments';

    protected $guarded = [];

    protected $casts = [
        'status' => ChatAssignmentStatusEnum::class,
        'assigned_by' => ChatAssignmentAssignedByEnum::class,
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function metaChannel(): BelongsTo
    {
        return $this->belongsTo(MetaChannel::class);
    }

    public function metaChatSession(): BelongsTo
    {
        return $this->belongsTo(MetaChatSession::class);
    }

    public function metaChatAgent(): BelongsTo
    {
        return $this->belongsTo(MetaChatAgent::class);
    }
}
