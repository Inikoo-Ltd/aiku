<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 02:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One short piece of what customer service knows, for the AI to answer from: a section of the
 * shop's returns, delivery or terms page, a fact read from the shop's settings (delivery prices,
 * blocked countries, the first order bonus), or a note staff wrote because it is on no page ("the
 * UK shop cannot ship to Germany, use the EU warehouses"). Pages and settings are copied in by
 * HydrateChatKnowledge; staff notes are never overwritten and win when they disagree. A shop of
 * null means every shop of the organisation, or of the group when that is null too.
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property string $kind
 * @property string $title
 * @property string $body
 * @property string|null $url
 * @property string $source_type
 * @property string|null $source_id
 * @property bool $is_manual
 * @property bool $is_active
 * @property int|null $created_by_user_id
 * @property \Illuminate\Support\Carbon|null $hydrated_at
 * @property string $status active, candidate (learned, not yet confirmed), conflict or removed
 * @property array|null $evidence
 * @property int $customers_count
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $conflict
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Shop|null $shop
 */
class ChatKnowledgeEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_manual'   => 'boolean',
        'is_active'   => 'boolean',
        'hydrated_at'  => 'datetime',
        'evidence'     => 'array',
        'last_seen_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * What applies to a shop: its own entries, its organisation's and the group's.
     *
     * @param  Builder<ChatKnowledgeEntry>  $query
     */
    public function scopeForShop(Builder $query, Shop $shop): void
    {
        $query->where('is_active', true)
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where('group_id', $shop->group_id)
            ->where(fn (Builder $query) => $query->where('shop_id', $shop->id)
                ->orWhere(fn (Builder $query) => $query->whereNull('shop_id')->where('organisation_id', $shop->organisation_id))
                ->orWhere(fn (Builder $query) => $query->whereNull('shop_id')->whereNull('organisation_id')));
    }
}
