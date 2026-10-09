<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks;

use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The system user that raises automatic tasks, so it is clear no colleague created them. It is
 * switched off, so nobody can sign in as it and it is never offered as someone to give work to.
 */
class GetAikuAssistant
{
    use AsObject;

    public const string USERNAME = 'aiku-assistant';

    public function handle(int $groupId): User
    {
        $assistant = User::where('group_id', $groupId)->where('username', self::USERNAME)->first();

        if ($assistant) {
            return $assistant;
        }

        return DB::transaction(function () use ($groupId) {
            $assistant = User::create([
                'group_id'     => $groupId,
                'username'     => self::USERNAME,
                'contact_name' => 'Aiku assistant',
                'status'       => false,
            ]);
            $assistant->stats()->create();

            return $assistant;
        });
    }
}
