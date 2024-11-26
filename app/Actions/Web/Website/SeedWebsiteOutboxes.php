<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Jul 2024 17:26:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Comms\Outbox\StoreOutbox;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\Web\Website;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedWebsiteOutboxes
{
    use AsAction;

    public function handle(Website $website): void
    {
        foreach (OutboxCodeEnum::cases() as $case) {
            if ($case->scope() == 'Website' and  in_array($website->shop->type->value, $case->shopTypes())) {
                $postRoom = PostRoom::where('code', $case->postRoomCode()->value)->first();

                if (!Outbox::where('website_id', $website->id)->where('code', $case)->exists()) {
                    StoreOutbox::make()->action(
                        $postRoom,
                        $website,
                        [
                            'name'      => $case->label(),
                            'code'      => $case,
                            'type'      => $case->type(),
                            'state'     => $case->defaultState(),

                        ]
                    );
                }
            }
        }
    }

    public string $commandSignature = 'website:seed-outboxes {website? : The website slug}';

    public function asCommand(Command $command): int
    {

        if ($command->argument('website') == null) {
            $websites = Website::all();
            foreach ($websites as $website) {
                $this->handle($website);
            }
            return 0;
        }

        try {
            $website = Website::where('slug', $command->argument('website'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($website);

        return 0;
    }


}
