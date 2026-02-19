<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ShowTiktokOrderApi extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public string $commandSignature = 'tiktok:show-order {tiktokShop} {orderId}';

    public function handle(TiktokUser $tiktokUser, string $orderId)
    {
        return $tiktokUser->getOrder($orderId);
    }

    public function asCommand(Command $command)
    {
        $tiktokUser = TiktokUser::where('tiktok_shop_id', $command->argument('tiktokShop'))->firstOrFail();

        $this->handle($tiktokUser, $command->argument('orderId'));
    }
}
