<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 00:06:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Aurora;

use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Transfers\Aurora\FetchAuroraOrders;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairOrderFetch
{
    use AsAction;
    use WithOrganisationSource;

    private int $count = 0;
    /**
     * @var \App\Transfers\AuroraOrganisationService|\App\Transfers\WowsbarOrganisationService|null
     */
    private \App\Transfers\WowsbarOrganisationService|null|AuroraOrganisationService $organisationSource;

    /**
     * @throws \Throwable
     */
    public function handle(Command $command, Organisation $organisation): void
    {
        $this->setSource($organisation);
        $this->checkCount($command);
        $this->findNotFetchedOrders();
        $this->findDuplicateOrders($command);
    }


    public function findNotFetchedOrders(): void
    {
        $counter = 0;

        $sevenDays = now()->subDays(100000)->startOfDay();
        DB::connection('aurora')->table('Order Dimension')
            ->where('Order Date', '>=', $sevenDays)
            ->orderBy('Order Date', 'desc')
            ->chunk(
                1000,
                function ($chunkedData) use ($counter) {
                    foreach ($chunkedData as $auroraData) {
                        $sourceId = $this->organisationSource->organisation->id.':'.$auroraData->{'Order Key'};
                        if (!Order::where('source_id', $sourceId)->exists()) {
                            $counter++;
                            print "$counter Order $sourceId  ".$auroraData->{'Order Date'}."  not fetched\n";

                            FetchAuroraOrders::make()->action($this->organisationSource->organisation->id, $auroraData->{'Order Key'}, ['transactions','payments']);
                        }
                    }
                }
            );
    }

    public function checkCount(Command $command): void
    {
        DB::connection('aurora')->table('Order Dimension')->count();


        $auroraOrders = DB::connection('aurora')->table('Order Dimension')->count();
        $aikuOrders   = DB::connection('aiku')->table('orders')->whereNotNull('source_id')->whereNull('deleted_at')->where('organisation_id', $this->organisationSource->organisation->id)->count();


        $command->table(
            ['', 'Aurora', 'Aiku', ''],
            [
                [
                    'orders',
                    number_format($auroraOrders),
                    number_format($aikuOrders),
                    ($aikuOrders > $auroraOrders ? '+' : '').$aikuOrders - $auroraOrders
                ],
            ]
        );
    }


    /**
     * @throws \Exception
     */
    private function setSource(Organisation $organisation): void
    {
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:repair_order_fetch {organisation}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();


        try {
            $this->handle($command, $organisation);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

    public function findDuplicateOrders(Command $command): void
    {
        $command->info('Checking for orders with duplicated source_id...');


        $duplicates = DB::connection('aiku')
            ->table('orders')
            ->select('source_id', DB::raw('COUNT(id) as count'))
            ->where('organisation_id', $this->organisationSource->organisation->id)
            ->whereNotNull('source_id')
            ->groupBy('source_id')
            ->havingRaw('COUNT(id) > 1')  // Use havingRaw with the actual function
            ->get();

        if ($duplicates->isEmpty()) {
            $command->info('No duplicate orders found.');

            return;
        }

        $command->info('Found '.$duplicates->count().' source_id values with duplicates:');

        foreach ($duplicates as $duplicate) {
            $orders = Order::where('source_id', $duplicate->source_id)
                ->select('id', 'reference', 'date', 'deleted_at')
                ->get();

            $command->line("Source ID: $duplicate->source_id ($duplicate->count occurrences)");

            $details = [];
            /** @var Order $order */
            foreach ($orders as $order) {
                $details[] = [
                    'id' => $order->id,
                    'reference' => $order->reference,
                    'date' => $order->date,
                    'deleted' => $order->deleted_at ? 'Yes' : 'No'
                ];
            }

            $command->table(['ID', 'Reference', 'Date', 'Deleted'], $details);
        }
    }

}
