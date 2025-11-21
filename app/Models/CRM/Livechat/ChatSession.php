<?php

namespace App\Models\CRM\Livechat;

use App\Models\CRM\WebUser;
use Illuminate\Database\Eloquent\Model;
use App\Enums\CRM\Livechat\ChatPriority;
use App\Enums\CRM\Livechat\ClosedByType;
use App\Models\CRM\Livechat\ChatMessage;
use App\Enums\CRM\Livechat\ChatSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatSession extends Model
{
    use HasFactory;
    protected $table = 'chat_sessions';

    protected $fillable = [
        'web_user_id',
        'session_uuid',
        'status',
        'guest_identifier',
        'ai_model_version',
        'language',
        'rating',
        'priority',
        'closed_by',
        'closed_at',
        'deleted_at',
    ];

     protected $casts = [
        'status' => ChatSessionStatus::class,
        'priority' => ChatPriority::class,
        'closed_by' => ClosedByType::class,
        'closed_at' => 'datetime',
        'rating' => 'decimal:1',
    ];

    public function getRatingAttribute($value): float|null
    {
        return $value ? round($value, 1) : null;
    }

    // Mutator untuk memastikan valid range
    public function setRatingAttribute($value): void
    {
        if ($value !== null) {
            $value = max(1, min(5, round($value, 1))); // Clamp antara 1-5
        }
        $this->attributes['rating'] = $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */


    public function webUser()
    {
        return $this->belongsTo(WebUser::class, 'web_user_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

}
