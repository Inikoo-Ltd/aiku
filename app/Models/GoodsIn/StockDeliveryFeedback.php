<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\ProcurementNote;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The feedback round of one delivery, one per delivery: the general part (stars, topics, comment, photos) is for
 * whoever sent it (the agent of a consolidated container, else its only supplier or partner, supplier_id set only
 * when the delivery has one supplier) and its items are notes on single lines, each for that line's supplier. Notes
 * can come before the stars: until sent_at the round is a draft nobody outside sees. A message only: it never
 * touches stock, claims, discrepancy outcomes or money.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property int|null $supplier_id
 * @property int|null $agent_id
 * @property int|null $org_partner_id
 * @property int|null $buyer_id
 * @property int|null $author_id
 * @property int|null $rating
 * @property array<int, string> $topics
 * @property string|null $comment
 * @property int|null $procurement_note_id
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read StockDelivery $stockDelivery
 * @property-read Supplier|null $supplier
 * @property-read Agent|null $agent
 * @property-read OrgPartner|null $orgPartner
 * @property-read User|null $buyer
 * @property-read User|null $author
 * @property-read ProcurementNote|null $procurementNote
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StockDeliveryItemFeedback> $items
 */
class StockDeliveryFeedback extends Model
{
    use InOrganisation;

    protected $table = 'stock_delivery_feedbacks';

    protected $guarded = [];

    protected $attributes = [
        'topics' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'topics'  => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function orgPartner(): BelongsTo
    {
        return $this->belongsTo(OrgPartner::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function procurementNote(): BelongsTo
    {
        return $this->belongsTo(ProcurementNote::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockDeliveryItemFeedback::class)->orderBy('id');
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }
}
