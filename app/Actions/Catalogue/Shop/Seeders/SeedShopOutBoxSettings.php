<?php

/*
 * Author: ekayudinata <ekayudinatha@gmail.com>
 * Created: Tue, 19 Dec 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, ekayudinata
 */

namespace App\Actions\Catalogue\Shop\Seeders;

use App\Models\Comms\Outbox;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Console\Command;

class SeedShopOutBoxSettings
{
    use AsAction;

    public string $commandSignature = 'shop:seed_outbox_settings';

    public function handle()
    {
        $outboxes = Outbox::query()->whereNotNull('shop_id');
        foreach ($outboxes->cursor() as $outbox) {
            if ($outbox->setting()->exists()) {
                continue;
            }

            $outbox->setting()->create([
                'days_after' => 20, // default value
            ]);
        }
    }

    public function asCommand(Command $command)
    {
        $command->info("Seeding shop outbox settings");
        $this->handle();
    }

}
