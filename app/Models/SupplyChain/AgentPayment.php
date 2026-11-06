<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderPaymentMethodEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Media;
use App\Models\Helpers\Currency;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A line of an agent's ledger for one client organisation, in the agent's currency. Money in is a transfer the
 * client sends (optionally against a container, where it is taken off the container's invoice together with the
 * deposits applied to it) or a supplier's refund; money out is what the agent spends from the client's funds.
 * The client's wallet with the agent is the sum of the lines.
 *
 * @property int $id
 * @property int $group_id
 * @property int $agent_id
 * @property int $organisation_id
 * @property AgentPaymentTypeEnum $type
 * @property int|null $stock_delivery_id
 * @property int|null $purchase_order_id
 * @property int|null $supplier_id
 * @property string|null $original_amount
 * @property int|null $original_currency_id
 * @property int|null $deposit_request_id
 * @property int|null $org_stock_id the SKO a packaging line belongs to
 * @property bool $needs_review
 * @property string|null $review_reason
 * @property array<string, mixed> $data
 * @property \Illuminate\Support\Carbon $date
 * @property string $amount
 * @property int $currency_id
 * @property string|null $reference
 * @property string|null $notes
 * @property PurchaseOrderPaymentMethodEnum|null $method
 * @property int|null $media_id
 * @property int|null $created_by
 */
class AgentPayment extends Model
{
    use InGroup;
    use SoftDeletes;

    protected $guarded = [];

    protected $attributes = [
        'data' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'date'   => 'date',
            'type'         => AgentPaymentTypeEnum::class,
            'needs_review' => 'boolean',
            'data'         => 'array',
            'method' => PurchaseOrderPaymentMethodEnum::class,
        ];
    }

    public const string RECORDED_BY_CLIENT = 'client';

    public const int MONEY_REQUEST_TRANSFER_DAYS = 3;

    /**
     * When a transfer the client sent against a money request should reach the agent's bank.
     */
    public function expectedArrival(): ?Carbon
    {
        if ($this->deposit_request_id === null || $this->type !== AgentPaymentTypeEnum::TRANSFER_RECEIVED) {
            return null;
        }

        return $this->date->copy()->addDays(self::MONEY_REQUEST_TRANSFER_DAYS);
    }

    /**
     * A payment the client recorded itself, against a container or a money request: only the client removes it.
     */
    public function isRecordedByClient(): bool
    {
        return ($this->data['recorded_by'] ?? null) === self::RECORDED_BY_CLIENT;
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function depositRequest(): BelongsTo
    {
        return $this->belongsTo(DepositRequest::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function originalCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'original_currency_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
