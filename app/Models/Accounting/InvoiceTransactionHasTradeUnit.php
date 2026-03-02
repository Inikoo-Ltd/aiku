<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
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
 * @property int $trade_unit_id
 * @property int|null $trade_unit_family_id
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
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read InvoiceTransaction $invoiceTransaction
 * @property-read TradeUnit $tradeUnit
 * @property-read TradeUnitFamily|null $tradeUnitFamily
 * @property-read Customer|null $customer
 * @property-read Order|null $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasTradeUnit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasTradeUnit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasTradeUnit query()
 * @mixin \Eloquent
 */
class InvoiceTransactionHasTradeUnit extends Model
{
    use InGroup;

    protected $table = 'invoice_transaction_has_trade_units';

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

    public function tradeUnit(): BelongsTo
    {
        return $this->belongsTo(TradeUnit::class);
    }

    public function tradeUnitFamily(): BelongsTo
    {
        return $this->belongsTo(TradeUnitFamily::class);
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
