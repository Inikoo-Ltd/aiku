<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jul 2024 01:58:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateApiTokens;
use App\Models\SysAdmin\Group;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CreateAccessToken extends GrpAction
{
    use AsAction;
    use WithAttributes;


    public function handle(Group $group, $data): string
    {
        $plainAccessToken = $group->createToken($data['name'], $data['abilities'])->plainTextToken;

        GroupHydrateApiTokens::dispatch($group);
        return $plainAccessToken;
    }

    public string $commandSignature = 'group:access-token {group : group slug} {name} {abilities*}';


    public function rules(): array
    {
        return [
            'name'      => ['required', 'string'],
            'abilities' => ['required', 'array'],
        ];
    }

    public function action(Group $group, array $data): string
    {
        $this->initialisation($group, $data);
        return $this->handle($group, $this->validatedData);
    }


    public function asCommand(Command $command): int
    {
        try {
            $group = Group::where('slug', $command->argument('group'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $fields = [
            'name'      => $command->argument('name'),
            'abilities' => $command->argument('abilities'),
        ];

        $this->initialisation($group, $fields);


        $token = $this->handle($group, $this->validatedData);

        $command->info('Token: '.$token);

        return 0;
    }


}
