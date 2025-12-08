<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 May 2024 08:37:52 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\Helpers\Media\StoreMediaFromIcon;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Catalogue\Shop;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SetIconAsShopLogo
{
    use AsAction;
    use WithAttachMediaToModel;

    public function handle(Shop $shop): Shop
    {
        $media = StoreMediaFromIcon::run($shop);
        $this->attachMediaToModel($shop, $media, 'logo');
        $shop->updateQuietly(
            [
                'image_id' => $media->id,
            ]
        );

        return $shop;
    }

    public string $commandSignature = 'shop:logo {shop : Shop slug}';

    public function asCommand(Command $command): int
    {
        try {
            $shop = Shop::where('slug', $command->argument('organisation'))->firstOrFail();
        } catch (Exception) {
            $command->error('Organisation not found');

            return 1;
        }

        $command->info('Logo set');

        return 0;

    }
}
