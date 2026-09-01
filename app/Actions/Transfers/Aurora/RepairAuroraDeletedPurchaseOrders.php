<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 15:30:00 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrder;
use App\Models\Procurement\PurchaseOrder;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairAuroraDeletedPurchaseOrders
{
    use AsAction;

    public string $commandSignature = 'repair:aurora_deleted_purchase_orders {organisations?*} {--N|dry_run}';
    public string $commandDescription = 'Soft delete purchase orders that were deleted in Aurora but never propagated to aiku';

    public function handle(Organisation $organisation, bool $dryRun = false): array
    {
        $organisationSource = new AuroraOrganisationService();
        $organisationSource->initialisation($organisation);

        $deletedKeys = DB::connection('aurora')
            ->table('Purchase Order Deleted Dimension')
            ->pluck('Purchase Order Deleted Key');

        $sourceIds = $deletedKeys->map(fn ($key) => $organisation->id.':'.$key);

        $deleted = 0;
        $skippedWithDeliveries = 0;

        PurchaseOrder::where('organisation_id', $organisation->id)
            ->whereIn('source_id', $sourceIds)
            ->chunkById(200, function ($purchaseOrders) use ($dryRun, &$deleted, &$skippedWithDeliveries) {
                foreach ($purchaseOrders as $purchaseOrder) {
                    if ($purchaseOrder->number_stock_deliveries > 0) {
                        $skippedWithDeliveries++;

                        continue;
                    }
                    if (!$dryRun) {
                        DeletePurchaseOrder::make()->handle($purchaseOrder);
                    }
                    $deleted++;
                }
            });

        return [
            'organisation'            => $organisation->slug,
            'aurora_deleted'          => $deletedKeys->count(),
            'deleted'                 => $deleted,
            'skipped_with_deliveries' => $skippedWithDeliveries,
        ];
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
                '%s: %d deleted in aurora, %d %s in aiku, %d skipped (have deliveries)',
                $result['organisation'],
                $result['aurora_deleted'],
                $result['deleted'],
                $dryRun ? 'would be soft-deleted' : 'soft-deleted',
                $result['skipped_with_deliveries']
            ));
        }

        return 0;
    }
}
