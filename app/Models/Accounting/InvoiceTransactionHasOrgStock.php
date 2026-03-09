<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_transaction_id
 * @property int $org_stock_id
 * @property int|null $org_stock_family_id
 * @property numeric $net_amount
 * @property numeric $org_net_amount
 * @property numeric $grp_net_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Accounting\InvoiceTransaction $invoiceTransaction
 * @property-read OrgStock $orgStock
 * @property-read OrgStockFamily|null $orgStockFamily
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasOrgStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasOrgStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasOrgStock query()
 * @mixin \Eloquent
 */
class InvoiceTransactionHasOrgStock extends Model
{
    protected $table = 'invoice_transaction_has_org_stocks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'net_amount'     => 'decimal:2',
            'org_net_amount' => 'decimal:2',
            'grp_net_amount' => 'decimal:2',
        ];
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
}
