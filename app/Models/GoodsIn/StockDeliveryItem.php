<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 14:32:31 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyOutcomeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property int|null $supplier_product_id
 * @property int|null $historic_supplier_product_id
 * @property int|null $org_supplier_product_id
 * @property int|null $stock_id Null allowed when org_stock is exclusive to an organization
 * @property int $org_stock_id
 * @property StockDeliveryItemStateEnum $state
 * @property array<array-key, mixed> $data
 * @property numeric $unit_quantity
 * @property numeric $unit_quantity_checked
 * @property numeric $unit_quantity_placed
 * @property numeric $gross_unit_price
 * @property numeric $net_amount
 * @property numeric|null $grp_net_amount
 * @property numeric|null $org_net_amount
 * @property bool $is_costed
 * @property numeric $gross_amount
 * @property numeric|null $grp_gross_amount
 * @property numeric|null $org_gross_amount
 * @property numeric|null $grp_exchange
 * @property numeric|null $org_exchange
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $not_received_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property \Illuminate\Support\Carbon|null $placed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $deleted_at
 * @property string|null $source_id
 * @property numeric|null $cost_items
 * @property numeric|null $cost_extra
 * @property numeric|null $cost_shipping
 * @property numeric|null $cost_duties
 * @property numeric $cost_tax
 * @property numeric $cost_total
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read OrgStock|null $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GoodsIn\Sowing> $sowings
 * @property-read \App\Models\GoodsIn\StockDelivery|null $stockDelivery
 * @property-read SupplierProduct|null $supplierProduct
 * @method static \Database\Factories\GoodsIn\StockDeliveryItemFactory factory($count = null, $state = [])
 * @method static Builder<static>|StockDeliveryItem newModelQuery()
 * @method static Builder<static>|StockDeliveryItem newQuery()
 * @method static Builder<static>|StockDeliveryItem query()
 * @mixin Eloquent
 */
class StockDeliveryItem extends Model implements Auditable
{
    use HasFactory;
    use InOrganisation;
    use HasHistory;

    protected array $auditEvents = [
        'updated',
    ];

    protected array $auditInclude = [
        'cost_items',
        'cost_extra',
        'cost_shipping',
        'cost_duties',
        'cost_tax',
        'unit_quantity',
        'net_amount',
        'discrepancy_outcome',
        'stock_delivery_customs_line_id',
    ];

    public function generateTags(): array
    {
        return [
            'procurement'
        ];
    }

    protected $casts = [
        'state'           => StockDeliveryItemStateEnum::class,
        'data'            => 'array',
        'unit_quantity'   => 'decimal:4',
        'unit_quantity_checked' => 'decimal:4',
        'unit_quantity_placed'  => 'decimal:4',
        'unit_price'      => 'decimal:4',
        'discrepancy_outcome'     => StockDeliveryItemDiscrepancyOutcomeEnum::class,
        'discrepancy_resolved_at' => 'datetime',

        'dispatched_at'   => 'datetime',
        'not_received_at' => 'datetime',
        'received_at'     => 'datetime',
        'checked_at'      => 'datetime',
        'placed_at'       => 'datetime',
        'cancelled_at'    => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function customsLine(): BelongsTo
    {
        return $this->belongsTo(StockDeliveryCustomsLine::class, 'stock_delivery_customs_line_id');
    }

    public function claim(): HasOne
    {
        return $this->hasOne(StockDeliveryClaim::class);
    }

    public function sowings(): HasMany
    {
        return $this->hasMany(Sowing::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(StockDeliveryItemBatch::class)->orderBy('id');
    }

    /**
     * SKOs of each batch already put on a shelf, read off the stock movements of this line's put-aways.
     *
     * @return array<int, float>
     */
    public function placedBatchQuantities(): array
    {
        return DB::table('org_stock_movement_batches')
            ->join('sowings', 'sowings.org_stock_movement_id', '=', 'org_stock_movement_batches.org_stock_movement_id')
            ->where('sowings.stock_delivery_item_id', $this->id)
            ->groupBy('org_stock_movement_batches.batch_code_id')
            ->selectRaw('org_stock_movement_batches.batch_code_id, sum(org_stock_movement_batches.quantity) as quantity')
            ->pluck('quantity', 'batch_code_id')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();
    }

    /**
     * What is still to be put away of each batch, in the order the batches were entered.
     *
     * @return array<int, array{batch_code_id: int, quantity: float}>
     */
    public function unplacedBatches(): array
    {
        $placed = $this->placedBatchQuantities();

        return $this->batches()->get()
            ->map(fn (StockDeliveryItemBatch $batch) => [
                'batch_code_id' => $batch->batch_code_id,
                'quantity'      => round((float) $batch->quantity - ($placed[$batch->batch_code_id] ?? 0), 6),
            ])
            ->filter(fn (array $batch) => $batch['quantity'] > 0)
            ->values()
            ->all();
    }

    public function discrepancy(): ?StockDeliveryItemDiscrepancyEnum
    {
        if (!$this->checked_at || $this->state === StockDeliveryItemStateEnum::CANCELLED) {
            return null;
        }

        $settings = Arr::get($this->organisation->settings, 'procurement', []);

        return StockDeliveryItemDiscrepancyEnum::classify(
            expected: (float) $this->unit_quantity,
            received: (float) $this->unit_quantity_checked,
            lineAmount: (float) ($this->org_net_amount ?? $this->net_amount),
            tolerancePercentage: (float) Arr::get($settings, 'delivery_tolerance_percentage', 0),
            toleranceAmount: (float) Arr::get($settings, 'delivery_tolerance_amount', 0),
        );
    }

    public function unitsPerSko(): float
    {
        return (float) ($this->orgStock?->packed_in ?: 1);
    }

    /**
     * A partner delivery is costed the moment its last line is booked in, so a line marked not received by
     * mistake can still be checked afterwards: doing so takes the delivery back to booking in.
     */
    public function canBeReceivedAfterAll(): bool
    {
        return $this->state === StockDeliveryItemStateEnum::NOT_RECEIVED
            && $this->stockDelivery->state === StockDeliveryStateEnum::PLACED
            && $this->stockDelivery->parent_type === 'OrgPartner';
    }

    /**
     * What one SKO put away from this line cost in the organisation's currency: the landed cost
     * once the delivery is costed, the goods price until then.
     *
     * @return array{cost_per_sku: float, cost_status: OrgStockMovementCostStatusEnum}|null
     */
    public function orgStockMovementCost(): ?array
    {
        $isLanded  = $this->is_costed && $this->cost_total > 0;
        $orgAmount = $isLanded ? $this->cost_total * ($this->org_exchange ?? 1) : (float) $this->org_net_amount;
        $skos      = $this->unit_quantity / $this->unitsPerSko();

        if ($orgAmount <= 0 || $skos <= 0) {
            return null;
        }

        return [
            'cost_per_sku' => round($orgAmount / $skos, 6),
            'cost_status'  => $isLanded ? OrgStockMovementCostStatusEnum::COSTED : OrgStockMovementCostStatusEnum::DELIVERY,
        ];
    }
}
