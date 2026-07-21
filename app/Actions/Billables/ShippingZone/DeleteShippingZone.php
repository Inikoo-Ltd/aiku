<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 14:13:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZone;

use App\Actions\Billables\ShippingZoneSchema\Hydrators\ShippingZoneSchemaHydrateShippingZones;
use App\Actions\OrgAction;
use App\Models\Billables\ShippingZone;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShippingZone extends OrgAction
{
    use AsAction;

    public string $commandSignature = 'delete:shipping_zone {slug}';

    public function handle(ShippingZone $shippingZone): void
    {
        $shippingZoneSchema = $shippingZone->schema;

        $shippingZone->stats()->delete();
        $shippingZone->asset?->delete();
        $shippingZone->delete();

        ShippingZoneSchemaHydrateShippingZones::dispatch($shippingZoneSchema)->delay($this->hydratorsDelay);
    }

    public function action(ShippingZone $shippingZone, int $hydratorsDelay = 0, bool $audit = true): void
    {
        if (!$audit) {
            ShippingZone::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shippingZone->shop, []);

        $this->handle($shippingZone);
    }

    public function asCommand(Command $command): int
    {
        try {
            $shippingZone = ShippingZone::where('slug', $command->argument('slug'))->firstOrFail();
        } catch (Exception) {
            $command->error('Shipping zone not found');

            return 1;
        }

        $this->action($shippingZone);

        $command->info('Shipping zone '.$shippingZone->name.' deleted');

        return 0;
    }
}
