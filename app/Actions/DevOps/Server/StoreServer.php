<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jun 2026 10:59:54 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\Server;

use App\Models\DevOps\Server;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreServer
{
    use AsAction;

    public function handle(array $modelData): Server
    {
        return Server::create($modelData);
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'ip'     => ['required', 'ip'],
            'active' => ['sometimes', 'boolean']
        ];
    }

    public function getCommandSignature(): string
    {
        return 'server:store {name} {ip_address} {active?}';
    }

    public function asCommand(Command $command): int
    {
        $modelData = [
            'name'   => $command->argument('name'),
            'ip'     => $command->argument('ip_address'),
            'active' => $command->argument('active') ?? false
        ];

        $server = $this->handle($modelData);
        $command->info('Server created successfully '.$server->name);

        return 0;
    }

}
