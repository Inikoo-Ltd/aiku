<?php

namespace App\Models\CRM\Livechat;

use App\Models\CRM\Livechat\ChatAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;

class ChatAssignment extends Model
{
     use HasFactory, SoftDeletes;

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





}