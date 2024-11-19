<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jul 2024 11:19:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment;

use App\Actions\Comms\Outbox\StoreOutbox;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\Fulfilment\Fulfilment;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedFulfilmentOutboxes
{
    use AsAction;

    public function handle(Fulfilment $fulfilment): void
    {
        foreach (OutboxTypeEnum::cases() as $case) {
            if ($case->scope() == 'Fulfilment') {
                $postRoom = PostRoom::where('code', $case->postRoomCode()->value)->first();

                if (!Outbox::where('type', $case)->exists()) {
                    StoreOutbox::run(
                        $postRoom,
                        $fulfilment,
                        [
                            'name'      => $case->label(),
                            'type'      => $case,
                            'state'     => $case->defaultState(),
                            'blueprint' => $case->blueprint(),
                        ]
                    );
                }
            }
        }
    }

    public string $commandSignature = 'fulfilment:seed-outboxes {fulfilment? : The fulfilment slug}';

    public function asCommand(Command $command): int
    {

        if ($command->argument('fulfilment') == null) {
            $fulfilments = Fulfilment::all();
            foreach ($fulfilments as $fulfilment) {
                $this->handle($fulfilment);
            }
            return 0;
        }

        try {
            $fulfilment = Fulfilment::where('slug', $command->argument('fulfilment'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());
            return 1;
        }

        $this->handle($fulfilment);

        return 0;
    }


}
