<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionMatchStateEnum;
use App\Models\Ordering\Order;
use App\Enums\Accounting\BankTransaction\BankTransactionStatusEnum;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * One line of a bank account as the bank reports it, whichever bank it comes from.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $bank_connection_id
 * @property string $provider_transaction_id
 * @property string|null $provider_type
 * @property BankTransactionDirectionEnum $direction
 * @property string $amount
 * @property int $currency_id
 * @property BankTransactionStatusEnum $status
 * @property string|null $account_reference
 * @property string|null $counterparty_name
 * @property string|null $counterparty_account
 * @property string|null $reference
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $booked_at
 * @property \Illuminate\Support\Carbon|null $provider_updated_at
 * @property array<string, mixed> $data
 * @property BankTransactionMatchStateEnum|null $match_state
 * @property int|null $order_id
 * @property int|null $payment_id
 * @property string|null $fee_amount the bank's own fee on this line, in its currency
 * @property int|null $bank_fee_payment_id the absorbed customer-side fee recorded with the payment
 * @property array<string, mixed> $match_data
 * @property \Illuminate\Support\Carbon|null $matched_at
 * @property string|null $payee_key the payee's normalised name, on lines paid out to a payee
 * @property string|null $out_category what a line paid out was for: a BankPayeeCategoryEnum value or needs_person
 * @property array{customer_id: int, customer_reference: string, customer_name: string|null, shop_id: int, shop_code: string, note: string|null, user_id: int, by: string, at: string}|null $staff_hint the customer shop staff say sent this money, kept apart from match_data so syncing and matching never wipe it
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read BankConnection $bankConnection
 * @property-read Currency $currency
 * @property-read Organisation $organisation
 * @mixin \Eloquent
 */
class BankTransaction extends Model implements AuditableContract
{
    use Auditable;

    protected $guarded = [];

    /**
     * Syncing and matching rewrite lines all day without a person: only the decisions staff take are audited, through
     * recordDecision.
     *
     * @var list<string>
     */
    protected $auditEvents = [];

    protected $attributes = [
        'data'       => '{}',
        'match_data' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'direction'           => BankTransactionDirectionEnum::class,
            'status'              => BankTransactionStatusEnum::class,
            'data'                => 'array',
            'booked_at'           => 'datetime',
            'provider_updated_at' => 'datetime',
            'match_state'         => BankTransactionMatchStateEnum::class,
            'match_data'          => 'array',
            'matched_at'          => 'datetime',
            'staff_hint'          => 'array',
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    public function recordDecision(string $event, array $values): void
    {
        $this->auditEvent     = $event;
        $this->isCustomEvent  = true;
        $this->auditCustomOld = [];
        $this->auditCustomNew = $values;
        Event::dispatch(new AuditCustom($this));
        $this->isCustomEvent  = false;
        $this->auditCustomNew = [];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * A marketplace payout names its order in order_id, or, when it settles several (an Orderchamp settlement),
     * all of them in match_data.order_ids.
     */
    public static function paidByMarketplaceFor(int $orderId): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('match_state', BankTransactionMatchStateEnum::PAID_BY_MARKETPLACE)
            ->where(fn ($query) => $query->where('order_id', $orderId)->orWhereRaw("match_data->'order_ids' @> to_jsonb(?::bigint)", [$orderId]));
    }

    /**
     * @return list<int>
     */
    public function settledOrderIds(): array
    {
        return array_values(array_unique(array_filter([$this->order_id, ...($this->match_data['order_ids'] ?? [])])));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function bankFeePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'bank_fee_payment_id');
    }

    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
