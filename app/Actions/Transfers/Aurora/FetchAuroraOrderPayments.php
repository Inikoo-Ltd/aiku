<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Apr 2026 17:52:22 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Ordering\Order\HasOrderHydrators;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Models\Ordering\Order;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraOrderPayments extends FetchAuroraAction
{
    use WithAuroraAttachments;
    use WithAuroraParsers;
    use HasOrderHydrators;

    public string $commandSignature = 'fetch:orders_payments {organisations?*} {--S|shop= : Shop slug} {--s|source_id=} {--d|db_suffix=}  {--d|db_suffix=}';

    private bool $errorReported = false;
    private string $fingerPrint;
    private bool $getSourceOnly = true;

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId, bool $forceWithTransactions = false): ?Order
    {
        $this->organisationSource = $organisationSource;
        $organisation             = $organisationSource->getOrganisation();

        $orderData = $organisationSource->fetchOrder($organisationSourceId);

        $order = Order::where('source_id', $organisation->id.':'.$organisationSourceId)->first();
        if ($order) {
            $sourceData = explode(':', $order->source_id);


            $modelHasPayments = [];
            foreach (

                DB::connection('aurora')
                    ->table('Order Payment Bridge')
                    ->select('Payment Key')
                    ->where('Order Key', $sourceData[1])
                    ->get() as $auroraData
            ) {
                $payment = $this->parsePayment($organisation->id.':'.$auroraData->{'Payment Key'});

                if ($payment) {
                    $modelHasPayments[$payment->id] = [
                        'amount' => $payment->amount,
                        'share'  => 1
                    ];
                }
            }


            $currentPayments  = $order->payments()->pluck('payment_id')->toArray();
            $modelHasPayments = array_diff_key($modelHasPayments, array_flip($currentPayments));


            $order->payments()->syncWithoutDetaching($modelHasPayments);
            UpdateOrderPaymentsStatus::run($order);
            if (count($modelHasPayments) > 0) {
                print_r([$currentPayments, $modelHasPayments, $order->id, $order->slug]);
            }
        }


        return $order;
    }


    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')->table('Order Dimension')->select('Order Key as source_id');
        $query = $this->commonSelectModelsToFetch($query);
        $query->orderBy('Order Date', $this->orderDesc ? 'desc' : 'asc');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Order Dimension');
        $query = $this->commonSelectModelsToFetch($query);

        return $query->count();
    }

    public function commonSelectModelsToFetch($query)
    {
        if ($this->basket) {
            $query->where('Order State', 'InBasket');

            $query->where(function ($q) {
                $q->whereNull('last_fetched_at')
                    ->orWhereRaw('last_fetched_at  < `Order Last Updated Date`');
            });
        }

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        } elseif ($this->onlyOrdersNoTransactions) {
            $query->whereNull('aiku_all_id');
        } elseif ($this->onlyCancelled) {
            $query->where('Order State', 'Cancelled');
        }

        if ($this->fromDays) {
            $query->where('Order Date', '>=', now()->subDays($this->fromDays)->format('Y-m-d'));
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Order Store Key', $sourceData[1]);
        }


        return $query;
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Order Dimension')->update(['aiku_id' => null]);
    }

}
