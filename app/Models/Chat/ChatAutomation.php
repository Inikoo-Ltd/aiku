<?php

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatAutomationTriggerEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property int $shop_id
 * @property string $name
 * @property ChatAutomationTriggerEnum $trigger_type
 * @property bool $is_enabled
 * @property string $message
 * @property array<array-key, mixed>|null $flow
 * @property array<array-key, mixed>|null $conditions
 * @property int $priority
 * @property bool $send_once
 * @property array<array-key, mixed>|null $stats
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Shop $shop
 * @mixin \Eloquent
 */
class ChatAutomation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'chat_automations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trigger_type' => ChatAutomationTriggerEnum::class,
            'is_enabled'   => 'boolean',
            'send_once'    => 'boolean',
            'flow'         => 'array',
            'conditions'   => 'array',
            'stats'        => 'array',
            'deleted_at'   => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function knowledgeSources(): HasMany
    {
        return $this->hasMany(ChatKnowledgeSource::class);
    }

    public function knowledgeChunks(): HasMany
    {
        return $this->hasMany(ChatKnowledgeChunk::class);
    }

    /**
     * @return array<int, array{id: string, type: string, data: array<string, mixed>}>
     */
    public function flowNodes(): array
    {
        return Arr::get($this->flow ?? [], 'nodes', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function flowNodesOfType(string $type): array
    {
        return array_values(array_filter(
            $this->flowNodes(),
            fn ($node) => ($node['type'] ?? null) === $type
        ));
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForTrigger(Builder $query, ChatAutomationTriggerEnum|string $trigger): Builder
    {
        $value = $trigger instanceof ChatAutomationTriggerEnum ? $trigger->value : $trigger;

        return $query->where('trigger_type', $value);
    }

    public function scopeForShop(Builder $query, Shop|int $shop): Builder
    {
        $shopId = $shop instanceof Shop ? $shop->id : $shop;

        return $query->where('shop_id', $shopId);
    }
}
