<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 12:40:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\Search;

use App\Http\Resources\SysAdmin\UserSearchResultResource;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UserRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(User $user): void
    {
        if ($user->trashed()) {

            if ($user->universalSearch) {
                $user->universalSearch()->delete();
            }

            return;
        }

        $user->universalSearch()->updateOrCreate(
            [],
            [
                'group_id' => $user->group_id,
                'sections' => ['sysadmin'],
                'haystack_tier_1' => $user->username,
                'haystack_tier_2' => trim($user->email.' '.$user->contact_name),
                'result' => [
                    // 'aaa'       => $user,
                    'route' => [
                        'name' => 'grp.sysadmin.users.show',
                        'parameters' => $user->username,
                    ],
                    'container' => [
                        'key' => 'auth_type',
                        'label' => $user->auth_type->labels()[$user->auth_type->value],
                        'tooltip' => 'Auth type',
                    ],
                    'title' => $user->contact_name,
                    'afterTitle' => [
                        'label' => '('.$user->username.')',
                    ],
                    'icon' => [
                        'icon' => 'fal fa-terminal',
                    ],
                    'meta' => [
                        [
                            'key' => 'type',
                            'label' => $user->type,
                        ],
                        [
                            'key' => 'created_date',
                            'type' => 'date',
                            'label' => $user->created_at,
                            'tooltip' => 'Created at',
                        ],
                        [
                            'key' => 'email',
                            'label' => $user->email,
                            'tooltip' => 'Email',
                        ],
                    ],

                    // 'meta'       => UserSearchResultResource::make($user)
                ],
            ]
        );
    }
}
