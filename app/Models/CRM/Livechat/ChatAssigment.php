<?php

namespace App\Models\CRM\Livechat;

use App\Models\CRM\Livechat\ChatAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\CRM\Livechat\ChatAssigmentStatusEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\CRM\Livechat\ChatAssigmentAssignedByEnum;

class ChatAssigment extends Model
{
     use HasFactory, SoftDeletes;

     protected $table = 'chat_assignments';

     protected $casts = [
        'status' => ChatAssigmentStatusEnum::class,
        'assigned_by' => ChatAssigmentAssignedByEnum::class,
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
            'status' => ChatAssigmentStatusEnum::ACTIVE,
        ]);
    }

    public function markAsResolved(): bool
    {
        return $this->update([
            'status' => ChatAssigmentStatusEnsum::RESOLVED,
            'resolved_at' => now(),
        ]);
    }





}
