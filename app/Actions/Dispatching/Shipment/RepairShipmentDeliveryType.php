<?php

namespace App\Actions\Dispatching\Shipment;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\Shipment\ShipmentDeliveryTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShipmentDeliveryType
{
    use AsAction;

    public string $commandSignature = 'repair:shipment_delivery_type {--N|dry_run}';
    public string $commandDescription = 'Fill home / pickup point on dropshipping shipments made before it was recorded (HELP-4769)';

    /**
     * @return array{home: int, pickup_point: int, skipped: int}
     */
    public function handle(bool $dryRun = false): array
    {
        $counts = ['home' => 0, 'pickup_point' => 0, 'skipped' => 0];

        Shipment::query()
            ->whereNull('delivery_type')
            ->whereIn('shop_id', DB::table('shops')->where('type', ShopTypeEnum::DROPSHIPPING->value)->select('id'))
            ->select('id')
            ->chunkById(500, function ($shipments) use ($dryRun, &$counts) {
                $deliveryNoteIds = DB::table('model_has_shipments')
                    ->where('model_type', 'DeliveryNote')
                    ->whereIn('shipment_id', $shipments->pluck('id'))
                    ->pluck('model_id', 'shipment_id');

                $deliveryNotes = DeliveryNote::with(['orders', 'deliveryAddress'])
                    ->whereIn('id', $deliveryNoteIds->values()->unique())
                    ->get()
                    ->keyBy('id');

                foreach ($shipments as $shipment) {
                    $deliveryNote = $deliveryNotes->get($deliveryNoteIds->get($shipment->id));
                    if (!$deliveryNote) {
                        $counts['skipped']++;

                        continue;
                    }

                    $deliveryType = GetShipmentDeliveryType::run($deliveryNote);
                    $counts[$deliveryType['delivery_type']->value]++;

                    if (!$dryRun) {
                        DB::table('shipments')->where('id', $shipment->id)->whereNull('delivery_type')->update([
                            'delivery_type'     => $deliveryType['delivery_type']->value,
                            'pickup_point_code' => $deliveryType['pickup_point_code'],
                        ]);
                    }
                }
            });

        return $counts;
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry_run');
        $counts = $this->handle($dryRun);

        $command->info(($dryRun ? 'Dry run, nothing written. ' : '')
            .sprintf(
                '%s: %d, %s: %d, skipped (no delivery note): %d',
                ShipmentDeliveryTypeEnum::HOME->value,
                $counts['home'],
                ShipmentDeliveryTypeEnum::PICKUP_POINT->value,
                $counts['pickup_point'],
                $counts['skipped']
            ));

        return 0;
    }
}
