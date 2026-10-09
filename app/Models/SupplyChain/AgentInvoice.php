<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The invoice an agent issues to one of our organisations for a container: the goods, made by the system from the
 * container's lines, and the charges the agent adds on top (commission, packing, local freight), which are its margin.
 * The goods lines are a copy taken when it is made, so it keeps saying what was invoiced after the container moves on.
 *
 * @property int $id
 * @property int $group_id
 * @property int $agent_id
 * @property int $organisation_id
 * @property int $stock_delivery_id
 * @property int|null $number
 * @property StockDeliveryInvoiceSourceEnum $source
 * @property string $reference
 * @property \Illuminate\Support\Carbon $date
 * @property int $currency_id
 * @property int $number_lines
 * @property string $goods_amount
 * @property string $charges_amount
 * @property string $total_amount
 * @property array<int, array{stock_delivery_item_id: int, org_stock_id: int|null, code: string|null, name: string|null, quantity: float, unit_price: float, amount: float}> $lines
 * @property array<int, array{description: string, type: string, amount: float}> $charges
 * @property array<string, mixed> $data
 */
class AgentInvoice extends Model
{
    use InGroup;

    protected $guarded = [];

    private ?array $advancePaymentsCache = null;

    protected function casts(): array
    {
        return [
            'date'    => 'date',
            'source'  => StockDeliveryInvoiceSourceEnum::class,
            'lines'   => 'array',
            'charges' => 'array',
            'data'    => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * What has been paid towards this container before its balance: the deposits applied to it and the payments made
     * to the agent for it directly.
     *
     * @return array<int, array{type: string, reference: string|null, date: string|null, amount: float}>
     */
    public function advancePayments(): array
    {
        if ($this->advancePaymentsCache !== null) {
            return $this->advancePaymentsCache;
        }

        $deposits = $this->stockDelivery->depositApplications()
            ->with('aspoDeposit:id,reference')
            ->get()
            ->map(fn ($application) => [
                'type'      => 'deposit',
                'reference' => $application->aspoDeposit?->reference,
                'date'      => $application->created_at?->toDateString(),
                'amount'    => (float) $application->amount,
            ]);

        $payments = $this->stockDelivery->agentPayments()
            ->orderBy('date')
            ->get()
            ->map(fn (AgentPayment $payment) => [
                'type'      => 'payment',
                'reference' => $payment->reference,
                'date'      => $payment->date->toDateString(),
                'amount'    => (float) $payment->amount,
            ]);

        return $this->advancePaymentsCache = $deposits->concat($payments)->values()->all();
    }

    public function paidAmount(): float
    {
        return round(array_sum(array_column($this->advancePayments(), 'amount')), 2);
    }

    public function balanceDue(): float
    {
        return round((float) $this->total_amount - $this->paidAmount(), 2);
    }
}
