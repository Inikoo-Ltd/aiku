<?php

namespace App\Models\CRM\Livechat;

use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Enums\CRM\Livechat\ChatKnowledgeSourceTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $chat_automation_id
 * @property string $knowledge_node_id
 * @property string $source_id
 * @property ChatKnowledgeSourceTypeEnum $type
 * @property string|null $name
 * @property string|null $content
 * @property ChatKnowledgeSourceStatusEnum $status
 * @property string|null $content_hash
 * @property int|null $tokens
 * @property-read ChatAutomation $chatAutomation
 * @mixin \Eloquent
 */
class ChatKnowledgeSource extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $table = 'chat_knowledge_sources';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'   => ChatKnowledgeSourceTypeEnum::class,
            'status' => ChatKnowledgeSourceStatusEnum::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('knowledge_file')->singleFile();
    }

    public function chatAutomation(): BelongsTo
    {
        return $this->belongsTo(ChatAutomation::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ChatKnowledgeChunk::class);
    }
}
