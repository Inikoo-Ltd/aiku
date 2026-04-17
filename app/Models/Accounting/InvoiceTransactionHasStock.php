<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_transaction_id
 * @property int $stock_id
 * @property int|null $stock_family_id
 * @property numeric $net_amount
 * @property numeric $org_net_amount
 * @property numeric $grp_net_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Accounting\InvoiceTransaction|null $invoiceTransaction
 * @property-read Stock|null $stock
 * @property-read StockFamily|null $stockFamily
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasStock query()
 * @mixin \Eloquent
 */
class InvoiceTransactionHasStock extends Model
{
    protected $table = 'invoice_transaction_has_stocks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'net_amount' => 'decimal:2',
            'org_net_amount' => 'decimal:2',
            'grp_net_amount' => 'decimal:2',
        ];
    }

    public function invoiceTransaction(): BelongsTo
    {
        return $this->belongsTo(InvoiceTransaction::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function stockFamily(): BelongsTo
    {
        return $this->belongsTo(StockFamily::class);
    }
}
