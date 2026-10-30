<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:42:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionPriceQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionQuantityQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Address;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AspoDeposit;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InOrganisation;
use App\Models\Traits\HasSearch;
use App\Models\Traits\HasStageMarks;
use Eloquent;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Procurement\PurchaseOrder
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $parent_type OrgAgent|OrgSupplier|Partner(intra-group sales)
 * @property int $parent_id
 * @property string $parent_code Parent code on the time of consolidation
 * @property string $parent_name Parent name on the time of consolidation
 * @property string $reference
 * @property PurchaseOrderStateEnum $state
 * @property PurchaseOrderDeliveryStateEnum $delivery_state
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $date latest relevant date
 * @property string|null $in_process_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $not_received_at
 * @property int|null $agent_id
 * @property int|null $supplier_id
 * @property int|null $partner_id
 * @property float|null $gross_weight
 * @property float|null $net_weight
 * @property int $number_purchase_order_transactions
 * @property int $number_current_purchase_order_transactions Number purchase order transactions (except: cancelled and not_received)
 * @property int $number_open_purchase_order_transactions Number purchase order transactions (except: in_process,settled,cancelled,not_received)
 * @property int $number_purchase_order_transactions_state_in_process
 * @property int $number_purchase_order_transactions_state_submitted
 * @property int $number_purchase_order_transactions_state_confirmed
 * @property int $number_purchase_order_transactions_state_settled
 * @property int $number_purchase_order_transactions_state_cancelled
 * @property int $number_purchase_order_transactions_state_not_received
 * @property int $number_purchase_orders_transactions_delivery_state_in_process
 * @property int $number_purchase_orders_transactions_delivery_state_confirmed
 * @property int $number_purchase_orders_transactions_delivery_state_ready_to_shi
 * @property int $number_purchase_orders_transactions_delivery_state_dispatched
 * @property int $number_purchase_orders_transactions_delivery_state_received
 * @property int $number_purchase_orders_transactions_delivery_state_checked
 * @property int $number_purchase_orders_transactions_delivery_state_settled
 * @property int $number_purchase_orders_transactions_delivery_state_not_received
 * @property int $number_purchase_orders_transactions_delivery_state_cancelled
 * @property int $currency_id
 * @property numeric|null $grp_exchange
 * @property numeric|null $org_exchange
 * @property bool $is_costed
 * @property bool $is_partner_rescue
 * @property array<array-key, mixed> $cost_data
 * @property numeric|null $cost_items
 * @property numeric|null $cost_extra
 * @property numeric|null $cost_shipping
 * @property numeric|null $cost_duties
 * @property numeric $cost_tax
 * @property numeric $cost_total
 * @property int $number_stock_deliveries Number supplier deliveries
 * @property int $number_current_stock_deliveries Number supplier deliveries (except: cancelled and not_received)
 * @property int $number_is_costed_stock_deliveries is_costed=true
 * @property int $number_is_not_costed_stock_deliveries is_costed=false
 * @property int $number_is_costed_stock_deliveries_state_placed state=placed is_costed=true
 * @property int $number_is_not_costed_stock_deliveries_state_placed state=placed  is_costed=true
 * @property int $number_stock_deliveries_state_in_process
 * @property int $number_stock_deliveries_state_confirmed
 * @property int $number_stock_deliveries_state_ready_to_ship
 * @property int $number_stock_deliveries_state_dispatched
 * @property int $number_stock_deliveries_state_received
 * @property int $number_stock_deliveries_state_checked
 * @property int $number_stock_deliveries_state_placed
 * @property int $number_stock_deliveries_state_cancelled
 * @property int $number_stock_deliveries_state_not_received
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property int $number_stock_deliveries_state_booking_in
 * @property int $number_stock_deliveries_state_booked_in
 * @property int|null $estimated_delivery_days
 * @property \Illuminate\Support\Carbon|null $estimated_received_at
 * @property int|null $buyer_id
 * @property \Illuminate\Support\Carbon|null $sample_approved_at
 * @property bool $sample_required the goods may not ship before the sample is approved
 * @property string|null $sample_override_reason why the goods ship without an approved sample
 * @property \Illuminate\Support\Carbon|null $produced_at
 * @property \Illuminate\Support\Carbon|null $qc_passed_at
 * @property \Illuminate\Support\Carbon|null $handed_over_at
 * @property \Illuminate\Support\Carbon|null $proposed_ready_at
 * @property \Illuminate\Support\Carbon|null $approved_ready_at
 * @property \Illuminate\Support\Carbon|null $compliance_complete_at
 * @property bool $chs_excluded
 * @property string|null $chs_exclusion_reason
 * @property int|null $agent_supplier_purchase_order_id legacy agent supplier purchase order this order was split from
 * @property string|null $agent_order_reference legacy agent order number shared by supplier orders placed together through an agent before Oct 2026
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Agent|null $agent
 * @property-read Currency $currency
 * @property-read Collection<int, AspoDeposit> $deposits
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation $organisation
 * @property-read Model|\Eloquent $parent
 * @property-read Collection<int, \App\Models\Procurement\PurchaseOrderTransaction> $purchaseOrderTransactions
 * @property-read Collection<int, \App\Models\Procurement\PurchaseOrderChase> $chases
 * @property-read Collection<int, StockDelivery> $stockDeliveries
 * @method static \Database\Factories\Procurement\PurchaseOrderFactory factory($count = null, $state = [])
 * @method static Builder<static>|PurchaseOrder newModelQuery()
 * @method static Builder<static>|PurchaseOrder newQuery()
 * @method static Builder<static>|PurchaseOrder onlyTrashed()
 * @method static Builder<static>|PurchaseOrder query()
 * @method static Builder<static>|PurchaseOrder withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PurchaseOrder withoutTrashed()
 * @mixin Eloquent
 */
class PurchaseOrder extends Model implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasAddress;
    use HasAddresses;
    use HasSlug;
    use HasFactory;
    use HasHistory;
    use InOrganisation;
    use HasAttachments;
    use HasSearch;
    use HasStageMarks;

    public const string AGENT_TRACKING_STARTED_AT = '2026-10-08';

    protected $casts = [
        'data'            => 'array',
        'cost_data'       => 'array',
        'is_partner_rescue' => 'boolean',
        'state'           => PurchaseOrderStateEnum::class,
        'delivery_state' => PurchaseOrderDeliveryStateEnum::class,
        'date'            => 'datetime',
        'deposit_paid_at' => 'datetime',
        'balance_paid_at' => 'datetime',
        'submitted_at'    => 'datetime',
        'confirmed_at'    => 'datetime',
        'manufactured_at' => 'datetime',
        'dispatched_at'   => 'datetime',
        'received_at'     => 'datetime',
        'checked_at'      => 'datetime',
        'settled_at'      => 'datetime',
        'cancelled_at'    => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'estimated_received_at' => 'datetime',
        'sample_approved_at'    => 'datetime',
        'sample_required'       => 'boolean',
        'produced_at'           => 'datetime',
        'qc_passed_at'          => 'datetime',
        'handed_over_at'        => 'datetime',
        'proposed_ready_at'      => 'datetime',
        'approved_ready_at'      => 'datetime',
        'compliance_complete_at' => 'datetime',
        'chs_excluded'           => 'boolean',
        'production_started_at'  => 'datetime',
        'online_at'              => 'datetime',
        'stage_marks'            => 'array',
    ];


    protected $attributes = [
        'data'        => '{}',
        'cost_data'   => '{}',
        'stage_marks' => '{}',
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function generateTags(): array
    {
        return [
            'procurement'
        ];
    }

    protected array $auditInclude = [
        'reference',
        'agent_reference',
        'state',
        'notes',
        'cost_total',
        'cost_items',
        'cost_shipping',
        'cost_duties',
        'buyer_id',
        'sample_approved_at',
        'sample_required',
        'sample_override_reason',
        'deposit_amount',
        'deposit_paid_at',
        'deposit_paid_by_id',
        'production_started_at',
        'produced_at',
        'qc_passed_at',
        'handed_over_at',
        'proposed_ready_at',
        'approved_ready_at',
        'compliance_complete_at',
        'chs_excluded',
        'chs_exclusion_reason',
    ];

    public function stageMarkColumns(): array
    {
        return [
            'submitted_at',
            'confirmed_at',
            'deposit_paid_at',
            'balance_paid_at',
            'sample_approved_at',
            'production_started_at',
            'proposed_ready_at',
            'approved_ready_at',
            'produced_at',
            'qc_passed_at',
            'handed_over_at',
            'compliance_complete_at',
        ];
    }

    public function estimatedReceivingDate(): ?string
    {
        return Arr::get($this->data, 'estimated_receiving_date') ?: $this->estimated_received_at?->toDateString();
    }

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated || $this->wasChanged([
                'organisation_id',
                'state',
                'reference',
                'parent_code',
                'parent_name',
            ]);
    }

    public function toSearchableArray(): array
    {
        return [
            'id'               => (string)$this->id,
            'organisation_id'  => $this->organisation_id,
            'state'            => $this->state?->value,
            'reference'        => (string)$this->reference,
            'slug'             => $this->slug,
            'parent_code'      => (string)$this->parent_code,
            'parent_name'      => (string)$this->parent_name,
            'agent_order_reference' => (string)$this->agent_order_reference,
            'supplier_id'      => $this->supplier_id,
            'agent_id'         => $this->agent_id,
            'created_at'       => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
        ];
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function purchaseOrderTransactions(): HasMany
    {
        return $this->hasMany(PurchaseOrderTransaction::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function stockDeliveries(): BelongsToMany
    {
        return $this->belongsToMany(StockDelivery::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function chases(): HasMany
    {
        return $this->hasMany(PurchaseOrderChase::class);
    }

    /**
     * Supplier orders through an agent whose money Aiku follows: those created since agent supplier orders were
     * split on 8 Oct 2026, and the older ones the split numbered. Earlier unsplit orders were paid outside Aiku.
     */
    public function scopeTrackedThroughAgent(Builder $query): void
    {
        $query->where('purchase_orders.parent_type', 'OrgSupplier')
            ->where(fn (Builder $query) => $query
                ->whereNotNull('purchase_orders.agent_order_reference')
                ->orWhere('purchase_orders.created_at', '>=', self::AGENT_TRACKING_STARTED_AT));
    }

    public function isAgentOrder(): bool
    {
        return $this->agent_id !== null && $this->parent_type === 'OrgSupplier';
    }

    /**
     * Its sample must be approved before the goods ship, it is not yet, and nobody recorded why they ship anyway.
     */
    public function isWaitingForSample(): bool
    {
        return $this->sample_required && $this->sample_approved_at === null && blank($this->sample_override_reason);
    }

    /**
     * When the order was started. The orders split per supplier on 8 Oct 2026 (INI-059) were created that day and
     * kept their submitted and confirmed dates, so their start is the creation of the order they were split from
     * (data.split_from), soft deleted or kept.
     */
    public function startedAt(): ?Carbon
    {
        return self::earliestStartedAt(new Collection([$this]));
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     */
    public static function earliestStartedAt(Collection $purchaseOrders): ?Carbon
    {
        $splitFromIds = $purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => Arr::get($purchaseOrder->data, 'split_from.id'))->filter()->unique();

        $splitFromCreatedAt = $splitFromIds->isEmpty()
            ? []
            : self::withTrashed()->whereIn('id', $splitFromIds->all())->get(['id', 'created_at'])->pluck('created_at', 'id')->all();

        return $purchaseOrders
            ->map(fn (PurchaseOrder $purchaseOrder) => $splitFromCreatedAt[Arr::get($purchaseOrder->data, 'split_from.id')] ?? $purchaseOrder->created_at)
            ->filter()
            ->min();
    }

    /**
     * The supplier is making the goods: a stage from production start on is recorded.
     */
    public function hasProductionStarted(): bool
    {
        return $this->production_started_at !== null || $this->sample_approved_at !== null || $this->produced_at !== null;
    }

    /**
     * An order through an agent can no longer lose lines once its supplier is making the goods: the agent receives them
     * in batches against those lines.
     */
    public function areLinesLockedByProduction(): bool
    {
        return $this->isAgentOrder() && $this->hasProductionStarted();
    }

    /**
     * Lines whose new supplier price the agent asked about and the organisation has not answered yet.
     */
    public function numberPendingPriceChanges(): int
    {
        return $this->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::SUBMITTED)
            ->where('price_query_state', PurchaseOrderTransactionPriceQueryStateEnum::PENDING)
            ->count();
    }

    /**
     * Lines whose new quantity the agent asked for and the organisation has not answered yet, before or after the
     * order was confirmed.
     */
    public function numberPendingQuantityChanges(): int
    {
        return $this->purchaseOrderTransactions()
            ->whereIn('state', [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED])
            ->where('quantity_query_state', PurchaseOrderTransactionQuantityQueryStateEnum::PENDING)
            ->count();
    }

    public function isWaitingForLineAnswers(): bool
    {
        return $this->numberPendingPriceChanges() > 0 || $this->numberPendingQuantityChanges() > 0;
    }

    /**
     * The org agent this order was placed through. Read from the order, not from its supplier, which
     * may have moved to another agent or become independent since.
     */
    public function orgAgentOfOrder(): ?OrgAgent
    {
        if (!$this->agent_id) {
            return null;
        }

        return OrgAgent::where('organisation_id', $this->organisation_id)->where('agent_id', $this->agent_id)->first();
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(AspoDeposit::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function depositPaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deposit_paid_by_id');
    }

    public function depositPayments(): HasMany
    {
        return $this->hasMany(PurchaseOrderDepositPayment::class);
    }
}
