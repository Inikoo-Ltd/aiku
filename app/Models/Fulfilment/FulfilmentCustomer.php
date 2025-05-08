<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 16:46:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\FulfilmentCustomer\FulfilmentCustomerStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Helpers\SerialReference;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InFulfilment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property FulfilmentCustomerStatusEnum $status
 * @property string $slug
 * @property int $customer_id
 * @property int $fulfilment_id
 * @property bool $pallets_storage
 * @property bool $items_storage
 * @property bool $dropshipping
 * @property string|null $webhook_access_key
 * @property int|null $current_recurring_bill_id
 * @property int $number_pallets
 * @property int $number_pallets_with_stored_items
 * @property int $number_pallets_type_pallet
 * @property int $number_pallets_type_box
 * @property int $number_pallets_type_oversize
 * @property int $number_pallets_state_in_process
 * @property int $number_pallets_with_stored_items_state_in_process
 * @property int $number_pallets_state_submitted
 * @property int $number_pallets_with_stored_items_state_submitted
 * @property int $number_pallets_state_confirmed
 * @property int $number_pallets_with_stored_items_state_confirmed
 * @property int $number_pallets_state_received
 * @property int $number_pallets_with_stored_items_state_received
 * @property int $number_pallets_state_booking_in
 * @property int $number_pallets_with_stored_items_state_booking_in
 * @property int $number_pallets_state_booked_in
 * @property int $number_pallets_with_stored_items_state_booked_in
 * @property int $number_pallets_state_not_received
 * @property int $number_pallets_with_stored_items_state_not_received
 * @property int $number_pallets_state_storing
 * @property int $number_pallets_with_stored_items_state_storing
 * @property int $number_pallets_state_request_return_in_process
 * @property int $number_pallets_with_stored_items_state_request_return
 * @property int $number_pallets_state_picking
 * @property int $number_pallets_with_stored_items_state_picking
 * @property int $number_pallets_state_picked
 * @property int $number_pallets_with_stored_items_state_picked
 * @property int $number_pallets_state_damaged
 * @property int $number_pallets_with_stored_items_state_damaged
 * @property int $number_pallets_state_lost
 * @property int $number_pallets_with_stored_items_state_lost
 * @property int $number_pallets_state_other_incident
 * @property int $number_pallets_with_stored_items_state_other_incident
 * @property int $number_pallets_state_dispatched
 * @property int $number_pallets_with_stored_items_state_dispatched
 * @property int $number_pallets_status_in_process
 * @property int $number_pallets_with_stored_items_status_in_process
 * @property int $number_pallets_status_receiving
 * @property int $number_pallets_with_stored_items_status_receiving
 * @property int $number_pallets_status_not_received
 * @property int $number_pallets_with_stored_items_status_not_received
 * @property int $number_pallets_status_storing
 * @property int $number_pallets_with_stored_items_status_storing
 * @property int $number_pallets_status_returning
 * @property int $number_pallets_with_stored_items_status_returning
 * @property int $number_pallets_status_returned
 * @property int $number_pallets_with_stored_items_status_returned
 * @property int $number_pallets_status_incident
 * @property int $number_pallets_with_stored_items_status_incident
 * @property int $number_stored_items
 * @property int $number_stored_items_state_submitted
 * @property int $number_stored_items_state_in_process
 * @property int $number_stored_items_state_active
 * @property int $number_stored_items_state_discontinuing
 * @property int $number_stored_items_state_discontinued
 * @property int $number_pallet_deliveries
 * @property int $number_pallet_deliveries_state_in_process
 * @property int $number_pallet_deliveries_state_submitted
 * @property int $number_pallet_deliveries_state_confirmed
 * @property int $number_pallet_deliveries_state_received
 * @property int $number_pallet_deliveries_state_not_received
 * @property int $number_pallet_deliveries_state_booking_in
 * @property int $number_pallet_deliveries_state_booked_in
 * @property int $number_pallet_returns
 * @property int $number_pallet_returns_state_in_process
 * @property int $number_pallet_returns_state_submitted
 * @property int $number_pallet_returns_state_confirmed
 * @property int $number_pallet_returns_state_picking
 * @property int $number_pallet_returns_state_picked
 * @property int $number_pallet_returns_state_dispatched
 * @property int $number_pallet_returns_state_consolidated
 * @property int $number_pallet_returns_state_cancel
 * @property int $number_stored_item_audits
 * @property int $number_stored_item_audits_state_in_process
 * @property int $number_stored_item_audits_state_completed
 * @property int $number_recurring_bills
 * @property int $number_recurring_bills_status_current
 * @property int $number_recurring_bills_status_former
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property int $number_pallets_state_request_return_submitted
 * @property int $number_pallets_state_request_return_confirmed
 * @property bool $space_rental For customer renting spaces, e.g. storage, parking
 * @property int|null $previous_recurring_bill_id Safeguard in case consolidation of current bill fails
 * @property int $number_spaces
 * @property int $number_spaces_state_reserved
 * @property int $number_spaces_state_renting
 * @property int $number_spaces_state_finished
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read \App\Models\Fulfilment\RecurringBill|null $currentRecurringBill
 * @property-read Customer $customer
 * @property-read \App\Models\Fulfilment\Fulfilment $fulfilment
 * @property-read Group $group
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\PalletDelivery> $palletDeliveries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\PalletReturn> $palletReturns
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\Pallet> $pallets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\RecurringBill> $recurringBills
 * @property-read \App\Models\Fulfilment\RentalAgreement|null $rentalAgreement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\RentalAgreementClause> $rentalAgreementClauses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SerialReference> $serialReferences
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\Space> $spaces
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\StoredItemAudit> $storedItemAudits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\StoredItem> $storedItems
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\FulfilmentTransaction> $transactions
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FulfilmentCustomer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FulfilmentCustomer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FulfilmentCustomer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FulfilmentCustomer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FulfilmentCustomer withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FulfilmentCustomer withoutTrashed()
 * @mixin \Eloquent
 */
class FulfilmentCustomer extends Model implements HasMedia
{
    use SoftDeletes;
    use HasUniversalSearch;
    use HasSlug;
    use InFulfilment;
    use HasAttachments;
    use Notifiable;

    protected $guarded = [];
    protected $casts   = [
        'data'   => 'array',
        'status' => FulfilmentCustomerStatusEnum::class

    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->customer()->withTrashed()->first()->slug;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }


    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FulfilmentTransaction::class, 'fulfilment_customer_id');
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    public function storedItems(): HasMany
    {
        return $this->hasMany(StoredItem::class);
    }

    public function palletDeliveries(): HasMany
    {
        return $this->hasMany(PalletDelivery::class);
    }

    public function palletReturns(): HasMany
    {
        return $this->hasMany(PalletReturn::class);
    }

    public function recurringBills(): HasMany
    {
        return $this->hasMany(RecurringBill::class);
    }

    public function serialReferences(): MorphMany
    {
        return $this->morphMany(SerialReference::class, 'container');
    }

    public function rentalAgreement(): HasOne
    {
        return $this->hasOne(RentalAgreement::class);
    }

    public function rentalAgreementClauses(): HasMany
    {
        return $this->hasMany(RentalAgreementClause::class);
    }

    public function currentRecurringBill(): BelongsTo
    {
        return $this->belongsTo(RecurringBill::class, 'current_recurring_bill_id');
    }

    public function storedItemAudits(): HasMany
    {
        return $this->hasMany(StoredItemAudit::class);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }
}
