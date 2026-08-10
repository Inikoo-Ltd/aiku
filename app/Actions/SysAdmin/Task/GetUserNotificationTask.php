<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Task;

use App\Models\SysAdmin\Task;
use App\Models\SysAdmin\TaskType;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUserNotificationTask
{
    use AsAction;

    public const TASK_TYPE_CODE = 'user-notification';

    /**
     * Every user owns exactly one task of this type; the sub tasks under it are what actually
     * accumulate. Created on first use so no backfill is needed for existing users.
     */
    public function handle(User $user): Task
    {
        return Task::firstOrCreate(
            ['code' => self::TASK_TYPE_CODE.'-'.$user->id],
            [
                'group_id'        => $user->group_id,
                'organisation_id' => $this->organisationId($user),
                'task_type_id'    => TaskType::where('code', self::TASK_TYPE_CODE)->value('id'),
                'name'            => __('Notifications for :user', ['user' => $user->username]),
            ]
        );
    }

    /**
     * A user notification task spans every organisation the user can reach, but the column is a
     * real foreign key, so the organisation they are employed in stands in for all of them.
     */
    private function organisationId(User $user): ?int
    {
        return $user->employed_in_organisation_id
            ?? $user->group->organisations()->value('id');
    }
}
