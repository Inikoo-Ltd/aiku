<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Sept 2024 22:37:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\SysAdmin\User\StoreUser;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Actions\SysAdmin\User\UpdateUsersPseudoJobPositions;
use App\Models\SysAdmin\User;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraUsers extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:users {organisations?*} {--s|source_id=} {--d|db_suffix=}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?User
    {
        setPermissionsTeamId($organisationSource->getOrganisation()->group_id);
        if ($userData = $organisationSource->fetchUser($organisationSourceId)) {

            if ($userData['user']) {
                if ($user = User::withTrashed()->where('source_id', $userData['user']['source_id'])->first()) {
                    try {
                        $user = UpdateUser::make()->action(
                            user: $user,
                            modelData: $userData['user'],
                            hydratorsDelay: 60,
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

                    $user = User::where('id', $foundUserData->user_id)->first();



                    return $user;
                }


                $user = $organisationSource->getOrganisation()->group->users()->where('username', $userData['user']['username'])->first();



                if ($user) {


                    $user = UpdateUsersPseudoJobPositions::make()->action(
                        $user,
                        $organisationSource->getOrganisation(),
                        [
                            'positions' => $userData['user']['positions']
                        ]
                    );
                } else {
                    // User not found
                    // todo add as a guest
                    //dd($userData['user']);
                }


                //                elseif($userData['parent_type']=='Staff'){
                //                        try {
                //                            $user = StoreUser::make()->action(
                //                                parent: $userData['parent'],
                //                                modelData: $userData['user'],
                //                                hydratorsDelay: $this->hydrateDelay,
                //                                strict: false
                //                            );
                //
                //                            $this->recordNew($organisationSource);
                //                        } catch
                //                        (Exception $e) {
                //                            $this->recordError($organisationSource, $e, $userData['user'], 'DeletedUser', 'store');
                //
                //                            return null;
                //                        }
                //                    }


            }



            return $user;
        }


        return null;
    }


    private function updateAurora($user): void
    {
        $sourceData = explode(':', $user->source_id);
        DB::connection('aurora')->table('User Deleted Dimension')
            ->where('User Key', $sourceData[1])
            ->update(['aiku_id' => $user->id]);
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
