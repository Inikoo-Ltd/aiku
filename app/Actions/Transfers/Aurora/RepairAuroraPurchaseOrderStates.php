<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 16:30:00 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairAuroraPurchaseOrderStates
{
    use AsAction;

    public string $commandSignature = 'repair:aurora_purchase_order_states {organisations?*} {--N|dry_run}';
    public string $commandDescription = 'Sync purchase order states with their real Aurora states';

    public function handle(Organisation $organisation, bool $dryRun = false): array
    {
        $organisationSource = new AuroraOrganisationService();
        $organisationSource->initialisation($organisation);

        return [
            'organisation'    => $organisation->slug,
            'purchase_orders' => $this->repairPurchaseOrders($organisation, $dryRun),
        ];
    }

    private function mapPurchaseOrderState(string $auroraState): PurchaseOrderStateEnum
    {
        return match ($auroraState) {
            'InProcess' => PurchaseOrderStateEnum::IN_PROCESS,
            'Submitted' => PurchaseOrderStateEnum::SUBMITTED,
            'Cancelled' => PurchaseOrderStateEnum::CANCELLED,
            'Received', 'Checked', 'QC_Pass', 'Placed', 'Costing', 'InvoiceChecked' => PurchaseOrderStateEnum::SETTLED,
            'NoReceived' => PurchaseOrderStateEnum::NOT_RECEIVED,
            default => PurchaseOrderStateEnum::CONFIRMED,
        };
    }

    private function mapPurchaseOrderDeliveryState(string $auroraState): PurchaseOrderDeliveryStateEnum
    {
        return match ($auroraState) {
            'Placed', 'Costing', 'InvoiceChecked' => PurchaseOrderDeliveryStateEnum::PLACED,
            'NoReceived' => PurchaseOrderDeliveryStateEnum::NOT_RECEIVED,
            'Cancelled' => PurchaseOrderDeliveryStateEnum::CANCELLED,
            'Manufactured', 'Inputted' => PurchaseOrderDeliveryStateEnum::READY_TO_SHIP,
            'Dispatched' => PurchaseOrderDeliveryStateEnum::DISPATCHED,
            'Confirmed' => PurchaseOrderDeliveryStateEnum::CONFIRMED,
            'Received' => PurchaseOrderDeliveryStateEnum::RECEIVED,
            'Checked', 'QC_Pass' => PurchaseOrderDeliveryStateEnum::CHECKED,
            default => PurchaseOrderDeliveryStateEnum::IN_PROCESS,
        };
    }

    private function repairPurchaseOrders(Organisation $organisation, bool $dryRun): int
    {
        $auroraStates = DB::connection('aurora')
            ->table('Purchase Order Dimension')
            ->pluck('Purchase Order State', 'Purchase Order Key');

        $fixed = 0;

        PurchaseOrder::where('organisation_id', $organisation->id)
            ->whereIn('state', [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])
            ->whereNotNull('source_id')
            ->chunkById(200, function ($purchaseOrders) use ($organisation, $auroraStates, $dryRun, &$fixed) {
                foreach ($purchaseOrders as $purchaseOrder) {
                    $sourceKey   = explode(':', $purchaseOrder->source_id)[1] ?? null;
                    $auroraState = $sourceKey ? $auroraStates->get($sourceKey) : null;
                    if (!$auroraState) {
                        continue;
                    }

                    $expectedState         = $this->mapPurchaseOrderState($auroraState);
                    $expectedDeliveryState = $this->mapPurchaseOrderDeliveryState($auroraState);

                    $modelData = [];
                    if ($expectedState !== $purchaseOrder->state) {
                        $modelData['state'] = $expectedState;
                    }
                    if ($expectedDeliveryState !== $purchaseOrder->delivery_state) {
                        $modelData['delivery_state'] = $expectedDeliveryState;
                    }
                    if ($modelData === []) {
                        continue;
                    }

                    if (!$dryRun) {
                        UpdatePurchaseOrder::make()->action($purchaseOrder, $modelData, strict: false, audit: false);
                    }
                    $fixed++;
                }
            });

        return $fixed;
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry_run');

        $query = Organisation::query()
            ->where('type', OrganisationTypeEnum::SHOP->value)
            ->whereNotNull('source');
        if ($command->argument('organisations')) {
            $query->whereIn('slug', $command->argument('organisations'));
        }

        foreach ($query->get() as $organisation) {
            if (!Arr::get($organisation->source, 'db_name')) {
                continue;
            }
            $result = $this->handle($organisation, $dryRun);
            $command->info(sprintf(
                '%s: %d purchase orders %s',
                $result['organisation'],
                $result['purchase_orders'],
                $dryRun ? 'would be fixed' : 'fixed'
            ));
        }

        return 0;
    }
}
