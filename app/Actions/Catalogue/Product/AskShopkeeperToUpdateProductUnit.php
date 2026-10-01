<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Tasks\StoreStaffTask;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A shop that does not follow the master keeps its own unit label, so when the master's
 * label changes its shopkeeper is asked to update it. Changes pile onto the shop's open
 * unit task instead of opening one task per product.
 */
class AskShopkeeperToUpdateProductUnit
{
    use AsAction;

    public const string TASK_KIND = 'product_unit';

    public string $commandSignature = 'catalogue:ask_shopkeepers_product_unit {master : master product slug} {username : who the tasks come from} {--commit : Create the tasks, otherwise dry run}';

    public function handle(Product $product, string $suggestedUnit, User $requester): StaffTask
    {
        $shop = $product->shop;
        $line = __(':code: change the unit from «:current» to «:suggested»', [
            'code'      => $product->code,
            'current'   => $product->unit,
            'suggested' => $suggestedUnit,
        ])."\n".route('grp.org.shops.show.catalogue.products.all_products.show', [$shop->organisation->slug, $shop->slug, $product->slug]);

        $openTask = StaffTask::open()
            ->where('data->kind', self::TASK_KIND)
            ->where('data->shop_id', $shop->id)
            ->first();

        if ($openTask) {
            $openTask->update(['description' => mb_substr($openTask->description."\n\n".$line, 0, 5000)]);
            SendStaffMessage::run($openTask->conversation, $requester, ['body' => $line]);

            return $openTask;
        }

        $shopkeeper = User::where('id', data_get($shop->settings, 'catalog.shopkeeper_in_charge_id'))->where('status', true)->first();
        $assignment = $shopkeeper && StaffTask::canBeAssigned($shopkeeper) ? ['assignee_id' => $shopkeeper->id] : ['department' => 'products'];

        $task = StoreStaffTask::make()->action($requester, [
            'subject'     => __('Update product units in :shop', ['shop' => $shop->name]),
            'description' => __('The master changed the unit of these products. This shop keeps its own texts, so please update them:')."\n\n".$line,
            'model_type'  => 'Product',
            'model_id'    => $product->id,
            ...$assignment,
        ]);
        $task->update(['data' => ['kind' => self::TASK_KIND, 'shop_id' => $shop->id]]);

        return $task;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $masterAsset = MasterAsset::where('slug', $command->argument('master'))->firstOrFail();
        $requester   = User::where('username', $command->argument('username'))->firstOrFail();
        $english     = Language::where('code', 'en')->first();

        foreach ($masterAsset->products()->with('shop.language')->get() as $product) {
            $shop = $product->shop;
            if ($shop->state != ShopStateEnum::OPEN || $shop->language_id == $english->id || data_get($shop->settings, 'catalog.product_follow_master')) {
                continue;
            }

            $translatedUnit = Translate::run($masterAsset->unit, $english, $shop->language, 'catalogue');
            if ($product->unit === $translatedUnit) {
                continue;
            }

            $command->line("$shop->slug: $product->unit → $translatedUnit");
            if ($command->option('commit')) {
                $this->handle($product, $translatedUnit, $requester);
            }
        }

        return 0;
    }
}
