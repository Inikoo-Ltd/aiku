<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankPayee;

use App\Actions\Accounting\BankTransaction\MatchBankTransactions;
use App\Enums\Accounting\BankPayee\BankPayeeCategoryEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\BankPayee;
use App\Models\Accounting\BankTransaction;
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Order;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Labels every line the group's banks paid out with what it was for. A transfer, card payment or direct debit
 * follows its payee: the category a person chose for that name, else the supplier, agent, company of ours or
 * employee of the same name, else services for card payments and direct debits, else it waits for a person. The
 * bank's own charges are bank fees and its other movements are other. A transfer naming one of our order numbers
 * is a customer refund, whoever the payee. A label only: no payment or invoice is touched.
 */
class ClassifyBankOutgoing
{
    use AsAction;

    public const array PAYEE_TYPES = ['TRANSFER', 'CARD_PAYMENT', 'DIRECT_DEBIT_TRANSACTION'];

    public const array SERVICE_TYPES = ['CARD_PAYMENT', 'DIRECT_DEBIT_TRANSACTION'];

    public const array FEE_TYPES = ['BALANCE_ASSET_FEE', 'BALANCE_HOLD_FEE', 'CARD_ORDER', 'BANK_DETAILS_ORDER'];

    public const string OWN_BALANCE_TYPE = 'INTERBALANCE';

    /** PayPal refunds (T1107) and reversals (T1106) of customer payments: already refund invoices, never a cost. */
    public const array CUSTOMER_REFUND_TYPES = ['T1107', 'T1106'];

    public const string NEEDS_PERSON = 'needs_person';

    public string $commandSignature = 'bank-transactions:classify-outgoing';

    /**
     * @return array<string, int> lines per category
     */
    public function handle(Group $group): array
    {
        $this->keyPayees($group);
        $this->linkRefundedOrders($group);
        $this->guessPayees($group);

        $inList = fn (array $types) => "'".implode("', '", $types)."'";
        $label  = 'case
            when bank_transactions.provider_type in ('.$inList(self::FEE_TYPES).") then '".BankPayeeCategoryEnum::BANK_FEES->value."'
            when bank_transactions.provider_type = 'TRANSFER' and bank_transactions.order_id is not null then '".BankPayeeCategoryEnum::CUSTOMER_REFUNDS->value."'
            when bank_transactions.provider_type in (".$inList(self::CUSTOMER_REFUND_TYPES).") then '".BankPayeeCategoryEnum::CUSTOMER_REFUNDS->value."'
            when bank_transactions.provider_type is null or bank_transactions.provider_type not in (".$inList(self::PAYEE_TYPES).") then '".BankPayeeCategoryEnum::OTHER->value."'
            else coalesce(
                (select bank_payees.category from bank_payees where bank_payees.group_id = bank_transactions.group_id and bank_payees.key = bank_transactions.payee_key),
                case when bank_transactions.provider_type in (".$inList(self::SERVICE_TYPES).") then '".BankPayeeCategoryEnum::SERVICES->value."' else '".self::NEEDS_PERSON."' end
            ) end";

        DB::update(
            "update bank_transactions set out_category = $label where group_id = ? and direction = ? and out_category is distinct from ($label)",
            [$group->id, BankTransactionDirectionEnum::OUT->value]
        );

        return BankTransaction::where('group_id', $group->id)
            ->where('direction', BankTransactionDirectionEnum::OUT)
            ->selectRaw('out_category, count(*) as lines')
            ->groupBy('out_category')
            ->pluck('lines', 'out_category')
            ->all();
    }

    /**
     * A transfer whose reference names an order of its organisation is money sent back to that customer. Each
     * transfer is read once; the order stays linked to the line.
     */
    private function linkRefundedOrders(Group $group): void
    {
        $matcher = MatchBankTransactions::make();

        BankTransaction::where('group_id', $group->id)
            ->where('direction', BankTransactionDirectionEnum::OUT)
            ->where('provider_type', 'TRANSFER')
            ->whereNull('order_id')
            ->whereNull('match_data->refund_read')
            ->select(['id', 'organisation_id', 'reference', 'match_data'])
            ->chunkById(500, function ($bankTransactions) use ($matcher) {
                foreach ($bankTransactions as $bankTransaction) {
                    $tokens  = $matcher->orderReferenceTokens($bankTransaction->reference);
                    $orderId = $tokens ? Order::where('organisation_id', $bankTransaction->organisation_id)->whereIn('reference', $tokens)->orderBy('id')->value('id') : null;
                    $bankTransaction->update(['order_id' => $orderId, 'match_data' => array_merge($bankTransaction->match_data, ['refund_read' => true])]);
                }
            });
    }

    private function keyPayees(Group $group): void
    {
        BankTransaction::where('group_id', $group->id)
            ->where('direction', BankTransactionDirectionEnum::OUT)
            ->whereIn('provider_type', self::PAYEE_TYPES)
            ->select('counterparty_name', 'payee_key')
            ->distinct()
            ->get()
            ->each(function (BankTransaction $line) use ($group) {
                $key = BankPayee::normalise($line->counterparty_name);
                if ($key === $line->payee_key) {
                    return;
                }
                BankTransaction::where('group_id', $group->id)
                    ->where('direction', BankTransactionDirectionEnum::OUT)
                    ->whereIn('provider_type', self::PAYEE_TYPES)
                    ->where(fn ($query) => $line->counterparty_name === null ? $query->whereNull('counterparty_name') : $query->where('counterparty_name', $line->counterparty_name))
                    ->update(['payee_key' => $key]);
            });
    }

    /**
     * A payee nobody classified yet is who Aiku already knows by that name: a company of the group first, else the
     * supplier, agent or employee when the name means one thing only.
     */
    private function guessPayees(Group $group): void
    {
        $payees = BankTransaction::where('bank_transactions.group_id', $group->id)
            ->where('direction', BankTransactionDirectionEnum::OUT)
            ->whereNotNull('payee_key')
            ->whereNotExists(fn ($query) => $query->from('bank_payees')->whereColumn('bank_payees.group_id', 'bank_transactions.group_id')->whereColumn('bank_payees.key', 'bank_transactions.payee_key'))
            ->selectRaw('payee_key, min(counterparty_name) as name')
            ->groupBy('payee_key')
            ->pluck('name', 'payee_key');
        if ($payees->isEmpty()) {
            return;
        }

        $known = [];
        $knows = function (?string $name, BankPayeeCategoryEnum $category, string $modelType, int $modelId) use (&$known) {
            $key = BankPayee::normalise($name);
            if ($key !== null) {
                $known[$key][$category->value] ??= ['category' => $category, 'model_type' => $modelType, 'model_id' => $modelId];
            }
        };

        Organisation::where('group_id', $group->id)->get(['id', 'name', 'type'])->each(fn (Organisation $organisation) => $knows(
            $organisation->name,
            $organisation->type === OrganisationTypeEnum::AGENT ? BankPayeeCategoryEnum::SUPPLIERS : BankPayeeCategoryEnum::OWN_COMPANIES,
            'Organisation',
            $organisation->id
        ));
        Agent::where('group_id', $group->id)->get(['id', 'name'])->each(fn (Agent $agent) => $knows($agent->name, BankPayeeCategoryEnum::SUPPLIERS, 'Agent', $agent->id));
        Supplier::where('group_id', $group->id)->get(['id', 'name', 'company_name'])->each(function (Supplier $supplier) use ($knows) {
            $knows($supplier->name, BankPayeeCategoryEnum::SUPPLIERS, 'Supplier', $supplier->id);
            $knows($supplier->company_name, BankPayeeCategoryEnum::SUPPLIERS, 'Supplier', $supplier->id);
        });
        Employee::where('group_id', $group->id)->get(['id', 'contact_name'])->each(fn (Employee $employee) => $knows($employee->contact_name, BankPayeeCategoryEnum::STAFF, 'Employee', $employee->id));

        foreach ($payees as $key => $name) {
            $matches = $known[$key] ?? [];
            $ours    = collect($matches)->firstWhere('model_type', 'Organisation');
            if (!$ours && count($matches) !== 1) {
                continue;
            }
            $guess = $ours ?? reset($matches);
            BankPayee::firstOrCreate(
                ['group_id' => $group->id, 'key' => $key],
                ['name' => $name, 'category' => $guess['category'], 'model_type' => $guess['model_type'], 'model_id' => $guess['model_id'], 'source' => 'auto']
            );
        }
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        Group::all()->each(function (Group $group) use ($command) {
            $command->info($group->name);
            $command->table(['Category', 'Lines'], collect($this->handle($group))->map(fn (int $lines, string $category) => [$category ?: '-', $lines])->values()->all());
        });

        return 0;
    }
}
