<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Nov 2025 12:26:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateShopTypeDeliveryNotesState implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:organisation-shop-type-delivery-notes-state {--s|slug= : Organisation slug}';

    public function getJobUniqueId(int $organisationID, ShopTypeEnum $shopTypeEnum, DeliveryNoteStateEnum $state): string
    {
        return $organisationID.'_'.$shopTypeEnum->value.'-'.$state->value;
    }

    public function asCommand(Command $command): void
    {
        if ($command->option('slug')) {
            $organisation = Organisation::where('slug', $command->option('slug'))->first();

            if (!$organisation) {
                $command->error("Organisation not found.");
                return;
            }

            $this->hydrateOrganisation($command, $organisation);
        } else {
            $organisations = Organisation::all();

            if ($organisations->isEmpty()) {
                $command->warn("No organisations found.");
                return;
            }

            $command->info("Hydrating shop type delivery notes state for all organisations...");

            foreach ($organisations as $organisation) {
                $this->hydrateOrganisation($command, $organisation);
            }

            $command->info("");
            $command->info("Completed hydrating all organisations!");
        }
    }

    private function hydrateOrganisation(Command $command, Organisation $organisation): void
    {
        $command->info("Hydrating shop type delivery notes state for organisation: {$organisation->slug}");

        $shopTypes = array_filter(ShopTypeEnum::cases(), fn ($type) => $type !== ShopTypeEnum::FULFILMENT);
        $states = DeliveryNoteStateEnum::cases();
        $totalOperations = count($shopTypes) * count($states);

        $bar = $command->getOutput()->createProgressBar($totalOperations);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($shopTypes as $shopType) {
            foreach ($states as $state) {
                $this->handle($organisation->id, $shopType, $state);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed hydrating shop type delivery notes state for organisation: {$organisation->slug}");
    }

    public function handle(int $organisationID, ShopTypeEnum $shopTypeEnum, DeliveryNoteStateEnum $state): void
    {
        if ($shopTypeEnum == ShopTypeEnum::FULFILMENT) {
            return;
        }
        $organisation = Organisation::find($organisationID);
        if (!$organisation) {
            return;
        }

        // Get directly from shop.type because some deliveryNote has no shop_type somehow (null), probably old order_data
        $count = DeliveryNote::leftJoin('shops', 'shops.id', '=', 'delivery_notes.shop_id')
            ->where('delivery_notes.organisation_id', $organisation->id)
            ->where('shops.type', $shopTypeEnum->value) // Use shops.type instead of delivery_notes.shop_type
            ->where('shops.is_aiku', true) // Todo: this hacks has to be deleted after we migrate from aurora
            ->whereNull('delivery_notes.deleted_at')
            ->where('delivery_notes.state', $state)->count();

        $organisation->orderingStats()->update(
            [
                "number_".$shopTypeEnum->snake()."_shop_delivery_notes_state_".$state->snake() => $count

            ]
        );


    }


}
