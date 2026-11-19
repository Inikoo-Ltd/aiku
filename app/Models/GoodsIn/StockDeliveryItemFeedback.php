<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryItemFeedbackIssueEnum;
use App\Models\Procurement\ProcurementNote;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A note on one line of a delivery inside its feedback round: it belongs to the supplier of that line, the only
 * supplier who sees it. A message only: it never touches stock, claims, discrepancy outcomes or money.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property int $stock_delivery_feedback_id
 * @property int $stock_delivery_item_id
 * @property int|null $supplier_id
 * @property int|null $agent_id
 * @property int|null $buyer_id
 * @property int|null $author_id
 * @property StockDeliveryItemFeedbackIssueEnum $issue
 * @property string|null $comment
 * @property int|null $procurement_note_id
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read StockDelivery $stockDelivery
 * @property-read StockDeliveryFeedback $feedback
 * @property-read StockDeliveryItem $stockDeliveryItem
 * @property-read Supplier|null $supplier
 * @property-read Agent|null $agent
 * @property-read User|null $buyer
 * @property-read User|null $author
 * @property-read ProcurementNote|null $procurementNote
 */
class StockDeliveryItemFeedback extends Model
{
    use InOrganisation;

    protected $table = 'stock_delivery_item_feedbacks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issue'       => StockDeliveryItemFeedbackIssueEnum::class,
            'notified_at' => 'datetime',
        ];
    }

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(StockDeliveryFeedback::class, 'stock_delivery_feedback_id');
    }

    public function stockDeliveryItem(): BelongsTo
    {
        return $this->belongsTo(StockDeliveryItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
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
}
