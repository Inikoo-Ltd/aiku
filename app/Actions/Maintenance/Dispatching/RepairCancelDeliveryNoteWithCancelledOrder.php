<?php
/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 15:29:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairCancelDeliveryNoteWithCancelledOrder
{
    use WithActionUpdate;


    public function handle(DeliveryNote $deliveryNote, bool $dryRun = false, ?Command $command = null): void
    {
        $order = $deliveryNote->orders()->first();
        if ($order && $order->state == OrderStateEnum::CANCELLED) {
            $mode = $dryRun ? '[DRY RUN] ' : '';
            $command->info("{$mode}Delivery note $deliveryNote->slug has a cancelled order. Cancelling delivery note.");
            if (!$dryRun) {
                CancelDeliveryNote::run($deliveryNote, null, false);
            }
        }
    }


    public string $commandSignature = 'delivery_note:repair_cancelled {shop} {--dry-run : Run without making actual changes}';

    public function asCommand(Command $command): void
    {
        $shop   = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $dryRun = (bool)$command->option('dry-run');

        if ($dryRun) {
            $command->warn('Running in dry-run mode. No changes will be made.');
        }

        $count = DeliveryNote::where(
            'shop_id',
            $shop->id
        )->where('state', DeliveryNoteStateEnum::UNASSIGNED)
            ->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        DeliveryNote::orderBy('date', 'desc')->where(
            'shop_id',
            $shop->id
        )->where('state', DeliveryNoteStateEnum::UNASSIGNED)
            ->chunk(1000, function (Collection $models) use ($bar, $dryRun, $command) {
                foreach ($models as $model) {
                    $this->handle($model, $dryRun, $command);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->info('');
        $command->info('Done.');
    }

}
