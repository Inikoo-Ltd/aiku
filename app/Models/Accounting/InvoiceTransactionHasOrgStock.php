<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Models\CRM\Customer;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $invoice_transaction_id
 * @property int $org_stock_id
 * @property int|null $org_stock_family_id
 * @property int|null $customer_id
 * @property int|null $order_id
 * @property numeric $net_amount
 * @property numeric $org_net_amount
 * @property numeric $grp_net_amount
 * @property string|null $type
 * @property \Illuminate\Support\Carbon $date
 * @property bool $in_process
 * @property bool $is_refund
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Group $group
 * @property-read \App\Models\Accounting\InvoiceTransaction $invoiceTransaction
 * @property-read Order|null $order
 * @property-read OrgStock $orgStock
 * @property-read OrgStockFamily|null $orgStockFamily
 * @property-read Organisation $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasOrgStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasOrgStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasOrgStock query()
 * @mixin \Eloquent
 */
class InvoiceTransactionHasOrgStock extends Model
{
    use InGroup;

    protected $table = 'invoice_transaction_has_org_stocks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'           => 'datetime',
            'in_process'     => 'boolean',
            'is_refund'      => 'boolean',
            'net_amount'     => 'decimal:2',
            'org_net_amount' => 'decimal:2',
            'grp_net_amount' => 'decimal:2',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function invoiceTransaction(): BelongsTo
    {
        return $this->belongsTo(InvoiceTransaction::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function orgStockFamily(): BelongsTo
    {
        return $this->belongsTo(OrgStockFamily::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
