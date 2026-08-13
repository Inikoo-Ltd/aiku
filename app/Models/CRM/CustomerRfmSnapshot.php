<?php

namespace App\Models\CRM;

use App\Enums\CRM\Customer\CustomerRfmSegmentEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shop_id
 * @property array<array-key, mixed> $tags_summary
 * @property \Illuminate\Support\Carbon $snapshot_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerRfmSnapshot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerRfmSnapshot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerRfmSnapshot query()
 * @mixin \Eloquent
 */
class CustomerRfmSnapshot extends Model
{
    protected $fillable = [
        'shop_id',
        'tags_summary',
        'snapshot_date',
    ];

    protected $casts = [
        'tags_summary' => 'array',
        'snapshot_date' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function rfm_data(): array
    {
        $tagsSummary = $this->tags_summary ?? [];

        $result = [];
        foreach (CustomerRfmSegmentEnum::types() as $type) {
            foreach (CustomerRfmSegmentEnum::tagNamesOfType($type) as $tagName) {
                $result[$type][$tagName] = $tagsSummary[$tagName] ?? 0;
            }
        }

        return $result;
    }
}
