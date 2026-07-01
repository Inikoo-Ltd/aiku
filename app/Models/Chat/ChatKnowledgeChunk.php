<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

/**
 * @property int $id
 * @property int $chat_automation_id
 * @property int|null $chat_knowledge_source_id
 * @property string $knowledge_node_id
 * @property string $guid
 * @property int|null $section_number
 * @property string|null $content
 * @property array<array-key, mixed>|null $metadata
 * @property \Pgvector\Laravel\Vector|null $embedding_384
 * @property \Pgvector\Laravel\Vector|null $embedding_768
 * @property \Pgvector\Laravel\Vector|null $embedding_1024
 * @property \Pgvector\Laravel\Vector|null $embedding_1536
 * @property \Pgvector\Laravel\Vector|null $embedding_2048
 * @property \Pgvector\Laravel\Vector|null $embedding_3072
 * @property \Pgvector\Laravel\Vector|null $embedding_4096
 * @property-read ChatAutomation $chatAutomation
 * @property-read ChatKnowledgeSource|null $source
 * @mixin \Eloquent
 */
class ChatKnowledgeChunk extends Model
{
    use HasFactory;
    use HasNeighbors;

    protected $table = 'chat_knowledge_chunks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata'       => 'array',
            'embedding_384'  => Vector::class,
            'embedding_768'  => Vector::class,
            'embedding_1024' => Vector::class,
            'embedding_1536' => Vector::class,
            'embedding_2048' => Vector::class,
            'embedding_3072' => Vector::class,
            'embedding_4096' => Vector::class,
        ];
    }

    public function chatAutomation(): BelongsTo
    {
        return $this->belongsTo(ChatAutomation::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ChatKnowledgeSource::class, 'chat_knowledge_source_id');
    }
}
