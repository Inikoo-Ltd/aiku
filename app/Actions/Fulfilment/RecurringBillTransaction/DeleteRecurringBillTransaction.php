<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Jan 2025 23:52:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBillTransaction;

use App\Actions\Fulfilment\RecurringBill\CalculateRecurringBillTotals;
use App\Actions\Fulfilment\RecurringBill\Hydrators\RecurringBillHydrateTransactions;
use App\Actions\OrgAction;
use App\Models\Fulfilment\RecurringBillTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Throwable;

class DeleteRecurringBillTransaction extends OrgAction
{
    use AsController;
    use WithAttributes;

    private ?string $precursor;

    /**
     * @throws \Throwable
     */
    public function handle(RecurringBillTransaction $recurringBillTransaction): void
    {

        $recurringBill = $recurringBillTransaction->recurringBill;

        DB::transaction(function () use ($recurringBillTransaction) {
            DB::table('invoice_transactions')->where('recurring_bill_transaction_id', $recurringBillTransaction->id)->update(['recurring_bill_transaction_id' => null]);
            $recurringBillTransaction->forceDelete();

        });


        if ($this->precursor != 'recurring_bill') {
            RecurringBillHydrateTransactions::dispatch($recurringBill)->delay($this->hydratorsDelay);
            CalculateRecurringBillTotals::dispatch($recurringBill)->delay($this->hydratorsDelay);
        }

    }


    /**
     * @throws \Throwable
     */
    public function action(RecurringBillTransaction $recurringBillTransaction, ?string $precursor = null, int $hydratorDelay = 0): void
    {
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorDelay;
        $this->precursor = $precursor;
        $this->initialisationFromFulfilment($recurringBillTransaction->fulfilment, []);
        $this->handle($recurringBillTransaction);

    }

    /**
     * @throws \Throwable
     */
    public function asController(RecurringBillTransaction $recurringBillTransaction, ActionRequest $request): void
    {
        $this->precursor = null;
        $this->initialisationFromFulfilment($recurringBillTransaction->fulfilment, []);

        $this->handle($recurringBillTransaction);
    }

    public function getCommandSignature(): string
    {
        return 'delete:recurring_bill_transaction {id}';
    }

    public function asCommand(Command $command): int
    {

        $recurringBillTransaction = RecurringBillTransaction::withTrashed()->where('id', $command->argument('id'))->first();

        if (!$recurringBillTransaction) {
            $command->error("Recurring bill transaction not found");

            return 1;
        }

        $this->asAction = true;
        $this->initialisationFromFulfilment($recurringBillTransaction->fulfilment, []);
        try {
            $this->handle($recurringBillTransaction);
        } catch (Throwable $exception) {
            $command->error("Error deleting recurring bill transaction ".$exception->getMessage());

            return 1;
        }

        $command->info("Recurring bill transaction deleted");

        return 0;
    }


}
