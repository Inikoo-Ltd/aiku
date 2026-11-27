<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Models\Procurement;

use App\Enums\Procurement\SupplierOrderProposal\SupplierOrderProposalStateEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPortalContact;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line a supplier proposes from its portal for a product an organisation is out of, or will run out of before a
 * new order can arrive. The organisation's buyer accepts it onto the draft purchase order, rejects it with a reason
 * the supplier reads, or leaves it waiting.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $supplier_id
 * @property int $org_supplier_id
 * @property int $org_supplier_product_id
 * @property int $supplier_product_id
 * @property int|null $supplier_share_link_id
 * @property int|null $supplier_portal_contact_id
 * @property SupplierOrderProposalStateEnum $state
 * @property string|null $bucket
 * @property numeric $suggested_quantity_units
 * @property numeric $quantity_units
 * @property numeric|null $accepted_quantity_units
 * @property numeric $unit_cost
 * @property string|null $supplier_note
 * @property int|null $decided_by_user_id
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property string|null $reject_reason
 * @property int|null $purchase_order_id
 * @property int|null $purchase_order_transaction_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Supplier $supplier
 * @property-read OrgSupplier $orgSupplier
 * @property-read OrgSupplierProduct $orgSupplierProduct
 * @property-read SupplierProduct $supplierProduct
 * @property-read SupplierPortalContact|null $supplierPortalContact
 * @property-read User|null $decidedBy
 * @property-read PurchaseOrder|null $purchaseOrder
 * @mixin \Eloquent
 */
class SupplierOrderProposal extends Model
{
    use InOrganisation;

    protected $table = 'supplier_order_proposals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state'                    => SupplierOrderProposalStateEnum::class,
            'suggested_quantity_units' => 'decimal:3',
            'quantity_units'           => 'decimal:3',
            'accepted_quantity_units'  => 'decimal:3',
            'unit_cost'                => 'decimal:4',
            'decided_at'               => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function orgSupplier(): BelongsTo
    {
        return $this->belongsTo(OrgSupplier::class);
    }

    public function orgSupplierProduct(): BelongsTo
    {
        return $this->belongsTo(OrgSupplierProduct::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function supplierPortalContact(): BelongsTo
    {
        return $this->belongsTo(SupplierPortalContact::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
