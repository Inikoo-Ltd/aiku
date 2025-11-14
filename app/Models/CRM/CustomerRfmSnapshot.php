<?php

namespace App\Models\CRM;

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
 * @property-read Shop $shop
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

        $rfmStructure = [
            'recency' => [
                'Active',
                'At Risk',
                'Inactive',
                'Lost Customer',
                'New Customer'
            ],
            'frequency' => [
                'One-Time Buyer',
                'Occasional Shopper',
                'Frequent Buyer',
                'Brand Advocate'
            ],
            'monetary' => [
                'Low Value',
                'Medium Value',
                'High Value',
                'Gold Reward',
                'Top 100',
                'Top 10'
            ]
        ];

        $result = [];
        foreach ($rfmStructure as $type => $tags) {
            $result[$type] = [];
            foreach ($tags as $tag) {
                $result[$type][$tag] = $tagsSummary[$tag] ?? 0;
            }
        }

        return $result;
    }
}
