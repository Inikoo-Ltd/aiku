<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentPayment;

use App\Actions\Procurement\PurchaseOrder\SyncPurchaseOrderPaidFromAgentLedger;
use App\Actions\SupplyChain\AgentInvoice\SyncAgentInvoiceChargesFromLedger;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Brings an agent's Excel balance sheet for one client into the agent's ledger. Everything dated before the
 * cut-off becomes two opening lines (all money received, all money paid out), so the wallet totals equal the
 * Excel's; every row from the cut-off becomes its own line. A supplier payment is tied to the one order its
 * order number and product code point to; rows the agent has to look at again (no single order, an unclear expense)
 * come in flagged for review. The sheet dates a row on the day the goods reached the warehouse, so a row with no date
 * or a future date is goods still to arrive: it comes in with no date, flagged until the agent sets it. A row that
 * lost its date among rows from before the cut-off is history and goes into the opening lines with them. It only reads
 * the file unless told to apply, and replaces a client's earlier import only when asked and nothing of it was touched.
 */
class ImportAgentLedgerExcel
{
    use AsAction;

    public const string SOURCE = 'excel_import';

    public const string DATE_NOT_SET = 'Date not set yet: set the arrival date';

    public string $commandSignature = 'agent_ledger:import-excel
        {agent : slug of the agent}
        {client : slug of the client organisation}
        {file : path to the balance sheet (.xlsx)}
        {--from=2026-01-01 : rows before this date become the opening lines}
        {--order-reference=AWChina-#-SK : the client\'s agent order reference, # standing for the order number in the sheet}
        {--apply : write the lines; without it nothing is saved}
        {--replace : remove the client\'s earlier import first (refused if any of its lines was edited, removed or asked for)}';

    public string $commandDescription = 'Import an agent\'s Excel balance sheet for a client into the agent ledger (dry run unless --apply)';

    /**
     * @return array{rows: int, opening: array{received: float, paid: float}, lines: list<array<string, mixed>>, received: float, paid: float, balance: float, excel_balance: float|null, applied: bool}
     *
     * @throws Exception
     */
    public function handle(Agent $agent, Organisation $client, string $path, Carbon $from, string $orderReference, bool $apply = false, bool $replace = false): array
    {
        $imported = AgentPayment::withTrashed()->where('agent_id', $agent->id)->where('organisation_id', $client->id)->where('data->source', self::SOURCE)->whereNull('data->replaced_at');
        if ($apply && $imported->clone()->exists()) {
            if (!$replace) {
                throw new Exception("$client->code's balance sheet is already imported into this agent's ledger: --replace imports it again");
            }
            $touched = $imported->clone()->where(fn ($query) => $query->whereNotNull('deleted_at')
                ->orWhereColumn('updated_at', '>', 'created_at')
                ->orWhereExists(fn ($items) => $items->from('deposit_request_items')->whereColumn('deposit_request_items.agent_payment_id', 'agent_payments.id')))
                ->count();
            if ($touched) {
                throw new Exception("$touched line(s) of $client->code's earlier import were edited, removed or asked for in a money request since: not replacing it");
            }
        }

        $spreadsheet = IOFactory::load($path);
        $balance     = $spreadsheet->getSheetByName('Balance') ?? $spreadsheet->getSheet(0);
        $fund        = Arr::first($spreadsheet->getAllSheets(), fn (Worksheet $sheet) => str_ends_with(strtolower(trim($sheet->getTitle())), 'fund'));
        $transfers   = $this->transfersInClientCurrency($fund);
        $today       = Carbon::today();

        $opening  = ['received' => 0.0, 'paid' => 0.0];
        $lines    = [];
        $lastDate = null;
        $rows     = 0;

        for ($row = 7; $row <= $balance->getHighestDataRow(); $row++) {
            $moneyIn  = $this->amount($this->cell($balance, "B$row"));
            $moneyOut = $this->amount($this->cell($balance, "C$row"));
            if (!$moneyIn && !$moneyOut) {
                continue;
            }
            $rows++;

            $dateInExcel = $this->date($this->cell($balance, "A$row", false));
            $date        = $dateInExcel?->lte($today) ? $dateInExcel : null;
            $reason      = $date ? null : self::DATE_NOT_SET;
            $lastDate    = $date ?? $lastDate;
            $matchingOn  = $date ?? $lastDate ?? $from->copy()->subDay();

            if ($matchingOn->lt($from)) {
                $opening['received'] += $moneyIn;
                $opening['paid']     += $moneyOut;
                continue;
            }

            $description = trim((string) $this->cell($balance, "D$row", false));
            $code        = trim((string) $this->cell($balance, "I$row", false));
            $orderNumber = trim((string) $this->cell($balance, "J$row", false));

            $line = [
                'row'               => $row,
                'date'              => $date?->toDateString(),
                'amount'            => $moneyIn ?: $moneyOut,
                'type'              => null,
                'purchase_order_id' => null,
                'supplier_id'       => null,
                'stock_delivery_id' => null,
                'original_amount'   => null,
                'reference'         => trim((string) $this->cell($balance, "H$row", false)) ?: null,
                'notes'             => implode(' · ', array_filter([$description, $code, $orderNumber ? "PO $orderNumber" : null])),
                'review_reason'     => $reason,
                'data'              => array_filter(['source' => self::SOURCE, 'row' => $row, 'description' => $description, 'code' => $code, 'po' => $orderNumber, 'date_in_excel' => $dateInExcel?->toDateString()]),
            ];

            $chargeType = $this->expenseType($description);

            if ($moneyIn) {
                $line['type']            = AgentPaymentTypeEnum::TRANSFER_RECEIVED;
                $line['original_amount'] = $this->sentByClient($transfers, $moneyIn, $matchingOn);
            } elseif ($this->isOverhead($description)) {
                $line['type'] = AgentPaymentTypeEnum::OTHER_EXPENSE;
            } elseif ($chargeType === AgentPaymentTypeEnum::OTHER_EXPENSE && $this->amount($this->cell($balance, "F$row"))) {
                $line['type'] = AgentPaymentTypeEnum::PACKAGING;
            } elseif ($chargeType === AgentPaymentTypeEnum::OTHER_EXPENSE && str_contains(strtolower($description), 'shop')) {
                $line['type']   = AgentPaymentTypeEnum::SUPPLIER_PAYMENT;
                $purchaseOrder  = $this->purchaseOrder($agent, $client, $orderReference, $orderNumber, $code);
                if (!$purchaseOrder && ($purchaseOrder = $this->recentPurchaseOrderWithCode($agent, $client, $code, $matchingOn))) {
                    $line['data']['linked_by'] = 'code';
                }
                $line['purchase_order_id'] = $purchaseOrder?->id;
                $line['supplier_id']       = $purchaseOrder?->supplier_id;
                if (!$purchaseOrder) {
                    $line['review_reason'] ??= 'Not tied to one order';
                }
            } else {
                $line['type'] = $chargeType;
                if ($line['type'] === AgentPaymentTypeEnum::OTHER_EXPENSE) {
                    $line['review_reason'] ??= 'Expense type unclear';
                } else {
                    [$line['stock_delivery_id'], $candidates] = $this->container($agent, $client, $description);
                    if ($candidates > 1) {
                        $line['data']['container_candidates'] = $candidates;
                    }
                }
            }

            $lines[] = $line;
        }

        $openingLines = array_values(array_filter([
            $opening['received'] > 0 ? $this->openingLine(AgentPaymentTypeEnum::TRANSFER_RECEIVED, $opening['received'], $from) : null,
            $opening['paid'] > 0 ? $this->openingLine(AgentPaymentTypeEnum::OTHER_EXPENSE, $opening['paid'], $from) : null,
        ]));
        $lines = array_merge($openingLines, $lines);

        $received = round(array_sum(array_map(fn (array $line) => $line['type']->isMoneyIn() ? $line['amount'] : 0, $lines)), 2);
        $paid     = round(array_sum(array_map(fn (array $line) => $line['type']->isMoneyIn() ? 0 : $line['amount'], $lines)), 2);

        if ($apply) {
            $this->apply($agent, $client, $lines, $imported);
        }

        return [
            'rows'          => $rows,
            'opening'       => array_map(fn (float $amount) => round($amount, 2), $opening),
            'lines'         => $lines,
            'received'      => $received,
            'paid'          => $paid,
            'balance'       => round($received - $paid, 2),
            'excel_balance' => ($this->amount($this->cell($balance, 'D4')) ?: $this->amount($this->cell($balance, 'I1'))) ?: null,
            'applied'       => $apply,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  Builder<AgentPayment>  $imported
     */
    private function apply(Agent $agent, Organisation $client, array $lines, Builder $imported): void
    {
        $containers = $imported->clone()->whereNotNull('stock_delivery_id')->distinct()->pluck('stock_delivery_id')->all();
        $paidDatesBefore = SyncPurchaseOrderPaidFromAgentLedger::ledgerDatesFor(array_merge(
            $imported->clone()->whereNull('deleted_at')->whereNotNull('purchase_order_id')->distinct()->pluck('purchase_order_id')->all(),
            array_column($lines, 'purchase_order_id')
        ));

        DB::transaction(function () use ($agent, $client, $lines, $imported) {
            $imported->clone()->whereNull('deleted_at')->update(['deleted_at' => now(), 'data->replaced_at' => now()->toDateTimeString()]);

            foreach ($lines as $line) {
                AgentPayment::create([
                    'group_id'             => $agent->group_id,
                    'agent_id'             => $agent->id,
                    'organisation_id'      => $client->id,
                    'type'                 => $line['type'],
                    'date'                 => $line['date'],
                    'amount'               => round($line['amount'], 2),
                    'currency_id'          => $agent->organisation->currency_id,
                    'purchase_order_id'    => $line['purchase_order_id'],
                    'supplier_id'          => $line['supplier_id'],
                    'stock_delivery_id'    => $line['stock_delivery_id'],
                    'original_amount'      => $line['original_amount'],
                    'original_currency_id' => $line['original_amount'] ? $client->currency_id : null,
                    'reference'            => $line['reference'],
                    'notes'                => $line['notes'],
                    'needs_review'         => $line['review_reason'] !== null,
                    'review_reason'        => $line['review_reason'],
                    'data'                 => $line['data'],
                ]);
            }
        });

        StockDelivery::whereIn('id', array_filter(array_unique(array_merge($containers, array_column($lines, 'stock_delivery_id')))))
            ->get()
            ->each(fn (StockDelivery $stockDelivery) => SyncAgentInvoiceChargesFromLedger::run($stockDelivery));

        SyncPurchaseOrderPaidFromAgentLedger::runForOrders($paidDatesBefore);
    }

    /**
     * @return array<string, mixed>
     */
    private function openingLine(AgentPaymentTypeEnum $type, float $amount, Carbon $from): array
    {
        return [
            'row'               => null,
            'date'              => $from->copy()->subDay()->toDateString(),
            'amount'            => round($amount, 2),
            'type'              => $type,
            'purchase_order_id' => null,
            'supplier_id'       => null,
            'stock_delivery_id' => null,
            'original_amount'   => null,
            'reference'         => null,
            'notes'             => $type->isMoneyIn()
                ? "Received before {$from->toDateString()}, from the Excel balance sheet"
                : "Paid out before {$from->toDateString()}, from the Excel balance sheet",
            'review_reason'     => null,
            'data'              => ['source' => self::SOURCE, 'opening' => true],
        ];
    }

    private function expenseType(string $description): AgentPaymentTypeEnum
    {
        $description = strtolower($description);

        return match (true) {
            (bool) preg_match('/\binspection/', $description) => AgentPaymentTypeEnum::INSPECTION,
            (bool) preg_match('/\b(mou?ld|logo)/', $description) => AgentPaymentTypeEnum::MOULD_OR_LOGO,
            str_contains($description, 'container value') => AgentPaymentTypeEnum::AGENT_FEE,
            str_contains($description, 'loading')         => AgentPaymentTypeEnum::CONTAINER_LOADING,
            str_contains($description, 'inland')          => AgentPaymentTypeEnum::INLAND_CHARGE,
            default                                       => AgentPaymentTypeEnum::OTHER_EXPENSE,
        };
    }

    /**
     * The agent's own running costs charged to the client (power, the car, wages, rent, internet, tax, phone): an
     * Other expense whatever columns the row fills, and nothing to review. "CAR" after a product code is that
     * product's carton ("AWPSP CAR"), so only a row that starts with it, or names the car's rental or insurance, is
     * the car.
     */
    private function isOverhead(string $description): bool
    {
        return (bool) preg_match('/^\s*car\b|\bcar\s+(rental|insurance|repair|fuel|petrol)|\belectricity\b|\belectric\s+(charges?|bills?|fees?)\b|\b(wages?|salary|salaries|rent|internet|tax|phone|telephone)\b/i', $description);
    }

    private function purchaseOrder(Agent $agent, Organisation $client, string $orderReference, string $orderNumber, string $code): ?PurchaseOrder
    {
        if ($code === '' || !preg_match('/^\d+(\+\d+)*$/', $orderNumber)) {
            return null;
        }

        $references = array_map(fn (string $number) => strtolower(str_replace('#', $number, $orderReference)), explode('+', $orderNumber));

        $purchaseOrders = PurchaseOrder::where('organisation_id', $client->id)
            ->where('agent_id', $agent->id)
            ->where(function ($query) use ($references) {
                $query->whereIn(DB::raw('lower(reference)'), $references)->orWhereIn(DB::raw('lower(agent_order_reference)'), $references);
                foreach ($references as $reference) {
                    $query->orWhere(DB::raw('lower(reference)'), 'like', addcslashes($reference, '%_\\').' %');
                }
            })
            ->whereHas('purchaseOrderTransactions.orgStock', fn ($query) => $query->where('code', 'ILIKE', addcslashes($code, '%_\\').'%'))
            ->limit(2)
            ->get(['id', 'supplier_id']);

        return $purchaseOrders->count() === 1 ? $purchaseOrders->first() : null;
    }

    /**
     * Orders placed before the sheet's order numbers reached Aiku carry container references, so the number can not
     * find them: the one order of the client through this agent from the half year before the payment with a line
     * for the product stands in.
     */
    private function recentPurchaseOrderWithCode(Agent $agent, Organisation $client, string $code, Carbon $paidOn): ?PurchaseOrder
    {
        if ($code === '') {
            return null;
        }

        $purchaseOrders = PurchaseOrder::where('organisation_id', $client->id)
            ->where('agent_id', $agent->id)
            ->trackedThroughAgent()
            ->whereBetween('date', [$paidOn->copy()->subDays(180), $paidOn->copy()->endOfDay()])
            ->whereHas('purchaseOrderTransactions.orgStock', fn ($query) => $query->where('code', 'ILIKE', addcslashes($code, '%_\\').'%'))
            ->limit(2)
            ->get(['id', 'supplier_id']);

        return $purchaseOrders->count() === 1 ? $purchaseOrders->first() : null;
    }

    /**
     * The container the description's trailing number names, with how many deliveries carry that number. Extra
     * deliveries share the number (AWChina-86-FGDB, 8 lines, next to AWChina-86-VAL260152, 141): a delivery with at
     * least twice the lines of any other is the container itself. Two of a similar size (an old container that reused
     * the number) are left for the agent to choose.
     *
     * @return array{0: int|null, 1: int}
     */
    private function container(Agent $agent, Organisation $client, string $description): array
    {
        if (!preg_match('/(\d+)\s*$/', $description, $matches)) {
            return [null, 0];
        }

        $candidates = StockDelivery::where('organisation_id', $client->id)
            ->where('agent_id', $agent->id)
            ->where('state', '!=', StockDeliveryStateEnum::CANCELLED)
            ->where('reference', '~*', '^awchina-0*'.(int) $matches[1].'([-_ ]|$)')
            ->orderByDesc('number_stock_delivery_items')
            ->get(['id', 'number_stock_delivery_items']);

        $main = $candidates->first();
        if (!$main || ($candidates->count() > 1 && $main->number_stock_delivery_items < 2 * $candidates->get(1)->number_stock_delivery_items)) {
            return [null, $candidates->count()];
        }

        return [$main->id, $candidates->count()];
    }

    /**
     * @return list<array{date: Carbon, amount: float, received: float}>
     */
    private function transfersInClientCurrency(?Worksheet $fund): array
    {
        if (!$fund) {
            return [];
        }

        $transfers = [];
        for ($row = 5; $row <= $fund->getHighestDataRow(); $row++) {
            $date     = $this->date($this->cell($fund, "A$row", false));
            $amount   = $this->amount($this->cell($fund, "B$row"));
            $received = $this->amount($this->cell($fund, "F$row"));
            if ($date && $amount && $received) {
                $transfers[] = ['date' => $date, 'amount' => $amount, 'received' => $received];
            }
        }

        return $transfers;
    }

    /**
     * @param  list<array{date: Carbon, amount: float, received: float}>  $transfers
     */
    private function sentByClient(array $transfers, float $received, Carbon $date): ?float
    {
        $match = Arr::first($transfers, fn (array $transfer) => abs($transfer['received'] - $received) < 0.01 && $transfer['date']->diffInDays($date, true) <= 15);

        return $match ? round($match['amount'], 2) : null;
    }

    /**
     * Reads a cell only when the sheet has it: asking for a missing one creates it, which crawls on a sheet with a
     * stray value far down.
     */
    private function cell(Worksheet $sheet, string $coordinate, bool $calculated = true): mixed
    {
        if (!$sheet->cellExists($coordinate)) {
            return null;
        }

        $cell = $sheet->getCell($coordinate);

        return $calculated ? $cell->getCalculatedValue() : $cell->getValue();
    }

    private function amount(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = str_replace(['￥', '¥', ',', ' '], '', (string) $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function date(mixed $value): ?Carbon
    {
        if (is_numeric($value) && $value > 20000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->startOfDay();
        }

        $value = trim((string) $value);
        foreach (['j-M-y', 'j-M-Y', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date) {
                    return $date->startOfDay();
                }
            } catch (Exception) {
            }
        }

        return null;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $agent  = Agent::where('slug', $command->argument('agent'))->firstOrFail();
        $client = Organisation::where('slug', $command->argument('client'))->firstOrFail();
        $apply  = (bool) $command->option('apply');

        try {
            $result = $this->handle($agent, $client, $command->argument('file'), Carbon::parse($command->option('from')), $command->option('order-reference'), $apply, (bool) $command->option('replace'));
        } catch (Exception $exception) {
            $command->error($exception->getMessage());

            return 1;
        }

        $lines  = collect($result['lines']);
        $review = $lines->whereNotNull('review_reason');

        $command->info(($apply ? 'Imported' : 'Dry run, nothing saved').": $client->code with $agent->code, {$result['rows']} money rows in the sheet");
        $command->line('Before '.$command->option('from').' (and rows with no date among them): received '.number_format($result['opening']['received'], 2).', paid '.number_format($result['opening']['paid'], 2).' (two opening lines)');
        $command->table(['Type', 'Lines', 'Amount'], $lines->groupBy(fn (array $line) => $line['type']->value)
            ->map(fn ($group, $type) => [$type, $group->count(), number_format($group->sum('amount'), 2)])->values()->all());
        $command->line('Tied to an order: '.$lines->whereNotNull('purchase_order_id')->count().', to a container: '.$lines->whereNotNull('stock_delivery_id')->count()
            .' ('.$lines->whereNotNull('stock_delivery_id')->filter(fn (array $line) => isset($line['data']['container_candidates']))->count().' picked as the biggest of several deliveries with its number, '
            .$lines->whereNull('stock_delivery_id')->filter(fn (array $line) => isset($line['data']['container_candidates']))->count().' left unlinked between similar ones)'.', transfers with the amount the client sent: '.$lines->whereNotNull('original_amount')->count());
        $command->line('Received '.number_format($result['received'], 2).', paid out '.number_format($result['paid'], 2).', balance '.number_format($result['balance'], 2)
            .($result['excel_balance'] !== null ? ' (Excel says '.number_format($result['excel_balance'], 2).')' : ''));
        $command->line('For the agent to review: '.$review->count());
        $command->table(['Row', 'Date', 'Type', 'Amount', 'Notes', 'Reason'], $review->map(fn (array $line) => [
            $line['row'], $line['date'], $line['type']->value, number_format($line['amount'], 2), mb_strimwidth($line['notes'], 0, 50, '…'), $line['review_reason'],
        ])->values()->all());

        return 0;
    }
}
