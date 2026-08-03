<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 12:57:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema;

use App\Actions\Billables\ShippingZone\DeleteShippingZone;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateShippingZoneSchemas;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateShippingZoneSchemas;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShippingZoneSchemas;
use App\Models\Billables\ShippingZoneSchema;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShippingZoneSchema extends OrgAction
{
    use AsAction;

    public string $commandSignature = 'delete:shipping_zone_schema {slug}';

    public function handle(ShippingZoneSchema $shippingZoneSchema): void
    {
        $shop = $shippingZoneSchema->shop;
        $shippingZoneSchema->stats()->delete();
        foreach ($shippingZoneSchema->shippingZones as $shippingZone) {
            DeleteShippingZone::dispatch($shippingZone);
        }

        $shippingZoneSchema->delete();

        ShopHydrateShippingZoneSchemas::dispatch($shop)->delay($this->hydratorsDelay);
        OrganisationHydrateShippingZoneSchemas::dispatch($shop->organisation)->delay($this->hydratorsDelay);
        GroupHydrateShippingZoneSchemas::dispatch($shop->group)->delay($this->hydratorsDelay);
    }

    public function action(ShippingZoneSchema $shippingZoneSchema, int $hydratorsDelay = 0, bool $audit = true): void
    {
        if (!$audit) {
            ShippingZoneSchema::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shippingZoneSchema->shop, []);

        $this->handle($shippingZoneSchema);
    }

    public function asCommand(Command $command): int
    {
        try {
            $shippingZoneSchema = ShippingZoneSchema::where('slug', $command->argument('slug'))->firstOrFail();
        } catch (Exception) {
            $command->error('Shipping zone schema not found');

            return 1;
        }

        $this->action($shippingZoneSchema);

        $command->info('Shipping zone schema '.$shippingZoneSchema->name.' deleted');

        return 0;
    }
}
