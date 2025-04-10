<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 15:20:49 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Billables\Rental;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasRetinaSearch;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InFulfilmentCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string|null $slug
 * @property string|null $reference
 * @property string|null $customer_reference
 * @property int $fulfilment_id
 * @property int $fulfilment_customer_id
 * @property int $warehouse_id
 * @property int|null $warehouse_area_id
 * @property int|null $rental_id
 * @property int|null $rental_agreement_clause_id
 * @property int|null $location_id
 * @property int|null $pallet_delivery_id
 * @property int|null $pallet_return_id
 * @property PalletStatusEnum $status
 * @property PalletStateEnum $state
 * @property PalletTypeEnum $type
 * @property int|null $current_recurring_bill_id
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $booking_in_at
 * @property \Illuminate\Support\Carbon|null $set_as_not_received_at
 * @property \Illuminate\Support\Carbon|null $booked_in_at
 * @property \Illuminate\Support\Carbon|null $storing_at
 * @property \Illuminate\Support\Carbon|null $requested_for_return_at
 * @property \Illuminate\Support\Carbon|null $picking_at
 * @property \Illuminate\Support\Carbon|null $picked_at
 * @property \Illuminate\Support\Carbon|null $set_as_incident_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property string|null $notes
 * @property array<array-key, mixed> $data
 * @property object $incident_report
 * @property bool $with_stored_items
 * @property int $number_stored_items
 * @property int $number_stored_items_state_submitted
 * @property int $number_stored_items_state_in_process
 * @property int $number_stored_items_state_active
 * @property int $number_stored_items_state_discontinuing
 * @property int $number_stored_item_audits
 * @property int $number_stored_item_audits_state_in_process
 * @property int $number_stored_item_audits_state_completed
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $deleted_note
 * @property string|null $source_id
 * @property int|null $deleted_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Fulfilment\RecurringBill|null $currentRecurringBill
 * @property-read \App\Models\Fulfilment\Fulfilment $fulfilment
 * @property-read \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Location|null $location
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\MovementPallet> $movements
 * @property-read Organisation $organisation
 * @property-read \App\Models\Fulfilment\PalletDelivery|null $palletDelivery
 * @property-read \App\Models\Fulfilment\PalletReturn|null $palletReturn
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\PalletStoredItem> $palletStoredItems
 * @property-read Rental|null $rental
 * @property-read \App\Models\Fulfilment\RentalAgreementClause|null $rentalAgreementClause
 * @property-read \App\Models\Helpers\RetinaSearch|null $retinaSearch
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\StoredItemAuditDelta> $storedItemAuditDeltas
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\StoredItemAudit> $storedItemAudits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\StoredItem> $storedItems
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Warehouse $warehouse
 * @method static \Database\Factories\Fulfilment\PalletFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pallet locationId($located)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pallet onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pallet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pallet withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Pallet withoutTrashed()
 * @mixin \Eloquent
 */
class Pallet extends Model implements Auditable
{
    use HasSlug;
    use SoftDeletes;
    use HasFactory;
    use HasUniversalSearch;
    use HasRetinaSearch;
    use InFulfilmentCustomer;
    use HasHistory;

    protected $guarded = [];
    protected $casts = [
        'data'                    => 'array',
        'incident_report'         => 'object',
        'state'                   => PalletStateEnum::class,
        'status'                  => PalletStatusEnum::class,
        'type'                    => PalletTypeEnum::class,
        'set_as_not_received_at'  => 'datetime',
        'received_at'             => 'datetime',
        'booking_in_at'           => 'datetime',
        'booked_in_at'            => 'datetime',
        'storing_at'              => 'datetime',
        'requested_for_return_at' => 'datetime',
        'picking_at'              => 'datetime',
        'picked_at'               => 'datetime',
        'set_as_incident_at'      => 'datetime',
        'dispatched_at'           => 'datetime',

    ];

    protected $attributes = [
        'data'            => '{}',
        'incident_report' => '{}',
        'notes'           => '',
    ];

    public function generateTags(): array
    {
        return ['fulfilment', 'inventory'];
    }

    protected array $auditInclude = [
        'reference',
        'customer_reference',
        'status',
        'state',
        'type',
        'notes'
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->doNotGenerateSlugsOnUpdate()
            ->doNotGenerateSlugsOnCreate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function scopeLocationId(Builder $query, $located): Builder
    {
        if ($located) {
            return $query->whereNotNull('location_id');
        }

        return $query->whereNull('location_id');
    }

    public function storedItemAudits()
    {
        return $this->morphMany(StoredItemAudit::class, 'scope');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MovementPallet::class);
    }

    public function palletStoredItems(): HasMany
    {
        return $this->hasMany(PalletStoredItem::class);
    }

    public function storedItems(): BelongsToMany
    {
        return $this->belongsToMany(StoredItem::class, 'pallet_stored_items')->withPivot('quantity', 'delivered_quantity')->withTimestamps();
    }

    public function storedItemAuditDeltas(): BelongsToMany
    {
        return $this->belongsToMany(StoredItemAuditDelta::class, 'stored_item_audit_deltas')->withPivot('audited_quantity', 'state', 'audit_type', 'notes')->withTimestamps();
    }

    public function getEditStoredItemDeltasQuery(int $palletID, int $storedItemAuditId): Builder
    {
        return DB::table('pallet_stored_items')
            ->leftJoin('stored_items', 'stored_items.id', '=', 'pallet_stored_items.stored_item_id')


            ->leftJoin('stored_item_audit_deltas', function ($join) use ($palletID, $storedItemAuditId) {
                $join->on('pallet_stored_items.stored_item_id', '=', 'stored_item_audit_deltas.stored_item_id')
                    ->where('stored_item_audit_deltas.stored_item_audit_id', '=', $storedItemAuditId)
                  ->where('stored_item_audit_deltas.pallet_id', '=', $palletID);
            })

            ->select(
                'stored_items.reference  as stored_item_reference',
                'stored_items.id  as stored_item_id',
                'stored_item_audit_deltas.notes as audit_notes',
                'pallet_stored_items.quantity',
                'stored_item_audit_deltas.audited_quantity',
                'stored_item_audit_deltas.state',
                'stored_item_audit_deltas.audit_type',
                'stored_item_audit_deltas.id as stored_item_audit_delta_id',
                'pallet_stored_items.pallet_id as pallet_id'
            )->orderBy('stored_items.reference');
    }


    public function getEditNewStoredItemDeltasQuery(int $palletID): Builder
    {
        return DB::table('stored_item_audit_deltas')
            ->where('stored_item_audit_deltas.is_stored_item_new_in_pallet', true)
            ->leftJoin('stored_items', 'stored_item_audit_deltas.stored_item_id', '=', 'stored_items.id')
            ->leftJoin('pallet_stored_items', function ($join) use ($palletID) {
                $join->on('pallet_stored_items.stored_item_id', '=', 'stored_item_audit_deltas.stored_item_id')
                    ->where('pallet_stored_items.pallet_id', '=', $palletID);
            })
            ->whereNull('pallet_stored_items.id')
            ->select(
                'stored_items.reference  as stored_item_reference',
                'stored_items.id  as stored_item_id',
                'stored_item_audit_deltas.notes as audit_notes',
                'stored_item_audit_deltas.audited_quantity',
                'stored_item_audit_deltas.state',
                'stored_item_audit_deltas.audit_type',
                'stored_item_audit_deltas.id as audit_id'
            )->orderBy('stored_item_audit_deltas.created_at');
    }


    public function palletDelivery(): BelongsTo
    {
        return $this->belongsTo(PalletDelivery::class);
    }

    public function palletReturn(): BelongsTo
    {
        return $this->belongsTo(PalletReturn::class);
    }

    public function currentRecurringBill(): BelongsTo
    {
        return $this->belongsTo(RecurringBill::class, 'current_recurring_bill_id');
    }

    public function rentalAgreementClause(): BelongsTo
    {
        return $this->belongsTo(RentalAgreementClause::class);
    }

}
