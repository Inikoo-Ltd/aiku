<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Accounting\Invoice;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A truck to a partner, the consolidated delivery note (HELP-4482): packed or finalised delivery
 * notes put on it in Dispatching, then its customs invoices (one per split: cosmetic, GB origin,
 * rest) once every note is invoiced, then dispatched together.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $org_partner_id
 * @property string|null $reference AROES-T0001...
 * @property int|null $warehouse_id
 * @property \Illuminate\Support\Carbon|null $customs_invoiced_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon $date
 * @property int|null $number_pallets
 * @property \Illuminate\Support\Carbon|null $voided_at a voided truck keeps its numbers and lines; its invoices and notes are free for another truck
 * @property int|null $voided_by_user_id
 * @property string|null $void_reason
 * @property array<string, mixed> $data customs header as issued with the customs invoices: shipper, consignee, currency, delivery note and invoice references
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryNote> $deliveryNotes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices
 * @property-read OrgPartner $orgPartner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PartnerCustomsInvoice> $customsInvoices
 * @mixin \Eloquent
 */
class PartnerShipment extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'      => 'date',
            'data'      => 'array',
            'customs_invoiced_at' => 'datetime',
            'dispatched_at'       => 'datetime',
            'voided_at'           => 'datetime',
        ];
    }

    /**
     * Dispatching sees consolidations, and so do customer services (orders view in any shop of the
     * organisation), read only: pages and downloads, never make, dispatch or void.
     */
    public static function canBeSeenBy(User $user, Organisation $organisation): bool
    {
        return $user->authTo([
            ...WarehousePermissionsEnum::getDispatchingViewPermissionNames($organisation),
            ...$organisation->shops()->pluck('id')->map(fn ($shopId) => "orders.$shopId.view")->all(),
        ]);
    }

    /**
     * A delivery note cancelled or deleted comes off a consolidation still open (no customs invoices
     * yet). Once its customs invoices are made it stays, and Dispatch all names it instead.
     */
    public static function releaseDeliveryNote(int $deliveryNoteId): void
    {
        DB::table('partner_shipment_delivery_notes')
            ->where('delivery_note_id', $deliveryNoteId)
            ->whereIn('partner_shipment_id', PartnerShipment::whereNull('customs_invoiced_at')->select('id'))
            ->delete();
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orgPartner(): BelongsTo
    {
        return $this->belongsTo(OrgPartner::class);
    }

    public function deliveryNotes(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryNote::class, 'partner_shipment_delivery_notes');
    }

    /**
     * The shop invoices staff put on the truck; a void truck lets go of them and keeps their
     * references in data.
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'partner_shipment_invoices');
    }

    public function customsInvoices(): HasMany
    {
        return $this->hasMany(PartnerCustomsInvoice::class)->orderBy('id');
    }
}
