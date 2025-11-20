<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 10:06:04 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Models\SysAdmin\Group;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class HydratePing
{
    use AsAction;

    public string $commandSignature = 'hydrate:ping';

    public function handle(): void
    {
        $group = Group::find(1);

        if ($group) {
            $group->update([
                'ping' => Carbon::now(),
            ]);
        }
    }
}
