<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jan 2025 14:56:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Fulfilment;

use App\Actions\Fulfilment\RecurringBill\CalculateRecurringBillTotals;
use App\Actions\Fulfilment\RecurringBillTransaction\CalculateRecurringBillTransactionAmounts;
use App\Actions\Fulfilment\RecurringBillTransaction\CalculateRecurringBillTransactionCurrencyExchangeRates;
use App\Actions\Fulfilment\RecurringBillTransaction\CalculateRecurringBillTransactionDiscountPercentage;
use App\Actions\Fulfilment\RecurringBillTransaction\CalculateRecurringBillTransactionTemporalQuantity;
use App\Actions\Fulfilment\RecurringBillTransaction\StoreRecurringBillTransaction;
use App\Actions\Fulfilment\RecurringBillTransaction\UpdateRecurringBillTransaction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\RecurringBillTransaction;
use App\Models\Fulfilment\Space;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairPalletDeliveriesAndReturns
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        //$this->fixSpacesInRecurringBill();
        $this->fixPalletDispatchedAt();
        $this->fixPalletDeliveryRecurringBill();
        $this->fixPalletDeliveryTransactionsRecurringBill();
        $this->fixPalletDeliveryTransactionsRecurringBillThatShouldNotBeThere();
        $this->palletsStartDate();
        $this->palletsEndDate();
        $this->palletsValidateStartEndDate();
        $this->fixPalletReturnTransactionsRecurringBill();
        $this->fixPalletReturnTransactionsRecurringBillThatShouldNotBeThere();
        $this->fixNonRentalRecurringBillTransactions();


        print "last fix\n";
        /** @var RecurringBill $recurringBill */
        foreach (RecurringBill::where('status', RecurringBillStatusEnum::CURRENT)->get() as $recurringBill) {
            $transactions = $recurringBill->transactions()->get();

            foreach ($transactions as $transaction) {
                $transaction = CalculateRecurringBillTransactionDiscountPercentage::make()->action($transaction);
                $transaction = CalculateRecurringBillTransactionTemporalQuantity::run($transaction);
                $transaction = CalculateRecurringBillTransactionAmounts::run($transaction);
                CalculateRecurringBillTransactionCurrencyExchangeRates::run($transaction);
            }

            CalculateRecurringBillTotals::dispatch($recurringBill);
        }
    }


    public function palletsValidateStartEndDate(): void
    {
        /** @var RecurringBill $recurringBill */
        foreach (RecurringBill::where('status', RecurringBillStatusEnum::CURRENT)->get() as $recurringBill) {
            $transactions = $recurringBill->transactions()->where('item_type', 'Pallet')->get();

            /** @var RecurringBillTransaction $transaction */
            foreach ($transactions as $transaction) {
                $currentStartDay = $transaction->start_date->startOfDay();
                $currentEndDay   = $transaction->end_date ? $transaction->end_date->startOfDay() : null;

                if ($currentEndDay and $currentEndDay->isBefore($currentStartDay)) {
                    print 'Pallet: '.$transaction->id.' end date is before start date '.$currentStartDay->format('Y-m-d').' -> '.$currentEndDay->format('Y-m-d')."\n";
                }
            }
        }
    }

    public function fixSpacesInRecurringBill(): void
    {
        $spaces = Space::where('state', SpaceStateEnum::RENTING)->get();
        /** @var Space $space */
        foreach ($spaces as $space) {
            $currentRecurringBill = $space->currentRecurringBill;

            if (!$currentRecurringBill) {
                print "Ohh shit space no even a recurring bill $space->id  (TODO)\n";
            } elseif ($currentRecurringBill->status != RecurringBillStatusEnum::CURRENT) {

                if ($this->checkIfSpaceInRecurringBillTransaction($space)) {
                    print "Ohh shit space $space->id mmm  it is in the RCT  \n";
                    $currentRecurringBill = $space->fulfilmentCustomer->currentRecurringBill;
                    $space->update(
                        [
                            'current_recurring_bill_id' => $currentRecurringBill->id
                        ]
                    );
                } else {
                    print "Ohh shit space $space->id  $space->fulfilment_id  \n";

                    $currentRecurringBill = $space->fulfilmentCustomer->currentRecurringBill;
                    if ($currentRecurringBill) {
                        if ($currentRecurringBill->status != RecurringBillStatusEnum::CURRENT) {
                            print "oh shit  current RB in space customer is not actually current\n";
                        }
                    } else {
                        print "oh shit not even current RB in space customer\n";
                    }

                    $startDate = $space->start_at;
                    if ($startDate < $currentRecurringBill->start_date) {
                        $startDate = $currentRecurringBill->start_date;
                    }

                    //                   print_r( [
                    //                       'start_date'                => $startDate,
                    //                       'quantity'                  => 1
                    //                   ]);

                    StoreRecurringBillTransaction::make()->action(
                        $currentRecurringBill,
                        $space,
                        [
                            'start_date'                => $startDate,
                            'quantity'                  => 1
                        ]
                    );
                    $space->update(
                        [
                            'current_recurring_bill_id' => $currentRecurringBill->id
                        ]
                    );


                }


            } else {
                // print "Ohh yes space $space->id\n";
            }
        }
    }


    public function checkIfSpaceInRecurringBillTransaction(Space $space)
    {
        return DB::table('recurring_bill_transactions')->leftJoin('recurring_bills', 'recurring_bills.id', 'recurring_bill_transactions.recurring_bill_id')
            ->where('item_type', 'Space')->where('item_id', $space->id)->where('recurring_bills.status', RecurringBillStatusEnum::CURRENT->value)->first();
    }

    public function fixPalletDispatchedAt(): void
    {
        $pallets = Pallet::where('state', PalletStateEnum::DISPATCHED)->get();
        /** @var Pallet $pallet */
        foreach ($pallets as $pallet) {
            $palletReturn = $pallet->palletReturn;
            if (!$palletReturn and !$pallet->dispatched_at) {
                // print 'Pallet: '.$pallet->id.' dont have pallet return!!'."\n";
                continue;
            }

            if (!$palletReturn) {
                //print 'Pallet: '.$pallet->id.' dont have pallet return!!'."\n";
                // dd($pallet);
                continue;
            }


            if (
                !$pallet->dispatched_at or
                $palletReturn->dispatched_at or
                $palletReturn->dispatched_at->ne($pallet->dispatched_at)) {
                $palletDate = 'no date';
                if ($pallet->dispatched_at) {
                    $palletDate = $pallet->dispatched_at->format('Y-m-d');
                }

                $palledReturnDate = 'no date';
                if ($palletReturn->dispatched_at) {
                    $palledReturnDate = $palletReturn->dispatched_at->format('Y-m-d');
                }

                if ($palletDate == 'no date' and $palledReturnDate != 'no date') {
                    print '===+++==== Pallet: Dispatch mismatch '.$pallet->id.' '.$palletDate.' -> '.$palledReturnDate."\n";


                    //$pallet->update(['dispatched_at' => $palletReturn->dispatched_at]);
                }
                if ($palletDate != 'no date' and $palledReturnDate == 'no date') {
                    print 'Pallet: Big error Dispatch mismatch '.$pallet->id.' '.$palletDate.' -> '.$palledReturnDate."\n";
                } elseif ($palletReturn->dispatched_at->toDateString() != $pallet->dispatched_at->toDateString()) {
                    print 'Pallet: * Dispatch mismatch  '.$pallet->id.'  P  '.$pallet->dispatched_at.' -> '.$palletReturn->dispatched_at."\n";
                    // $palletReturn->update(['dispatched_at' => $pallet->dispatched_at]);

                }
            }
        }
    }

    public function palletsStartDate(): void
    {
        /** @var RecurringBill $recurringBill */
        foreach (RecurringBill::where('status', RecurringBillStatusEnum::CURRENT)->get() as $recurringBill) {
            $transactions = $recurringBill->transactions()->where('item_type', 'Pallet')->get();

            /** @var RecurringBillTransaction $transaction */
            foreach ($transactions as $transaction) {
                $currentStartDay   = $transaction->start_date->startOfDay();
                $originalStartDate = $currentStartDay;
                /** @var Pallet $pallet */
                $pallet = $transaction->item;
                if ($pallet->received_at and $pallet->received_at->startOfDay()->isAfter($currentStartDay)) {
                    $currentStartDay = $pallet->received_at->startOfDay();
                }

                if ($originalStartDate->ne($currentStartDay)) {
                    print 'PSD: '.$transaction->id.'  '.$originalStartDate->format('Y-m-d').' -> '.$currentStartDay->format('Y-m-d')."\n";
                    //$transaction->update(['start_date' => $currentStartDay]);
                }
            }
        }
    }

    public function palletsEndDate(): void
    {
        /** @var RecurringBill $recurringBill */
        foreach (RecurringBill::where('status', RecurringBillStatusEnum::CURRENT)->get() as $recurringBill) {
            $transactions = $recurringBill->transactions()->where('item_type', 'Pallet')->get();

            /** @var RecurringBillTransaction $transaction */
            foreach ($transactions as $transaction) {
                /** @var Pallet $pallet */
                $pallet = $transaction->item;
                if ($pallet->dispatched_at) {
                    if (!$transaction->end_date) {
                        print '***PED: Trans'.$transaction->id.' add   pallet id  '.$pallet->id.'  end day '.$pallet->dispatched_at->format('Y-m-d')."\n";
                        //$transaction->update(['end_date' => $pallet->dispatched_at]);
                    } else {
                        $currentEndDay   = $transaction->end_date->startOfDay();
                        $originalEndDate = $currentEndDay;
                        if ($pallet->dispatched_at->startOfDay()->ne($currentEndDay)) {
                            $currentEndDay = $pallet->dispatched_at->startOfDay();
                        }

                        if ($originalEndDate->ne($currentEndDay)) {
                            print '===========>PEDx: '.$transaction->id.' '.$originalEndDate->format('Y-m-d').' -> '.$currentEndDay->format('Y-m-d')."\n";
                            $transaction->update(['end_date' => $pallet->dispatched_at]);
                        }
                    }
                } elseif ($transaction->end_date) {
                    //print 'PED: '.$transaction->id.'  pallet id  '.$pallet->id.'  set end day '.$transaction->end_date->format('Y-m-d')." to recurring bill end date  ".$recurringBill->end_date." \n";
                    $transaction->update(['end_date' => $recurringBill->end_date]);
                }
            }
        }
    }


    public function fixNonRentalRecurringBillTransactions(): void
    {
        $recurringBills = RecurringBill::where('status', RecurringBillStatusEnum::CURRENT)->get();

        /** @var RecurringBill $recurringBill */
        foreach ($recurringBills as $recurringBill) {
            $recurringBill->transactions->each(function ($transaction) use ($recurringBill) {
                if (($transaction->pallet_delivery_id || $transaction->pallet_return_id) && !in_array($transaction->item_type, ['Pallet', 'StoredItem'])) {
                    $fulfilmentTransaction = $transaction->fulfilmentTransaction;


                    if ($fulfilmentTransaction->quantity != $transaction->quantity) {
                        print "(todo) Fix Fulfilment Transaction Qty: $fulfilmentTransaction->id\n";
                        //                        UpdateRecurringBillTransaction::make()->action(
                        //                            $transaction,
                        //                            [
                        //                                'quantity' => $fulfilmentTransaction->quantity
                        //                            ]
                        //                        );
                    }
                }
            });
        }
    }


    public function fixPalletDeliveryTransactionsRecurringBillThatShouldNotBeThere(): void
    {
        $palletDeliveries = PalletDelivery::whereIn('state', [
            PalletDeliveryStateEnum::NOT_RECEIVED,
            PalletDeliveryStateEnum::IN_PROCESS,
            PalletDeliveryStateEnum::SUBMITTED,
        ])->whereNotNull('recurring_bill_id')->get();
        /** @var PalletDelivery $palletDelivery */
        foreach ($palletDeliveries as $palletDelivery) {
            $palletDelivery->transactions->each(function ($transaction) use (
                $palletDelivery
            ) {
                if ($palletDelivery->recurringBill->transactions()->where('fulfilment_transaction_id', $transaction->id)->exists()) {
                    print "Fix Pallet Delivery Transaction that should  not be here CRB: $transaction->id\n";


                }
            });
        }
    }

    public function fixPalletDeliveryTransactionsRecurringBill(): void
    {
        $palletDeliveries = PalletDelivery::whereNotIn('state', [
            PalletDeliveryStateEnum::NOT_RECEIVED,
            PalletDeliveryStateEnum::IN_PROCESS,
            PalletDeliveryStateEnum::SUBMITTED,
        ])->whereNotNull('recurring_bill_id')->get();
        /** @var PalletDelivery $palletDelivery */
        foreach ($palletDeliveries as $palletDelivery) {
            $palletDelivery->transactions->each(function ($transaction) use (
                $palletDelivery
            ) {
                if (!$palletDelivery->recurringBill->transactions()->where('fulfilment_transaction_id', $transaction->id)->exists()) {
                    print "+++ +++ ++ Fix Pallet Delivery Transaction CRB: $transaction->id\n";

                    //                    StoreRecurringBillTransaction::make()->action(
                    //                        $palletDelivery->recurringBill,
                    //                        $transaction,
                    //                        [
                    //                            'start_date'                => now(),
                    //                            'quantity'                  => $transaction->quantity,
                    //                            'pallet_delivery_id'        => $palletDelivery->id,
                    //                            'fulfilment_transaction_id' => $transaction->id
                    //                        ]
                    //                    );
                }
            });
        }
    }

    public function fixPalletReturnTransactionsRecurringBillThatShouldNotBeThere(): void
    {
        $palletReturns = PalletReturn::whereIn('state', [
            PalletReturnStateEnum::CANCEL,
            PalletReturnStateEnum::IN_PROCESS,
            PalletReturnStateEnum::SUBMITTED,
        ])
            ->whereNotNull('recurring_bill_id')->get();
        /** @var PalletReturn $palletReturn */
        foreach ($palletReturns as $palletReturn) {
            if ($palletReturn->recurringBill->status != RecurringBillStatusEnum::CURRENT) {
                continue;
            }


            $palletReturn->transactions()->each(function ($transaction) use (
                $palletReturn
            ) {
                if ($palletReturn->recurringBill->transactions()->where('fulfilment_transaction_id', $transaction->id)->exists()) {
                    print "Fix Pallet return Transaction CRB that should not be there!  >> ".$palletReturn->state->value." <<  (todo) : $transaction->id\n";
                    // delete it

                }
            });
        }
    }

    public function fixPalletReturnTransactionsRecurringBill(): void
    {
        $palletReturns = PalletReturn::whereNotIn('state', [
            PalletReturnStateEnum::CANCEL,
            PalletReturnStateEnum::IN_PROCESS,
            PalletReturnStateEnum::SUBMITTED,
        ])
            ->whereNotNull('recurring_bill_id')->get();
        /** @var PalletReturn $palletReturn */
        foreach ($palletReturns as $palletReturn) {
            if ($palletReturn->recurringBill->status != RecurringBillStatusEnum::CURRENT) {
                continue;
            }


            $palletReturn->transactions()->each(function ($transaction) use (
                $palletReturn
            ) {
                if (!$palletReturn->recurringBill->transactions()->where('fulfilment_transaction_id', $transaction->id)->exists()) {
                    print "** Fix Pallet return Transaction CRB: $transaction->id\n";

                    StoreRecurringBillTransaction::make()->action(
                        $palletReturn->recurringBill,
                        $transaction,
                        [
                            'start_date'                => $transaction->created_at,
                            'quantity'                  => $transaction->quantity,
                            'pallet_return_id'          => $palletReturn->id,
                            'fulfilment_transaction_id' => $transaction->id
                        ]
                    );
                }
            });
        }
    }

    public function fixPalletDeliveryRecurringBill(): void
    {
        $palletDeliveries = PalletDelivery::whereNull('recurring_bill_id')->get();
        /** @var PalletDelivery $palletDelivery */
        foreach ($palletDeliveries as $palletDelivery) {
            if ($receivedDate = $palletDelivery->received_at and $palletDelivery->fulfilmentCustomer->currentRecurringBill) {
                $currentRecurringBill = $palletDelivery->fulfilmentCustomer->currentRecurringBill;
                if ($receivedDate->isAfter($currentRecurringBill->start_date)) {
                    print "Fix Pallet Delivery CRB: $palletDelivery->id\n";
                    //$palletDelivery->update(['recurring_bill_id' => $currentRecurringBill->id]);
                }
            }
        }
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:repair_pallet_deliveries_and_returns';
    }

    public function asCommand(Command $command): int
    {
        //        try {
        $this->handle();
        //        } catch (Throwable $e) {
        //          $command->error($e->getMessage());

        //          return 1;
        //   }

        return 0;
    }

}
