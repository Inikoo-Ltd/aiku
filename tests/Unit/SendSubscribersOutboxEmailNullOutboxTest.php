<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use Illuminate\Support\Facades\Log;

test('a missing outbox is logged instead of throwing', function () {
    Log::shouldReceive('warning')->once();

    $sender = new class () {
        use WithSendSubscribersOutboxEmail;

        public function send(): void
        {
            $this->sendOutboxEmailToSubscribers(null);
        }
    };

    $sender->send();

    expect(true)->toBeTrue();
});
