<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\GroupSetUpKey;

use App\Enums\SysAdmin\GroupSetUpKey\GroupSetUpKeyStateEnum;
use App\Models\SysAdmin\GroupSetUpKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreGroupSetUpKey
{
    use AsAction;
    use WithAttributes;

    public function handle(array $modelData): GroupSetUpKey
    {
        data_set($modelData, 'state', GroupSetUpKeyStateEnum::ACTIVE);
        data_set($modelData, 'key', Str::ulid());
        data_set($modelData, 'expires_at', now()->addHours(24));

        /** @var GroupSetUpKey $groupSetUpKey */
        $groupSetUpKey = GroupSetUpKey::create($modelData);

        return $groupSetUpKey;

    }

    public string $commandSignature = 'group:set-up-key {--O|organisations=2} {--S|shops=4} {--W|warehouses=2} {--M|manufactures=1} {--A|agents=3}';

    public function asCommand(Command $command): int
    {

        $modelData = [
            'limits' => [
                'organisations' => $command->option('organisations'),
                'shops' => $command->option('shops'),
                'warehouses' => $command->option('warehouses'),
                'manufactures' => $command->option('manufactures'),
                'agents' => $command->option('agents'),
            ],
        ];

        $groupSetUpKey = $this->handle($modelData);

        $command->info('Set up key created:  '.config('app.url').'/setup/'.$groupSetUpKey->key);
        $command->info('Expires at: '.$groupSetUpKey->expires_at);

        return 0;
    }
}
