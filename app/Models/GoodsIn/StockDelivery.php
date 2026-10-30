<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:52:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\GoodsIn;

use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SupplyChain\SupplierInvoice;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SupplyChain\SupplierPayment;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Procurement\PurchaseOrder;
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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $parent_type OrgAgent|OrgSupplier|Partner(intra-group sales)
 * @property int $parent_id
 * @property string $parent_code Parent code on the time of consolidation
 * @property string $parent_name Parent name on the time of consolidation
 * @property string $reference
 * @property string|null $agent_reference what the agent calls this shipment, e.g. its spreadsheet tab "Stuffing 25 July 2026"; reference stays the buyer's
 * @property StockDeliveryStateEnum $state
 * @property \Illuminate\Support\Carbon $date latest relevant date
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property \Illuminate\Support\Carbon|null $placed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $not_received_at
 * @property int|null $agent_id
 * @property int|null $supplier_id
 * @property int|null $partner_id
 * @property int $number_purchase_orders
 * @property float|null $gross_weight
 * @property float|null $net_weight
 * @property int $number_stock_delivery_items Number supplier deliveries
 * @property int $number_stock_delivery_items_except_cancelled Number supplier deliveries
 * @property int $number_stock_delivery_items_state_in_process
 * @property int $number_stock_delivery_items_state_confirmed
 * @property int $number_stock_delivery_items_state_ready_to_ship
 * @property int $number_stock_delivery_items_state_dispatched
 * @property int $number_stock_delivery_items_state_received
 * @property int $number_stock_delivery_items_state_checked
 * @property int $number_stock_delivery_items_state_placed
 * @property int $number_stock_delivery_items_state_cancelled
 * @property int $number_stock_delivery_items_state_not_received
 * @property int $currency_id
 * @property numeric|null $grp_exchange
 * @property numeric|null $org_exchange
 * @property bool $is_costed
 * @property array<array-key, mixed> $cost_data
 * @property numeric|null $cost_items
 * @property numeric|null $cost_extra
 * @property numeric|null $cost_shipping
 * @property numeric|null $cost_duties
 * @property numeric $cost_tax
 * @property numeric $cost_total
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property int $number_stock_delivery_items_under_delivered unit_quantity_checked < unit_quantity
 * @property int $number_stock_delivery_items_over_delivered unit_quantity_checked > unit_quantity
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $ready_to_ship_at
 * @property \Illuminate\Support\Carbon|null $booking_in_at
 * @property \Illuminate\Support\Carbon|null $booked_in_at
 * @property numeric|null $cbm carton cubic meters
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Currency $currency
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read Collection<int, \App\Models\GoodsIn\StockDeliveryItem> $items
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation $organisation
 * @property-read Model|\Eloquent $parent
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 * @method static \Database\Factories\GoodsIn\StockDeliveryFactory factory($count = null, $state = [])
 * @method static Builder<static>|StockDelivery newModelQuery()
 * @method static Builder<static>|StockDelivery newQuery()
 * @method static Builder<static>|StockDelivery onlyTrashed()
 * @method static Builder<static>|StockDelivery query()
 * @method static Builder<static>|StockDelivery withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StockDelivery withoutTrashed()
 * @mixin Eloquent
 */
class StockDelivery extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasAddress;
    use HasAddresses;
    use HasSlug;
    use HasFactory;
    use InOrganisation;
    use HasAttachments;
    use HasHistory;
    use HasSearch;
    use HasStageMarks;

    public const string COSTS_REQUIRED_FROM = '2026-10-12';

    public const int TO_COST_ACTIVITY_DAYS = 120;

    public const array STATES_RECEIVED = [
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
        StockDeliveryStateEnum::BOOKED_IN,
        StockDeliveryStateEnum::PLACED,
    ];

    public const array STATES_WITHOUT_COSTS = [
        StockDeliveryStateEnum::CANCELLED,
        StockDeliveryStateEnum::NOT_RECEIVED,
    ];


    protected $casts = [
        'data'             => 'array',
        'cost_data'        => 'array',
        'state'            => StockDeliveryStateEnum::class,
        'date'             => 'datetime',
        'customs_released_at' => 'date',
        'arrived_at'       => 'datetime',
        'stage_marks'      => 'array',
        'confirmed_at'     => 'datetime',
        'ready_to_ship_at' => 'datetime',
        'dispatched_at'    => 'datetime',
        'received_at'      => 'datetime',
        'checked_at'       => 'datetime',
        'booking_in_at'    => 'datetime',
        'booked_in_at'     => 'datetime',
        'placed_at'        => 'datetime',
        'cancelled_at'     => 'datetime',
        'not_received_at'  => 'datetime',
        'fetched_at'       => 'datetime',
        'last_fetched_at'  => 'datetime',
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
        'cost_total',
        'cost_items',
        'cost_extra',
        'cost_shipping',
        'cost_duties',
        'is_costed',
        'customs_mrn',
        'customs_released_at',
        'arrived_at',
    ];

    public function stageMarkColumns(): array
    {
        return [
            'dispatched_at',
            'arrived_at',
            'customs_released_at',
            'received_at',
            'checked_at',
            'placed_at',
        ];
    }

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated
            || $this->wasChanged([
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
            'id'              => (string)$this->id,
            'organisation_id' => $this->organisation_id,
            'state'           => $this->state?->value,
            'reference'       => $this->reference,
            'slug'            => $this->slug,
            'parent_code'     => $this->parent_code,
            'parent_name'     => $this->parent_name,
            'agent_id'        => $this->agent_id,
            'created_at'      => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
        ];
    }

    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class);
    }

    /**
     * Supplier and agent deliveries are booked in to locations from aiku; Aurora still creates,
     * dispatches, receives and checks them. Production job orders are fully Aurora's.
     */
    public function placesInAiku(): bool
    {
        return $this->organisation->is_aiku_stock_control && $this->parent_type !== 'Production';
    }

    /**
     * A partner delivery mirrors the seller's delivery note, and the seller owns it until the
     * buyer receives the goods.
     */
    public function isInGoodsIn(): bool
    {
        return in_array($this->state, [
            StockDeliveryStateEnum::RECEIVED,
            StockDeliveryStateEnum::CHECKED,
            StockDeliveryStateEnum::BOOKING_IN,
        ], true);
    }

    /**
     * Still with the agent or supplier: being filled, confirmed or ready to ship.
     */
    public function isStaged(): bool
    {
        return in_array($this->state, [
            StockDeliveryStateEnum::IN_PROCESS,
            StockDeliveryStateEnum::CONFIRMED,
            StockDeliveryStateEnum::READY_TO_SHIP,
        ], true);
    }

    public function hasNoProducts(): bool
    {
        return !$this->items()->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)->exists();
    }

    public function isManagedByPartner(): bool
    {
        return $this->delivery_note_id !== null && in_array($this->state, [
            StockDeliveryStateEnum::IN_PROCESS,
            StockDeliveryStateEnum::CONFIRMED,
            StockDeliveryStateEnum::READY_TO_SHIP,
            StockDeliveryStateEnum::DISPATCHED,
        ], true);
    }

    /**
     * An accounting manager updates a costed delivery, or the person they named when they reopened it, until it is finished.
     */
    public function canUpdateCosting(User $user): bool
    {
        if ($user->authTo("org-supervisor.{$this->organisation_id}.accounting")) {
            return true;
        }

        return !$this->is_costed
            && (int) Arr::get($this->data, 'costing_reopened.assignee_id') === $user->id;
    }

    /**
     * Whoever finished the costing may reopen it themselves for 2 working days (Monday to Friday), to correct a mistake without an accounting manager (HELP-4329).
     */
    public function canUndoCosting(User $user): bool
    {
        $costedAt = Arr::get($this->data, 'costed.at');

        return $this->is_costed
            && $costedAt
            && (int) Arr::get($this->data, 'costed.user_id') === $user->id
            && now()->lt(Carbon::parse($costedAt)->addWeekdays(2));
    }

    public function isCostingReopenable(): bool
    {
        return $this->is_costed
            && $this->parent_type !== 'OrgPartner'
            && !in_array($this->state, [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED], true);
    }

    /**
     * Strips what Aurora knows about placement from a fetched delivery or item payload, so a
     * re-fetch can never regress what aiku booked in. The state is dropped from checked onwards
     * because aiku derives it from its own sowings.
     *
     * @param  array<string, mixed>  $modelData
     * @return array<string, mixed>
     */
    public static function withoutAuroraPlacement(array $modelData): array
    {
        $state = $modelData['state'] ?? null;
        if ($state instanceof \BackedEnum) {
            $state = $state->value;
        }
        if (in_array($state, ['checked', 'placed'], true)) {
            unset($modelData['state']);
        }
        unset($modelData['placed_at'], $modelData['unit_quantity_placed']);

        return $modelData;
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockDeliveryItem::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(StockDeliveryCost::class);
    }

    public function customsLines(): HasMany
    {
        return $this->hasMany(StockDeliveryCustomsLine::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(StockDeliveryClaim::class);
    }

    /**
     * The costs procurement still owes on a delivery: the agent or supplier invoice, the shipping and the duty
     * each need an amount, an estimate or a not applicable. They never hold the warehouse back; procurement is
     * told when booking in starts and reminded every day. Deliveries created before the rule started and partner
     * deliveries, which cost themselves, owe nothing.
     *
     * @return array<int, string> labels of the costs still missing
     */
    public function costsMissing(): array
    {
        if ($this->is_costed || $this->parent_type === 'OrgPartner' || in_array($this->state, self::STATES_WITHOUT_COSTS, true) || $this->created_at?->lt(self::COSTS_REQUIRED_FROM)) {
            return [];
        }

        $costs   = $this->costs()->get();
        $isReady = fn (StockDeliveryCostTypeEnum $type) => (bool) $costs->first(fn (StockDeliveryCost $cost) => $cost->type === $type && ($cost->received_at || $cost->is_na));
        $missing = [];

        if (!$isReady(StockDeliveryCostTypeEnum::AGENT_INVOICE) && !$this->agentInvoice()->exists() && !$this->supplierInvoice()->exists()) {
            $missing[] = __('Agent or supplier invoice');
        }

        foreach ([StockDeliveryCostTypeEnum::SHIPPING, StockDeliveryCostTypeEnum::DUTY] as $type) {
            if (!$isReady($type)) {
                $missing[] = StockDeliveryCostTypeEnum::labels()[$type->value];
            }
        }

        return $missing;
    }

    /**
     * The work queue of whoever costs deliveries: not costed, on its way or arrived, with activity in the last
     * months, so the old deliveries that were never costed stay out. Partner deliveries cost themselves.
     */
    public function scopeToCost(Builder $query): Builder
    {
        return $query
            ->where('stock_deliveries.is_costed', false)
            ->where('stock_deliveries.parent_type', '!=', 'OrgPartner')
            ->whereIn('stock_deliveries.state', [
                StockDeliveryStateEnum::DISPATCHED,
                StockDeliveryStateEnum::RECEIVED,
                StockDeliveryStateEnum::CHECKED,
                StockDeliveryStateEnum::BOOKING_IN,
                StockDeliveryStateEnum::BOOKED_IN,
                StockDeliveryStateEnum::PLACED,
            ])
            ->whereRaw('coalesce(stock_deliveries.placed_at, stock_deliveries.booked_in_at, stock_deliveries.received_at, stock_deliveries.dispatched_at, stock_deliveries.created_at) >= ?', [now()->subDays(self::TO_COST_ACTIVITY_DAYS)->startOfDay()]);
    }

    /**
     * The same rule as costsMissing(), for lists and counts.
     */
    public function scopeWithCostsMissing(Builder $query): Builder
    {
        $isReady = fn (StockDeliveryCostTypeEnum $type) => fn ($query) => $query->where('type', $type)
            ->where(fn ($query) => $query->whereNotNull('received_at')->orWhere('is_na', true));

        return $query
            ->where('stock_deliveries.is_costed', false)
            ->whereNotIn('stock_deliveries.state', self::STATES_WITHOUT_COSTS)
            ->where('stock_deliveries.parent_type', '!=', 'OrgPartner')
            ->where('stock_deliveries.created_at', '>=', self::COSTS_REQUIRED_FROM)
            ->where(fn ($query) => $query
                ->whereDoesntHave('costs', $isReady(StockDeliveryCostTypeEnum::SHIPPING))
                ->orWhereDoesntHave('costs', $isReady(StockDeliveryCostTypeEnum::DUTY))
                ->orWhere(fn ($query) => $query
                    ->whereDoesntHave('costs', $isReady(StockDeliveryCostTypeEnum::AGENT_INVOICE))
                    ->whereDoesntHave('agentInvoice')
                    ->whereDoesntHave('supplierInvoice')));
    }

    public function serviceInvoices(): BelongsToMany
    {
        return $this->belongsToMany(StockDeliveryServiceInvoice::class, 'stock_delivery_service_invoice_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function depositApplications(): HasMany
    {
        return $this->hasMany(StockDeliveryDepositApplication::class);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Transfers the client sent the agent against this container, the ones taken off its invoice.
     */
    public function agentPayments(): HasMany
    {
        return $this->hasMany(AgentPayment::class)->where('type', AgentPaymentTypeEnum::TRANSFER_RECEIVED);
    }

    /**
     * Every line of the agent's ledger tagged with this container, money in and out.
     */
    public function agentLedgerEntries(): HasMany
    {
        return $this->hasMany(AgentPayment::class);
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function agentInvoice(): HasOne
    {
        return $this->hasOne(AgentInvoice::class);
    }

    public function supplierInvoice(): HasOne
    {
        return $this->hasOne(SupplierInvoice::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
