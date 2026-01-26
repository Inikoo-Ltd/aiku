<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 26 Jan 2026 08:40:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Models\Catalogue\Shop;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Console\Command;

class ShopHydrateEmailTemplates implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:shop-email-templates {shop}';

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_email_templates' => $shop->emailTemplates()->count(),
        ];

        $shop->commsStats->update($stats);
    }

    public function asCommand(Command $command): int
    {

        try {
            /** @var Shop $shop */
            $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($shop);

        return 0;
    }
}
