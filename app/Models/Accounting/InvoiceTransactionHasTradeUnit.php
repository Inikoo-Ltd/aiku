<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_transaction_id
 * @property int $trade_unit_id
 * @property int|null $trade_unit_family_id
 * @property numeric $net_amount
 * @property numeric $org_net_amount
 * @property numeric $grp_net_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Accounting\InvoiceTransaction $invoiceTransaction
 * @property-read TradeUnit $tradeUnit
 * @property-read TradeUnitFamily|null $tradeUnitFamily
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasTradeUnit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasTradeUnit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasTradeUnit query()
 * @mixin \Eloquent
 */
class InvoiceTransactionHasTradeUnit extends Model
{
    protected $table = 'invoice_transaction_has_trade_units';

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

    public function tradeUnit(): BelongsTo
    {
        return $this->belongsTo(TradeUnit::class);
    }

    public function tradeUnitFamily(): BelongsTo
    {
        return $this->belongsTo(TradeUnitFamily::class);
    }
}
