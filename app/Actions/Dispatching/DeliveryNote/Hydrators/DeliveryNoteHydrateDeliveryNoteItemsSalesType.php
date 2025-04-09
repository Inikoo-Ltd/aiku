<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:53:53 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Hydrators;

use App\Actions\Dispatching\DeliveryNoteItem\Hydrators\DeliveryNoteItemHydrateSalesType;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeliveryNoteHydrateDeliveryNoteItemsSalesType implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public function getJobUniqueId(DeliveryNote $deliveryNote): string
    {
        return $deliveryNote->id;
    }


    public function handle(DeliveryNote $deliveryNote): void
    {
        if ($deliveryNote->type == DeliveryNoteTypeEnum::REPLACEMENT) {
            $this->updateItemsSalesType($deliveryNote, DeliveryNoteItemSalesTypeEnum::NA);
        } else {
            $numberOrders = $deliveryNote->orders()->count();
            if ($numberOrders == 0) {
                $this->updateItemsSalesType($deliveryNote, DeliveryNoteItemSalesTypeEnum::NA);
            } elseif ($numberOrders == 1) {
                $order     = $deliveryNote->orders->first();
                $salesType = DeliveryNoteItemHydrateSalesType::make()->getSalesTypeFromOrder($order);
                $this->updateItemsSalesType($deliveryNote, $salesType);
            } else {
                foreach ($deliveryNote->deliveryNoteItems as $deliveryNoteItem) {
                    DeliveryNoteItemHydrateSalesType::run($deliveryNoteItem);
                }
            }
        }
    }

    public function updateItemsSalesType(DeliveryNote $deliveryNote, DeliveryNoteItemSalesTypeEnum $hydrateSalesType): void
    {
        $deliveryNote->deliveryNoteItems()->update(
            [
                'sales_type' => $hydrateSalesType
            ]
        );
    }

    public string $commandSignature = 'delivery_note:hydrate_sales_type {organisations?*} {--S|shop= shop slug} {--i|id=}';

    protected function getOrganisationsIds(Command $command): array
    {
        return Organisation::query()->whereIn('type', [OrganisationTypeEnum::SHOP->value, OrganisationTypeEnum::DIGITAL_AGENCY->value])
            ->when($command->argument('organisations'), function ($query) use ($command) {
                $query->whereIn('slug', $command->argument('organisations'));
            })
            ->get()->pluck('id')->toArray();
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());


        $query = DB::table('delivery_notes')->select('id')->orderBy('date', 'desc');

        if ($command->hasOption('shop') && $command->option('shop')) {
            $shop = Shop::where('slug', $command->option('shop'))->first();
            if ($shop) {
                $query->where('shop_id', $shop->id);
            }
        }

        if ($command->hasArgument('organisations') && $command->argument('organisations')) {

            $this->getOrganisationsIds($command);
            $query->whereIn('organisation_id', $this->getOrganisationsIds($command));
        }


        $count = $query->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(1000, function (Collection $modelsData) use ($bar) {
            foreach ($modelsData as $modelId) {
                $instance = DeliveryNote::withTrashed()->find($modelId->id);

                $this->handle($instance);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info("");

        return 0;
    }


}
