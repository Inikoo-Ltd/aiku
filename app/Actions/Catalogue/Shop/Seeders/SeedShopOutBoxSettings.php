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
use App\Enums\Comms\Outbox\OutboxCodeEnum;

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
            $timezone = $outbox->shop->timezone;
            $timezoneOffset = trim(str_replace('GMT', '', $timezone->formatOffset()));

            if ($timezoneOffset == '00:00') {
                $timezoneOffset = '+00:00';
            }
            $sendTimeWithTimezone = '15:00:00' . $timezoneOffset; // default value


            if ($outbox->code == OutboxCodeEnum::REORDER_REMINDER_2ND) {
                $outbox->setting()->create([
                    'days_after' => 30, // default value
                    'send_time' => $sendTimeWithTimezone
                ]);
            } elseif ($outbox->code == OutboxCodeEnum::REORDER_REMINDER_3RD) {
                $outbox->setting()->create([
                    'days_after' => 60, // default value
                    'send_time' => $sendTimeWithTimezone
                ]);
            } else {
                $outbox->setting()->create([
                    'days_after' => 20, // default value
                    'send_time' => $sendTimeWithTimezone
                ]);
            }
        }
    }

    public function asCommand(Command $command)
    {
        $command->info("Seeding shop outbox settings");
        $this->handle();
    }

}
