<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Sept 2024 22:37:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Actions\SysAdmin\User\UpdateUserOrganisationPseudoJobPositions;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use App\Transfers\SourceOrganisationService;
use Arr;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraUsers extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:users {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    /**
     * @throws \Throwable
     */
    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?User
    {
        $user = null;
        setPermissionsTeamId($organisationSource->getOrganisation()->group_id);

        if ($userData = $organisationSource->fetchUser($organisationSourceId)) {
            if ($userData['user']) {
                if ($user = User::withTrashed()->where('source_id', $userData['user']['source_id'])->first()) {
                    try {

                        //                        if ($user->auth_type == UserAuthTypeEnum::DEFAULT) {
                        //                            data_forget($userData, 'user.auth_type');
                        //                        }
                        data_forget($userData, 'user.password');

                        $user = UpdateUser::make()->action(
                            user: $user,
                            modelData: $userData['user'],
                            hydratorsDelay: $this->hydratorsDelay,
                            strict: false,
                            audit: false
                        );
                        $this->recordChange($organisationSource, $user->wasChanged());
                    } catch (Exception $e) {
                        $this->recordError($organisationSource, $e, $userData['user'], 'DeletedUser', 'update');

                        return null;
                    }
                }

                if ($foundUserData = Db::table('user_has_models')
                    ->select('user_id')
                    ->where('group_id', $organisationSource->getOrganisation()->group_id)
                    ->where('source_id', $userData['user']['source_id'])->first()) {
                    return User::where('id', $foundUserData->user_id)->first();
                }

                if (! $userData['parent']) {
                    $group_id = $organisationSource->getOrganisation()->group_id;
                    $user = User::withTrashed()->where('group_id', $group_id)->where('username', $userData['related_username'])->first();

                    if ($user) {
                        //                        if ($userData['user']['status']) {
                        //
                        //                            $employee = $user->employees()->where('organisation_id', $organisationSource->getOrganisation()->id)->first();
                        //
                        //                            // NOTE UNTESTED CODE
                        //                            if ($employee) {
                        //
                        //                                UpdateEmployee::make()->action(
                        //                                    $employee,
                        //                                    [
                        //                                        'permissions' => $userData['user']['positions']
                        //                                    ],
                        //                                    hydratorsDelay: $this->hydratorsDelay,
                        //                                    strict: false,
                        //                                    audit: false
                        //                                );
                        //
                        //
                        //                            } else {
                        //                                $user = UpdateUserOrganisationPseudoJobPositions::make()->action(
                        //                                    $user,
                        //                                    $organisationSource->getOrganisation(),
                        //                                    [
                        //                                        'permissions' => $userData['user']['positions']
                        //                                    ]
                        //                                );
                        //                            }
                        //
                        //
                        //
                        //
                        //                        }

                        return $this->updateUserSources($user, $userData);
                    }

                    if ($userData['add_guest']) {
                        try {
                            $guest = StoreGuest::make()->action(
                                $organisationSource->getOrganisation()->group,
                                $userData['guest'],
                                hydratorsDelay: $this->hydratorsDelay,
                                strict: false,
                                audit: false
                            );

                            Guest::enableAuditing();
                            $this->saveMigrationHistory(
                                $guest,
                                Arr::except($userData['guest'], ['fetched_at', 'last_fetched_at', 'source_id'])
                            );

                            $this->recordNew($organisationSource);

                            return $guest->getUser();
                        } catch (Exception|Throwable $e) {
                            $this->recordError($organisationSource, $e, $userData['guest'], 'Guest', 'store');

                            return null;
                        }
                    }

                    return $user;
                }
            }

            return $user;
        }

        return null;
    }

    public function updateUserSources(User $user, array $userData): User
    {
        $sourcesUsers = Arr::get($user->sources, 'users', []);
        $sourcesParents = Arr::get($user->sources, 'parents', []);
        $sourcesUsers[] = $userData['user']['source_id'];
        if ($userData['parentSource']) {
            $sourcesParents[] = $userData['parentSource'];
        }
        $sourcesUsers = array_unique($sourcesUsers);
        $sourcesParents = array_unique($sourcesParents);
        $user->updateQuietly([
            'sources' => [
                'users' => $sourcesUsers,
                'parents' => $sourcesParents,
            ],
        ]);

        return $user;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('User Dimension')
            ->select('User Key as source_id')
            ->where('aiku_ignore', 'No')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('User Dimension')->where('aiku_ignore', 'No');

        return $query->count();
    }
}
