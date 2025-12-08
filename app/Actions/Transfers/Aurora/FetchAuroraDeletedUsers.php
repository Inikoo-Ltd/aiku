<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Sept 2024 10:44:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\User\StoreUser;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Models\SysAdmin\User;
use App\Transfers\SourceOrganisationService;
use Arr;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraDeletedUsers extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:deleted_users {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    /**
     * @throws \Throwable
     */
    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?User
    {
        if ($userData = $organisationSource->fetchDeletedUser($organisationSourceId)) {
            if ($userData['user']) {
                if ($user = User::withTrashed()->where('source_id', $userData['user']['source_id'])->first()) {
                    if (Arr::get($user->data, 'deleted.source') == 'aurora') {
                        try {
                            $user = UpdateUser::make()->action(
                                user: $user,
                                modelData: $userData['user'],
                                hydratorsDelay: $this->hydratorsDelay,
                                strict: false,
                                audit: false
                            );
                            $this->recordChange($organisationSource, $user->wasChanged());

                            return $user;
                        } catch (Exception $e) {
                            $this->recordError($organisationSource, $e, $userData['user'], 'DeletedUser', 'update');

                            return null;
                        }
                    }

                    return $user;
                }

                if (! $userData['parent']) {
                    $group_id = $organisationSource->getOrganisation()->group_id;

                    $user = User::withTrashed()->where('group_id', $group_id)->where('username', $userData['related_username'])->first();

                    if ($user) {
                        $sourceData = explode(':', $userData['user']['source_id']);
                        DB::connection('aurora')->table('User Deleted Dimension')
                            ->where('User Deleted Key', $sourceData[1])
                            ->update(['aiku_related_id' => $user->id]);

                        $user = FetchAuroraUsers::make()->updateUserSources($user, $userData);
                    }

                    if ($userData['add_guest']) {
                        $guest = StoreGuest::make()->action(
                            $organisationSource->getOrganisation()->group,
                            $userData['guest'],
                            hydratorsDelay: $this->hydratorsDelay,
                            strict: false
                        );

                        return $guest->getUser();
                    }

                    return $user;
                }

                try {
                    $user = StoreUser::make()->action(
                        parent: $userData['parent'],
                        modelData: $userData['user'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    $sourceData = explode(':', $user->source_id);
                    DB::connection('aurora')->table('User Deleted Dimension')
                        ->where('User Deleted Key', $sourceData[1])
                        ->update(['aiku_id' => $user->id]);

                    $this->recordNew($organisationSource);
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $userData['user'], 'DeletedUser', 'store');

                    return null;
                }

                return $user;
            }
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('User Deleted Dimension')
            ->select('User Deleted Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('User Deleted Dimension');

        return $query->count();
    }
}
