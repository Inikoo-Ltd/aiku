<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:11:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Admin;

use App\Models\SysAdmin\Admin;
use App\Rules\AlphaDashDot;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreAdmin
{
    use AsAction;
    use WithAttributes;

    public function handle(array $modelData): Admin
    {
        return Admin::create($modelData);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', new AlphaDashDot, 'unique:App\Models\SysAdmin\Admin,code'],
            'name' => 'sometimes|required',
            'email' => ['required', 'email', 'unique:App\Models\SysAdmin\Admin,email'],
        ];
    }

    public function action($modelData): Admin
    {
        $this->fill($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($validatedData);
    }

    public string $commandSignature = 'create:admin
    {code : will be used as username}
    {name}
    {email}';

    public function getCommandDescription(): string
    {
        return 'Create admin.';
    }

    public function asCommand(Command $command): int
    {

        $this->fill([
            'code' => $command->argument('code'),
            'name' => $command->argument('name'),
            'email' => $command->argument('email'),
        ]);

        try {
            $validatedData = $this->validateAttributes();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $admin = $this->handle($validatedData);
        $command->line(" admin created $admin->code");

        return 0;
    }
}
